<?php

namespace App\Controller\Security;

use App\Entity\Security\User;
use App\Service\RechercheNotifier;
use App\Utils\JsonHelper;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: "/api/v1/secure/mercure")]
#[OA\Tag(name: "Mercure (temps réel)")]
class MercureTokenController extends AbstractController
{
    /** Durée de validité du jeton d'abonnement (le mobile en redemande un à chaque reconnexion). */
    private const TOKEN_LIFETIME = '+6 hours';

    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    #[Route("/token", name: "secure_mercure_token", methods: ["GET"])]
    #[OA\Get(
        path: "/api/v1/secure/mercure/token",
        summary: "Jeton d'abonnement Mercure pour la pharmacie du pharmacien connecté"
    )]
    public function token(): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \Exception('Utilisateur non connecté');
            }

            $pharmacie = $user->getPharmacie();
            if (!$pharmacie) {
                throw new \Exception('Cet utilisateur n\'est rattaché à aucune pharmacie');
            }

            // Le jeton n'autorise QUE le topic de SA pharmacie : impossible d'écouter les
            // alertes des autres pharmacies. Aucun droit de publication.
            $topic = RechercheNotifier::pharmacieTopic((int) $pharmacie->getId());
            $factory = $this->hub->getFactory();
            if ($factory === null) {
                throw new \Exception('Mercure n\'est pas configuré');
            }

            $jwt = $factory->create([$topic], [], ['exp' => new \DateTimeImmutable(self::TOKEN_LIFETIME)]);

            return $this->json(
                (new JsonHelper([
                    'url' => $this->hub->getPublicUrl(),
                    'topic' => $topic,
                    'token' => $jwt,
                    'expires_in' => 6 * 3600,
                ], 'Jeton Mercure généré', 'success', 200, []))->serialize(),
                200
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Jeton Mercure refusé : {error}', ['error' => $e->getMessage()]);

            return $this->json(
                (new JsonHelper(null, $e->getMessage(), 'error', 400, []))->serialize(),
                400
            );
        }
    }
}
