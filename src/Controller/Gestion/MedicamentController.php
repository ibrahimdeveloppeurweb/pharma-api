<?php

namespace App\Controller\Gestion;

use App\Utils\JsonHelper;
use App\Utils\TypeVariable;
use App\Exception\ExceptionApi;
use App\Manager\Gestion\MedicamentManager;
use App\Repository\Security\UserRepository;
use App\Repository\Gestion\MedicamentRepository;
use App\Repository\Gestion\PharmacieRepository;
use App\Utils\PharmacyHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

#[Route(path: "/api/v1/secure/pharmacie/medicament")]
#[OA\Tag(name: "Médicaments")]
class MedicamentController extends AbstractController
{
    private $medicamentManager;
    private $medicamentRepository;
    private $pharmacieRepository;

    public function __construct(
        MedicamentManager $medicamentManager,
        MedicamentRepository $medicamentRepository,
        PharmacieRepository $pharmacieRepository,
    )
    {
        $this->medicamentManager = $medicamentManager;
        $this->medicamentRepository = $medicamentRepository;
        $this->pharmacieRepository = $pharmacieRepository;
    }
    
    #[Route("", name: "index_medicament", methods: ["GET"])]
    #[Route("/", name: "index_medicament_slash", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/medicament",
        summary: "Liste des médicaments",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des médicaments récupérée avec succès",
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
            new OA\Response(response: 400, description: "Erreur lors de la récupération des médicaments")
        ]
    )]
    public function index(Request $request): Response
    {
        $data = \json_decode(json_encode($request->query->all()));
        $pharmacie = null;
        $medicaments = [];
        
        try {
            $pharmacie = PharmacyHelper::pharmacy($this->getUser());
            $medicaments = $this->medicamentRepository->findIndex($data, $this->getUser(), $pharmacie);
            $response = (new JsonHelper($medicaments, null, 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['medicament:read']]);
        }
        return $this->json($response, 200, [], ['groups' => ['medicament:read', 'entity']]);
    }

    #[Route("/new", name: "new_medicament", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/medicament/new",
        summary: "Ajouter un nouveau médicament",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "nom", type: "string", example: "Paracétamol"),
                    new OA\Property(property: "description", type: "string", example: "Antalgique et antipyrétique"),
                    new OA\Property(property: "prix", type: "number", format: "float", example: 5.50),
                    new OA\Property(property: "stock", type: "integer", example: 100)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Médicament ajouté avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Médicament ajouté avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de l'ajout du médicament")
        ]
    )]
    public function new(Request $request)
    {
        try {
            $pharmacie = PharmacyHelper::pharmacy($this->getUser());
            $content = json_decode($request->getContent());
            $medicament = $this->medicamentManager->create($content, $pharmacie);
            $response = (new JsonHelper($medicament, 'Médicament ajouté avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['medicament', 'entity', 'file', 'folder']]);
        }
        return $this->json($response, 200, [], ['groups' => ['medicament', 'entity', 'file', 'folder']]);
    }

    #[Route("/{uuid}/edit", name: "edit_medicament", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/medicament/{uuid}/edit",
        summary: "Modifier un médicament",
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
                    new OA\Property(property: "nom", type: "string", example: "Paracétamol"),
                    new OA\Property(property: "description", type: "string", example: "Antalgique et antipyrétique"),
                    new OA\Property(property: "prix", type: "number", format: "float", example: 5.50),
                    new OA\Property(property: "stock", type: "integer", example: 100)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Médicament modifié avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Médicament modifié avec succès"),
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
            $pharmacie = PharmacyHelper::pharmacy($this->getUser());
            $content = json_decode($request->getContent());
            $medicament = $this->medicamentManager->update($content, $uuid, $pharmacie);
            $response = (new JsonHelper($medicament, 'Médicament modifié avec succès', 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['medicament', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['medicament', 'entity', 'file', 'folder']]);
        }
    }

    #[Route("/{uuid}/delete", name: "delete_medicament", methods: ["DELETE"])]
    #[OA\Delete(
        path: "/api/v1/secure/pharmacie/medicament/{uuid}/delete",
        summary: "Supprimer un médicament",
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
                description: "Médicament supprimé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "null"),
                        new OA\Property(property: "message", type: "string", example: "Le médicament a été supprimé avec succès"),
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
            $this->medicamentManager->delete($uuid);
            $response = (new JsonHelper(null, 'Le médicament a été supprimé avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['medicament', 'entity', 'file', 'folder']]);
        }
        return $this->json($response, 200, [], ['groups' => ['medicament', 'entity', 'file', 'folder']]);
    }

    #[Route("/plus-demandes", name: "list_medicaments_plus_demandes", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/medicament/plus-demandes",
        summary: "Liste des médicaments les plus demandés",
        parameters: [
            new OA\Parameter(
                name: "pharmacie",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string"),
                description: "UUID de la pharmacie"
            ),
            new OA\Parameter(
                name: "limit",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 10),
                description: "Nombre de médicaments à retourner"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des médicaments les plus demandés",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(type: "object")
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function plusDemandes(Request $request)
    {
        try {
            $data = \json_decode(json_encode($request->query->all()));
            $pharmacie = null;
            $limit = 10;
            
            if (isset($data->pharmacie) && TypeVariable::is_not_null($data->pharmacie)) {
                $pharmacie = $this->pharmacieRepository->findOneByUuid($data->pharmacie);
            }

            if (isset($data->limit) && TypeVariable::is_not_null($data->limit)) {
                $limit = (int)$data->limit;
            }

            $medicaments = $this->medicamentRepository->findPlusDemandes($pharmacie, $limit);
            return $this->json($medicaments, 200, [], ['groups' => ['medicament', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/statistics", name: "statistics_medicaments", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/medicament/statistics",
        summary: "Statistiques des médicaments",
        parameters: [
            new OA\Parameter(
                name: "pharmacie",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string"),
                description: "UUID de la pharmacie"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistiques des médicaments",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "total", type: "integer", example: 150),
                        new OA\Property(property: "stock_faible", type: "integer", example: 5),
                        new OA\Property(property: "plus_demandes", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération des statistiques")
        ]
    )]
    public function statistics(Request $request)
    {
        try {
            $data = \json_decode(json_encode($request->query->all()));
            $pharmacie = null;
            
            if (isset($data->pharmacie) && TypeVariable::is_not_null($data->pharmacie)) {
                $pharmacie = $this->pharmacieRepository->findOneByUuid($data->pharmacie);
            }

            $statistics = $this->medicamentRepository->getStatistics($pharmacie);
            return $this->json($statistics, 200, []);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }
}