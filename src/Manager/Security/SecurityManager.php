<?php

namespace App\Manager\Security;

use App\Models\UserModel;
use App\Utils\FonctionUtil;
use App\Entity\Security\User;
use App\Exception\ExceptionApi;
use App\Entity\Extra\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use App\Repository\Security\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\Security\RefreshTokenRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;


class SecurityManager
{
    private EntityManagerInterface $em;
    private ?User $user = null;
    private ?string $roles = null;
    private JWTTokenManagerInterface $jwtManager;
    private ?string $username = null;
    private ?string $refreshToken = null;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private RefreshTokenRepository $refreshTokenRepository;
    private $jwtEncoder;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        TokenStorageInterface $tokenStorage,
        UserPasswordHasherInterface $passwordHasher,
        RefreshTokenRepository $refreshTokenRepository,
        JWTEncoderInterface $jwtEncoder,
    ) {
        $this->em = $em;
        $this->jwtManager = $jwtManager;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->jwtEncoder = $jwtEncoder;

        if ($tokenStorage->getToken() && is_object($tokenStorage->getToken()->getUser())) {
            $this->user = $tokenStorage->getToken()->getUser();
        }
    }

    /**
     * Check credentials and create refresh token
     *
     * @param Request $request
     * @return $this
     * @throws ExceptionApi
     */
    public function checkCredential(Request $request)
    {
        $body = json_decode($request->getContent(), true);
       
        if (!isset($body['email']) || !isset($body['password'])) {
            throw new ExceptionApi(
                "L'email et le mot de passe sont obligatoire.",
                ['msg' => "L'email et le mot de passe sont obligatoire."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $username = $body['email'];
        $password = $body['password'];
        
        try {
          
            $user = $this->userRepository->findOneByEmail($username);
        } catch (NonUniqueResultException $e) {
            $user = null;
        }

        if (!$user) {
            throw new ExceptionApi(
                "Cet utilisateur est introuvable",
                ['msg' => "Cet utilisateur est introuvable"],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $isValid = $this->passwordHasher->isPasswordValid($user, $password);
        if (!$isValid) {
            throw new ExceptionApi(
                'Le mot de passe est incorrect.',
                ['msg' => ['Le mot de passe est incorrect.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
        
        // if ($user->emailVerified()) {
        //     throw new ExceptionApi(
        //         "Le compte de cet utilisateur n'est pas active",
        //         ['msg' => ["Le compte de cet utilisateur n'est pas active."]],
        //         Response::HTTP_UNPROCESSABLE_ENTITY
        //     );
        // }

        if (!$user->isActive()) {
            throw new ExceptionApi(
                "Le compte de cet utilisateur n'est pas validé.",
                ['msg' => ["Le compte de cet utilisateur n'est pas validé."]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Mise à jour automatique du FCM token à chaque connexion (s'il est fourni)
        if (isset($body['fcmToken']) && !empty($body['fcmToken'])) {
            $user->setFcmToken($body['fcmToken']);
            $this->em->persist($user);
            $this->em->flush();
        }
      
        $this->username = $user->getEmail();
        $roles = $user->getRoles();
        $this->roles = $roles[0] ?? null;
       
        // Génération d'un refresh token
        $refreshToken = $user->generateRefreshToken();
        $this->em->persist($refreshToken);
        $this->em->flush();

        $this->refreshToken = (string)$refreshToken->getId();

        return $this;
    }

    /**
     * Mot de passe oublié
     */
    public function forgot(object $data): ?User
    {
        $user = $this->userRepository->findOneByEmail($data->email);
        if (!$user) {
            throw new ExceptionApi(
                'Il n\'existe aucun utilisateur avec cet adresse email.',
                ['msg' => 'Il n\'existe aucun utilisateur avec cet adresse email.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $password = FonctionUtil::password(8);
        $user
            ->setPassword($this->passwordHasher->hashPassword($user, $password))
            ->setLastLogin(null);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Logout : supprime refresh token, met à jour lastLogin et decode JWT de la requête
     *
     * @param object $data
     * @param Request $request
     * @return array
     */
    public function logout(object $data, Request $request): array
    {
        $refreshToken = $this->refreshTokenRepository->find($data->refreshToken);
        $user = $this->userRepository->findOneByUuid($data->user);

        if ($refreshToken) {
            $this->em->remove($refreshToken);
        }

        if ($user) {
            $user->setLastLogin(new \DateTime());
            $this->em->persist($user);
        }

        $this->em->flush();

        $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
        $token = $extractor->extract($request); // string|false

        $decoded = null;
        if ($token && is_string($token)) {
            try {
                $decoded = $this->jwtEncoder->decode($token);
            } catch (\Exception $e) {
                $decoded = null;
            }
        }

        return [
            'token' => $token,
            'decoded' => $decoded,
        ];
    }

    /**
     * Retourne un access token et info utilisateur
     * NOTE: ici on utilise jwtManager->create($user) (génère un token à partir de l'entité User)
     * Si tu veux insérer des claims personnalisés (refreshToken...) dans le token lui-même,
     * il faudra utiliser un encodeur bas-niveau ou créer un handler custom.
     *
     * @return array
     */
    public function getAccessToken(): array
    {
        $data = [];
        /** @var User $user */
        $user = $this->userRepository->findOneByEmail($this->username);

        // Crée le token via jwtManager (qui utilise l'utilisateur)
        try {
            $token = $this->jwtManager->create($user);
        } catch (\Exception $e) {
            throw new ExceptionApi("Erreur lors de la création du token JWT : " . $e->getMessage(), 500);
        }

        $userModel = new UserModel();
        $userModel
            ->setNom($user->getLibelle())
            ->setContact($user->getTelephone())
            ->setCivilite($user->getCivilite())
            ->setRole($this->roles)
            ->setToken($token)
            ->setRefreshToken($this->refreshToken)
            ->setEmail($user->getEmail())
            ->setUuid($user->getUuid())
            ->setLastLogin($user->getLastLogin())
            ->setPermissions($user->getPermissions());

        $user->setIsOnline(true);
        $this->em->persist($user);
        $this->em->flush();

        $data = [
            'token' => $token,
            'refreshToken' => $this->refreshToken,
            'user' => [
                'id' => $user->getId(),
                'uuid' => $user->getUuid(),
                'email' => $user->getEmail(),
                'nom' => $user->getNom(),
                'type' => $user->getType(),
                'role' => $user->getType(),
                'contact' => $user->getTelephone(),
                'adresse' => $user->getPharmacie() ? $user->getPharmacie()->getAdresse() : null,
                'pharmacie' =>  $user->getPharmacie() ? [
                    'uuid' => $user->getPharmacie()->getUuid(),
                    'nom' => $user->getPharmacie()->getNom(),
                    'latitude' => $user->getPharmacie()->getLatitude(),
                    'longitude' => $user->getPharmacie()->getLongitude(),
                    'lieuExact' => $user->getPharmacie()->getLieuExact(),
                    'isDeGarde' => $user->getPharmacie()->getIsDeGarde(),
                    'isDisponibleRecherche' => $user->getPharmacie()->getIsDisponibleRecherche(),
                ] : null,
                'refreshToken' => $this->refreshToken,
            ]
        ];

        return $data;
    }

    /**
     * Modifier le mot de passe
     */
    public function editPassword(object $data, User $user): User
    {
        //$this->checkRequirements($data, $user, 'edit');
        // Utiliser hashPassword avec UserPasswordHasherInterface
    
        $user->setPassword($this->passwordHasher->hashPassword($user, $data->new));
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    /**
     * Modifier le nom d'utilisateur
     */
    public function editUsername(object $data, User $user): User
    {
        $this->checkRequirements($data, $user, 'username');
        $user->setEmail($data->new);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    /**
     * Reinitialiser un mot de passe
     */
    public function resetPassword(object $data): User
    {
        $user = $this->userRepository->findOneByUuid($data->user);
        $this->checkRequirements($data, $user, 'rest');
        $user->setPassword($this->passwordHasher->hashPassword($user, $data->new));
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    /**
     * Vérifications communes
     */
    public function checkRequirements(object $data, ?User $user, string $verif)
    {
        if ($verif === 'edit') {
            $isValid = $this->passwordHasher->isPasswordValid($user, $data->current);
            if (!$isValid) {
                throw new ExceptionApi(
                    "L'ancien mot de passe est incorrect.",
                    ['password' => "L'ancien mot de passe est incorrect."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            if ($data->new !== $data->confirm) {
                throw new ExceptionApi(
                    'Le nouveau mot de passe et celui de confirmation ne correspondent pas.',
                    ['password' => 'Le nouveau mot de passe et celui de confirmation ne correspondent pas.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        if ($verif === 'username') {
            // ici on suppose $user contient l'entité ou l'id selon ton usage
            $userEntity = is_int($user) ? $this->userRepository->findOneById($user) : $user;
            if ($data->current !== $userEntity->getUsername()) {
                throw new ExceptionApi(
                    "L'ancien email est incorrect.",
                    ['msg' => "L'ancien email est incorrect."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            if ($data->new !== $data->confirm) {
                throw new ExceptionApi(
                    'Les adresses email ne correspondent pas.',
                    ['msg' => 'Les adresses email ne correspondent pas.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        if (!$user) {
            throw new ExceptionApi(
                "Cet utilisateur est introuvable.",
                ['password' => "Cet utilisateur est introuvable"],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
