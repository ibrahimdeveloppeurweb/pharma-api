<?php

namespace App\Controller\Security;

use App\Utils\JsonHelper;
use App\Entity\Security\Role;
use App\Exception\ExceptionApi;
use App\Manager\Security\RoleManager;
use App\Repository\Security\RoleRepository;
use App\Repository\Extra\PaginateRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

#[Route("/api/v1/secure/permission")]
#[OA\Tag(name: "Role")]
class RoleController extends AbstractController
{
    private $paginate;
    private $roleManager;
    private $roleRepository;
    
    public function __construct(
        PaginateRepository $paginate,
        RoleManager $roleManager,
        RoleRepository $roleRepository
    )
    {
        $this->paginate = $paginate;
        $this->roleManager = $roleManager;
        $this->roleRepository = $roleRepository;
    }
    
    #[Route("", name: "index_role", methods: ["GET"])]
    #[Route("/", name: "index_role_slash", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/permission",
        summary: "Liste des permissions avec pagination",
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
                description: "Liste des permissions avec pagination",
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
            new OA\Response(response: 400, description: "Erreur lors de la récupération des permissions")
        ]
    )]
    public function index(Request $request)
    {
        $roles = [];
        try {
            $page = $request->query->getInt('page', 1);
            $limit = $request->query->getInt('limit', 10);
            $roles = $this->paginate->paginator(Role::class, $page, $limit, 'desc');
            $response = (new JsonHelper($roles, null,'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['role']]);
        }
        return $this->json($response, 200, [], ['groups' => ['role', 'entity']]);
    }
    
    #[Route("/all", name: "index_all_role", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/permission/all",
        summary: "Liste de toutes les permissions sans pagination",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste complète des permissions",
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
            new OA\Response(response: 400, description: "Erreur lors de la récupération des permissions")
        ]
    )]
    public function all()
    {
        $roles = [];
        try {
            $roles = $this->roleRepository->findAll();
            $response = (new JsonHelper($roles, null,'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['role']]);
        }
        return $this->json($response, 200, [], ['groups' => ['role', 'entity']]);
    }
    
    #[Route("/new", name: "new_role", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/permission/new",
        summary: "Ajouter une permission",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "name", type: "string", example: "ADMIN"),
                    new OA\Property(property: "description", type: "string", example: "Rôle administrateur"),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "string"), example: ["USER:LIST", "USER:EDIT"])
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Permission créée avec succès",
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
            new OA\Response(response: 400, description: "Erreur lors de la création de la permission")
        ]
    )]
    public function new(Request $request)
    {
        try {
            $data = json_decode($request->getContent());
            $Role = $this->roleManager->create($data);
            $response = (new JsonHelper($Role, 'Ajout effectué avec succès','success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['role']]);
        }
        return $this->json($response, 200, [], ['groups' => ['role', 'entity']]);
    }
    
    #[Route("/{uuid}/show", name: "show_role", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/permission/{uuid}/show",
        summary: "Détails d'une permission",
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
                description: "Informations détaillées de la permission",
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
            new OA\Response(response: 400, description: "Erreur lors de la récupération de la permission")
        ]
    )]
    public function show(string $uuid)
    {
        try {
            $Role = $this->roleRepository->findOneByUuid($uuid);
            $response = (new JsonHelper($Role, null,'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['role']]);
        }
        return $this->json($response, 200, [], ['groups' => ['role', 'entity']]);
    }
    
    #[Route("/{uuid}/edit", name: "edit_role", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/permission/{uuid}/edit",
        summary: "Modifier une permission",
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
                    new OA\Property(property: "name", type: "string", example: "ADMIN"),
                    new OA\Property(property: "description", type: "string", example: "Rôle administrateur"),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "string"), example: ["USER:LIST", "USER:EDIT"])
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Permission mise à jour avec succès",
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
            new OA\Response(response: 400, description: "Erreur lors de la modification de la permission")
        ]
    )]
    public function edit(string $uuid, Request $request)
    {
        try {
            $data = json_decode($request->getContent());
            $Role = $this->roleManager->update($data, $uuid);
            $response = (new JsonHelper($Role, 'Mise à jour effectuée avec succès','success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['role']]);
        }
        return $this->json($response, 200, [], ['groups' => ['role', 'entity']]);
    }
    
    #[Route("/{uuid}/delete", name: "delete_role", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/permission/{uuid}/delete",
        summary: "Supprimer une permission",
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
                description: "Permission supprimée avec succès",
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
            new OA\Response(response: 400, description: "Erreur lors de la suppression de la permission")
        ]
    )]
    public function delete(string $uuid)
    {
        try {
            $this->roleManager->delete($uuid);
            $response = (new JsonHelper(null, 'Suppression effectuée avec succès','success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['role']]);
        }
        return $this->json($response, 200, [], ['groups' => ['role']]);
    }
}