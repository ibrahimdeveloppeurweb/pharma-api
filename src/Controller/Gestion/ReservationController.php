<?php

namespace App\Controller\Gestion;

use App\Utils\JsonHelper;
use App\Utils\PharmacyHelper;
use App\Repository\ReservationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;

#[Route(path: "/api/v1/secure/pharmacie/reservation")]
#[OA\Tag(name: "Reservations")]
class ReservationController extends AbstractController
{
    private $reservationRepository;

    public function __construct(ReservationRepository $reservationRepository)
    {
        $this->reservationRepository = $reservationRepository;
    }

    #[Route("", name: "index_reservation", methods: ["GET"])]
    #[Route("/", name: "index_reservation_slash", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/pharmacie/reservation",
        summary: "Liste des reservations pour la pharmacie",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des reservations récupérée avec succès"
            )
        ]
    )]
    public function index(Request $request): Response
    {
        try {
            $pharmacy = PharmacyHelper::pharmacy($this->getUser());

            $page = max(1, $request->query->getInt('page', 1));
            $limit = max(1, $request->query->getInt('limit', 10));
            $offset = ($page - 1) * $limit;

            $reservations = $this->reservationRepository->findBy(
                ['pharmacie' => $pharmacy],
                ['createdAt' => 'DESC'],
                $limit,
                $offset
            );

            // Total pour information (optionnel pour l'instant)
            $total = $this->reservationRepository->count(['pharmacie' => $pharmacy]);
            $totalPages = ceil($total / $limit);

            $response = (new JsonHelper($reservations, null, 'success', 200, [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages
            ]))->serialize();
        } catch (\Exception $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', 400, []))->serialize();
            return $this->json($response, 400, [], ['groups' => ['recherche:read']]);
        }
        return $this->json($response, 200, [], ['groups' => ['recherche:read']]);
    }

    #[Route("/{uuid}/status", name: "update_reservation_status", methods: ["PUT"])]
    #[OA\Put(
        path: "/api/v1/secure/pharmacie/reservation/{uuid}/status",
        summary: "Mettre à jour le statut d'une réservation"
    )]
    public function updateStatus(string $uuid, Request $request, EntityManagerInterface $em): Response
    {
        try {
            $pharmacy = PharmacyHelper::pharmacy($this->getUser());
            $reservation = $this->reservationRepository->findOneBy(['uuid' => $uuid, 'pharmacie' => $pharmacy]);


            if (!$reservation) {
                $response = (new JsonHelper(null, "Réservation non trouvée", 'not_found', 404, []))->serialize();
                return $this->json($response, 404);
            }

            $data = json_decode($request->getContent(), true);
            if (isset($data['status'])) {
                $reservation->setStatus($data['status']);
                $em->flush();
                $response = (new JsonHelper($reservation, "Statut mis à jour avec succès", 'success', 200, []))->serialize();
                return $this->json($response, 200, [], ['groups' => ['recherche:read']]);
            }

            $response = (new JsonHelper(null, "Statut manquant", 'bad_request', 400, []))->serialize();
            return $this->json($response, 400);
        } catch (\Exception $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'error', 500, []))->serialize();
            return $this->json($response, 500);
        }
    }

    #[Route("/{uuid}/read", name: "update_reservation_read_status", methods: ["PUT"])]
    #[OA\Put(
        path: "/api/v1/secure/pharmacie/reservation/{uuid}/read",
        summary: "Marquer une réservation comme lue"
    )]
    public function updateReadStatus(string $uuid, EntityManagerInterface $em): Response
    {
        try {
            $pharmacy = PharmacyHelper::pharmacy($this->getUser());
            $reservation = $this->reservationRepository->findOneBy(['uuid' => $uuid, 'pharmacie' => $pharmacy]);

            if (!$reservation) {
                $response = (new JsonHelper(null, "Réservation non trouvée", 'not_found', 404, []))->serialize();
                return $this->json($response, 404);
            }

            $reservation->setIsRead(true);
            $em->flush();

            $response = (new JsonHelper($reservation, "Réservation marquée comme lue", 'success', 200, []))->serialize();
            return $this->json($response, 200, [], ['groups' => ['recherche:read']]);
        } catch (\Exception $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'error', 500, []))->serialize();
            return $this->json($response, 500);
        }
    }

    #[Route("/{uuid}", name: "delete_reservation", methods: ["DELETE"])]
    #[OA\Delete(
        path: "/api/v1/secure/pharmacie/reservation/{uuid}",
        summary: "Supprimer une réservation"
    )]
    public function delete(string $uuid, EntityManagerInterface $em): Response
    {
        try {
            $pharmacy = PharmacyHelper::pharmacy($this->getUser());
            $reservation = $this->reservationRepository->findOneBy(['uuid' => $uuid, 'pharmacie' => $pharmacy]);


            if (!$reservation) {
                $response = (new JsonHelper(null, "Réservation non trouvée", 'not_found', 404, []))->serialize();
                return $this->json($response, 404);
            }

            $em->remove($reservation);
            $em->flush();

            $response = (new JsonHelper(null, "Réservation supprimée avec succès", 'success', 200, []))->serialize();
            return $this->json($response, 200);
        } catch (\Exception $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'error', 500, []))->serialize();
            return $this->json($response, 500);
        }
    }
}
