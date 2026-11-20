<?php

namespace App\Controller\Gestion;

use App\Utils\JsonHelper;
use App\Utils\TypeVariable;
use App\Exception\ExceptionApi;
use App\Manager\Gestion\AbonnementManager;
use App\Repository\Gestion\AbonnementRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

#[Route(path: "/api/v1/secure/abonnement")]
#[OA\Tag(name: "Abonnements")]
class AbonnementController extends AbstractController
{
    private AbonnementManager $abonnementManager;
    private AbonnementRepository $abonnementRepository;

    public function __construct(
        AbonnementManager $abonnementManager,
        AbonnementRepository $abonnementRepository
    ) {
        $this->abonnementManager = $abonnementManager;
        $this->abonnementRepository = $abonnementRepository;
    }

    #[Route("", name: "index_abonnement", methods: ["GET"])]
    #[Route("/", name: "index_abonnement_slash", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/abonnement",
        summary: "Liste des abonnements",
        parameters: [
            new OA\Parameter(
                name: "actif",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "boolean"),
                description: "Filtrer par statut actif/inactif"
            ),
            new OA\Parameter(
                name: "pharmacie_uuid",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string"),
                description: "Filtrer par UUID de pharmacie"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des abonnements récupérée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "message", type: "string", example: null),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function index(Request $request): Response
    {
        try {
            $filters = [];
            
            if ($request->query->has('actif')) {
                $filters['actif'] = filter_var($request->query->get('actif'), FILTER_VALIDATE_BOOLEAN);
            }
            
            if ($request->query->has('pharmacie_uuid')) {
                $filters['pharmacie_uuid'] = $request->query->get('pharmacie_uuid');
            }

            $abonnements = $this->abonnementRepository->findBy($filters);
            $response = (new JsonHelper($abonnements, null, 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }

    #[Route("/new", name: "new_abonnement", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/abonnement/new",
        summary: "Créer un nouvel abonnement",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "pharmacie_uuid", type: "string", example: "uuid-de-la-pharmacie"),
                    new OA\Property(property: "date_debut", type: "string", format: "date", example: "2024-01-01"),
                    new OA\Property(property: "date_fin", type: "string", format: "date", example: "2024-12-31"),
                    new OA\Property(property: "actif", type: "boolean", example: true),
                    new OA\Property(property: "faveur_active", type: "boolean", example: false),
                    new OA\Property(property: "date_fin_faveur", type: "string", format: "date", example: "2024-06-30")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Abonnement créé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Abonnement créé avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Données invalides"),
            new OA\Response(response: 400, description: "Erreur lors de la création")
        ]
    )]
    public function new(Request $request): Response
    {
        try {
            $content = json_decode($request->getContent());
            $abonnement = $this->abonnementManager->create($content);
            $response = (new JsonHelper($abonnement, 'Abonnement créé avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read', 'entity']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }

    #[Route("/{uuid}/edit", name: "edit_abonnement", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/abonnement/{uuid}/edit",
        summary: "Modifier un abonnement",
        parameters: [
            new OA\Parameter(
                name: "uuid",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "date_debut", type: "string", format: "date", example: "2024-01-01"),
                    new OA\Property(property: "date_fin", type: "string", format: "date", example: "2024-12-31"),
                    new OA\Property(property: "actif", type: "boolean", example: true),
                    new OA\Property(property: "faveur_active", type: "boolean", example: false),
                    new OA\Property(property: "date_fin_faveur", type: "string", format: "date", example: "2024-06-30")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Abonnement modifié avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Abonnement modifié avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Abonnement introuvable"),
            new OA\Response(response: 400, description: "Erreur lors de la modification")
        ]
    )]
    public function edit(Request $request, string $uuid): Response
    {
        try {
            $content = json_decode($request->getContent());
            $abonnement = $this->abonnementManager->update($content, $uuid);
            $response = (new JsonHelper($abonnement, 'Abonnement modifié avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read', 'entity']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }

    #[Route("/{uuid}/delete", name: "delete_abonnement", methods: ["DELETE"])]
    #[OA\Delete(
        path: "/api/v1/secure/abonnement/{uuid}/delete",
        summary: "Supprimer un abonnement",
        parameters: [
            new OA\Parameter(
                name: "uuid",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Abonnement supprimé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "null"),
                        new OA\Property(property: "message", type: "string", example: "L'abonnement a été supprimé avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Abonnement introuvable"),
            new OA\Response(response: 400, description: "Erreur lors de la suppression")
        ]
    )]
    public function delete(string $uuid): Response
    {
        try {
            $this->abonnementManager->delete($uuid);
            $response = (new JsonHelper(null, 'L\'abonnement a été supprimé avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read', 'entity']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }

    #[Route("/{uuid}/renouveler", name: "renouveler_abonnement", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/abonnement/{uuid}/renouveler",
        summary: "Renouveler un abonnement",
        parameters: [
            new OA\Parameter(
                name: "uuid",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "duree", type: "integer", example: 30, description: "Durée en jours (par défaut: 30)")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Abonnement renouvelé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Abonnement renouvelé avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Abonnement introuvable"),
            new OA\Response(response: 400, description: "Erreur lors du renouvellement")
        ]
    )]
    public function renouveler(Request $request, string $uuid): Response
    {
        try {
            $content = json_decode($request->getContent());
            $duree = isset($content->duree) ? (int)$content->duree : 30;
            
            $abonnement = $this->abonnementManager->renouveler($uuid, $duree);
            $response = (new JsonHelper($abonnement, 'Abonnement renouvelé avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read', 'entity']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }

    #[Route("/{uuid}/toggle-actif", name: "toggle_actif_abonnement", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/abonnement/{uuid}/toggle-actif",
        summary: "Activer/Désactiver un abonnement",
        parameters: [
            new OA\Parameter(
                name: "uuid",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "actif", type: "boolean", example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Statut de l'abonnement modifié avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Statut modifié avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Abonnement introuvable"),
            new OA\Response(response: 400, description: "Erreur lors de la modification")
        ]
    )]
    public function toggleActif(Request $request, string $uuid): Response
    {
        try {
            $content = json_decode($request->getContent());
            
            if (!isset($content->actif)) {
                throw new ExceptionApi(
                    "Le paramètre 'actif' est requis.",
                    ["msg" => "Paramètre requis."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            
            $abonnement = $this->abonnementManager->toggleActif($uuid, $content->actif);
            $response = (new JsonHelper($abonnement, 'Statut modifié avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read', 'entity']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }

    #[Route("/{uuid}/activer-faveur", name: "activer_faveur_abonnement", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/abonnement/{uuid}/activer-faveur",
        summary: "Activer une faveur sur un abonnement",
        parameters: [
            new OA\Parameter(
                name: "uuid",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "duree", type: "integer", example: 15, description: "Durée de la faveur en jours")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Faveur activée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Faveur activée avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Abonnement introuvable"),
            new OA\Response(response: 422, description: "Durée requise"),
            new OA\Response(response: 400, description: "Erreur lors de l'activation")
        ]
    )]
    public function activerFaveur(Request $request, string $uuid): Response
    {
        try {
            $content = json_decode($request->getContent());
            $content->duree = 3;
            if (!isset($content->duree)) {
                throw new ExceptionApi(
                    "La durée de la faveur est requise.",
                    ["msg" => "Durée requise."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            
            $abonnement = $this->abonnementManager->activerFaveur($uuid, (int)$content->duree);
            $response = (new JsonHelper($abonnement, 'Faveur activée avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read', 'entity']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }

    #[Route("/{uuid}/desactiver-faveur", name: "desactiver_faveur_abonnement", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/abonnement/{uuid}/desactiver-faveur",
        summary: "Désactiver une faveur sur un abonnement",
        parameters: [
            new OA\Parameter(
                name: "uuid",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Faveur désactivée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Faveur désactivée avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Abonnement introuvable"),
            new OA\Response(response: 400, description: "Erreur lors de la désactivation")
        ]
    )]
    public function desactiverFaveur(string $uuid): Response
    {
        try {
            $abonnement = $this->abonnementManager->desactiverFaveur($uuid);
            $response = (new JsonHelper($abonnement, 'Faveur désactivée avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read', 'entity']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }

    #[Route("/expirer", name: "expirer_abonnements", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/abonnement/expirer",
        summary: "Désactiver tous les abonnements expirés",
        responses: [
            new OA\Response(
                response: 200,
                description: "Abonnements expirés désactivés",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object", properties: [
                            new OA\Property(property: "count", type: "integer", example: 5)
                        ]),
                        new OA\Property(property: "message", type: "string", example: "5 abonnement(s) désactivé(s)"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de l'opération")
        ]
    )]
    public function expirer(): Response
    {
        try {
            $count = $this->abonnementManager->desactiverAbonnementsExpires();
            $message = $count > 0 ? "{$count} abonnement(s) désactivé(s)" : "Aucun abonnement expiré trouvé";
            $response = (new JsonHelper(['count' => $count], $message, 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
        
        return $this->json($response, 200);
    }

    #[Route("/statistics", name: "statistics_abonnements", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/abonnement/statistics",
        summary: "Statistiques des abonnements",
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistiques récupérées avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object", properties: [
                            new OA\Property(property: "total", type: "integer", example: 100),
                            new OA\Property(property: "actifs", type: "integer", example: 85),
                            new OA\Property(property: "expires", type: "integer", example: 10),
                            new OA\Property(property: "avec_faveur", type: "integer", example: 15),
                            new OA\Property(property: "inactifs", type: "integer", example: 15)
                        ]),
                        new OA\Property(property: "message", type: "string", example: null),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function statistics(): Response
    {
        try {
            $stats = $this->abonnementManager->getStatistics();
            $response = (new JsonHelper($stats, null, 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
        
        return $this->json($response, 200);
    }

    #[Route("/actifs", name: "abonnements_actifs", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/abonnement/actifs",
        summary: "Liste des abonnements actifs",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des abonnements actifs",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function actifs(): Response
    {
        try {
            $abonnements = $this->abonnementRepository->findBy(['actif' => true]);
            $response = (new JsonHelper($abonnements, null, 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read', 'entity']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }



    #[Route("/expires", name: "abonnements_expires", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/abonnement/expires",
        summary: "Liste des abonnements expirés",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des abonnements expirés",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function expires(): Response
    {
        try {
            $today = new \DateTime('today');
            $abonnements = $this->abonnementRepository->createQueryBuilder('a')
                ->where('a.dateFin < :today')
                ->setParameter('today', $today)
                ->getQuery()
                ->getResult();
            
            $response = (new JsonHelper($abonnements, null, 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read', 'entity']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }



    #[Route("/avec-faveur", name: "abonnements_avec_faveur", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/abonnement/avec-faveur",
        summary: "Liste des abonnements avec faveur active",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des abonnements avec faveur",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function avecFaveur(): Response
    {
        try {
            $abonnements = $this->abonnementRepository->findBy(['faveurActive' => true]);
            $response = (new JsonHelper($abonnements, null, 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['abonnement:read', 'entity']]);
        }
        
        return $this->json($response, 200, [], ['groups' => ['abonnement:read', 'entity']]);
    }
}