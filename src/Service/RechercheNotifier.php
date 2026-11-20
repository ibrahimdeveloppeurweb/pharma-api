<?php

namespace App\Service;

use App\Entity\Gestion\Pharmacie;
use App\Entity\RecherchePublique;
use App\Entity\Security\User;
use App\Repository\Gestion\PharmacieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Centralise tout ce qui concerne les notifications push liées aux recherches
 * de médicaments (nouvelle recherche, annulation, réservation) et la recherche
 * des pharmacies à proximité.
 *
 * Principes :
 *  - les messages Android sont "data-only" (haute priorité) : sans bloc
 *    "notification", l'application garde la main en arrière-plan et peut
 *    déclencher sa sonnerie insistante plein écran ;
 *  - iOS reçoit son alerte via le bloc "aps" ;
 *  - les jetons FCM invalides/expirés sont supprimés automatiquement ;
 *  - chaque alerte part par DEUX canaux complémentaires : Mercure (temps réel, application
 *    ouverte) et FCM (réveil du téléphone en arrière-plan). Les deux portent la même clé
 *    d'offre ("offerKey") : l'application ne présente l'alerte qu'UNE fois ;
 *  - une même recherche n'est jamais envoyée deux fois à la même pharmacie (verrou),
 *    ni deux fois au même appareil (jetons dédoublonnés).
 */
class RechercheNotifier
{
    /**
     * Rayons de recherche successifs (km) pour la VAGUE INITIALE : on prend le plus petit rayon qui
     * contient au moins une pharmacie (inutile de réveiller le quartier voisin si une pharmacie est tout près).
     */
    private const RADII_KM = [2, 5, 10];

    /**
     * Élargissement dans le temps : [secondes écoulées, rayon en km]. Tant qu'AUCUNE pharmacie n'a répondu,
     * la recherche s'étend aux pharmacies plus lointaines (chacune n'est alertée qu'une fois : verrou).
     */
    private const WAVES = [[0, 2], [20, 5], [40, 10]];

    /** Rayon maximal d'une recherche (km). */
    public const MAX_RADIUS_KM = 10;

    /** Nombre maximal de pharmacies alertées par vague. */
    private const MAX_PER_WAVE = 40;

    /** Durée de vie d'une alerte de recherche (secondes). */
    public const SEARCH_TTL = 60;

    public function __construct(
        private EntityManagerInterface $em,
        private PharmacieRepository $pharmacieRepository,
        private Messaging $messaging,
        private LoggerInterface $logger,
        private HubInterface $hub,
        private CacheItemPoolInterface $cache,
        private HttpClientInterface $http,
    ) {
    }

    /**
     * Vague INITIALE : pharmacies du plus petit rayon (2, 5 puis 10 km) qui en contient au moins une.
     *
     * @return array{radius: int, pharmacies: array<int, array<string, mixed>>}
     */
    public function findInitialWave(float $lat, float $lng): array
    {
        foreach (self::RADII_KM as $radius) {
            $found = $this->pharmaciesWithin($lat, $lng, $radius);
            if (!empty($found)) {
                return ['radius' => $radius, 'pharmacies' => $found];
            }
        }

        return ['radius' => self::MAX_RADIUS_KM, 'pharmacies' => []];
    }

    /**
     * Pharmacies disponibles à la recherche (position connue, option activée) dans un rayon, avec distance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pharmaciesWithin(float $lat, float $lng, int $radiusKm): array
    {
        return $this->pharmacieRepository->findNearbyPharmacies($lat, $lng, $radiusKm, self::MAX_PER_WAVE);
    }

    /** Mémorise le rayon atteint par une recherche. */
    public function setRadius(int $rechercheId, int $radiusKm): void
    {
        $item = $this->cache->getItem('recherche_radius_' . $rechercheId);
        $item->set($radiusKm)->expiresAfter(600);
        $this->cache->save($item);
    }

    /** Rayon (km) atteint par la recherche (0 si inconnu). */
    public function currentRadius(int $rechercheId): int
    {
        $item = $this->cache->getItem('recherche_radius_' . $rechercheId);

        return $item->isHit() ? (int) $item->get() : 0;
    }

