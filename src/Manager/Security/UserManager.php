<?php

namespace App\Manager\Security;

use App\Utils\TypeUtil;
use App\Entity\Gestion\Agent;
use App\Entity\Security\User;
use App\Entity\Security\Role;
use App\Entity\Security\Admin;
use App\Exception\ExceptionApi;
use App\Entity\Gestion\Contribuable;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\Security\RoleRepository;
use App\Repository\Security\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    private $em;
    private $roleRepository;
    private $userRepository;
    private $passwordEncoder;
    public function __construct(
        EntityManagerInterface $em,
        RoleRepository $roleRepository,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordEncoder
    ) {
        $this->em = $em;
        $this->roleRepository = $roleRepository; 
        $this->userRepository = $userRepository;
        $this->passwordEncoder = $passwordEncoder;        
    }

    /**
     * Ajouter un élément
     * @param object $data
     * @return User
     */
    public function create(object $data): User
    {
        $user = new User();
        //Ajout de l*utilisateur
        $this->add($data, $user, $data->type); 
       
        $this->em->flush();

        return $user;
    }

    /**
     * Mettre à jour un élément
     * @param object $data
     * @param string $id
     * @return User
     */
    public function update(object $data, string $id): User
    {
       // $this->requirements($data);
        $user = $this->userRepository->findOneByUuid($id);
        if (!isset($data->email) || empty($data->email)) {
            $data->email = $user->getEmail();
        }
        if (!isset($data->telephone) || empty($data->telephone)) {
            $data->telephone = $user->getTelephone(); // ✅ Corrige ici aussi
        }
        if (!isset($data->nom_pharmacien) || empty($data->nom_pharmacien)) {
            $data->nom_pharmacien = $user->getNom();
        }

         if (isset($data->telephone) && !empty($data->telephone)) {
            $pharmacie = $user->getPharmacie();
            $pharmacie->setTelephone($data->telephone);
            $this->em->persist($pharmacie);
        }
        $user
            ->setNom(isset($data->nom_pharmacien) && TypeUtil::is_not_null($data->nom_pharmacien) ? $data->nom_pharmacien : null)
            ->setEmail(isset($data->email) && TypeUtil::is_not_null($data->email) ?  $data->email: null)
            ->setTelephone(isset($data->telephone) && TypeUtil::is_not_null($data->telephone) ? $data->telephone : null);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }
    
    /**
     * Supprimer un élément
     * @param string $id
     * @return void
     */
    public function delete(string $id)
    {
        $user = $this->userRepository->findOneByUuid($id);
        if (!$user) {
            throw new ExceptionApi("Utilisateur introuvable.", 
            ["msg" => "Utilisateur introuvable."], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->em->remove($user);
        $this->em->flush();
        return;
    }

    /**
     * @param object $data
     * @param User $user
     * @param Admin|null $admin
     * @param Contribuable|null $contribuable
     * @param Agent|null $agent
     * @return User
     */
    public function add(object $data, User $user, String $type='ADMIN'): User
    {
        $admin = null;
        $agent = null;
        $contribuable = null;
        $email = null;
        $nom = null;
        $contact = null;
        $password = null;

        $data->uuid = null;
        if ($type == 'ADMIN') {
            //Initialisation de l'utilisateur de type admin
            $admin = $user->getAdmin() ? $user->getAdmin() :  new Admin();
            
            $admin->setRelatedUser($user);
            $this->em->persist($admin);

            $email = $data->email;
            $nom = $data->nom_pharmacien;
            $prenom = $data->nom_pharmacien;
            $contact = $data->telephone;
            if(isset($data->password)) {
                $password = $data->password;
            }

        } else if($type == 'AGENT'){
            $email = $data->email;
            $nom = $data->nom.' '.$data->prenom;
            $contact = $data->contact;
            $password = $data->contact;
        } 
      
        $user
            ->setType(isset($type) && $type ? $type : null)
            ->setAdmin(isset($admin) && $admin ? $admin : null)
            ->setNom(isset($nom) && TypeUtil::is_not_null($nom) ? $nom : null)
            ->setPrenom(isset($prenom) && TypeUtil::is_not_null($prenom) ? $prenom : null)
            ->setEmail(isset($email) && TypeUtil::is_not_null($email) ? $email: null)
            ->setTelephone(isset($contact) && TypeUtil::is_not_null($contact) ? $contact : null)
            ->setCivilite(isset($data->civilite) && TypeUtil::is_not_null($data->civilite) ? $data->civilite : null)
            ->setIsActive(true)
        ;
        if (!isset($data->uuid) && isset($password) && !empty($password)) {
        $user->setPassword($this->passwordEncoder->hashPassword($user, $password));
        }
        
        if ($type === 'ADMIN') {
            foreach ($user->getDroits() as $key => $value) {
                $uuidR[] = !empty($data->permissions[$key]->uuid) ? $data->permissions[$key]->uuid : null;
                if ($uuidR !== null) {
                    if (!in_array($value->getUuid(), $uuidR)) {
                        $user->removeDroit($value); 
                    }
                } else { $user->removeDroit($value); }
            }
            if(isset($data->permissions)) {
                foreach ($data->permissions as $item) {
                    /** @var Role $role */
                    $role = $this->roleRepository->findOneByUuid($item->uuid);
                    $user->addDroit($role);
                    $role->addUser($user);
                }
            }
        }
        $this->em->persist($user);
        return $user;
    }

  

    /**
     * Element requis
     * @param object $data
     * @param User|null $user
     * @throws ExceptionApi
     * @return void
     */
    public function requirements(object $data, ?User $user = null) {
        $checkUsername = $this->userRepository->findOneByUsername(isset($data->username) ? $data->username : null);
        if (!($user && isset($data->username) && $user->getEmail() === $data->username)){
            if($checkUsername && $user && $checkUsername->getId() !== $user->getId()){
                throw new ExceptionApi("Il existe déjà un utilisateur avec ce login ". $data->username, 
                ["msg" => "Il existe déjà un utilisateur avec ce ce login  ". $data->username], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        $checkEmail = $this->userRepository->findOneByEmail(isset($data->email) ? $data->email : null);
        if (!($user && isset($data->email) && $user->getEmail() === $data->email)){
            if($checkEmail && $user && $checkEmail->getId() !== $user->getId()){
                throw new ExceptionApi("Il existe déjà un compte utilisateur lié à ce mail ". $data->email, 
                ["msg" => "Il existe déjà un compte utilisateur lié à ce mail ". $data->email], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
    }
}