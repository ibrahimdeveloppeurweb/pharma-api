<?php

namespace App\Controller\Extra;

use App\Utils\JsonHelper;
use App\Utils\TypeVariable;
use App\Exception\ExceptionApi;
use App\Manager\Gestion\MedicamentManager;
use App\Repository\Security\UserRepository;
use App\Repository\Gestion\MedicamentRepository;
use App\Repository\Gestion\PharmacieRepository;
use App\Repository\Extra\MedecineRepository;
use App\Utils\PharmacyHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

#[Route(path: "/api/v1/secure/medecine")]
#[OA\Tag(name: "Médicaments")]
class MedecineController extends AbstractController
{
    private $medicamentManager;
    private $medecineRepository;

    private EntityManagerInterface $em;

    public function __construct(
        MedecineRepository $medecineRepository,
        EntityManagerInterface $em,
    )
    {
        $this->medecineRepository = $medecineRepository;
        $this->em = $em;
    }
    
    #[Route("/sync", name: "medecine_sync", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/medecine/sync",
        summary: "Synchronisation incrémentale du catalogue de médicaments (nouveautés, modifications, suppressions)",
        parameters: [
            new OA\Parameter(name: "since", in: "query", required: false, description: "Date de la dernière synchro (server_time reçu précédemment). Absent = catalogue complet.", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "cursor", in: "query", required: false, description: "Dernier id reçu (pagination)", schema: new OA\Schema(type: "integer", default: 0)),
            new OA\Parameter(name: "limit", in: "query", required: false, description: "Taille de page (défaut 500, max 1000)", schema: new OA\Schema(type: "integer", default: 500)),
        ],
        responses: [new OA\Response(response: 200, description: "Lot de médicaments à appliquer")]
    )]
    public function sync(Request $request): Response
    {
        try {
            $since = trim((string) $request->query->get('since', ''));
            $cursor = max(0, (int) $request->query->get('cursor', 0));
            $limit = min(1000, max(1, (int) $request->query->get('limit', 500)));

            $serverTime = new \DateTimeImmutable();
            $params = ['cursor' => $cursor];
            $where = 'id > :cursor';

            if ($since !== '') {
                try {
                    $sinceDate = (new \DateTimeImmutable($since))
                        ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                } catch (\Exception) {
                    return $this->json(['status' => 'error', 'message' => 'Paramètre since invalide'], 400);
                }
                // Delta : ce qui a été créé, modifié OU supprimé depuis la dernière synchro
                $where .= ' AND (updated_at >= :since OR created_at >= :since OR deleted_at >= :since)';
                $params['since'] = $sinceDate->format('Y-m-d H:i:s');
            } else {
                // Catalogue complet : les médicaments supprimés n'ont aucune raison d'être envoyés
                $where .= ' AND deleted_at IS NULL';
            }

            $conn = $this->em->getConnection();
            // limit+1 : une ligne de plus que demandé permet de savoir s'il reste une page
            $rows = $conn->fetchAllAssociative(
                'SELECT id, uuid, nom, prix, deleted_at FROM medecine WHERE ' . $where
                . ' ORDER BY id ASC LIMIT ' . ($limit + 1),
                $params
            );

            $hasMore = count($rows) > $limit;
            if ($hasMore) {
                array_pop($rows);
            }

            $items = array_map(static fn (array $r) => [
                'uuid' => Uuid::fromBinary($r['uuid'])->toRfc4122(),
                'nom' => (string) ($r['nom'] ?? ''),
                'prix' => (float) ($r['prix'] ?? 0),
                'deleted' => $r['deleted_at'] !== null,
            ], $rows);

            $payload = [
                'items' => $items,
                'next_cursor' => $rows ? (int) end($rows)['id'] : $cursor,
                'has_more' => $hasMore,
                // Date à conserver comme prochain "since" (horloge du SERVEUR, jamais celle du téléphone)
                'server_time' => $serverTime->format(\DATE_ATOM),
                // Nombre de médicaments actifs : le mobile s'en sert pour vérifier son intégrité
                'total' => (int) $conn->fetchOne('SELECT COUNT(*) FROM medecine WHERE deleted_at IS NULL'),
            ];

            return $this->json((new JsonHelper($payload, null, 'success', 200, []))->serialize(), 200);
        } catch (\Throwable $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    #[Route("/last-update", name: "medecine_last_update", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/medecine/last-update",
        summary: "Obtenir la date de dernière modification des médicaments",
        responses: [
            new OA\Response(response: 200, description: "Date de dernière modification")
        ]
    )]
    public function lastUpdate(): Response
    {
        try {
            $qb = $this->medecineRepository->createQueryBuilder('m')
                ->select('MAX(m.updatedAt) as last_update, MAX(m.createdAt) as last_create')
                ->where('m.deletedAt IS NULL');
                
            $result = $qb->getQuery()->getSingleResult();
            
            $lastUpdate = $result['last_update'] ?? $result['last_create'];
            
            if (!$lastUpdate) {
                // S'il n'y a pas de médicaments ou pas de date
                $lastUpdate = '2000-01-01 00:00:00';
            }

            return $this->json([
                'status' => 'success',
                'data' => [
                    'lastUpdate' => $lastUpdate
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    #[Route("", name: "index_medecine", methods: ["GET"])]
    #[Route("/", name: "index_medecine_slash", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/medecine",
        summary: "Liste des medecines avec pagination",
        parameters: [
            new OA\Parameter(
                name: "page",
                in: "query",
                description: "Numéro de la page (défaut: 1)",
                required: false,
                schema: new OA\Schema(type: "integer", default: 1)
            ),
            new OA\Parameter(
                name: "limit",
                in: "query",
                description: "Nombre d'éléments par page (défaut: 50, max: 200)",
                required: false,
                schema: new OA\Schema(type: "integer", default: 50)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des medecines récupérée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(
                            property: "pagination",
                            type: "object",
                            properties: [
                                new OA\Property(property: "total", type: "integer", example: 20000),
                                new OA\Property(property: "current_page", type: "integer", example: 1),
                                new OA\Property(property: "per_page", type: "integer", example: 50),
                                new OA\Property(property: "last_page", type: "integer", example: 400),
                                new OA\Property(property: "has_more", type: "boolean", example: true)
                            ]
                        ),
                        new OA\Property(property: "message", type: "string", example: null),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération des médicaments")
        ]
    )]
    public function index(Request $request): Response
    {
        try {
            // Récupérer les paramètres de pagination
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(200, max(1, (int) $request->query->get('limit', 50)));
            
            // ⭐ UTILISER findPaginated au lieu de findBy
            $data = $this->medecineRepository->findPaginated($page, $limit);
            
            // Compter le total
            $total = $this->medecineRepository->count([]);
            $lastPage = (int) ceil($total / $limit);
            
            // Préparer la réponse avec pagination
            $responseData = [
                'medecine' => $data,
                'pagination' => [
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $limit,
                    'last_page' => $lastPage,
                    'has_more' => $page < $lastPage
                ],
                'message' => null,
                'type' => 'success',
                'code' => 200
            ];
            
            $response = (new JsonHelper($responseData, null, 'success', 200, []))->serialize();
            
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        } catch (\Exception $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'error', 500, []))->serialize();
            return $this->json($response, 500);
        }
        
        return $this->json($response, 200);
    }
}