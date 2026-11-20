<?php

namespace App\Repository\Gestion;

use App\Entity\Gestion\Pharmacie;
use App\Entity\Security\User;
use App\Utils\TypeVariable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pharmacie>
 *
 * @method Pharmacie|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pharmacie|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pharmacie[]    findAll()
 * @method Pharmacie[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PharmacieRepository extends ServiceEntityRepository
{
    /** @var TypeVariable $type */
    private $type;

    public function __construct(ManagerRegistry $registry, TypeVariable $type)
    {
        $this->type = $type;
        parent::__construct($registry, Pharmacie::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Pharmacie $entity, bool $flush = true): void
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
    public function remove(Pharmacie $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }




    /**
     * Liste des pharmacies avec filtres
     * @return Pharmacie[] Returns an array of Pharmacie objects
     */

    public function findIndex($data, User $user = null)
    {
        $query = $this->createQueryBuilder('p')
                ->leftJoin('p.user', 'u')
                ->leftJoin('p.patients', 'pat')
                ->leftJoin('p.demandes', 'd')
                ->leftJoin('p.medicaments', 'm')
                ->leftJoin('p.users', 'usr')
                ->leftJoin('p.abonnement', 'a') 
                ->addSelect('u.isActive AS actif')
                ->addSelect('u.telephone AS user_telephone')
                ->addSelect('u.email AS user_email')
                ->addSelect('a.uuid AS abonnement_uuid')
                ->addSelect('MAX(CASE WHEN a.actif = true THEN 1 ELSE 0 END) AS abonnementActif')
                ->addSelect('MAX(CASE WHEN a.faveurActive = true THEN 1 ELSE 0 END) AS faveurActive')
                ->addSelect('COUNT(DISTINCT d.id) AS total_demandes')
                ->addSelect('(SELECT COUNT(d1.id) FROM App\Entity\Gestion\Demande d1 WHERE d1.pharmacie = p AND d1.statut = \'notifie\') AS demandes_notifiees')
                ->addSelect('(SELECT COUNT(d2.id) FROM App\Entity\Gestion\Demande d2 WHERE d2.pharmacie = p AND d2.statut = \'en_attente\') AS demandes_en_attente')
                ->addSelect('(SELECT COUNT(d3.id) FROM App\Entity\Gestion\Demande d3 WHERE d3.pharmacie = p AND d3.statut = \'recupere\') AS demandes_recuperees')
                ->groupBy('p.id, u.isActive');

        // Filtre par propriétaire (si user de type PUBLIC)
        if (isset($user) && TypeVariable::is_not_null($user) && $user) {
            if ($user->getType() === "PUBLIC") {
                $query = $query
                    ->andWhere('u.id = :userId')
                    ->setParameter('userId', $user->getId());
            }
        }

        if (isset($data->statut) && TypeVariable::is_not_null($data->statut) && $data->statut) {
            if ($data->statut === "notifie") {
                $query = $query
                    ->andWhere('d.statut = :statut')
                    ->setParameter('statut',$data->statut );
            }else if ($data->statut != "toutes") {
                $isActive = $data->statut == "actives" ? true : false;
                $query = $query
                    ->andWhere('u.isActive = :isActive')
                    ->setParameter('isActive',$isActive);    
             }    
        }

        // Filtre par nom
        if (isset($data->nom) && TypeVariable::is_not_null($data->nom) && $data->nom) {
            $query = $query
                ->andWhere('p.nom LIKE :nom')
                ->setParameter('nom', '%' . $data->nom . '%');
        }

        // Filtre par ville
        if (isset($data->ville) && TypeVariable::is_not_null($data->ville) && $data->ville) {
            $query = $query
                ->andWhere('p.ville LIKE :ville')
                ->setParameter('ville', '%' . $data->ville . '%');
        }

        // Filtre par code postal
        if (isset($data->codePostal) && TypeVariable::is_not_null($data->codePostal) && $data->codePostal) {
            $query = $query
                ->andWhere('p.codePostal LIKE :codePostal')
                ->setParameter('codePostal', '%' . $data->codePostal . '%');
        }

        // Filtre par adresse
        if (isset($data->adresse) && TypeVariable::is_not_null($data->adresse) && $data->adresse) {
            $query = $query
                ->andWhere('p.adresse LIKE :adresse')
                ->setParameter('adresse', '%' . $data->adresse . '%');
        }

        // Filtre par téléphone
        if (isset($data->telephone) && TypeVariable::is_not_null($data->telephone) && $data->telephone) {
            $query = $query
                ->andWhere('p.telephone LIKE :telephone')
                ->setParameter('telephone', '%' . $data->telephone . '%');
        }

        // Filtre par email
        if (isset($data->email) && TypeVariable::is_not_null($data->email) && $data->email) {
            $query = $query
                ->andWhere('p.email LIKE :email')
                ->setParameter('email', '%' . $data->email . '%');
        }

        // Filtre pharmacies avec patients
        if (isset($data->avecPatients) && TypeVariable::is_not_null($data->avecPatients) && $data->avecPatients) {
            $query = $query
                ->andWhere('SIZE(p.patients) > 0');
        }

        // Filtre pharmacies sans patients
        if (isset($data->sansPatients) && TypeVariable::is_not_null($data->sansPatients) && $data->sansPatients) {
            $query = $query
                ->andWhere('SIZE(p.patients) = 0');
        }

        // Filtre pharmacies avec médicaments
        if (isset($data->avecMedicaments) && TypeVariable::is_not_null($data->avecMedicaments) && $data->avecMedicaments) {
            $query = $query
                ->andWhere('SIZE(p.medicaments) > 0');
        }

        // Filtre pharmacies sans médicaments
        if (isset($data->sansMedicaments) && TypeVariable::is_not_null($data->sansMedicaments) && $data->sansMedicaments) {
            $query = $query
                ->andWhere('SIZE(p.medicaments) = 0');
        }

        // Filtre par nombre minimum de patients
        if (isset($data->patientsMin) && TypeVariable::is_not_null($data->patientsMin) && $data->patientsMin) {
            $query = $query
                ->andWhere('SIZE(p.patients) >= :patientsMin')
                ->setParameter('patientsMin', $data->patientsMin);
        }

        // Filtre par nombre minimum de médicaments
        if (isset($data->medicamentsMin) && TypeVariable::is_not_null($data->medicamentsMin) && $data->medicamentsMin) {
            $query = $query
                ->andWhere('SIZE(p.medicaments) >= :medicamentsMin')
                ->setParameter('medicamentsMin', $data->medicamentsMin);
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

        // Filtre par année
        if (isset($data->annee) && TypeVariable::is_not_null($data->annee) && $data->annee) {
            $start = new \DateTime($data->annee . '-01-01');
            $end = new \DateTime($data->annee . '-12-31 23:59:59');

            $query = $query
                ->andWhere('p.createdAt >= :start')
                ->andWhere('p.createdAt <= :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

       $query = $query->orderBy('p.id', 'DESC');
       $results = $query->getQuery()->getArrayResult(); // <- ArrayResult pour récupérer tout

        // Transformer chaque résultat pour fusionner entité + stats
        $final = [];
        foreach ($results as $row) {
            $pharmacie = $row[0]; // Doctrine met l'entité complète dans le 0
            $stats = [
                'actif' => $row['actif'],
                'total_demandes' => (int) $row['total_demandes'],
                'demandes_notifiees' => (int) $row['demandes_notifiees'],
                'demandes_en_attente' => (int) $row['demandes_en_attente'],
                'demandes_recuperees' => (int) $row['demandes_recuperees'],
                'abonnementActif' => (bool) $row['abonnementActif'],
                'faveurActive' => (bool) $row['faveurActive'],
                'abonnement_uuid' => $row['abonnement_uuid'],
            ];

            $pharmacie['telephone'] = !empty($pharmacie['telephone']) ? $pharmacie['telephone'] : $row['user_telephone'];
            $pharmacie['email'] = !empty($pharmacie['email']) ? $pharmacie['email'] : $row['user_email'];

            $final[] = array_merge($pharmacie, $stats);
        }

        return $final;
    }


    /**
     * Filtre des pharmacies
     * @param object $data
     * @param User|null $user
     * @return Pharmacie[]
     */
    public function findFilter(object $data, User $user = null)
    {
        $query = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.patients', 'pat')
            ->leftJoin('p.demandes', 'd')
            ->leftJoin('p.medicaments', 'm');

        // Recherche globale (nom, ville, adresse, téléphone, email)
        if (isset($data->search) && TypeVariable::is_not_null($data->search) && $data->search) {
            $query = $query
                ->andWhere('p.nom LIKE :search OR p.ville LIKE :search OR p.adresse LIKE :search OR p.telephone LIKE :search OR p.email LIKE :search')
                ->setParameter('search', '%' . $data->search . '%');
        }

        // Filtre par nom
        if (isset($data->nom) && TypeVariable::is_not_null($data->nom) && $data->nom) {
            $query = $query
                ->andWhere('p.nom LIKE :nom')
                ->setParameter('nom', '%' . $data->nom . '%');
        }

        // Filtre par ville
        if (isset($data->ville) && TypeVariable::is_not_null($data->ville) && $data->ville) {
            $query = $query
                ->andWhere('p.ville LIKE :ville')
                ->setParameter('ville', '%' . $data->ville . '%');
        }

        // Filtre par code postal
        if (isset($data->codePostal) && TypeVariable::is_not_null($data->codePostal) && $data->codePostal) {
            $query = $query
                ->andWhere('p.codePostal LIKE :codePostal')
                ->setParameter('codePostal', '%' . $data->codePostal . '%');
        }

        // Filtre par téléphone
        if (isset($data->telephone) && TypeVariable::is_not_null($data->telephone) && $data->telephone) {
            $query = $query
                ->andWhere('p.telephone LIKE :telephone')
                ->setParameter('telephone', '%' . $data->telephone . '%');
        }

        // Filtre par email
        if (isset($data->email) && TypeVariable::is_not_null($data->email) && $data->email) {
            $query = $query
                ->andWhere('p.email LIKE :email')
                ->setParameter('email', '%' . $data->email . '%');
        }

   

        // Filtre pharmacies actives (avec patients et médicaments)
        if (isset($data->actives) && TypeVariable::is_not_null($data->actives) && $data->actives) {
            $query = $query
                ->andWhere('SIZE(p.patients) > 0 OR SIZE(p.medicaments) > 0');
        }

        // Filtre pharmacies inactives (sans patients ni médicaments)
        if (isset($data->inactives) && TypeVariable::is_not_null($data->inactives) && $data->inactives) {
            $query = $query
                ->andWhere('SIZE(p.patients) = 0 AND SIZE(p.medicaments) = 0');
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

        // Filtre par année
        if (isset($data->annee) && TypeVariable::is_not_null($data->annee) && $data->annee) {
            $start = new \DateTime($data->annee . '-01-01');
            $end = new \DateTime($data->annee . '-12-31 23:59:59');

            $query = $query
                ->andWhere('p.createdAt >= :start')
                ->andWhere('p.createdAt <= :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        $query = $query->orderBy('p.nom', 'ASC');

        return $query->getQuery()->getResult();
    }

    /**
     * Recherche de pharmacies par nom, ville ou adresse
     * @param string $search
     * @param int $limit
     * @return Pharmacie[]
     */
    public function search(string $search, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.nom LIKE :search OR p.ville LIKE :search OR p.adresse LIKE :search OR p.telephone LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('p.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }



 

    /**
     * Pharmacies les plus actives (par nombre de demandes)
     * @param int $limit
     * @return array
     */
    public function findPlusActives(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.demandes', 'd')
            ->select('p, COUNT(d.id) as HIDDEN totalDemandes')
            ->groupBy('p.id')
            ->having('COUNT(d.id) > 0')
            ->orderBy('totalDemandes', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Pharmacies avec le plus de patients
     * @param int $limit
     * @return array
     */
    public function findAvecPlusPatients(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.patients', 'pat')
            ->select('p, COUNT(pat.id) as HIDDEN totalPatients')
            ->groupBy('p.id')
            ->having('COUNT(pat.id) > 0')
            ->orderBy('totalPatients', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Pharmacies avec le plus de médicaments
     * @param int $limit
     * @return array
     */
    public function findAvecPlusMedicaments(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.medicaments', 'm')
            ->select('p, COUNT(m.id) as HIDDEN totalMedicaments')
            ->groupBy('p.id')
            ->having('COUNT(m.id) > 0')
            ->orderBy('totalMedicaments', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Pharmacies sans activité (sans patients et sans médicaments)
     * @return Pharmacie[]
     */
    public function findInactives(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.patients', 'pat')
            ->leftJoin('p.medicaments', 'm')
            ->where('pat.id IS NULL AND m.id IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nouvelles pharmacies (inscrites dans les X derniers jours)
     * @param int $jours
     * @return Pharmacie[]
     */
    public function findNouvelles(int $jours = 30): array
    {
        $dateLimit = new \DateTime("-{$jours} days");
        
        return $this->createQueryBuilder('p')
            ->where('p.createdAt >= :dateLimit')
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques globales
     * @return array
     */
    public function getStatistics(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.patients', 'pat')
            ->leftJoin('p.demandes', 'd')
            ->leftJoin('p.medicaments', 'm')
            ->select('
                COUNT(DISTINCT p.id) as total,
                COUNT(DISTINCT pat.id) as totalPatients,
                COUNT(DISTINCT d.id) as totalDemandes,
                COUNT(DISTINCT m.id) as totalMedicaments,
                COUNT(DISTINCT CASE WHEN SIZE(p.patients) > 0 OR SIZE(p.medicaments) > 0 THEN p.id END) as pharmaciesActives
            ')
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Statistiques détaillées d'une pharmacie
     * @param Pharmacie $pharmacie
     * @return array
     */
    public function getPharmacieStatistics(Pharmacie $pharmacie): array
    {
        return [
            'totalPatients' => $pharmacie->getPatients()->count(),
            'totalDemandes' => $pharmacie->getDemandes()->count(),
            'totalMedicaments' => $pharmacie->getMedicaments()->count(),
            'totalEmployes' => $pharmacie->getUsers()->count(),
            'demandesEnAttente' => $this->_em->createQueryBuilder()
                ->select('COUNT(d.id)')
                ->from('App\Entity\Gestion\Demande', 'd')
                ->where('d.pharmacie = :pharmacie')
                ->andWhere('d.statut = :statut')
                ->setParameter('pharmacie', $pharmacie)
                ->setParameter('statut', 'en_attente')
                ->getQuery()
                ->getSingleScalarResult(),
            'medicamentsEnRupture' => $this->_em->createQueryBuilder()
                ->select('COUNT(m.id)')
                ->from('App\Entity\Gestion\Medicament', 'm')
                ->where('m.pharmacie = :pharmacie')
                ->andWhere('m.stock = 0')
                ->setParameter('pharmacie', $pharmacie)
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    /**
     * Grouper par ville
     * @return array
     */
    public function groupByVille(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.ville, COUNT(p.id) as total')
            ->where('p.ville IS NOT NULL')
            ->groupBy('p.ville')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

   

    /**
     * Vérifier si une pharmacie existe par téléphone ou email
     * @param string $telephone
     * @param string|null $email
     * @return bool
     */
    public function exists(string $telephone, ?string $email = null): bool
    {
        $query = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.telephone = :telephone');

        $params = ['telephone' => $telephone];

        if ($email) {
            $query->orWhere('p.email = :email');
            $params['email'] = $email;
        }

        foreach ($params as $key => $value) {
            $query->setParameter($key, $value);
        }

        return (int) $query->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Recherche les pharmacies les plus proches en utilisant la formule de Haversine
     * @param float $latitude
     * @param float $longitude
     * @param int $radiusKm
     * @return array
     */
    public function findNearbyPharmacies(float $latitude, float $longitude, int $radiusKm = 10, int $limit = 20): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT p.*, 
            (6371 * acos(
                LEAST(1.0, GREATEST(-1.0, 
                    cos(radians(:lat)) * cos(radians(CAST(NULLIF(p.latitude, \'\') AS FLOAT))) * cos(radians(CAST(NULLIF(p.longitude, \'\') AS FLOAT)) - radians(:lng)) 
                    + sin(radians(:lat)) * sin(radians(CAST(NULLIF(p.latitude, \'\') AS FLOAT)))
                ))
            )) AS distance
            FROM pharmacie p
            WHERE p.latitude IS NOT NULL AND p.longitude IS NOT NULL AND p.latitude != \'\' AND p.longitude != \'\'
            AND p.is_disponible_recherche = 1
            HAVING distance <= :radius
            ORDER BY distance ASC
            LIMIT ' . max(1, min(100, $limit)) . '
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'lat' => $latitude,
            'lng' => $longitude,
            'radius' => $radiusKm
        ]);
        
        return $result->fetchAllAssociative();
    }
}