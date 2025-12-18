<?php

namespace App\Controller\Gestion;

use App\Utils\JsonHelper;
use App\Utils\TypeVariable;
use App\Exception\ExceptionApi;
use App\Manager\Gestion\PharmacieManager;
use App\Repository\Security\UserRepository;
use App\Repository\Gestion\PharmacieRepository;
use App\Utils\PharmacyHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

#[Route(path: "/api/v1/secure/pharmacie")]
#[OA\Tag(name: "Pharmacies")]
class PharmacieController extends AbstractController
{
    private $pharmacieManager;
    private $pharmacieRepository;
    private $userRepository;

    public function __construct(
        PharmacieManager $pharmacieManager,
        PharmacieRepository $pharmacieRepository,
        UserRepository $userRepository,
    )
    {
        $this->pharmacieManager = $pharmacieManager;
        $this->pharmacieRepository = $pharmacieRepository;
        $this->userRepository = $userRepository;
    }
    
    #[Route("/", name: "index_pharmacie", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie",
        summary: "Liste des pharmacies",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des pharmacies récupérée avec succès",
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
            new OA\Response(response: 400, description: "Erreur lors de la récupération des pharmacies")
        ]
    )]
    public function index(Request $request): Response
    {
        $parameters = (object) [
                'period' => $request->query->get('period'),
                'dateD' => $request->query->get('startDate'),
                'dateF' => $request->query->get('endDate'),
                'statut' => $request->query->get('statut'),
            ];

        $pharmacies = [];
        
        try {
            $pharmacies = $this->pharmacieRepository->findIndex($parameters);
            $response = (new JsonHelper($pharmacies, null, 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['pharmacie:read']]);
        }
        return $this->json($response, 200, [], ['groups' => ['pharmacie:read', 'entity']]);
    }

    #[Route("/new", name: "new_pharmacie", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/new",
        summary: "Créer une nouvelle pharmacie",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "type", type: "string", example: "ADMIN"),
                    new OA\Property(property: "nom_pharmacien", type: "string", example: "nom du Pharmacien "),
                    new OA\Property(property: "nom", type: "string", example: "Pharmacie Centrale"),
                    new OA\Property(property: "numero", type: "string", example: "Numero autorisation "),
                    new OA\Property(property: "adresse", type: "string", example: "15 Avenue des Champs"),
                    new OA\Property(property: "ville", type: "string", example: "Paris"),
                    new OA\Property(property: "code_postal", type: "string", example: "75008"),
                    new OA\Property(property: "telephone", type: "string", example: "+33145678901"),
                    new OA\Property(property: "email", type: "string", example: "contact@pharmacie-centrale.fr"),
                    new OA\Property(property: "password", type: "string", example: "12345678")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Pharmacie créée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Pharmacie créée avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la création de la pharmacie")
        ]
    )]
    public function new(Request $request)
    {
        try {
            $content = json_decode($request->getContent());
            $pharmacie = $this->pharmacieManager->create($content);
            $response = (new JsonHelper($pharmacie, 'Pharmacie créée avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['pharmacie:read', 'entity', 'file', 'folder']]);
        }
        return $this->json($response, 200, [], ['groups' => ['pharmacie:read', 'entity', 'file', 'folder']]);
    }

    #[Route("/{uuid}/edit", name: "edit_pharmacie", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/{uuid}/edit",
        summary: "Modifier une pharmacie",
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
                    new OA\Property(property: "nom", type: "string", example: "Pharmacie Centrale"),
                    new OA\Property(property: "adresse", type: "string", example: "15 Avenue des Champs"),
                    new OA\Property(property: "ville", type: "string", example: "Paris"),
                    new OA\Property(property: "code_postal", type: "string", example: "75008"),
                    new OA\Property(property: "telephone", type: "string", example: "+33145678901"),
                    new OA\Property(property: "email", type: "string", example: "contact@pharmacie-centrale.fr")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Pharmacie modifiée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Pharmacie modifiée avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la modification")
        ]
    )]
    public function edit(Request $request, $uuid)
    {
        try {
            $content = json_decode($request->getContent());
            $pharmacie = $this->pharmacieManager->update($content, $uuid);
            $response = (new JsonHelper($pharmacie, 'Pharmacie modifiée avec succès', 'success', 200, []))->serialize();
                  return $this->json($response, 200, [], ['groups' => ['pharmacie:read', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['pharmacie:read', 'entity', 'file', 'folder']]);
        }
    }

    #[Route("/{uuid}/delete", name: "delete_pharmacie", methods: ["DELETE"])]
    #[OA\Delete(
        path: "/api/v1/secure/pharmacie/{uuid}/delete",
        summary: "Supprimer une pharmacie",
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
                description: "Pharmacie supprimée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "null"),
                        new OA\Property(property: "message", type: "string", example: "La pharmacie a été supprimée avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la suppression")
        ]
    )]
    public function delete($uuid)
    {
        try {
            $this->pharmacieManager->delete($uuid);
            $response = (new JsonHelper(null, 'La pharmacie a été supprimée avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['pharmacie', 'entity', 'file', 'folder']]);
        }
        return $this->json($response, 200, [], ['groups' => ['pharmacie', 'entity', 'file', 'folder']]);
    }

    #[Route("/plus-actives", name: "list_pharmacies_plus_actives", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/plus-actives",
        summary: "Liste des pharmacies les plus actives",
        parameters: [
            new OA\Parameter(
                name: "limit",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 10),
                description: "Nombre de pharmacies à retourner"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des pharmacies les plus actives",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function plusActives(Request $request)
    {
        try {
            $data = \json_decode(json_encode($request->query->all()));
            $limit = 10;
            
            if (isset($data->limit) && TypeVariable::is_not_null($data->limit)) {
                $limit = (int)$data->limit;
            }

            $pharmacies = $this->pharmacieRepository->findPlusActives($limit);
            return $this->json($pharmacies, 200, [], ['groups' => ['pharmacie', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/plus-patients", name: "list_pharmacies_plus_patients", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/plus-patients",
        summary: "Liste des pharmacies avec le plus de patients",
        parameters: [
            new OA\Parameter(
                name: "limit",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 10),
                description: "Nombre de pharmacies à retourner"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des pharmacies avec le plus de patients",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function plusPatients(Request $request)
    {
        try {
            $data = \json_decode(json_encode($request->query->all()));
            $limit = 10;
            
            if (isset($data->limit) && TypeVariable::is_not_null($data->limit)) {
                $limit = (int)$data->limit;
            }

            $pharmacies = $this->pharmacieRepository->findAvecPlusPatients($limit);
            return $this->json($pharmacies, 200, [], ['groups' => ['pharmacie', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/statistics", name: "statistics_pharmacies", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/statistics",
        summary: "Statistiques globales des pharmacies",
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistiques globales des pharmacies",
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
            new OA\Response(response: 400, description: "Erreur lors de la récupération des statistiques")
        ]
    )]
    public function statistics(Request $request)
    { 
            $parameters = (object) [
                'period' => $request->query->get('period'),
                'dateD' => $request->query->get('startDate'),
                'dateF' => $request->query->get('endDate'),
            ];
        $datas = [];
        try {
             $datas = $this->pharmacieManager->statistics($parameters);
            $response = (new JsonHelper($datas, null, 'success', 200, []))->serialize();
          
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }

         return $this->json($response, 200, [], ['groups' => ['demande:read', 'entity']]);
    }



 



    #[Route("/nouvelles", name: "list_pharmacies_nouvelles", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/nouvelles",
        summary: "Liste des pharmacies récentes",
        parameters: [
            new OA\Parameter(
                name: "jours",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 30),
                description: "Nombre de jours pour considérer une pharmacie comme récente"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des pharmacies récentes",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function nouvelles(Request $request)
    {
        try {
            $data = \json_decode(json_encode($request->query->all()));
            $jours = 30;
            
            if (isset($data->jours) && TypeVariable::is_not_null($data->jours)) {
                $jours = (int)$data->jours;
            }

            $pharmacies = $this->pharmacieRepository->findNouvelles($jours);
            return $this->json($pharmacies, 200, [], ['groups' => ['pharmacie', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/par-ville", name: "pharmacies_par_ville", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/par-ville",
        summary: "Groupement des pharmacies par ville",
        responses: [
            new OA\Response(
                response: 200,
                description: "Pharmacies groupées par ville",
                content: new OA\JsonContent(
                    type: "object",
                    additionalProperties: new OA\AdditionalProperties(
                        type: "array",
                        items: new OA\Items(type: "object")
                    )
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors du groupement")
        ]
    )]
    public function parVille(Request $request)
    {
        try {
            $groupement = $this->pharmacieRepository->groupByVille();
            return $this->json($groupement, 200, []);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/search", name: "search_pharmacie", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/search",
        summary: "Rechercher une pharmacie",
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "string"),
                description: "Terme de recherche (nom, ville, adresse)"
            ),
            new OA\Parameter(
                name: "limit",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 20),
                description: "Nombre maximum de résultats"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Résultats de recherche",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 422, description: "Le terme de recherche est requis"),
            new OA\Response(response: 400, description: "Erreur lors de la recherche")
        ]
    )]
    public function search(Request $request)
    {
        try {
            $data = \json_decode(json_encode($request->query->all()));
            
            if (!isset($data->search) || !TypeVariable::is_not_null($data->search)) {
                throw new ExceptionApi(
                    "Le terme de recherche est requis.",
                    ["msg" => "Recherche requise."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $limit = 20;
            if (isset($data->limit) && TypeVariable::is_not_null($data->limit)) {
                $limit = (int)$data->limit;
            }

            $pharmacies = $this->pharmacieRepository->search($data->search, $limit);
            return $this->json($pharmacies, 200, [], ['groups' => ['pharmacie', 'entity']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }
}