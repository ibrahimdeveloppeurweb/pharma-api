<?php

namespace App\Repository\Extra;

use App\Entity\Extra\Medecine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Medecine>
 */
class MedecineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Medecine::class);
    }

    /**
     * Compter le total de médicaments
     */
    public function count(array $criteria = []): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ⭐ Méthode optimisée pour récupérer les médicaments avec pagination
     * 
     * @param int $page Numéro de la page
     * @param int $limit Nombre d'éléments par page
     * @return array
     */
    public function findPaginated(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        
        return $this->createQueryBuilder('m')
            ->select('m.id', 'm.uuid', 'm.nom', 'm.prix')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getArrayResult(); // Retourne directement en array (plus rapide)
    }
}