<?php

namespace App\Controller\Gestion;

use App\Utils\JsonHelper;
use App\Utils\TypeVariable;
use App\Exception\ExceptionApi;
use App\Manager\Gestion\PatientManager;
use App\Repository\Security\UserRepository;
use App\Repository\Gestion\PatientRepository;
use App\Repository\Gestion\PharmacieRepository;
use App\Utils\PharmacyHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Attributes as OA;

#[Route(path: "/api/v1/secure/pharmacie/patient")]
#[OA\Tag(name: "Patients")]
class PatientController extends AbstractController
{
    private $patientManager;
    private $patientRepository;
    private $pharmacieRepository;

    public function __construct(
        PatientManager $patientManager,
        PatientRepository $patientRepository,
        PharmacieRepository $pharmacieRepository
    )
    {
        $this->patientManager = $patientManager;
        $this->patientRepository = $patientRepository;
        $this->pharmacieRepository = $pharmacieRepository;
    }
    
    #[Route("/", name: "index_patient", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/patient",
        summary: "Liste des patients",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des patients récupérée avec succès",
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
            new OA\Response(response: 400, description: "Erreur lors de la récupération des patients")
        ]
    )]
    public function index(Request $request): Response
    {
        $data = \json_decode(json_encode($request->query->all()));
        $patients = [];
        
        try {
            $pharmacie = PharmacyHelper::pharmacy($this->getUser());
            $patients = $this->patientRepository->findIndex($data, $this->getUser(),$pharmacie);
            $response = (new JsonHelper($patients, null, 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['patient:read']]);
        }
        return $this->json($response, 200, [], ['groups' => ['patient:read', 'entity']]);
    }

    #[Route("/new", name: "new_patient", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/patient/new",
        summary: "Créer un nouveau patient",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "nom", type: "string", example: "Dupont"),
                    new OA\Property(property: "prenom", type: "string", example: "Jean"),
                    new OA\Property(property: "telephone", type: "string", example: "+33612345678"),
                    new OA\Property(property: "email", type: "string", example: "jean.dupont@example.com"),
                    new OA\Property(property: "adresse", type: "string", example: "123 Rue de la Paix")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Patient créé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Patient créé avec succès"),
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur lors de la création du patient")
        ]
    )]
    public function new(Request $request)
    {
        try {
            $pharmacie = PharmacyHelper::pharmacy($this->getUser());
            $content = json_decode($request->getContent());
            $patient = $this->patientManager->create($content, $pharmacie);
            $response = (new JsonHelper($patient, 'Patient créé avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['patient', 'entity', 'file', 'folder']]);
        }
        return $this->json($response, 200, [], ['groups' => ['patient', 'entity', 'file', 'folder']]);
    }

    #[Route("/{uuid}/edit", name: "edit_patient", methods: ["POST"])]
    #[OA\Post(
        path: "/api/v1/secure/pharmacie/patient/{uuid}/edit",
        summary: "Modifier un patient",
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
                    new OA\Property(property: "nom", type: "string", example: "Dupont"),
                    new OA\Property(property: "prenom", type: "string", example: "Jean"),
                    new OA\Property(property: "telephone", type: "string", example: "+33612345678"),
                    new OA\Property(property: "email", type: "string", example: "jean.dupont@example.com"),
                    new OA\Property(property: "adresse", type: "string", example: "123 Rue de la Paix")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Patient modifié avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object"),
                        new OA\Property(property: "message", type: "string", example: "Patient modifié avec succès"),
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
            $patient = $this->patientManager->update($content, $uuid, $pharmacie);
            $response = (new JsonHelper($patient, 'Patient modifié avec succès', 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['patient', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['patient', 'entity', 'file', 'folder']]);
        }
    }

    #[Route("/{uuid}/delete", name: "delete_patient", methods: ["DELETE"])]
    #[OA\Delete(
        path: "/api/v1/secure/pharmacie/patient/{uuid}/delete",
        summary: "Supprimer un patient",
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
                description: "Patient supprimé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "null"),
                        new OA\Property(property: "message", type: "string", example: "Le patient a été supprimé avec succès"),
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
            $this->patientManager->delete($uuid);
            $response = (new JsonHelper(null, 'Le patient a été supprimé avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['patient', 'entity', 'file', 'folder']]);
        }
        return $this->json($response, 200, [], ['groups' => ['patient', 'entity', 'file', 'folder']]);
    }

    #[Route("/plus-actifs", name: "list_patients_plus_actifs", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/patient/plus-actifs",
        summary: "Liste des patients les plus actifs",
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
                description: "Nombre de patients à retourner"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des patients les plus actifs",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function plusActifs(Request $request)
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

            $patients = $this->patientRepository->findPlusActifs($pharmacie, $limit);
            return $this->json($patients, 200, [], ['groups' => ['patient', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/avec-demandes-en-attente", name: "list_patients_demandes_en_attente", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/patient/avec-demandes-en-attente",
        summary: "Liste des patients avec demandes en attente",
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
                description: "Liste des patients avec demandes en attente",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function avecDemandesEnAttente(Request $request)
    {
        try {
            $data = \json_decode(json_encode($request->query->all()));
            $pharmacie = null;
            
            if (isset($data->pharmacie) && TypeVariable::is_not_null($data->pharmacie)) {
                $pharmacie = $this->pharmacieRepository->findOneByUuid($data->pharmacie);
            }

            $patients = $this->patientRepository->findAvecDemandesEnAttente($pharmacie);
            return $this->json($patients, 200, [], ['groups' => ['patient', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/statistics", name: "statistics_patients", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/patient/statistics",
        summary: "Statistiques des patients",
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
                description: "Statistiques des patients",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "total", type: "integer", example: 250),
                        new OA\Property(property: "nouveaux", type: "integer", example: 15),
                        new OA\Property(property: "actifs", type: "integer", example: 180)
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

            $statistics = $this->patientRepository->getStatistics($pharmacie);
            return $this->json($statistics, 200, []);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/sans-demandes", name: "list_patients_sans_demandes", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/patient/sans-demandes",
        summary: "Liste des patients sans demandes",
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
                description: "Liste des patients sans demandes",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function sansDemandes(Request $request)
    {
        try {
            $data = \json_decode(json_encode($request->query->all()));
            $pharmacie = null;
            
            if (isset($data->pharmacie) && TypeVariable::is_not_null($data->pharmacie)) {
                $pharmacie = $this->pharmacieRepository->findOneByUuid($data->pharmacie);
            }

            $patients = $this->patientRepository->findSansDemandes($pharmacie);
            return $this->json($patients, 200, [], ['groups' => ['patient', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/nouveaux", name: "list_patients_nouveaux", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/patient/nouveaux",
        summary: "Liste des patients récents",
        parameters: [
            new OA\Parameter(
                name: "pharmacie",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string"),
                description: "UUID de la pharmacie"
            ),
            new OA\Parameter(
                name: "jours",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 30),
                description: "Nombre de jours pour considérer un patient comme nouveau"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des patients récents",
                content: new OA\JsonContent(type: "array", items: new OA\Items(type: "object"))
            ),
            new OA\Response(response: 400, description: "Erreur lors de la récupération")
        ]
    )]
    public function nouveaux(Request $request)
    {
        try {
            $data = \json_decode(json_encode($request->query->all()));
            $pharmacie = null;
            $jours = 30;
            
            if (isset($data->pharmacie) && TypeVariable::is_not_null($data->pharmacie)) {
                $pharmacie = $this->pharmacieRepository->findOneByUuid($data->pharmacie);
            }

            if (isset($data->jours) && TypeVariable::is_not_null($data->jours)) {
                $jours = (int)$data->jours;
            }

            $patients = $this->patientRepository->findNouveaux($jours, $pharmacie);
            return $this->json($patients, 200, [], ['groups' => ['patient', 'entity', 'file', 'folder']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }

    #[Route("/search", name: "search_patient", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/patient/search",
        summary: "Rechercher un patient",
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "string"),
                description: "Terme de recherche (nom, prénom, téléphone, email)"
            ),
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
            $pharmacie = null;
            
            if (!isset($data->search) || !TypeVariable::is_not_null($data->search)) {
                throw new ExceptionApi(
                    "Le terme de recherche est requis.",
                    ["msg" => "Recherche requise."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            if (isset($data->pharmacie) && TypeVariable::is_not_null($data->pharmacie)) {
                $pharmacie = $this->pharmacieRepository->findOneByUuid($data->pharmacie);
            }

            $limit = 20;
            if (isset($data->limit) && TypeVariable::is_not_null($data->limit)) {
                $limit = (int)$data->limit;
            }

            $patients = $this->patientRepository->search($data->search, $pharmacie, $limit);
            return $this->json($patients, 200, [], ['groups' => ['patient', 'entity']]);
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode());
        }
    }
}