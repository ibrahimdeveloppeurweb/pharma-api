<?php

namespace App\Controller\Security;

use App\Entity\RecherchePublique;
use App\Entity\ReponseRecherche;
use App\Entity\Security\User;
use App\Service\RechercheNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Utils\JsonHelper;
use OpenApi\Attributes as OA;

#[Route(path: "/api/v1/secure/recherche")]
#[OA\Tag(name: "Réponse Recherche (Pharmacien)")]
class ReponseRechercheController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RechercheNotifier $notifier,
    ) {
    }

    #[Route("/active", name: "secure_recherche_active", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/recherche/active",
        summary: "Obtenir la recherche publique active la plus récente, à proximité de cette pharmacie, sans réponse de sa part"
    )]
    public function getActiveRecherche(): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \Exception('Utilisateur non connecté');
            }

            $pharmacie = $user->getPharmacie();
            if (!$pharmacie) {
                throw new \Exception('Cet utilisateur n\'est rattaché à aucune pharmacie');
            }

            // Recherches actives non expirées (les plus récentes d'abord)
            $recherches = $this->em->getRepository(RecherchePublique::class)->createQueryBuilder('r')
                ->where('r.status = :status')
                ->andWhere('r.expireAt > :now')
                ->setParameter('status', 'ACTIVE')
                ->setParameter('now', new \DateTimeImmutable())
                ->orderBy('r.createdAt', 'DESC')
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            foreach ($recherches as $recherche) {
                // Uniquement les recherches pour lesquelles CETTE pharmacie a été alertée (quel que soit le rayon
                // de la vague : 2, 5 ou 10 km) : même règle que l'envoi, donc aucune alerte fantôme.
                $distance = $this->notifier->distanceKm($pharmacie, $recherche);
                if ($distance === null || !$this->notifier->wasNotified((int) $recherche->getId(), (int) $pharmacie->getId())) {
                    continue;
                }

                // Ignorer celles auxquelles la pharmacie a déjà répondu
                $dejaRepondu = $this->em->getRepository(ReponseRecherche::class)->findOneBy([
                    'pharmacie' => $pharmacie,
                    'recherchePublique' => $recherche
                ]);
                if ($dejaRepondu) {
                    continue;
                }

                return $this->json(
                    (new JsonHelper([
                        'type' => 'RECHERCHE_PUBLIQUE',
                        // Même clé que Mercure et FCM : le téléphone ne présente l'offre qu'une fois
                        'offerKey' => RechercheNotifier::offerKey((int) $recherche->getId(), (int) $pharmacie->getId()),
                        'recherche_id' => (string) $recherche->getId(),
                        'client_name' => $recherche->getClientIdentifier() ?? 'Patient',
                        'medicaments' => json_encode($recherche->getMedicaments(), JSON_UNESCAPED_UNICODE),
                        // Même libellé que celui envoyé par Mercure/FCM (distance par la route) ; à défaut, ligne droite
                        'distance' => $this->notifier->cachedDistanceLabel((int) $recherche->getId(), (int) $pharmacie->getId())
                            ?? ('≈ ' . number_format($distance, 1, '.', '') . " km (à vol d'oiseau)"),
                        'created_at' => $recherche->getCreatedAt()->format('c'),
                        // Durée restante réelle (horloge du serveur) : le téléphone n'a pas à comparer les horloges
                        'expires_in' => (string) max(1, $recherche->getExpireAt()->getTimestamp() - time()),
                    ], 'Recherche active trouvée', 'success', 200, []))->serialize(),
                    200
                );
            }

            return $this->json(
                (new JsonHelper(null, 'Aucune recherche active', 'success', 200, []))->serialize(),
                200
            );

        } catch (\Exception $e) {
            return $this->json(
                (new JsonHelper(null, $e->getMessage(), 'error', 400, []))->serialize(),
                400
            );
        }
    }

    #[Route("/{id}/statut", name: "secure_recherche_statut", requirements: ["id" => "\d+"], methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/recherche/{id}/statut",
        summary: "Statut d'une recherche donnée pour la pharmacie connectée (encore active ? déjà répondu ?)"
    )]
    public function getStatut(int $id): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \Exception('Utilisateur non connecté');
            }

            $pharmacie = $user->getPharmacie();
            if (!$pharmacie) {
                throw new \Exception('Cet utilisateur n\'est rattaché à aucune pharmacie');
            }

            $recherche = $this->em->getRepository(RecherchePublique::class)->find($id);
            if (!$recherche) {
                return $this->json(
                    (new JsonHelper(null, 'Recherche introuvable', 'error', 404, []))->serialize(),
                    404
                );
            }

            $dejaRepondu = (bool) $this->em->getRepository(ReponseRecherche::class)->findOneBy([
                'pharmacie' => $pharmacie,
                'recherchePublique' => $recherche
            ]);

            $tempsRestant = $recherche->getExpireAt()
                ? max(0, $recherche->getExpireAt()->getTimestamp() - time())
                : 0;

            return $this->json(
                (new JsonHelper([
                    'recherche_id' => $recherche->getId(),
                    'statut' => $recherche->getStatus(),
                    'est_active' => $recherche->isActive(),
                    'deja_repondu' => $dejaRepondu,
                    'temps_restant_sec' => $tempsRestant,
                ], 'Statut récupéré', 'success', 200, []))->serialize(),
                200
            );

        } catch (\Exception $e) {
            return $this->json(
                (new JsonHelper(null, $e->getMessage(), 'error', 400, []))->serialize(),
                400
            );
        }
    }

    #[Route("/mes-reponses", name: "secure_reponses_pharmacie", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/recherche/mes-reponses",
        summary: "Obtenir l'historique des réponses de la pharmacie"
    )]
    public function getMesReponses(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \Exception('Utilisateur non connecté');
            }

            $pharmacie = $user->getPharmacie();
            if (!$pharmacie) {
                throw new \Exception('Cet utilisateur n\'est rattaché à aucune pharmacie');
            }

            $page = max(1, $request->query->getInt('page', 1));
            $limit = max(1, $request->query->getInt('limit', 10));
            $offset = ($page - 1) * $limit;

            $reponses = $this->em->getRepository(ReponseRecherche::class)->findBy(
                ['pharmacie' => $pharmacie],
                ['createdAt' => 'DESC'],
                $limit,
                $offset
            );

            $total = $this->em->getRepository(ReponseRecherche::class)->count(['pharmacie' => $pharmacie]);
            $totalPages = ceil($total / $limit);

            $data = [];
            foreach ($reponses as $reponse) {
                $recherche = $reponse->getRecherchePublique();
                $data[] = [
                    'id' => $reponse->getId(),
                    'status' => $reponse->getStatus(),
                    'delai' => $reponse->getDelai(),
                    'details' => $reponse->getDetails(),
                    'date_reponse' => $reponse->getCreatedAt() ? $reponse->getCreatedAt()->format(\DateTimeInterface::ATOM) : null,
                    'recherche' => $recherche ? [
                        'id' => $recherche->getId(),
                        'client_name' => $recherche->getClientIdentifier(),
                        'medicaments' => $recherche->getMedicaments(),
                        'date_recherche' => $recherche->getCreatedAt() ? $recherche->getCreatedAt()->format(\DateTimeInterface::ATOM) : null,
                    ] : null
                ];
            }

            return $this->json(
                (new JsonHelper($data, 'Historique récupéré avec succès', 'success', 200, [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalPages' => $totalPages
                ]))->serialize(),
                200
            );
        } catch (\Exception $e) {
            return $this->json(
                (new JsonHelper(null, $e->getMessage(), 'error', 400, []))->serialize(),
                400
            );
        }
    }

    #[Route("/{id}/repondre", name: "secure_repondre_recherche", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/recherche/{id}/repondre",
        summary: "Enregistrer la réponse d'une pharmacie à une recherche publique"
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "integer"),
        description: "ID de la recherche publique"
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "status", type: "string", example: "DISPONIBLE", description: "DISPONIBLE, BIENTOT_DISPONIBLE, NON_DISPONIBLE"),
                new OA\Property(property: "delai", type: "string", example: "30 min", nullable: true)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Réponse enregistrée avec succès"
    )]
    public function repondre(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \Exception('Utilisateur non connecté');
            }

            $pharmacie = $user->getPharmacie();
            if (!$pharmacie) {
                throw new \Exception('Cet utilisateur n\'est rattaché à aucune pharmacie');
            }

            $recherche = $this->em->getRepository(RecherchePublique::class)->find($id);
            if (!$recherche) {
                throw new \Exception('Recherche publique introuvable');
            }

            // ✅ Vérifier que la recherche est encore active (non expirée et non annulée)
            if (!$recherche->isActive()) {
                return $this->json(
                    (new JsonHelper(null, 'Cette recherche est terminée ou expirée. Aucune réponse n\'est plus acceptée.', 'error', 410, []))->serialize(),
                    410
                );
            }

            $content = json_decode($request->getContent(), true);
            if (!is_array($content)) {
                throw new \Exception('Corps de requête invalide');
            }

            // Colonnes limitées à 255 caractères : on borne les valeurs pour éviter une erreur SQL
            $status = mb_substr(trim((string) ($content['status'] ?? '')), 0, 255) ?: 'PARTIEL';
            $delai = isset($content['delai']) && $content['delai'] !== ''
                ? mb_substr((string) $content['delai'], 0, 255)
                : null;
            $details = isset($content['details']) && is_array($content['details']) ? $content['details'] : null;

            // Vérifier si la pharmacie a déjà répondu
            $existingReponse = $this->em->getRepository(ReponseRecherche::class)->findOneBy([
                'recherchePublique' => $recherche,
                'pharmacie' => $pharmacie
            ]);

            if ($existingReponse) {
                $existingReponse->setStatus($status);
                $existingReponse->setDelai($delai);
                $existingReponse->setDetails($details);
            } else {
                $reponse = new ReponseRecherche();
                $reponse->setRecherchePublique($recherche);
                $reponse->setPharmacie($pharmacie);
                $reponse->setStatus($status);
                $reponse->setDelai($delai);
                $reponse->setDetails($details);
                $this->em->persist($reponse);
            }

            $this->em->flush();

            return $this->json(
                (new JsonHelper(null, 'Réponse enregistrée avec succès', 'success', 200, []))->serialize(),
                200
            );

        } catch (\Exception $e) {
            return $this->json(
                (new JsonHelper(null, $e->getMessage(), 'error', 400, []))->serialize(),
                400
            );
        }
    }
}
