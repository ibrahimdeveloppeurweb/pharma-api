<?php

namespace App\Controller\Security;

use App\Utils\JsonHelper;
use App\Entity\Security\User;
use App\Exception\ExceptionApi;
use App\Entity\Security\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use App\Manager\Security\SecurityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\Security\RefreshTokenRepository;
use App\Repository\Security\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use OpenApi\Attributes as OA;

#[Route("/api/v1/auth")]
class SecurityController extends AbstractController
{
    private $em;
    private $user;
    private $securityManager;
    private $tokenStorage;
    private $refreshTokenRepository;
    private $userRepository;
    
    public function __construct(
        EntityManagerInterface $em,
        SecurityManager $securityManager,
        TokenStorageInterface $tokenStorage,
        RefreshTokenRepository $refreshTokenRepository,
        UserRepository $userRepository
    ) {
        $this->em = $em;
        $this->securityManager = $securityManager;
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->userRepository = $userRepository;
        if ($tokenStorage->getToken()) {
            $this->user = $tokenStorage->getToken()->getUser();
        }
    }

    #[Route("/login", name: "login", methods: ["POST"])]
    public function login(Request $request)
    {
        try {
      
            $token = $this->securityManager->checkCredential($request)->getAccessToken();
            $response = (new JsonHelper($token, 'Connexion reussie', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['user']]);
        }
        return $this->json($response);
    }

    #[Route("/logout", name: "logout", methods: ["POST"])]
    public function logout(Request $request)
    {
        $content = json_decode($request->getContent());
        try {
            $token = $this->securityManager->logout($content, $request);
            $response = (new JsonHelper($token, 'Déconnexion réussie', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ["file", "photo"]]);
        }
        return $this->json($response);
    }

    #[Route("/forgot", name: "forgot-password", methods: ["POST"])]
    public function forgot(Request $request)
    {
        try {
            $data = \json_decode($request->getContent());
            $user = $this->securityManager->forgot($data);
            $response = (new JsonHelper($user, 'E-mail envoyer avec success', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['user']]);
        }
        return $this->json($response, 200, [], ['groups' => ['user']]);
    }

    #[Route("/token/refresh", name: "api_refresh", methods: ["POST"])]
    public function refreshTokenAction(Request $request, JWTEncoderInterface $encoder)
    {
        $content = json_decode($request->getContent(), true);
        if (!$request->headers->has('Authorization')) {
            return $this->json(['status' => 'error', 'message' => 'No Token Found']);
        }
        if (!array_key_exists('refreshToken', $content)) {
            return $this->json(['status' => 'error', 'message' => 'request is empty']);
        }
        
        $refreshToken = $this->refreshTokenRepository->find($content['refreshToken']);
        if (!$refreshToken) {
            return $this->json(['status' => 'error', 'message' => 'unknow refresh token']);
        }

        $now = new \DateTime();
        if ($refreshToken->getExpireAt() < $now) {
            $when = clone $refreshToken->getExpireAt();
            $this->em->remove($refreshToken);
            $this->em->flush();
            return $this->json(['status' => 'error', 'message' => 'Refresh token expired at ' . $when->format('d-m-Y h:i:s')]);
        }
        
        $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
        $token = $extractor->extract($request);
      
        try {
            $data = $encoder->decode($token);
        } catch (JWTDecodeFailureException $exception) {
            if ($exception->getReason() === 'expired_token') {
                $data = $exception->getPayload();
            }
        }

        $user = $refreshToken->getCreateBy();
        $this->em->remove($refreshToken);
        $newRefreshToken = $user->generateRefreshToken();
        $token = $encoder->encode(['email' => $user->getEmail()]);
        $this->em->persist($newRefreshToken);
        $this->em->flush();
        
        $data = [
            'token' => $token,
            'role' => $user->getRoles()[0],
            'refreshToken' => (string)$newRefreshToken->getId(),
        ];
        $response = (new JsonHelper($data, 'Connexion reussie', 'success', 200, []))->serialize();
        return $this->json($response);
    }

    #[Route("/edit/password", name: "edit_passord", methods: ["POST"])]
    public function editPassword(Request $request)
    {
       
        try {
         
            
            $data = json_decode($request->getContent());
            if ($this->getUser() instanceof User) {
                $user = $this->getUser();
            } else {
               $user =   $this->userRepository->findOneByEmail($data->email);
               if (!$user) {
                   throw new ExceptionApi("Utilisateur non trouvé", 422, []);
               }
            }
            $user = $this->securityManager->editPassword($data, $user);
            $response = (new JsonHelper($user, 'Votre mot de passe a été modifié avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['user']]);
        }
        return $this->json($response, 200, [], ['groups' => ['user']]);
    }

    #[Route("/edit/username", name: "edit_username", methods: ["POST"])]
    public function editUsername(Request $request)
    {
        try {
            $user = $this->getUser();
            $data = json_decode($request->getContent());
            $user = $this->securityManager->editUsername($data, $user);
            $response = (new JsonHelper($user, "Votre nom d'utilisateur a été modifié avec succès", 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['user']]);
        }
        return $this->json($response, 200, [], ['groups' => ['user']]);
    }

    #[Route("/rest/password", name: "reset_passord", methods: ["POST"])]
    public function resetPassword(Request $request)
    {
        try {
            $data = json_decode($request->getContent());
            $user = $this->securityManager->resetPassword($data);
            $response = (new JsonHelper($user, 'Votre mot de passe a été réinitialisé avec succès', 'success', 200, []))->serialize();
        } catch (ExceptionApi $e) {
            $response = (new JsonHelper(null, $e->getMessage(), 'bad_request', $e->getCode(), $e->getErrors()))->serialize();
            return $this->json($response, $e->getCode(), [], ['groups' => ['user']]);
        }
        return $this->json($response, 200, [], ['groups' => ['user']]);
    }
}