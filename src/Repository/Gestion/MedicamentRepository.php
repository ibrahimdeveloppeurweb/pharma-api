<?php

namespace App\Repository\Gestion;

use App\Entity\Gestion\Medicament;
use App\Entity\Gestion\Pharmacie;
use App\Entity\Security\User;
use App\Utils\TypeVariable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Medicament>
 *
 * @method Medicament|null find($id, $lockMode = null, $lockVersion = null)
 * @method Medicament|null findOneBy(array $criteria, array $orderBy = null)
 * @method Medicament[]    findAll()
 * @method Medicament[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MedicamentRepository extends ServiceEntityRepository
{
    /** @var TypeVariable $type */
    private $type;

    public function __construct(ManagerRegistry $registry, TypeVariable $type)
    {
        $this->type = $type;
        parent::__construct($registry, Medicament::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Medicament $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(Medicament $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

 


    /**
     * Liste des médicaments avec filtres
     * @return Medicament[] Returns an array of Medicament objects
     */
    public function findIndex($data, User $user = null, Pharmacie $pharmacie = null)
    {
        $query = $this->createQueryBuilder('m')
            ->leftJoin('m.pharmacie', 'ph')
            ->leftJoin('m.pharmacien', 'phc')
            ->leftJoin('m.medecine', 'med');

          // Filtre par pharmacie 
        if (isset($pharmacie) && TypeVariable::is_not_null($pharmacie) && $pharmacie) {
          $query = $query
                    ->andWhere('ph.id = :pharmacieId')
                    ->setParameter('pharmacieId', $pharmacie->getId());
        }
        // Filtre par pharmacien créateur (si user de type PUBLIC)
        // if (isset($user) && TypeVariable::is_not_null($user) && $user) {
        //   $query = $query
        //             ->andWhere('phc.id = :userId')
        //             ->setParameter('userId', $user->getId());
        // }

        // Filtre par nom du médicament
        if (isset($data->nom) && TypeVariable::is_not_null($data->nom) && $data->nom) {
            $query = $query
                ->andWhere('m.nom LIKE :nom OR m.nomCommercial LIKE :nom')
                ->setParameter('nom', '%' . $data->nom . '%');
        }



        // Filtre par disponibilité
        if (isset($data->disponible) && TypeVariable::is_not_null($data->disponible)) {
            $query = $query
                ->andWhere('m.disponible = :disponible')
                ->setParameter('disponible', $data->disponible);
        }

        // Filtre par stock faible (stock <= stockMinimum)
        if (isset($data->stockFaible) && TypeVariable::is_not_null($data->stockFaible) && $data->stockFaible) {
            $query = $query
                ->andWhere('m.stock <= m.stockMinimum OR (m.stock <= 5 AND m.stockMinimum IS NULL)');
        }

        // Filtre par rupture de stock
        if (isset($data->enRupture) && TypeVariable::is_not_null($data->enRupture) && $data->enRupture) {
            $query = $query
                ->andWhere('m.stock = 0');
        }

        // Filtre par stock minimum
        if (isset($data->stockMin) && TypeVariable::is_not_null($data->stockMin) && $data->stockMin) {
            $query = $query
                ->andWhere('m.stock >= :stockMin')
                ->setParameter('stockMin', $data->stockMin);
        }

        // Filtre par stock maximum
        if (isset($data->stockMax) && TypeVariable::is_not_null($data->stockMax) && $data->stockMax) {
            $query = $query
                ->andWhere('m.stock <= :stockMax')
                ->setParameter('stockMax', $data->stockMax);
        }

        // Filtre par prix minimum
        if (isset($data->prixMin) && TypeVariable::is_not_null($data->prixMin) && $data->prixMin) {
            $query = $query
                ->andWhere('m.prix >= :prixMin')
                ->setParameter('prixMin', $data->prixMin);
        }

        // Filtre par prix maximum
        if (isset($data->prixMax) && TypeVariable::is_not_null($data->prixMax) && $data->prixMax) {
            $query = $query
                ->andWhere('m.prix <= :prixMax')
                ->setParameter('prixMax', $data->prixMax);
        }


        // Filtre par pharmacie
        if (isset($data->pharmacie) && TypeVariable::is_not_null($data->pharmacie) && $data->pharmacie) {
            $query = $query
                ->andWhere('ph.uuid = :pharmacie')
                ->setParameter('pharmacie', $data->pharmacie);
        }

        // Filtre par pharmacien
        if (isset($data->pharmacien) && TypeVariable::is_not_null($data->pharmacien) && $data->pharmacien) {
            $query = $query
                ->andWhere('phc.uuid = :pharmacien')
                ->setParameter('pharmacien', $data->pharmacien);
        }

        // Filtre par médecine (référence globale)
        if (isset($data->medecine) && TypeVariable::is_not_null($data->medecine) && $data->medecine) {
            $query = $query
                ->andWhere('med.uuid = :medecine')
                ->setParameter('medecine', $data->medecine);
        }

        // Filtre par date de création (début)
        if (isset($data->dateD) && TypeVariable::is_not_null($data->dateD) && $data->dateD) {
            $dateD = new \DateTime($this->type->trim($data->dateD));
            $query = $query
                ->andWhere('m.createdAt >= :dateD')
                ->setParameter('dateD', $dateD);
        }

        // Filtre par date de création (fin)
        if (isset($data->dateF) && TypeVariable::is_not_null($data->dateF) && $data->dateF) {
            $dateF = new \DateTime($this->type->trim($data->dateF));
            $query = $query
                ->andWhere('m.createdAt <= :dateF')
                ->setParameter('dateF', $dateF);
        }

        // Filtre par année
        if (isset($data->annee) && TypeVariable::is_not_null($data->annee) && $data->annee) {
            $start = new \DateTime($data->annee . '-01-01');
            $end = new \DateTime($data->annee . '-12-31 23:59:59');

            $query = $query
                ->andWhere('m.createdAt >= :start')
                ->andWhere('m.createdAt <= :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        $query = $query->orderBy('m.id', 'DESC');

        return $query->getQuery()->getResult();
    }

    public function findMostMedicaments($data = null, Pharmacie $pharmacie = null)
    {
        $query = $this->createQueryBuilder('m')
            ->select('m.nom AS medicament', 'COUNT(DISTINCT d.id) AS nbDemandes')
            ->leftJoin('m.demandeMedicaments', 'dm') // pivot
            ->leftJoin('dm.demande', 'd')            // la demande
            ->leftJoin('d.pharmacie', 'ph');
        // Filtre par pharmacie
        if ($pharmacie) {
            $query->andWhere('ph.id = :pharmacie')
            ->setParameter('pharmacie', $pharmacie->getId());
        }

        // Filtre par date de début
        if ($data && isset($data->dateD) && $data->dateD) {
            $dateD = new \DateTime($data->dateD);
            $dateD->setTime(0, 0, 0);
            $query->andWhere('d.createdAt >= :dateD')
            ->setParameter('dateD', $dateD);
        }

        // Filtre par date de fin
        if ($data && isset($data->dateF) && $data->dateF) {
            $dateF = new \DateTime($data->dateF);
            $dateF->setTime(23, 59, 59);
            $query->andWhere('d.createdAt <= :dateF')
            ->setParameter('dateF', $dateF);
        }

        // Regroupement
        $query->groupBy('m.id')
        ->orderBy('nbDemandes', 'DESC');



        return $query->getQuery()->getResult();
    }



    /**
     * Filtre des médicaments
     * @param object $data
     * @param User|null $user
     * @return Medicament[]
     */
    public function findFilter(object $data, User $user = null)
    {
        $query = $this->createQueryBuilder('m')
            ->leftJoin('m.pharmacie', 'ph')
            ->leftJoin('m.pharmacien', 'phc')
            ->leftJoin('m.medecine', 'med');

        // Filtre par nom (nom ou nom commercial)
        if (isset($data->nom) && TypeVariable::is_not_null($data->nom) && $data->nom) {
            $query = $query
                ->andWhere('m.nom LIKE :nom OR m.nomCommercial LIKE :nom')
                ->setParameter('nom', '%' . $data->nom . '%');
        }


        // Filtre par disponibilité
        if (isset($data->disponible) && TypeVariable::is_not_null($data->disponible)) {
            $query = $query
                ->andWhere('m.disponible = :disponible')
                ->setParameter('disponible', $data->disponible);
        }

    

        // Filtre par pharmacie assignée
        if (isset($data->pharmacieAssignee) && TypeVariable::is_not_null($data->pharmacieAssignee) && $data->pharmacieAssignee) {
            $query = $query
                ->andWhere('ph.uuid = :pharmacieAssignee')
                ->setParameter('pharmacieAssignee', $data->pharmacieAssignee);
        }



        // Filtre par plage de prix
        if (isset($data->prixMin) && TypeVariable::is_not_null($data->prixMin) && $data->prixMin) {
            $query = $query
                ->andWhere('m.prix >= :prixMin')
                ->setParameter('prixMin', $data->prixMin);
        }

        if (isset($data->prixMax) && TypeVariable::is_not_null($data->prixMax) && $data->prixMax) {
            $query = $query
                ->andWhere('m.prix <= :prixMax')
                ->setParameter('prixMax', $data->prixMax);
        }

        // Filtre par date de création (début)
        if (isset($data->dateD) && TypeVariable::is_not_null($data->dateD) && $data->dateD) {
            $dateD = new \DateTime($this->type->trim($data->dateD));
            $query = $query
                ->andWhere('m.createdAt >= :dateD')
                ->setParameter('dateD', $dateD);
        }

        // Filtre par date de création (fin)
        if (isset($data->dateF) && TypeVariable::is_not_null($data->dateF) && $data->dateF) {
            $dateF = new \DateTime($this->type->trim($data->dateF));
            $query = $query
                ->andWhere('m.createdAt <= :dateF')
                ->setParameter('dateF', $dateF);
        }

        // Alternative pour fin
        if (isset($data->fin) && TypeVariable::is_not_null($data->fin) && $data->fin) {
            if ($data->fin instanceof \DateTime) {
                $fin = $data->fin;
            } else {
                $fin = new \DateTime($this->type->trim($data->fin));
            }

            $query = $query
                ->andWhere('m.createdAt <= :fin')
                ->setParameter('fin', $fin);
        }

        // Filtre par année
        if (isset($data->annee) && TypeVariable::is_not_null($data->annee) && $data->annee) {
            $start = new \DateTime($data->annee . '-01-01');
            $end = new \DateTime($data->annee . '-12-31 23:59:59');

            $query = $query
                ->andWhere('m.createdAt >= :start')
                ->andWhere('m.createdAt <= :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        $query = $query->orderBy('m.nom', 'ASC');

        return $query->getQuery()->getResult();
    }

    /**
     * Recherche de médicaments disponibles dans une pharmacie
     * @param Pharmacie $pharmacie
     * @param string|null $search
     * @return Medicament[]
     */
    public function findDisponiblesByPharmacie(Pharmacie $pharmacie, ?string $search = null): array
    {
        $query = $this->createQueryBuilder('m')
            ->andWhere('m.pharmacie = :pharmacie')
            ->andWhere('m.disponible = true')
            ->andWhere('m.stock > 0')
            ->setParameter('pharmacie', $pharmacie);

        if ($search) {
            $query = $query
                ->andWhere('m.nom LIKE :search OR m.nomCommercial LIKE :search OR m.dci LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $query
            ->orderBy('m.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }



    /**
     * Médicaments les plus demandés
     * @param Pharmacie|null $pharmacie
     * @param int $limit
     * @return array
     */
    public function findPlusDemandes(?Pharmacie $pharmacie = null, int $limit = 10): array
    {
        $query = $this->createQueryBuilder('m')
            ->leftJoin('m.demandes', 'd')
            ->select('m, COUNT(d.id) as HIDDEN totalDemandes')
            ->groupBy('m.id')
            ->orderBy('totalDemandes', 'DESC')
            ->setMaxResults($limit);

        if ($pharmacie) {
            $query = $query
                ->andWhere('m.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Statistiques globales
     * @param Pharmacie|null $pharmacie
     * @return array
     */
    public function getStatistics(?Pharmacie $pharmacie = null): array
    {
        $query = $this->createQueryBuilder('m')
            ->select('
                COUNT(m.id) as total,
                SUM(CASE WHEN m.disponible = true THEN 1 ELSE 0 END) as disponibles,
                SUM(CASE WHEN m.stock = 0 THEN 1 ELSE 0 END) as enRupture,
                SUM(CASE WHEN m.stock <= m.stockMinimum OR (m.stock <= 5 AND m.stockMinimum IS NULL) THEN 1 ELSE 0 END) as stockFaible,
                SUM(m.stock) as stockTotal,
                AVG(m.prix) as prixMoyen
            ');

        if ($pharmacie) {
            $query = $query
                ->andWhere('m.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        return $query->getQuery()->getSingleResult();
    }

  
}