    /**
     * Élargit la recherche aux pharmacies plus lointaines quand le temps passe SANS réponse.
     * Appelé à chaque interrogation du patient (toutes les 2 s) : peu coûteux, sans tâche planifiée.
     *
     * @return int|null nouveau rayon (km) si la recherche vient de s'élargir, sinon null
     */
    public function expandIfNeeded(RecherchePublique $recherche): ?int
    {
        if (!$recherche->isActive() || !is_numeric($recherche->getLatitude()) || !is_numeric($recherche->getLongitude())) {
            return null;
        }

        $elapsed = time() - $recherche->getCreatedAt()->getTimestamp();
        $target = 0;
        foreach (self::WAVES as [$after, $radius]) {
            if ($elapsed >= $after) {
                $target = $radius;
            }
        }

        $id = (int) $recherche->getId();
        if ($target <= $this->currentRadius($id)) {
            return null;
        }

        // Une pharmacie a déjà répondu : inutile d'alerter de nouvelles pharmacies
        if ($recherche->getReponses()->count() > 0) {
            return null;
        }

        // Rayon mémorisé AVANT l'envoi : deux interrogations simultanées ne déclenchent pas deux vagues
        $this->setRadius($id, $target);

        $pharmacies = $this->pharmaciesWithin((float) $recherche->getLatitude(), (float) $recherche->getLongitude(), $target);
        // Les pharmacies déjà alertées sont ignorées par le verrou de notifyNewSearch()
        $this->notifyNewSearch($recherche, $pharmacies, (string) $recherche->getClientIdentifier());
        $this->logger->info('Recherche #{id} élargie à {radius} km ({count} pharmacies dans ce rayon).', [
            'id' => $id, 'radius' => $target, 'count' => count($pharmacies),
        ]);

        return $target;
    }

    /**
     * Distances RÉELLES par la route (service OSRM, le même que celui du site patient) entre la position du
     * patient et chaque pharmacie : à pied sous 1,5 km (en ligne droite), sinon en voiture. Un seul appel par profil
     * pour toutes les pharmacies. Retourne [] si le service est indisponible (repli : ligne droite).
     *
     * @param array<int, array<string, mixed>> $pharmacies
     *
     * @return array<int, string> libellé de distance par id de pharmacie
     */
    private function roadDistanceLabels(float $lat, float $lng, array $pharmacies): array
    {
        $groups = ['foot' => [], 'driving' => []];
        foreach ($pharmacies as $p) {
            if (!is_numeric($p['latitude'] ?? null) || !is_numeric($p['longitude'] ?? null)) {
                continue;
            }
            $profile = ((float) ($p['distance'] ?? 0)) < 1.5 ? 'foot' : 'driving';
            $groups[$profile][] = $p;
        }

        $labels = [];
        foreach ($groups as $profile => $list) {
            if (empty($list)) {
                continue;
            }
            $coords = [sprintf('%F,%F', $lng, $lat)];
            foreach ($list as $p) {
                $coords[] = sprintf('%F,%F', (float) $p['longitude'], (float) $p['latitude']);
            }

            try {
                $data = $this->http->request('GET', 'https://router.project-osrm.org/table/v1/' . $profile . '/' . implode(';', $coords), [
                    'query' => ['sources' => '0', 'annotations' => 'distance,duration'],
                    'timeout' => 4,
                ])->toArray(false);
            } catch (\Throwable $e) {
                $this->logger->warning('Distances par la route indisponibles ({profile}) : {error}', ['profile' => $profile, 'error' => $e->getMessage()]);
                continue;
            }
            if (($data['code'] ?? '') !== 'Ok') {
                continue;
            }

            foreach ($list as $i => $p) {
                $meters = $data['distances'][0][$i + 1] ?? null;
                $seconds = $data['durations'][0][$i + 1] ?? null;
                if (!is_numeric($meters)) {
                    continue;
                }
                $km = (float) $meters / 1000;
                $dist = $km < 1 ? (int) round($km * 1000) . ' m' : number_format($km, 1, '.', '') . ' km';
                $mode = $profile === 'foot' ? 'à pied' : 'par la route';
                $labels[(int) $p['id']] = $dist . ' ' . $mode . (is_numeric($seconds) ? ' (' . max(1, (int) ceil($seconds / 60)) . ' min)' : '');
            }
        }

        return $labels;
    }

