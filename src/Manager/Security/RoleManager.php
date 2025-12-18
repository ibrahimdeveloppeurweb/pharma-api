<?php

namespace App\Manager\Security;

use App\Entity\Security\Role;
use App\Entity\Security\Path;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\Security\PathRepository;
use App\Repository\Security\RoleRepository;

class RoleManager
{
    private $em;
    private $roleRepository;
    private $pathRepository;
    public function __construct(
        EntityManagerInterface $em,
        RoleRepository $roleRepository,
        PathRepository $pathRepository
    ) {
        $this->em = $em;
        $this->roleRepository = $roleRepository;
        $this->pathRepository = $pathRepository;
    }

    /**
     * Ajouter un élément
     * @param object $data
     * @return Role
     */
    public function create(object $data): Role
    {
        $role = new Role();
        $this->add($data, $role,);
        $this->em->flush();

        return $role;
    }

    /**
     * Mettre à jour un élément
     * @param object $data
     * @param string $id
     * @return Role
     */
    public function update(object $data, string $id): Role
    {
        $role = $this->roleRepository->findOneByUuid($id);
        $this->add($data, $role);
        return $role;
    }
    
    /**
     * Supprimer un élément
     * @param string $id
     * @return void
     */
    public function delete(string $id)
    {
        $role = $this->roleRepository->findOneByUuid($id);
        $this->em->remove($role);
        $this->em->flush();
        return;
    }

    /**
     * @param object $data
     * @param Role $role
     * @return Role
     */
    public function add(object $data, Role $role): Role
    {
        $role
            ->setNom($data->nom)
            ->setNiveau($data->niveau)
            ->setDescription($data->description)
        ;

        foreach ($role->getPaths() as $key => $value) {
            $uuidO[] = !empty($data->paths[$key]->uuid) ? $data->paths[$key]->uuid : null;
            if ($uuidO !== null) {
                if (!in_array($value->getUuid(), $uuidO)) {
                    $role->removePath($value); 
                }
            } else { $role->removePath($value); }
        } 
        
        foreach ($data->paths as $item) {
            /** @var Path $path*/
            $path = $this->pathRepository->findOneByUuid($item->uuid); 
            $role->addPath($path);
        }

        $this->em->persist($role);
        return $role;
    }
}