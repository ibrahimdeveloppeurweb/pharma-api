<?php

namespace App\Controller\Security;

use App\Utils\JsonHelper;
use App\Entity\Security\Path;
use App\Exception\ExceptionApi;
use App\Manager\Security\PathManager;
use App\Repository\Security\PathRepository;
use App\Repository\Extra\PaginateRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

#[Route("/api/v1/secure/path")]
#[OA\Tag(name: "Path")]
class PathController extends AbstractController
{
    private $paginate;
    private $pathManager;
    private $pathRepository;
    
    public function __construct(
        PaginateRepository $paginate,
        PathManager $pathManager,
        PathRepository $pathRepository
    )
    {
        $this->paginate = $paginate;
        $this->pathManager = $pathManager;
        $this->pathRepository = $pathRepository;
    }
    
    #[Route("", name: "index_path", methods: ["GET"])]
    #[Route("/", name: "index_path_slash", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/path",
        summary: "Liste des routes",
        parameters: [
            new OA\Parameter(
                name: "page",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 1),
                description: "Index de page de pagination"
            ),
            new OA\Parameter(
                name: "limit",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 10),
                description: "Nombre d'éléments à ramener"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des routes avec pagination",
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
            new OA\Response(response: 400, description: "Erreur lors de la récupération des routes")
        ]
    )]
    public function index(Request $request)
    {
        $paths = [];
        try {
            $page = $request->query->getInt('page', 1);
            $limit = $request->query->getInt('limit', 10);
            $paths = $this->paginate->paginator(Path::class, $page, $limit, 'desc');
            $response = (new JsonHelper($paths, null,'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['path']]);
        }
        return $this->json($response, 200, [], ['groups' => ['path', 'entity']]);
    }
    
    #[Route("/new", name: "new_path", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/path/new",
        summary: "Ajouter une route",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "name", type: "string", example: "route_example"),
                    new OA\Property(property: "path", type: "string", example: "/api/v1/example"),
                    new OA\Property(property: "method", type: "string", example: "GET"),
                    new OA\Property(property: "description", type: "string", example: "Description de la route")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Route créée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Ajout effectué avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la création de la route")
        ]
    )]
    public function new(Request $request)
    {
        try {
            $data = json_decode($request->getContent());
            $Path = $this->pathManager->create($data);
            $response = (new JsonHelper($Path, 'Ajout effectué avec succès','success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['path']]);
        }
        return $this->json($response, 200, [], ['groups' => ['path', 'entity']]);
    }
    
    #[Route("/{uuid}/show", name: "show_path", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/path/{uuid}/show",
        summary: "Détails d'une route",
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
                description: "Informations détaillées de la route",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: null),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération de la route")
        ]
    )]
    public function show(string $uuid)
    {
        try {
            $Path = $this->pathRepository->findOneByUuid($uuid);
            $response = (new JsonHelper($Path, null,'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['path']]);
        }
        return $this->json($response, 200, [], ['groups' => ['path', 'entity']]);
    }
    
    #[Route("/{uuid}/edit", name: "edit_path", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/path/{uuid}/edit",
        summary: "Modifier une route",
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
                    new OA\Property(property: "name", type: "string", example: "route_example"),
                    new OA\Property(property: "path", type: "string", example: "/api/v1/example"),
                    new OA\Property(property: "method", type: "string", example: "GET"),
                    new OA\Property(property: "description", type: "string", example: "Description de la route")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Route mise à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Mise à jour effectuée avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la modification de la route")
        ]
    )]
    public function edit(string $uuid, Request $request)
    {
        try {
            $data = json_decode($request->getContent());
            $Path = $this->pathManager->update($data, $uuid);
            $response = (new JsonHelper($Path, 'Mise à jour effectuée avec succès','success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['path']]);
        }
        return $this->json($response, 200, [], ['groups' => ['path', 'entity']]);
    }
    
    #[Route("/{uuid}/delete", name: "delete_path", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/path/{uuid}/delete",
        summary: "Supprimer une route",
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
                description: "Route supprimée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "null"),
                        new OA\Property(property: "message", type: "string", example: "Suppression effectuée avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la suppression de la route")
        ]
    )]
    public function delete(string $uuid)
    {
        try {
            $this->pathManager->delete($uuid);
            $response = (new JsonHelper(null, 'Suppression effectuée avec succès','success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['path']]);
        }
        return $this->json($response, 200, [], ['groups' => ['path']]);
    }
}