    /** Libellé de distance annoncé à une pharmacie pour une recherche (mémorisé à l'envoi), ou null. */
    public function cachedDistanceLabel(int $rechercheId, int $pharmacieId): ?string
    {
        $item = $this->cache->getItem('recherche_dist_' . $rechercheId . '_' . $pharmacieId);

        return $item->isHit() ? (string) $item->get() : null;
    }

    /** Cette pharmacie a-t-elle été alertée pour cette recherche ? */
    public function wasNotified(int $rechercheId, int $pharmacieId): bool
    {
        return $this->cache->getItem('recherche_sent_' . $rechercheId . '_' . $pharmacieId)->isHit();
    }

    /**
     * Distance (km) à vol d'oiseau entre une pharmacie et une recherche, ou null si inconnue.
     */
    public function distanceKm(Pharmacie $pharmacie, RecherchePublique $recherche): ?float
    {
        $pLat = $pharmacie->getLatitude();
        $pLng = $pharmacie->getLongitude();
        $rLat = $recherche->getLatitude();
        $rLng = $recherche->getLongitude();

        if (!is_numeric($pLat) || !is_numeric($pLng) || !is_numeric($rLat) || !is_numeric($rLng)) {
            return null;
        }

        $earth = 6371.0;
        $dLat = deg2rad((float) $rLat - (float) $pLat);
        $dLng = deg2rad((float) $rLng - (float) $pLng);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad((float) $pLat)) * cos(deg2rad((float) $rLat)) * sin($dLng / 2) ** 2;

        return round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    /**
     * Topic Mercure privé d'une pharmacie (identifiant d'IRI, pas une URL réellement appelée).
     */
    public static function pharmacieTopic(int $pharmacieId): string
    {
        return 'https://pharmaalerte.net/pharmacie/' . $pharmacieId . '/recherche';
    }

    /**
     * Clé unique d'une offre : une recherche proposée à une pharmacie. Identique sur tous les
     * canaux (Mercure, FCM, rattrapage REST) pour que le téléphone la dédoublonne.
     */
    public static function offerKey(int $rechercheId, int $pharmacieId): string
    {
        return $rechercheId . '#' . $pharmacieId;
    }

    /**
     * Prévient les pharmacies proches d'une nouvelle recherche.
     * Un message par pharmacie afin d'y inclure la distance réelle.
     *
     * @param array<int, array<string, mixed>> $pharmacies résultat de findNearbyPharmacies()
     *
     * @return int nombre d'appareils notifiés avec succès (FCM)
     */
    public function notifyNewSearch(RecherchePublique $recherche, array $pharmacies, string $clientName): int
    {
        if (empty($pharmacies)) {
            $this->logger->info('Recherche #{id} : aucune pharmacie à proximité.', ['id' => $recherche->getId()]);

            return 0;
        }

        $distances = [];
        foreach ($pharmacies as $p) {
            $distances[(int) $p['id']] = (float) $p['distance'];
        }

        $tokensByPharmacie = $this->tokensByPharmacie(array_keys($distances));
        $notified = 0;

        // Distance par la route (même valeur que sur le site du patient) pour les pharmacies pas encore alertées
        $toAlert = array_filter($pharmacies, fn (array $p) => !$this->wasNotified((int) $recherche->getId(), (int) $p['id']));
        $roadLabels = empty($toAlert) || !is_numeric($recherche->getLatitude()) || !is_numeric($recherche->getLongitude())
            ? []
            : $this->roadDistanceLabels((float) $recherche->getLatitude(), (float) $recherche->getLongitude(), array_values($toAlert));
        $seenTokens = [];

        foreach ($distances as $pharmacieId => $distance) {
            // Verrou : cette recherche n'est envoyée qu'une seule fois à cette pharmacie
            // (nouvelle tentative, requête dupliquée, appels concurrents...).
            if (!$this->claimOffer((int) $recherche->getId(), $pharmacieId)) {
                $this->logger->info('Recherche #{id} déjà envoyée à la pharmacie #{pharma}, ignorée.', [
                    'id' => $recherche->getId(), 'pharma' => $pharmacieId,
                ]);
                continue;
            }

            $data = [
                'type' => 'RECHERCHE_PUBLIQUE',
                'offerKey' => self::offerKey((int) $recherche->getId(), $pharmacieId),
                'recherche_id' => (string) $recherche->getId(),
                'client_name' => $clientName,
                'medicaments' => json_encode($recherche->getMedicaments(), JSON_UNESCAPED_UNICODE),
                // Distance par la route (celle que voit le patient) ; à défaut, ligne droite clairement annoncée
                'distance' => $roadLabels[$pharmacieId] ?? ('≈ ' . number_format($distance, 1, '.', '') . " km (à vol d'oiseau)"),
                'created_at' => (new \DateTimeImmutable())->format('c'),
                // Durée restante : le téléphone en déduit son échéance avec SA propre horloge
                'expires_in' => (string) self::SEARCH_TTL,
            ];

            // Même libellé pour le rattrapage REST (/active) : mémorisé le temps de la recherche
            $distItem = $this->cache->getItem('recherche_dist_' . $recherche->getId() . '_' . $pharmacieId);
            $distItem->set($data['distance'])->expiresAfter(600);
            $this->cache->save($distItem);

            // Canal 1 : Mercure (temps réel, application ouverte). Privé : seul le pharmacien
            // de CETTE pharmacie, muni de son jeton d'abonnement, peut lire le message.
            $this->publishMercure(self::pharmacieTopic($pharmacieId), $data);

            // Canal 2 : FCM (réveille le téléphone en arrière-plan). Un appareil ne reçoit
            // l'alerte qu'une fois, même s'il est rattaché à plusieurs comptes/pharmacies.
            $tokens = array_values(array_diff($tokensByPharmacie[$pharmacieId] ?? [], $seenTokens));
            $seenTokens = array_merge($seenTokens, $tokens);

            $message = CloudMessage::new()
                ->withData($data)
                ->withAndroidConfig(AndroidConfig::fromArray([
                    'priority' => 'high',
                    'ttl' => self::SEARCH_TTL . 's',
                ]))
                ->withApnsConfig(ApnsConfig::fromArray([
                    'headers' => ['apns-priority' => '10'],
                    'payload' => ['aps' => [
                        'alert' => [
                            'title' => 'Nouvelle demande patient !',
                            'body' => 'Un patient recherche des médicaments. Cliquez pour répondre.',
                        ],
                        'sound' => 'alarm.wav',
                        'content-available' => 1,
                    ]],
                ]));

            $notified += $this->send($message, $tokens, 'nouvelle recherche #' . $recherche->getId());
        }

        return $notified;
    }

