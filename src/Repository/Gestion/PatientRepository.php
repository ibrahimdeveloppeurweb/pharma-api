<?php

namespace App\Repository\Gestion;

use App\Entity\Gestion\Patient;
use App\Entity\Gestion\Pharmacie;
use App\Entity\Security\User;
use App\Utils\TypeVariable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Patient>
 *
 * @method Patient|null find($id, $lockMode = null, $lockVersion = null)
 * @method Patient|null findOneBy(array $criteria, array $orderBy = null)
 * @method Patient[]    findAll()
 * @method Patient[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PatientRepository extends ServiceEntityRepository
{
    /** @var TypeVariable $type */
    private $type;

    public function __construct(ManagerRegistry $registry, TypeVariable $type)
    {
        $this->type = $type;
        parent::__construct($registry, Patient::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Patient $entity, bool $flush = true): void
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
    public function remove(Patient $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }





    /**
     * Liste des patients avec filtres
     * @return Patient[] Returns an array of Patient objects
     */
    public function findIndex($data, User $user = null,Pharmacie $pharmacie = null)
    {
        $query = $this->createQueryBuilder('p')
            ->leftJoin('p.pharmacie', 'ph')
            ->leftJoin('p.pharmacien', 'phc')
            ->leftJoin('p.demandes', 'd');

    
                // Filtre par pharmacie 
        if (isset($pharmacie) && TypeVariable::is_not_null($pharmacie) && $pharmacie) {
          $query = $query
                    ->andWhere('ph.id = :pharmacieId')
                    ->setParameter('pharmacieId', $pharmacie->getId());
        }

        // Filtre par pharmacien créateur (si user de type PUBLIC)
        if (isset($user) && TypeVariable::is_not_null($user) && $user) {
            if ($user->getType() === "PUBLIC") {
                $query = $query
                    ->andWhere('phc.id = :userId')
                    ->setParameter('userId', $user->getId());
            }
        }

        // Filtre par nom
        if (isset($data->nom) && TypeVariable::is_not_null($data->nom) && $data->nom) {
            $query = $query
                ->andWhere('p.nom LIKE :nom')
                ->setParameter('nom', '%' . $data->nom . '%');
        }

        // Filtre par prénom
        if (isset($data->prenom) && TypeVariable::is_not_null($data->prenom) && $data->prenom) {
            $query = $query
                ->andWhere('p.prenom LIKE :prenom')
                ->setParameter('prenom', '%' . $data->prenom . '%');
        }

        // Recherche globale par nom complet (nom OU prénom)
        if (isset($data->nomComplet) && TypeVariable::is_not_null($data->nomComplet) && $data->nomComplet) {
            $query = $query
                ->andWhere('p.nom LIKE :nomComplet OR p.prenom LIKE :nomComplet')
                ->setParameter('nomComplet', '%' . $data->nomComplet . '%');
        }

        // Filtre par téléphone
        if (isset($data->telephone) && TypeVariable::is_not_null($data->telephone) && $data->telephone) {
            $query = $query
                ->andWhere('p.telephone LIKE :telephone')
                ->setParameter('telephone', '%' . $data->telephone . '%');
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

        // Filtre patients avec demandes en attente
        if (isset($data->avecDemandesEnAttente) && TypeVariable::is_not_null($data->avecDemandesEnAttente) && $data->avecDemandesEnAttente) {
            $query = $query
                ->andWhere('d.statut = :statutEnAttente')
                ->setParameter('statutEnAttente', 'en_attente')
                ->groupBy('p.id');
        }

        // Filtre patients sans demandes
        if (isset($data->sansDemandes) && TypeVariable::is_not_null($data->sansDemandes) && $data->sansDemandes) {
            $query = $query
                ->andWhere('SIZE(p.demandes) = 0');
        }

        // Filtre par date de création (début)
        if (isset($data->dateD) && TypeVariable::is_not_null($data->dateD) && $data->dateD) {
            $dateD = new \DateTime($this->type->trim($data->dateD));
            $query = $query
                ->andWhere('p.createdAt >= :dateD')
                ->setParameter('dateD', $dateD);
        }

        // Filtre par date de création (fin)
        if (isset($data->dateF) && TypeVariable::is_not_null($data->dateF) && $data->dateF) {
            $dateF = new \DateTime($this->type->trim($data->dateF));
            $query = $query
                ->andWhere('p.createdAt <= :dateF')
                ->setParameter('dateF', $dateF);
        }

        // Filtre par année d'inscription
        if (isset($data->annee) && TypeVariable::is_not_null($data->annee) && $data->annee) {
            $start = new \DateTime($data->annee . '-01-01');
            $end = new \DateTime($data->annee . '-12-31 23:59:59');

            $query = $query
                ->andWhere('p.createdAt >= :start')
                ->andWhere('p.createdAt <= :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        $query = $query->orderBy('p.nom', 'ASC')->addOrderBy('p.prenom', 'ASC');

        return $query->getQuery()->getResult();
    }

    /**
     * Filtre des patients
     * @param object $data
     * @param User|null $user
     * @return Patient[]
     */
    public function findFilter(object $data, User $user = null)
    {
        $query = $this->createQueryBuilder('p')
            ->leftJoin('p.pharmacie', 'ph')
            ->leftJoin('p.pharmacien', 'phc')
            ->leftJoin('p.demandes', 'd');

        // Recherche globale (nom, prénom, téléphone, email)
        if (isset($data->search) && TypeVariable::is_not_null($data->search) && $data->search) {
            $query = $query
                ->andWhere('p.nom LIKE :search OR p.prenom LIKE :search OR p.telephone LIKE :search OR p.email LIKE :search')
                ->setParameter('search', '%' . $data->search . '%');
        }

        // Filtre par nom
        if (isset($data->nom) && TypeVariable::is_not_null($data->nom) && $data->nom) {
            $query = $query
                ->andWhere('p.nom LIKE :nom')
                ->setParameter('nom', '%' . $data->nom . '%');
        }

        // Filtre par prénom
        if (isset($data->prenom) && TypeVariable::is_not_null($data->prenom) && $data->prenom) {
            $query = $query
                ->andWhere('p.prenom LIKE :prenom')
                ->setParameter('prenom', '%' . $data->prenom . '%');
        }

        // Filtre par téléphone
        if (isset($data->telephone) && TypeVariable::is_not_null($data->telephone) && $data->telephone) {
            $query = $query
                ->andWhere('p.telephone LIKE :telephone')
                ->setParameter('telephone', '%' . $data->telephone . '%');
        }

        // Filtre par pharmacie assignée
        if (isset($data->pharmacieAssignee) && TypeVariable::is_not_null($data->pharmacieAssignee) && $data->pharmacieAssignee) {
            $query = $query
                ->andWhere('ph.uuid = :pharmacieAssignee')
                ->setParameter('pharmacieAssignee', $data->pharmacieAssignee);
        }

        // Filtre par pharmacien assigné
        if (isset($data->pharmacienAssigne) && TypeVariable::is_not_null($data->pharmacienAssigne) && $data->pharmacienAssigne) {
            $query = $query
                ->andWhere('phc.uuid = :pharmacienAssigne')
                ->setParameter('pharmacienAssigne', $data->pharmacienAssigne);
        }


        if (isset($data->ageMax) && TypeVariable::is_not_null($data->ageMax) && $data->ageMax) {
            $dateMin = new \DateTime('-' . $data->ageMax . ' years');
            $query = $query
                ->andWhere('p.dateNaissance >= :dateMin')
                ->setParameter('dateMin', $dateMin);
        }

        // Filtre patients actifs (avec au moins une demande)
        if (isset($data->actifs) && TypeVariable::is_not_null($data->actifs) && $data->actifs) {
            $query = $query
                ->andWhere('SIZE(p.demandes) > 0');
        }

        // Filtre patients inactifs (sans demande)
        if (isset($data->inactifs) && TypeVariable::is_not_null($data->inactifs) && $data->inactifs) {
            $query = $query
                ->andWhere('SIZE(p.demandes) = 0');
        }

        // Filtre par date de création (début)
        if (isset($data->dateD) && TypeVariable::is_not_null($data->dateD) && $data->dateD) {
            $dateD = new \DateTime($this->type->trim($data->dateD));
            $query = $query
                ->andWhere('p.createdAt >= :dateD')
                ->setParameter('dateD', $dateD);
        }

        // Filtre par date de création (fin)
        if (isset($data->dateF) && TypeVariable::is_not_null($data->dateF) && $data->dateF) {
            $dateF = new \DateTime($this->type->trim($data->dateF));
            $query = $query
                ->andWhere('p.createdAt <= :dateF')
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
                ->andWhere('p.createdAt <= :fin')
                ->setParameter('fin', $fin);
        }

        // Filtre par année d'inscription
        if (isset($data->annee) && TypeVariable::is_not_null($data->annee) && $data->annee) {
            $start = new \DateTime($data->annee . '-01-01');
            $end = new \DateTime($data->annee . '-12-31 23:59:59');

            $query = $query
                ->andWhere('p.createdAt >= :start')
                ->andWhere('p.createdAt <= :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        $query = $query->orderBy('p.nom', 'ASC')->addOrderBy('p.prenom', 'ASC');

        return $query->getQuery()->getResult();
    }

    /**
     * Recherche de patients par nom, prénom ou téléphone
     * @param string $search
     * @param Pharmacie|null $pharmacie
     * @param int $limit
     * @return Patient[]
     */
    public function search(string $search, ?Pharmacie $pharmacie = null, int $limit = 20): array
    {
        $query = $this->createQueryBuilder('p')
            ->where('p.nom LIKE :search OR p.prenom LIKE :search OR p.telephone LIKE :search OR p.email LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('p.prenom', 'ASC')
            ->setMaxResults($limit);

        if ($pharmacie) {
            $query = $query
                ->andWhere('p.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Patients avec demandes en attente
     * @param Pharmacie|null $pharmacie
     * @return Patient[]
     */
    public function findAvecDemandesEnAttente(?Pharmacie $pharmacie = null): array
    {
        $query = $this->createQueryBuilder('p')
            ->leftJoin('p.demandes', 'd')
            ->where('d.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->groupBy('p.id')
            ->orderBy('p.nom', 'ASC');

        if ($pharmacie) {
            $query = $query
                ->andWhere('p.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Patients les plus actifs (avec le plus de demandes)
     * @param Pharmacie|null $pharmacie
     * @param int $limit
     * @return array
     */
    public function findPlusActifs(?Pharmacie $pharmacie = null, int $limit = 10): array
    {
        $query = $this->createQueryBuilder('p')
            ->leftJoin('p.demandes', 'd')
            ->select('p, COUNT(d.id) as HIDDEN totalDemandes')
            ->groupBy('p.id')
            ->having('COUNT(d.id) > 0')
            ->orderBy('totalDemandes', 'DESC')
            ->setMaxResults($limit);

        if ($pharmacie) {
            $query = $query
                ->andWhere('p.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Patients sans demandes
     * @param Pharmacie|null $pharmacie
     * @return Patient[]
     */
    public function findSansDemandes(?Pharmacie $pharmacie = null): array
    {
        $query = $this->createQueryBuilder('p')
            ->leftJoin('p.demandes', 'd')
            ->where('d.id IS NULL');

        if ($pharmacie) {
            $query = $query
                ->andWhere('p.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        return $query
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nouveaux patients (inscrits dans les X derniers jours)
     * @param int $jours
     * @param Pharmacie|null $pharmacie
     * @return Patient[]
     */
    public function findNouveaux(int $jours = 30, ?Pharmacie $pharmacie = null): array
    {
        $dateLimit = new \DateTime("-{$jours} days");
        
        $query = $this->createQueryBuilder('p')
            ->where('p.createdAt >= :dateLimit')
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('p.createdAt', 'DESC');

        if ($pharmacie) {
            $query = $query
                ->andWhere('p.pharmacie = :pharmacie')
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
        $query = $this->createQueryBuilder('p')
            ->leftJoin('p.demandes', 'd')
            ->select('
                COUNT(DISTINCT p.id) as total,
                COUNT(d.id) as totalDemandes,
                COUNT(DISTINCT CASE WHEN d.statut = \'en_attente\' THEN p.id END) as patientsAvecDemandesEnAttente,
                COUNT(DISTINCT CASE WHEN SIZE(p.demandes) = 0 THEN p.id END) as patientsSansDemandes
            ');

        if ($pharmacie) {
            $query = $query
                ->where('p.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        return $query->getQuery()->getSingleResult();
    }

  
  

    /**
     * Vérifier si un patient existe par téléphone ou email
     * @param string $telephone
     * @param string|null $email
     * @param Pharmacie|null $pharmacie
     * @return bool
     */
    public function exists(string $telephone, ?string $email = null, ?Pharmacie $pharmacie = null): bool
    {
        $query = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.telephone = :telephone');

        $params = ['telephone' => $telephone];

        foreach ($params as $key => $value) {
            $query->setParameter($key, $value);
        }

        if ($pharmacie) {
            $query->andWhere('p.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        return (int) $query->getQuery()->getSingleScalarResult() > 0;
    }

  
}