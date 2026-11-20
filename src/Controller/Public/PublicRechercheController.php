<?php

namespace App\Controller\Public;

use App\Entity\RecherchePublique;
use App\Entity\Reservation;
use App\Repository\Gestion\PharmacieRepository;
use App\Service\RechercheNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Utils\JsonHelper;
use OpenApi\Attributes as OA;

#[Route(path: "/api/v1/public/recherche-medicament")]
#[OA\Tag(name: "Recherche Médicaments Publique")]
class PublicRechercheController extends AbstractController
{
    /** Garde-fous sur l'endpoint public (non authentifié) qui déclenche des notifications. */
    private const MAX_SEARCHES_PER_MINUTE_PER_IP = 20;
    private const MAX_MEDICAMENTS = 10;
    private const MAX_MEDICAMENT_LENGTH = 150;

    public function __construct(
        private EntityManagerInterface $em,
        private PharmacieRepository $pharmacieRepository,
        private RechercheNotifier $notifier,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Réponse d'erreur standard (sans fuite de message technique).
     */
    private function error(string $message, int $status): JsonResponse
    {
        return $this->json((new JsonHelper(null, $message, 'error', $status, []))->serialize(), $status);
    }

    /**
     * Limite le nombre de recherches par IP et par minute (anti-spam des pharmacies).
     */
    private function isRateLimited(Request $request): bool
    {
        $key = 'public_search_rate_' . md5((string) $request->getClientIp());
        $item = $this->cache->getItem($key);
        $now = time();

        // Fenêtre fixe d'une minute : [nombre de requêtes, début de la fenêtre]
        [$count, $windowStart] = $item->isHit() ? $item->get() : [0, $now];
        if ($now - $windowStart >= 60) {
            [$count, $windowStart] = [0, $now];
        }

        if ($count >= self::MAX_SEARCHES_PER_MINUTE_PER_IP) {
            return true;
        }

        // L'expiration doit être redéfinie à chaque sauvegarde (sinon l'entrée devient permanente)
        $item->set([$count + 1, $windowStart])->expiresAfter(120);
        $this->cache->save($item);

        return false;
    }

    #[Route("/new", name: "public_new_recherche_medicament", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/public/recherche-medicament/new",
        summary: "Nouvelle recherche publique de médicaments par géolocalisation"
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "latitude", type: "number", example: 5.345317),
                new OA\Property(property: "longitude", type: "number", example: -4.024429),
                new OA\Property(
                    property: "medicaments",
                    type: "array",
                    items: new OA\Items(type: "string"),
                    example: ["Doliprane 1000mg", "Vitamine C"]
                ),
                new OA\Property(property: "clientIdentifier", type: "string", example: "device_abc123")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Recherche enregistrée et envoyée aux pharmacies à proximité"
    )]
    public function new(Request $request): JsonResponse
    {
        try {
            if ($this->isRateLimited($request)) {
                return $this->error('Trop de recherches. Réessayez dans une minute.', 429);
            }

            $content = json_decode($request->getContent(), true);
            if (!is_array($content)) {
                return $this->error('Corps de requête invalide', 400);
            }

            // --- Validation des entrées -----------------------------------------
            $lat = $content['latitude'] ?? null;
            $lng = $content['longitude'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lng)
                || (float) $lat < -90 || (float) $lat > 90
                || (float) $lng < -180 || (float) $lng > 180) {
                return $this->error('Coordonnées invalides', 400);
            }

            $medicaments = [];
            foreach ((array) ($content['medicaments'] ?? []) as $m) {
                if (!is_string($m)) {
                    continue;
                }
                $m = trim(mb_substr($m, 0, self::MAX_MEDICAMENT_LENGTH));
                if ($m !== '') {
                    $medicaments[] = $m;
                }
            }
            $medicaments = array_slice(array_values(array_unique($medicaments)), 0, self::MAX_MEDICAMENTS);
            if (empty($medicaments)) {
                return $this->error('Coordonnées et liste de médicaments requis', 400);
            }

            $clientIdentifier = $content['clientIdentifier'] ?? null;
            $clientIdentifier = is_string($clientIdentifier) && $clientIdentifier !== ''
                ? mb_substr($clientIdentifier, 0, 255)
                : uniqid('client_');

            // 1. Enregistrer la demande publique
            $recherche = new RecherchePublique();
            $recherche->setLatitude((string) $lat);
            $recherche->setLongitude((string) $lng);
            $recherche->setMedicaments($medicaments);
            $recherche->setClientIdentifier($clientIdentifier);

            $this->em->persist($recherche);
            $this->em->flush();

            // Origine de la position (aide au diagnostic : un GPS d'ordinateur peut se tromper de plusieurs km)
            $source = in_array($content['position_source'] ?? null, ['gps', 'lieu'], true) ? $content['position_source'] : 'inconnue';
            $precision = is_numeric($content['precision_m'] ?? null) ? (int) $content['precision_m'] : null;
            $this->logger->info('Recherche #{id} : position {source}{precision} ({lat}, {lng}).', [
                'id' => $recherche->getId(),
                'source' => $source,
                'precision' => $precision !== null ? ', précision ±' . $precision . ' m' : '',
                'lat' => round((float) $lat, 5),
                'lng' => round((float) $lng, 5),
            ]);

            // 2. Pharmacies à proximité (2 km, sinon 5 km) puis notification push
            $wave = $this->notifier->findInitialWave((float) $lat, (float) $lng);
            $pharmaciesProches = $wave['pharmacies'];
            $this->notifier->setRadius((int) $recherche->getId(), $wave['radius']);
            $devicesNotified = $this->notifier->notifyNewSearch($recherche, $pharmaciesProches, $clientIdentifier);

            $response = [
                'recherche_id' => $recherche->getId(),
                'rayon_km' => $wave['radius'],
                'pharmacies_notifiees_count' => count($pharmaciesProches),
                'devices_notified' => $devicesNotified,
                'pharmacies' => array_map(function ($p) {
                    $nom = mb_check_encoding($p['nom'], 'UTF-8') ? $p['nom'] : mb_convert_encoding($p['nom'], 'UTF-8', 'ISO-8859-1');
                    // Convert binary UUID to hex string to prevent JSON UTF-8 errors
                    $uuid = isset($p['uuid']) ? (mb_check_encoding($p['uuid'], 'UTF-8') ? $p['uuid'] : bin2hex($p['uuid'])) : null;

                    return [
                        'uuid' => $uuid,
                        'nom' => $nom,
                        'distance_km' => round($p['distance'], 2)
                    ];
                }, $pharmaciesProches)
            ];

            return $this->json(
                (new JsonHelper($response, 'Recherche enregistrée avec succès. Pharmacies notifiées.', 'success', 200, []))->serialize(),
                200
            );

        } catch (\Throwable $e) {
            $this->logger->error('Création de recherche publique échouée : {error}', ['error' => $e->getMessage(), 'exception' => $e]);

            return $this->error('Une erreur est survenue. Veuillez réessayer.', 500);
        }
    }

    #[Route("/autocomplete", name: "public_autocomplete_medicament", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/public/recherche-medicament/autocomplete",
        summary: "Autocomplétion pour les noms de médicaments"
    )]
    #[OA\Parameter(name: "q", in: "query", description: "Terme de recherche", schema: new OA\Schema(type: "string"))]
    public function autocomplete(Request $request): JsonResponse
    {
        $q = $request->query->get('q', '');
        if (strlen($q) < 2) {
            return $this->json((new JsonHelper([], 'Trop court', 'success', 200, []))->serialize(), 200);
        }

        $qb = $this->em->createQueryBuilder();
        $results = $qb->select('m.nom')
            ->from(\App\Entity\Extra\Medecine::class, 'm')
            ->where('m.nom LIKE :query')
            ->setParameter('query', $q . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        $names = array_column($results, 'nom');

        return $this->json((new JsonHelper($names, 'Suggestions récupérées', 'success', 200, []))->serialize(), 200);
    }

    #[Route("/autocomplete-prix", name: "public_autocomplete_medicament_prix", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/public/recherche-medicament/autocomplete-prix",
        summary: "Autocomplétion pour les noms de médicaments avec prix"
    )]
    #[OA\Parameter(name: "q", in: "query", description: "Terme de recherche", schema: new OA\Schema(type: "string"))]
    public function autocompletePrix(Request $request): JsonResponse
    {
        $q = $request->query->get('q', '');
        if (strlen($q) < 2) {
            return $this->json((new JsonHelper([], 'Trop court', 'success', 200, []))->serialize(), 200);
        }

        $qb = $this->em->createQueryBuilder();
        $results = $qb->select('m.nom, m.prix')
            ->from(\App\Entity\Extra\Medecine::class, 'm')
            ->where('m.nom LIKE :query')
            ->setParameter('query', $q . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        return $this->json((new JsonHelper($results, 'Suggestions avec prix récupérées', 'success', 200, []))->serialize(), 200);
    }


    #[Route("/{id}/reponses", name: "public_get_reponses", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/public/recherche-medicament/{id}/reponses",
        summary: "Récupérer les réponses des pharmacies + statut de la recherche"
    )]
    public function getReponses(int $id, Request $request): JsonResponse
    {
        $recherche = $this->em->getRepository(RecherchePublique::class)->find($id);
        if (!$recherche) {
            return $this->json((new JsonHelper(null, 'Recherche introuvable', 'error', 404, []))->serialize(), 404);
        }

        // --- SYSTÈME DE PING ---
        $pingFile = sys_get_temp_dir() . '/pharma_ping_' . $id;
        $role = $request->query->get('role');

        if ($role === 'patient') {
            @file_put_contents($pingFile, time());
            // Sans réponse après 20 s puis 40 s, la recherche s'étend aux pharmacies plus lointaines (5 puis 10 km)
            try {
                $this->notifier->expandIfNeeded($recherche);
            } catch (\Throwable $e) {
                $this->logger->error('Élargissement de la recherche #{id} impossible : {error}', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }

        $isActive = $recherche->isActive();

        if ($isActive && file_exists($pingFile)) {
            $lastPing = (int) @file_get_contents($pingFile);
            // Tolérance de 5 secondes (le frontend patient fait un ping toutes les 2s)
            if (time() - $lastPing > 5) {
                $recherche->setStatus('ANNULEE');
                $this->em->flush();
                $isActive = false;
                @unlink($pingFile);
            }
        }

        // Calculer le temps restant
        $tempsRestant = 0;
        if ($recherche->getExpireAt() !== null) {
            $now = new \DateTimeImmutable();
            $diff = $recherche->getExpireAt()->getTimestamp() - $now->getTimestamp();
            $tempsRestant = max(0, $diff);
        }

        $reponses = [];
        foreach ($recherche->getReponses() as $reponse) {
            $reponses[] = [
                'pharmacie_id'        => $reponse->getPharmacie()->getId(),
                'pharmacie_nom'       => $reponse->getPharmacie()->getNom(),
                'pharmacie_adresse'   => $reponse->getPharmacie()->getAdresse(),
                'pharmacie_telephone' => $reponse->getPharmacie()->getTelephone(),
                'pharmacie_lieu_exact'=> $reponse->getPharmacie()->getLieuExact(),
                'pharmacie_latitude'  => $reponse->getPharmacie()->getLatitude(),
                'pharmacie_longitude' => $reponse->getPharmacie()->getLongitude(),
                'status'              => $reponse->getStatus(),
                'delai'               => $reponse->getDelai(),
                'details'             => method_exists($reponse, 'getDetails') ? $reponse->getDetails() : null,
                'date_reponse'        => $reponse->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json(
            (new JsonHelper([
                'recherche_statut'  => $recherche->getStatus(),
                'est_active'        => $recherche->isActive(),
                'rayon_km'          => $this->notifier->currentRadius($id) ?: null,
                'temps_restant_sec' => $tempsRestant,
                'reponses'          => $reponses,
            ], 'Réponses récupérées', 'success', 200, []))->serialize(),
            200
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Endpoint : patient stoppe sa propre recherche avant 60 secondes
    // ─────────────────────────────────────────────────────────────────────────

    #[Route("/{id}/stopper", name: "public_stopper_recherche", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/public/recherche-medicament/{id}/stopper",
        summary: "Le patient stoppe manuellement sa recherche avant expiration"
    )]
    public function stopper(int $id, Request $request): JsonResponse
    {
        try {
            $recherche = $this->em->getRepository(RecherchePublique::class)->find($id);
            if (!$recherche) {
                return $this->error('Recherche introuvable', 404);
            }

            // Le clientIdentifier est obligatoire : sans lui, n'importe qui pourrait
            // annuler la recherche d'un autre patient en devinant son identifiant numérique.
            $content = json_decode($request->getContent(), true);
            $clientIdentifier = is_array($content) ? ($content['clientIdentifier'] ?? null) : null;

            if (!is_string($clientIdentifier) || !hash_equals((string) $recherche->getClientIdentifier(), $clientIdentifier)) {
                return $this->error('Non autorisé à stopper cette recherche', 403);
            }

            if ($recherche->getStatus() !== 'ACTIVE') {
                return $this->json(
                    (new JsonHelper(null, 'Cette recherche est déjà terminée', 'success', 200, []))->serialize(),
                    200
                );
            }

            $recherche->setStatus('ANNULEE');
            $this->em->flush();

            // Prévenir les pharmacies pour qu'elles coupent leur sonnerie
            $this->notifier->notifyCancellation($recherche);

            return $this->json(
                (new JsonHelper(null, 'Recherche annulée par le patient', 'success', 200, []))->serialize(),
                200
            );

        } catch (\Throwable $e) {
            $this->logger->error('Annulation de recherche échouée : {error}', ['error' => $e->getMessage(), 'exception' => $e]);

            return $this->error('Une erreur est survenue. Veuillez réessayer.', 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Endpoint : patient réserve / prévient de son arrivée
    // ─────────────────────────────────────────────────────────────────────────

    #[Route("/{id}/reserver", name: "public_reserver_medicament", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/public/recherche-medicament/{id}/reserver",
        summary: "Le patient prévient une pharmacie de son arrivée pour acheter les médicaments"
    )]
    public function reserver(int $id, Request $request): JsonResponse
    {
        try {
            $recherche = $this->em->getRepository(RecherchePublique::class)->find($id);
            if (!$recherche) {
                return $this->error('Recherche introuvable', 404);
            }

            $content = json_decode($request->getContent(), true);
            if (!is_array($content)) {
                return $this->error('Corps de requête invalide', 400);
            }

            // Seul le patient à l'origine de la recherche peut réserver
            $clientIdentifier = $content['clientIdentifier'] ?? null;
            if (!is_string($clientIdentifier) || !hash_equals((string) $recherche->getClientIdentifier(), $clientIdentifier)) {
                return $this->error('Non autorisé', 403);
            }

            $pharmacieId = $content['pharmacie_id'] ?? null;
            if (!$pharmacieId) {
                return $this->error('Pharmacie non précisée', 400);
            }

            $pharmacie = $this->pharmacieRepository->find($pharmacieId);
            if (!$pharmacie) {
                return $this->error('Pharmacie introuvable', 404);
            }

            // On ne peut réserver que dans une pharmacie qui a répondu à CETTE recherche
            $reponse = $this->em->getRepository(\App\Entity\ReponseRecherche::class)->findOneBy([
                'recherchePublique' => $recherche,
                'pharmacie' => $pharmacie,
            ]);
            if (!$reponse) {
                return $this->error("Cette pharmacie n'a pas répondu à votre recherche", 403);
            }

            $patientNom = trim(mb_substr((string) ($content['patient_nom'] ?? ''), 0, 100)) ?: 'Patient Anonyme';
            $patientPhone = trim(mb_substr((string) ($content['patient_telephone'] ?? ''), 0, 30)) ?: 'Non renseigné';
            $medicaments = array_slice(
                array_values(array_filter((array) ($content['medicaments'] ?? []), 'is_string')),
                0,
                self::MAX_MEDICAMENTS
            );

            // Une seule réservation par recherche et par pharmacie (anti double-clic / spam)
            $existing = $this->em->getRepository(Reservation::class)->findOneBy([
                'recherchePublique' => $recherche,
                'pharmacie' => $pharmacie,
            ]);
            if ($existing) {
                return $this->json(
                    (new JsonHelper(null, 'La pharmacie a déjà été prévenue de votre arrivée.', 'success', 200, []))->serialize(),
                    200
                );
            }

            // --- Enregistrement de la réservation en base ---
            $reservation = new Reservation();
            $reservation->setPatientNom($patientNom);
            $reservation->setPatientTelephone($patientPhone);
            $reservation->setMedicaments($medicaments);
            $reservation->setPharmacie($pharmacie);
            $reservation->setRecherchePublique($recherche);

            $this->em->persist($reservation);
            $this->em->flush();

            $this->notifier->notifyReservation($pharmacie, $recherche, $patientNom, $patientPhone, $medicaments);

            return $this->json(
                (new JsonHelper(null, 'La pharmacie a été prévenue de votre arrivée.', 'success', 200, []))->serialize(),
                200
            );

        } catch (\Throwable $e) {
            $this->logger->error('Réservation échouée : {error}', ['error' => $e->getMessage(), 'exception' => $e]);

            return $this->error('Une erreur est survenue. Veuillez réessayer.', 500);
        }
    }
}