    /**
     * Réserve l'envoi (recherche, pharmacie). Faux si déjà envoyé dans les 10 dernières minutes.
     */
    private function claimOffer(int $rechercheId, int $pharmacieId): bool
    {
        $item = $this->cache->getItem('recherche_sent_' . $rechercheId . '_' . $pharmacieId);
        if ($item->isHit()) {
            return false;
        }
        $item->set(true)->expiresAfter(600);
        $this->cache->save($item);

        return true;
    }

    /**
     * Publie un message PRIVÉ sur Mercure. Une panne du hub ne doit jamais empêcher la
     * recherche ni l'envoi FCM : l'erreur est journalisée et on continue.
     *
     * @param array<string, string> $data
     */
    private function publishMercure(string $topic, array $data): void
    {
        try {
            $this->hub->publish(new Update($topic, json_encode($data, JSON_UNESCAPED_UNICODE), true));
        } catch (\Throwable $e) {
            $this->logger->error('Échec Mercure ({topic}) : {error}', ['topic' => $topic, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Prévient les pharmacies proches que le patient a annulé sa recherche.
     */
    public function notifyCancellation(RecherchePublique $recherche): void
    {
        $lat = $recherche->getLatitude();
        $lng = $recherche->getLongitude();
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return;
        }

        // Toutes les pharmacies jusqu'au rayon maximal, filtrées sur celles qui ont VRAIMENT été alertées
        $pharmacies = $this->pharmaciesWithin((float) $lat, (float) $lng, self::MAX_RADIUS_KM);
        $ids = [];
        foreach ($pharmacies as $p) {
            if ($this->wasNotified((int) $recherche->getId(), (int) $p['id'])) {
                $ids[] = (int) $p['id'];
            }
        }
        if (empty($ids)) {
            return;
        }

        // Mercure : chaque pharmacie concernée coupe son alerte immédiatement
        foreach ($ids as $pharmacieId) {
            $this->publishMercure(self::pharmacieTopic($pharmacieId), [
                'type' => 'RECHERCHE_ANNULEE',
                'offerKey' => self::offerKey((int) $recherche->getId(), $pharmacieId),
                'recherche_id' => (string) $recherche->getId(),
            ]);
        }

        // FCM : un seul message par appareil
        $tokens = [];
        foreach ($this->tokensByPharmacie($ids) as $list) {
            $tokens = array_merge($tokens, $list);
        }
        $tokens = array_values(array_unique($tokens));

        $message = CloudMessage::new()
            ->withData([
                'type' => 'RECHERCHE_ANNULEE',
                'recherche_id' => (string) $recherche->getId(),
            ])
            ->withAndroidConfig(AndroidConfig::fromArray(['priority' => 'high', 'ttl' => '60s']));

        $this->send($message, $tokens, 'annulation #' . $recherche->getId());
    }

    /**
     * Prévient une pharmacie qu'un patient arrive (Click & Collect).
     *
     * @param array<int|string, mixed> $medicaments
     */
    public function notifyReservation(
        Pharmacie $pharmacie,
        RecherchePublique $recherche,
        string $patientNom,
        string $patientTelephone,
        array $medicaments,
    ): void {
        $tokens = $this->tokensByPharmacie([(int) $pharmacie->getId()])[(int) $pharmacie->getId()] ?? [];

        $message = CloudMessage::new()
            ->withData([
                'type' => 'RESERVATION',
                'recherche_id' => (string) $recherche->getId(),
                'pharmacie_id' => (string) $pharmacie->getId(),
                'patient_nom' => $patientNom,
                'patient_telephone' => $patientTelephone,
                'medicaments' => json_encode($medicaments, JSON_UNESCAPED_UNICODE),
            ])
            ->withAndroidConfig(AndroidConfig::fromArray(['priority' => 'high', 'ttl' => '3600s']))
            ->withApnsConfig(ApnsConfig::fromArray([
                'headers' => ['apns-priority' => '10'],
                'payload' => ['aps' => [
                    'alert' => [
                        'title' => 'Nouvelle Réservation (Click & Collect)',
                        'body' => $patientNom . ' est en route pour récupérer ses médicaments !',
                    ],
                    'sound' => 'alarm.wav',
                    'content-available' => 1,
                ]],
            ]));

        $this->send($message, $tokens, 'réservation pharmacie #' . $pharmacie->getId());
    }

    /**
     * Jetons FCM des utilisateurs, regroupés par pharmacie.
     *
     * @param int[] $pharmacieIds
     *
     * @return array<int, string[]>
     */
    private function tokensByPharmacie(array $pharmacieIds): array
    {
        if (empty($pharmacieIds)) {
            return [];
        }

        $rows = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->select('IDENTITY(u.pharmacie) AS pharmacieId', 'u.fcmToken AS token')
            ->where('u.pharmacie IN (:ids)')
            ->andWhere('u.fcmToken IS NOT NULL')
            ->andWhere("u.fcmToken != ''")
            ->setParameter('ids', $pharmacieIds)
            ->getQuery()
            ->getArrayResult();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['pharmacieId']][] = $row['token'];
        }

        return array_map(static fn (array $t) => array_values(array_unique($t)), $grouped);
    }

    /**
     * Envoie un message et supprime les jetons que Firebase déclare invalides.
     *
     * @param string[] $tokens
     *
     * @return int nombre d'envois réussis
     */
    private function send(CloudMessage $message, array $tokens, string $context): int
    {
        $tokens = array_values(array_unique($tokens));
        if (empty($tokens)) {
            return 0;
        }

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);
        } catch (\Throwable $e) {
            $this->logger->error('Échec FCM ({context}) : {error}', ['context' => $context, 'error' => $e->getMessage()]);

            return 0;
        }

        $dead = array_merge($report->invalidTokens(), $report->unknownTokens());
        if (!empty($dead)) {
            $this->em->createQueryBuilder()
                ->update(User::class, 'u')
                ->set('u.fcmToken', ':null')
                ->where('u.fcmToken IN (:dead)')
                ->setParameter('null', null)
                ->setParameter('dead', $dead)
                ->getQuery()
                ->execute();
        }

        $this->logger->info('FCM ({context}) : {ok} succès, {ko} échecs, {dead} jetons supprimés.', [
            'context' => $context,
            'ok' => $report->successes()->count(),
            'ko' => $report->failures()->count(),
            'dead' => count($dead),
        ]);

        return $report->successes()->count();
    }
}
