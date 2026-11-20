<?php

namespace App\Repository;

use App\Entity\ReponseRecherche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReponseRecherche>
 *
 * @method ReponseRecherche|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReponseRecherche|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReponseRecherche[]    findAll()
 * @method ReponseRecherche[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReponseRechercheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReponseRecherche::class);
    }

    public function save(ReponseRecherche $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ReponseRecherche $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
