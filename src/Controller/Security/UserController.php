<?php

namespace App\Controller\Security;

use App\Utils\JsonHelper;
use App\Entity\Security\User;
use App\Exception\ExceptionApi;
use App\Manager\Security\UserManager;
use App\Repository\Security\UserRepository;
use App\Repository\Extra\PaginateRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

#[Route("/api/v1/secure/user")]
#[OA\Tag(name: "Utilisateur", description: "Gestion des utilisateurs")]
class UserController extends AbstractController
{
    private PaginateRepository $paginate;
    private UserManager $userManager;
    private UserRepository $userRepository;

    public function __construct(
        UserManager $userManager,
        PaginateRepository $paginate,
        UserRepository $userRepository
    ) {
        $this->paginate = $paginate;
        $this->userManager = $userManager;
        $this->userRepository = $userRepository;
    }

    #[Route("", name: "index_user", methods: ["GET"])]
    #[Route("/", name: "index_user_slash", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/user",
        summary: "Liste des utilisateurs avec pagination",
        tags: ["Utilisateur"]
    )]
    #[OA\Parameter(
        name: "page",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "integer", default: 1),
        description: "Index de page de pagination"
    )]
    #[OA\Parameter(
        name: "limit",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "integer", default: 10),
        description: "Nombre d'éléments à ramener"
    )]
    #[OA\Response(
        response: 200,
        description: "Liste des utilisateurs paginée",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "uuid", type: "string", example: "abc-123"),
                            new OA\Property(property: "nom", type: "string", example: "Dupont"),
                            new OA\Property(property: "prenom", type: "string", example: "Jean"),
                            new OA\Property(property: "email", type: "string", example: "jean.dupont@example.com")
                        ]
                    )
                ),
                new OA\Property(property: "message", type: "string", example: null),
                new OA\Property(property: "type", type: "string", example: "success"),
                new OA\Property(property: "code", type: "integer", example: 200)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Erreur lors de la récupération des utilisateurs"
    )]
    public function index(Request $request)
    {
        try {
            $page = $request->query->getInt('page', 1);
            $limit = $request->query->getInt('limit', 10);
            $users = $this->paginate->query(User::class, $page, $limit, 'desc');
            $response = (new JsonHelper($users, null, 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['user', 'entity']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/{uuid}/show", name: "show_user", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/user/{uuid}/show",
        summary: "Détails d'un utilisateur",
        tags: ["Utilisateur"]
    )]
    #[OA\Parameter(
        name: "uuid",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "string"),
        description: "UUID de l'utilisateur"
    )]
    #[OA\Response(
        response: 200,
        description: "Détails de l'utilisateur",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        new OA\Property(property: "uuid", type: "string", example: "abc-123"),
                        new OA\Property(property: "nom", type: "string", example: "Dupont"),
                        new OA\Property(property: "prenom", type: "string", example: "Jean"),
                        new OA\Property(property: "email", type: "string", example: "jean.dupont@example.com")
                    ]
                ),
                new OA\Property(property: "message", type: "string", example: null),
                new OA\Property(property: "type", type: "string", example: "success"),
                new OA\Property(property: "code", type: "integer", example: 200)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Utilisateur non trouvé"
    )]
    public function show(string $uuid)
    {
        try {
            $user = $this->userRepository->findOneByUuid($uuid);
            $response = (new JsonHelper($user, null, 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['user', 'entity']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/new", name: "new_user", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/user/new",
        summary: "Créer un utilisateur",
        tags: ["Utilisateur"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "nom", type: "string", example: "Dupont"),
                new OA\Property(property: "prenom", type: "string", example: "Jean"),
                new OA\Property(property: "email", type: "string", example: "jean.dupont@example.com"),
                new OA\Property(property: "contact", type: "string", example: "+33612345678"),
                new OA\Property(property: "role", type: "string", example: "ROLE_USER")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Utilisateur créé avec succès",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "data", type: "object"),
                new OA\Property(property: "message", type: "string", example: "Créé avec succès"),
                new OA\Property(property: "type", type: "string", example: "success"),
                new OA\Property(property: "code", type: "integer", example: 200)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Erreur lors de la création"
    )]
    public function new(Request $request)
    {
        try {
            $data = json_decode($request->getContent());
            $user = $this->userManager->create($data);
            $response = (new JsonHelper($user, 'Créé avec succès', 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['user', 'entity']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/{uuid}/edit", name: "edit_user", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/user/{uuid}/edit",
        summary: "Modifier un utilisateur",
        tags: ["Utilisateur"]
    )]
    #[OA\Parameter(
        name: "uuid",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "string"),
        description: "UUID de l'utilisateur"
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "nom", type: "string", example: "Dupont"),
                new OA\Property(property: "prenom", type: "string", example: "Jean"),
                new OA\Property(property: "email", type: "string", example: "jean.dupont@example.com"),
                new OA\Property(property: "contact", type: "string", example: "+33612345678"),
                new OA\Property(property: "role", type: "string", example: "ROLE_USER")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Utilisateur modifié avec succès",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "data", type: "object"),
                new OA\Property(property: "message", type: "string", example: "Mise à jour effectuée"),
                new OA\Property(property: "type", type: "string", example: "success"),
                new OA\Property(property: "code", type: "integer", example: 200)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Erreur lors de la modification"
    )]
    public function edit(string $uuid, Request $request)
    {
        try {
            $data = json_decode($request->getContent());
            $this->userManager->update($data, $uuid);
            $user = $this->userRepository->findOneByUuid($uuid);
            $datas = [
                'id' => $user->getId(),
                'uuid' => $user->getUuid(),
                'email' => $user->getEmail(),
                'nom' => $user->getNom(),
                'type' => $user->getType(),
                'role' => $user->getType(),
                'contact' => $user->getTelephone(),
                'adresse' => $user->getPharmacie() ? $user->getPharmacie()->getAdresse() : null,
                'pharmacie' =>  $user->getPharmacie() ? [
                    'uuid' => $user->getPharmacie()->getUuid(),
                    'nom' => $user->getPharmacie()->getNom(),
                    'latitude' => $user->getPharmacie()->getLatitude(),
                    'longitude' => $user->getPharmacie()->getLongitude(),
                    'lieuExact' => $user->getPharmacie()->getLieuExact(),
                    'isDeGarde' => $user->getPharmacie()->getIsDeGarde(),
                    'isDisponibleRecherche' => $user->getPharmacie()->getIsDisponibleRecherche(),
                ] : null,
                'refreshToken' => $data->refreshToken,
            ];
            $this->getUser();
            $response = (new JsonHelper($datas, 'Mise à jour effectuée', 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['user:write', 'entity']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/update-fcm-token", name: "update_fcm_token", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/user/update-fcm-token",
        summary: "Mettre à jour le jeton Firebase (FCM) de l'utilisateur",
        tags: ["Utilisateur"]
    )]
    public function updateFcmToken(
        Request $request,
        \Doctrine\ORM\EntityManagerInterface $em,
        \Psr\Log\LoggerInterface $logger
    ): JsonResponse {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \Exception('Utilisateur non connecté');
            }

            $content = json_decode($request->getContent(), true);
            $token = is_array($content) ? trim((string) ($content['fcmToken'] ?? '')) : '';
            if ($token === '') {
                throw new \Exception('fcmToken manquant');
            }

            // Un appareil = un seul compte : si ce jeton était rattaché à un autre utilisateur
            // (déconnexion / reconnexion avec un autre compte sur le même téléphone), on le lui
            // retire, sinon l'ancien compte continuerait à recevoir les alertes de ce téléphone.
            $em->createQueryBuilder()
                ->update(User::class, 'u')
                ->set('u.fcmToken', ':null')
                ->where('u.fcmToken = :token')
                ->andWhere('u != :me')
                ->setParameter('null', null)
                ->setParameter('token', $token)
                ->setParameter('me', $user)
                ->getQuery()
                ->execute();

            $user->setFcmToken($token);
            $em->flush();

            $logger->info('Jeton FCM enregistré pour l\'utilisateur #{id}.', ['id' => $user->getId()]);

            return $this->json((new JsonHelper(null, 'Token FCM mis à jour', 'success', 200, []))->serialize(), 200);
        } catch (\Exception $e) {
            $logger->warning('Mise à jour du jeton FCM refusée : {error}', ['error' => $e->getMessage()]);

            return $this->json((new JsonHelper(null, $e->getMessage(), 'error', 400, []))->serialize(), 400);
        }
    }

    #[Route("/{uuid}/delete", name: "delete_user", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/user/{uuid}/delete",
        summary: "Supprimer un utilisateur",
        tags: ["Utilisateur"]
    )]
    #[OA\Parameter(
        name: "uuid",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "string"),
        description: "UUID de l'utilisateur"
    )]
    #[OA\Response(
        response: 200,
        description: "Utilisateur supprimé avec succès",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "data", type: "null"),
                new OA\Property(property: "message", type: "string", example: "Suppression réussie"),
                new OA\Property(property: "type", type: "string", example: "success"),
                new OA\Property(property: "code", type: "integer", example: 200)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Erreur lors de la suppression"
    )]
    public function delete(string $uuid)
    {
        try {
            $this->userManager->delete($uuid);
            $response = (new JsonHelper(null, 'Suppression réussie', 'success', 200, []))->serialize();
            return $this->json($response, 200);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }
}