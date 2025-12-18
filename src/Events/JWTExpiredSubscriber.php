<?php
// src/EventSubscriber/JWTExpiredSubscriber.php

namespace App\Events;

use App\Repository\Security\RefreshTokenRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class JWTExpiredSubscriber implements EventSubscriberInterface
{
    private JWTEncoderInterface $encoder;
    private RefreshTokenRepository $refreshTokenRepository;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(
        JWTEncoderInterface $encoder,
        RefreshTokenRepository $refreshTokenRepository,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->encoder = $encoder;
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->em = $em;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9], // Priorité élevée
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
       
        // Ignorer les routes de refresh et login
        $route = $request->attributes->get('_route');
          
        if (in_array($route, ['app_auth_refresh', 'api_login', 'api_register'])) {
            return;
        }
        
        // Vérifier si c'est une route API qui nécessite authentification
        if (!$request->headers->has('Authorization')) {
            return;
        }

        $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
        $token = $extractor->extract($request);

        if (!$token) {
            return;
        }

        try {
            // Essayer de décoder le token
            $this->encoder->decode($token);
            // Token valide, continuer normalement
            return;
        } catch (JWTDecodeFailureException $exception) {
            // Token expiré ?
            if ($exception->getReason() !== 'expired_token') {
                // Autre erreur (signature invalide, etc.), laisser le système gérer
                $this->logger->warning('JWT decode error: ' . $exception->getReason());
                return;
            }

            $this->logger->info('JWT token expired, attempting automatic refresh');

            // Token expiré, essayer de le rafraîchir automatiquement
            $refreshTokenId = $request->headers->get('X-Refresh-Token');
           
            
            if (!$refreshTokenId) {
                // Pas de refresh token fourni
                $this->logger->warning('No refresh token provided for expired JWT');
                $response = new JsonResponse([
                    'status' => 'error',
                    'message' => 'Token expired and no refresh token provided',
                    'code' => 'TOKEN_EXPIRED'
                ], 401);
                $event->setResponse($response);
                return;
            }
 
            // Tenter le refresh
            $newTokenData = $this->refreshToken($refreshTokenId, $exception->getPayload());
           
            if (!$newTokenData) {
                $this->logger->error('Failed to refresh token');
                $response = new JsonResponse([
                    'status' => 'error',
                    'message' => 'Unable to refresh token',
                    'code' => 'REFRESH_FAILED'
                ], 401);
                $event->setResponse($response);
                return;
            }
  
            // Mettre à jour le header Authorization avec le nouveau token
            $request->headers->set('Authorization', 'Bearer ' . $newTokenData['token']);
         
            // Ajouter les nouveaux tokens dans les attributs de la requête
            // pour les passer au ResponseSubscriber
            $request->attributes->set('_new_tokens', $newTokenData);
               
            $this->logger->info('Token successfully refreshed automatically');
        }
    }

    private function refreshToken(string $refreshTokenId, array $expiredPayload): ?array
    {
       
        try {

            
            $refreshToken = $this->refreshTokenRepository->find($refreshTokenId);
            
            if (!$refreshToken) {
                $this->logger->warning('Refresh token not found: ' . $refreshTokenId);
                return null;
            }
           
            $now = new \DateTime();
            // if ($refreshToken->getExpireAt() < $now) {
            //     $this->logger->warning('Refresh token expired: ' . $refreshTokenId);
            //     $this->em->remove($refreshToken);
            //     $this->em->flush();
            //     return null;
            // }

            $user = $refreshToken->getCreateBy();
           
            // Vérifier que l'email du token expiré correspond à l'utilisateur du refresh token
            if (isset($expiredPayload['email']) && $expiredPayload['email'] !== $user->getEmail()) {
                $this->logger->error('Token email mismatch during refresh');
                return null;
            }
                
            // Supprimer l'ancien refresh token
            $this->em->remove($refreshToken);
            
            // Créer un nouveau refresh token
            $newRefreshToken = $user->generateRefreshToken();
            $this->em->persist($newRefreshToken);
           
            // Générer un nouveau JWT avec les mêmes claims que l'ancien
            $newTokenPayload = [
                'username' => $user->getEmail(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'exp' => time() + 3600, // 1 heure
            ];
             
            $newToken = $this->encoder->encode($newTokenPayload);
           
            $this->em->flush();

            return [
                'token' => $newToken,
                'refreshToken' => (string)$newRefreshToken->getId(),
                'role' => $user->getRoles()[0] ?? 'ROLE_USER',
                'email' => $user->getEmail(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error during token refresh: ' . $e->getMessage());
            return null;
        }
    }
}