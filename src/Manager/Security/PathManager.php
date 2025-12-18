<?php

namespace App\Manager\Security;

use App\Entity\Security\Path;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\Security\PathRepository;

class PathManager
{
    private $em;
    private $pathRepository;
    public function __construct(
        EntityManagerInterface $em,
        PathRepository $pathRepository
    )
    {
        $this->em = $em;
        $this->pathRepository = $pathRepository;
    }

    /**
     * Ajouter un élément
     * @param object $data
     * @return Path
     */
    public function create(object $data): Path
    {
        $path = new Path();
        $this->add($data, $path,);
        $this->em->flush();

        return $path;
    }

    /**
     * Mettre à jour un élément
     * @param object $data
     * @param string $id
     * @return Path
     */
    public function update(object $data, string $id): Path
    {
        $path = $this->pathRepository->findOneByUuid($id);
        $this->add($data, $path);
        return $path;
    }
    
    /**
     * Supprimer un élément
     * @param string $id
     * @return void
     */
    public function delete(string $id)
    {
        $path = $this->pathRepository->findOneByUuid($id);
        $this->em->remove($path);
        $this->em->flush();
        return;
    }

    /**
     * @param object $data
     * @param Path $path
     * @return Path
     */
    public function add(object $data, Path $path): Path
    {
        $path
            ->setNom($data->nom)
            ->setChemin($data->chemin)
            ->setLibelle($data->libelle)
            ->setPermission($data->permission)
        ;
        $this->em->persist($path);
        return $path;
    }
}