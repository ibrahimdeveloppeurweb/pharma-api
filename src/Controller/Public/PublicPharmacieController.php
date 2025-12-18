<?php

namespace App\Controller\Public;

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

#[Route(path: "/api/v1/public/pharmacie")]
#[OA\Tag(name: "Pharmacies publiques")]
class PublicPharmacieController extends AbstractController
{
    private $pharmacieManager;
    private $pharmacieRepository;
    private $userRepository;

    public function __construct(
        PharmacieManager $pharmacieManager,
        PharmacieRepository $pharmacieRepository,
        UserRepository $userRepository
    )
    {
        $this->pharmacieManager = $pharmacieManager;
        $this->pharmacieRepository = $pharmacieRepository;
        $this->userRepository = $userRepository;
    }


    
   

    #[Route("/new", name: "publi_cnew_pharmacie", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/public/pharmacie/new",
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
            return $this->json($response, $e->getCode(), [], ['groups' => ['pharmacie', 'entity', 'file', 'folder']]);
        }
        return $this->json($response, 200, [], ['groups' => ['pharmacie', 'entity', 'file', 'folder']]);
    }



}