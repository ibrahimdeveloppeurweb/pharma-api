<?php

namespace App\Controller\Gestion;

use App\Utils\JsonHelper;
use App\Exception\ExceptionApi;
use App\Manager\Gestion\DemandeManager;
use App\Repository\Security\UserRepository;
use App\Repository\Gestion\PatientRepository;
use App\Repository\Gestion\DemandeRepository;
use App\Repository\Gestion\PharmacieRepository;
use App\Repository\Gestion\MedicamentRepository;
use App\Utils\PharmacyHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

#[Route(path: "/api/v1/secure/pharmacie/demande")]
#[OA\Tag(name: "Demandes")]
class DemandeController extends AbstractController
{
    private $demandeManager;
    private $demandeRepository;
    private $patientRepository;
    private $medicamentRepository;
    private $pharmacieRepository;
    private $userRepository;

    public function __construct(
        DemandeManager $demandeManager,
        DemandeRepository $demandeRepository,
        PatientRepository $patientRepository,
        MedicamentRepository $medicamentRepository,
        PharmacieRepository $pharmacieRepository,
        UserRepository $userRepository,
    )
    {
        $this->demandeManager = $demandeManager;
        $this->demandeRepository = $demandeRepository;
        $this->patientRepository = $patientRepository;
        $this->medicamentRepository = $medicamentRepository;
        $this->pharmacieRepository = $pharmacieRepository;
        $this->userRepository = $userRepository;
    }
    
