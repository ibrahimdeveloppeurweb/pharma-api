<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Proxy vers Google Places (autocomplete + détails) pour la recherche de lieux.
 * La requête passe par le serveur : l'API Places REST n'autorise pas les appels
 * directs depuis le navigateur (CORS).
 */
#[Route(path: '/api/v1/public/places')]
class PublicPlacesController extends AbstractController
{
    private const BASE = 'https://maps.googleapis.com/maps/api/place';

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(GOOGLE_MAPS_API_KEY)%')] private string $apiKey,
    ) {
    }

    #[Route('/autocomplete', name: 'public_places_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return $this->json([]);
        }

        try {
            $data = $this->http->request('GET', self::BASE . '/autocomplete/json', [
                'query' => [
                    'input' => $q,
                    'components' => 'country:ci',
                    'language' => 'fr',
                    'key' => $this->apiKey,
                ],
                'timeout' => 8,
            ])->toArray(false);
        } catch (\Throwable) {
            return $this->json([]);
        }

        $out = [];
        foreach ($data['predictions'] ?? [] as $p) {
            $out[] = ['label' => $p['description'], 'placeId' => $p['place_id']];
        }

        return $this->json($out);
    }

    #[Route('/details', name: 'public_places_details', methods: ['GET'])]
    public function details(Request $request): JsonResponse
    {
        $placeId = (string) $request->query->get('place_id', '');
        if ($placeId === '') {
            return $this->json(['error' => 'place_id manquant'], 400);
        }

        try {
            $data = $this->http->request('GET', self::BASE . '/details/json', [
                'query' => [
                    'place_id' => $placeId,
                    'fields' => 'geometry,formatted_address',
                    'language' => 'fr',
                    'key' => $this->apiKey,
                ],
                'timeout' => 8,
            ])->toArray(false);
        } catch (\Throwable) {
            return $this->json(['error' => 'Service indisponible'], 502);
        }

        $loc = $data['result']['geometry']['location'] ?? null;
        if (!$loc) {
            return $this->json(['error' => 'Lieu introuvable'], 404);
        }

        return $this->json(['lat' => $loc['lat'], 'lon' => $loc['lng']]);
    }
}
