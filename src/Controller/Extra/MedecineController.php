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

    public function __construct(
        MedecineRepository $medecineRepository,
    )
    {
        $this->medecineRepository = $medecineRepository;
    }
    
    #[Route("/", name: "index_medecine", methods: ["GET"])]
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