    #[Route("/", name: "index_demande", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/demande",
        summary: "Liste des demandes",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des demandes récupérée avec succès",
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
            new OA\Response(response: 400, description: "Erreur lors de la récupération des demandes")
        ]
    )]
    public function index(Request $request): Response
    {
        
       $data = $request->query->all();
       $demandes = [];
       $datas = (object)$data;
       $datas->patient = isset($datas->recherche) ? $datas->recherche : null;
        try {
            $pharmacy = PharmacyHelper::pharmacy($this->getUser());
            $demandes = $this->demandeRepository->findIndex($datas, null, $pharmacy);
            $response = (new JsonHelper($demandes , null,'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['demande:read']]);
        }
        return $this->json($response, 200, [], ['groups' => ['demande:read', 'entity']]);
    }

    #[Route("/new", name: "new_demande", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/demande/new",
        summary: "Créer une nouvelle demande",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "nom_patient", type: "string", example: "nom du Patient"),
                    new OA\Property(property: "telephone_patient", type: "string", example: "telephone du Patient"),
                    new OA\Property(
                        property: "medicaments",
                        type: "array",
                        items: new OA\Items(
                            type: "object",
                            properties: [
                                new OA\Property(property: "medicament", type: "string", example: "Paracétamol"),
                                new OA\Property(property: "quantite", type: "integer", example: 2),
                                new OA\Property(property: "prix", type: "number", format: "float", example: 500)
                            ]
                        )
                    ),
                    new OA\Property(property: "mode_paiement", type: "string", example: "mobile money, cash"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Demande créée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Demande créée avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la création de la demande")
        ]
    )]
    public function new(Request $request)
    {
        try {
            $pharmacy = PharmacyHelper::pharmacy($this->getUser());
            $content = json_decode($request->getContent());
            $demande = $this->demandeManager->create($content, $pharmacy);
            $response = (new JsonHelper($demande, 'Demande créée avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['demande:write','entity']]);
        }
        return $this->json($response, 200, [], ['groups' => ['demande:write','entity']]);
    }

    #[Route("/{uuid}/edit", name: "edit_demande", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/demande/{uuid}/edit",
        summary: "Modifier une demande",
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
            content: new OA\JsonContent(type: "object")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Demande modifiée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Demande modifiée avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la modification")
        ]
    )]

    
    #[Route("/{uuid}/show", name: "show_demande", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/demande/{uuid}/show",
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
                description: "Informations détaillées de la demane",
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

            $demande = $this->demandeRepository->findOneByUuid($uuid);
            $response = (new JsonHelper($demande, null,'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(),'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['demande:read','entity'  ]]);
        }
        return $this->json($response, 200, [], ['groups' => ['demande:read', 'entity']]);
    }

    public function edit(Request $request, $uuid)
    {
        try {
            $data = json_decode($request->getContent());
            $demande = $this->demandeManager->update($data, $uuid);
            $response = (new JsonHelper($demande, 'Demande modifiée avec succès', 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['demande', 'patient', 'medicament', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['demande', 'patient', 'medicament', 'file', 'folder']]);
        }
    }

    #[Route("/{uuid}/notifier", name: "notifier_patient_demande", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/demande/{uuid}/notifier",
        summary: "Notifier le patient",
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
                description: "Patient notifié avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Patient notifié avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la notification")
        ]
    )]
    public function notifier($uuid)
    {
        try {
           
            $pharmacy = PharmacyHelper::pharmacy($this->getUser());
            $demande = $this->demandeManager->notifierPatient($uuid, $pharmacy);
            $response = (new JsonHelper($demande, 'Patient notifié avec succès', 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['demande:write', 'patient', 'medicament', 'entity', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['demande:write', 'patient', 'medicament', 'entity', 'folder']]);
        }
    }

    #[Route("/{uuid}/recuperee", name: "marquer_recuperee_demande", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/demande/{uuid}/recuperee",
        summary: "Marquer une demande comme récupérée",
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
                description: "Demande marquée comme récupérée",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Demande marquée comme récupérée"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de l'opération")
        ]
    )]
    public function marquerRecuperee($uuid)
    {

        try {
            $demande = $this->demandeManager->marquerRecuperee($uuid);
            $response = (new JsonHelper($demande, 'Demande marquée comme récupérée', 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['demande:write', 'patient', 'medicament', 'entity', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['demande:write', 'patient', 'medicament', 'entity', 'folder']]);
        }
    }

    #[Route("/{uuid}/annuler", name: "annuler_demande", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/demande/{uuid}/annuler",
        summary: "Annuler une demande",
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
                    new OA\Property(property: "motif", type: "string", example: "Stock indisponible")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Demande annulée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Demande annulée avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de l'annulation")
        ]
    )]
    public function annuler(Request $request, $uuid)
    {
        try {
            $data = json_decode($request->getContent());
            $motif = isset($data->motif) ? $data->motif : null;
            $demande = $this->demandeManager->annuler($uuid, $motif);
            $response = (new JsonHelper($demande, 'Demande annulée avec succès', 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['demande', 'patient', 'medicament', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['demande', 'patient', 'medicament', 'file', 'folder']]);
        }
    }

    #[Route("/{uuid}/delete", name: "delete_demande", methods: ["DELETE"])]
    #[OA\Delete(
        path: "/api/v1/secure/pharmacie/demande/{uuid}/delete",
        summary: "Supprimer une demande",
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
                description: "Demande supprimée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "null"),
                        new OA\Property(property: "message", type: "string", example: "La demande a été supprimée avec succès"),
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
            $this->demandeManager->delete($uuid);
            $response = (new JsonHelper(null, 'La demande a été supprimée avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['demande', 'patient', 'medicament', 'file', 'folder']]);
        }
        return $this->json($response, 200, [], ['groups' => ['demande', 'patient', 'medicament', 'file', 'folder']]);
    }

    #[Route("/retard", name: "list_demandes_retard", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/demande/retard",
        summary: "Liste des demandes en retard",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des demandes en retard récupérée avec succès",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(type: "object")
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération des demandes en retard")
        ]
    )]
    public function retard(Request $request)
    {
        try {
            $demandes = $this->demandeManager->getDemandesEnRetard();
            return $this->json($demandes, 200, [], ['groups' => ['demande', 'patient', 'medicament', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }


    #[Route("/statistics", name: "statistics_demande_pharmacie", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/demande/statistics",
        summary: "Statistiques globales des pharmacies",
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistiques globales demandes de la pharmacies",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "total", type: "integer", example: 50),
                        new OA\Property(property: "actives", type: "integer", example: 45),
                        new OA\Property(property: "inactives", type: "integer", example: 5)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération des statistiques")
        ]
    )]
    public function statistics(Request $request)
    {
            $datas = (object) [
                'period' => $request->query->get('period'),
                'dateD' => $request->query->get('start_date'),
                'dateF' => $request->query->get('end_date'),
            ];
       
        try {
            $pharmacy = PharmacyHelper::pharmacy($this->getUser());
            
            $statistics = $this->demandeManager->statistics($datas, $pharmacy);
            $response = (new JsonHelper($statistics , null,'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['demande:read']]);
        }
        return $this->json($response, 200, [], ['groups' => ['demande:read', 'entity']]);
    }
}