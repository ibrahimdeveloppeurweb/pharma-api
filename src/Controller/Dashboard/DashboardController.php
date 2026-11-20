<?php

namespace App\Controller\Dashboard;

use App\Utils\JsonHelper;
use App\Exception\ExceptionApi;
use App\Manager\Dashboard\DashboardManager;
use App\Utils\PharmacyHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

#[Route(path: "/api/v1/secure/pharmacie/dashboard")]
#[OA\Tag(name: "Dashboard")]
class DashboardController extends AbstractController
{

    private $dashboardManager;
    public function __construct(
     DashboardManager $dashboardManager
    )
       
    {
         $this->dashboardManager = $dashboardManager;
    }
    
    #[Route("", name: "index_dashboard", methods: ["GET"])]
    #[Route("/", name: "index_dashboard_slash", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/dashboard",
        summary: "tableau de bord pharmacie",
        responses: [
            new OA\Response(
                response: 200,
                description: "tableau de bord récupéré avec succès",
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
    public function dashboard(Request $request): Response
    {
           $parameters = (object) [
                'period' => $request->query->get('period'),
                'dateD' => $request->query->get('start_date'),
                'dateF' => $request->query->get('end_date'),
            ];;
        $datas = [];
        
        try {
          
            $pharmacy = PharmacyHelper::pharmacy($this->getUser());
            $datas = $this->dashboardManager->dashboard($parameters, $pharmacy);
            $response = (new JsonHelper($datas, null, 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['dashboard']]);
        }
        return $this->json($response, 200, [], ['groups' => ['dashboard', 'entity']]);
    }




}