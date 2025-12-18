<?php

namespace App\Repository\Gestion;

use App\Entity\Gestion\Demande;
use App\Entity\Gestion\Pharmacie;
use App\Entity\Security\User;
use App\Utils\TypeVariable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Demande>
 *
 * @method Demande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Demande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Demande[]    findAll()
 * @method Demande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeRepository extends ServiceEntityRepository
{
    /** @var TypeVariable $type */
    private $type;

    public function __construct(ManagerRegistry $registry, TypeVariable $type)
    {
        $this->type = $type;
        parent::__construct($registry, Demande::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Demande $entity, bool $flush = true): void
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
    public function remove(Demande $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // Vérifie si la date contient une heure (format: Y-m-d H:i:s)
    private function hasTime($date): bool
    {
        if ($date instanceof \DateTimeInterface) {
            $dateStr = $date->format('Y-m-d H:i:s');
        } else {
            $dateStr = (string) $date;
        }
        return (bool) preg_match('/\d{2}:\d{2}(:\d{2})?/', $dateStr);
    }

    /**
     * Liste des demandes avec filtres
     * @return Demande[] Returns an array of Demande objects
     */
    public function findIndex($data, User $user = null, Pharmacie $pharmacie= null)
    {
        $query = $this->createQueryBuilder('d')
            ->leftJoin('d.patient', 'p')
            ->leftJoin('d.pharmacie', 'ph')
            ->leftJoin('d.pharmacien', 'phc')
            ->leftJoin('d.demandeMedicaments', 'm');

        // Filtre par pharmacien créateur (si user de type PUBLIC)
        if (isset($user) && TypeVariable::is_not_null($user) && $user) {
         $query = $query
                    ->andWhere('phc.id = :userId')
                    ->setParameter('userId', $user->getId());
        }
       
        // Filtre par référence
        if (isset($data->reference) && TypeVariable::is_not_null($data->reference) && $data->reference) {
            $query = $query
                ->andWhere('d.reference LIKE :reference')
                ->setParameter('reference', '%' . $data->reference . '%');
        }

        // Filtre par statut
        if (isset($data->statut) && TypeVariable::is_not_null($data->statut) && $data->statut !== null) {
            $query = $query
                ->andWhere('d.statut = :statut')
                ->setParameter('statut', $data->statut);
        }

        // Filtre par patient (nom, prénom ou téléphone)
        if (isset($data->patient) && TypeVariable::is_not_null($data->patient) && $data->patient) {
            $query = $query
                ->andWhere('p.nom LIKE :patient OR p.prenom LIKE :patient OR p.telephone LIKE :patient')
                ->setParameter('patient', '%' . $data->patient . '%');
        }

        // Filtre par patient UUID
        if (isset($data->patientUuid) && TypeVariable::is_not_null($data->patientUuid) && $data->patientUuid) {
            $query = $query
                ->andWhere('p.uuid = :patientUuid')
                ->setParameter('patientUuid', $data->patientUuid);
        }

        // Filtre par médicament (nom)
        if (isset($data->medicament) && TypeVariable::is_not_null($data->medicament) && $data->medicament) {
            $query = $query
                ->andWhere('m.nom LIKE :medicament')
                ->setParameter('medicament', '%' . $data->medicament . '%');
        }

        // Filtre par pharmacie
        if (isset($pharmacie) && TypeVariable::is_not_null($pharmacie) && $pharmacie) {
            $query = $query
                ->andWhere('ph.id = :pharmacie')
                ->setParameter('pharmacie', $pharmacie->getId());
        }   

        // Filtre par pharmacien
        if (isset($data->pharmacien) && TypeVariable::is_not_null($data->pharmacien) && $data->pharmacien) {
            $query = $query
                ->andWhere('phc.uuid = :pharmacien')
                ->setParameter('pharmacien', $data->pharmacien);
        }

        // Filtre par SMS envoyé
        if (isset($data->smsEnvoye) && TypeVariable::is_not_null($data->smsEnvoye)) {
            $query = $query
                ->andWhere('d.smsEnvoye = :smsEnvoye')
                ->setParameter('smsEnvoye', $data->smsEnvoye);
        }

        // Filtre par prescription requise
        if (isset($data->prescriptionRequise) && TypeVariable::is_not_null($data->prescriptionRequise)) {
            $query = $query
                ->andWhere('d.prescriptionRequise = :prescriptionRequise')
                ->setParameter('prescriptionRequise', $data->prescriptionRequise);
        }

        // Filtre par quantité minimale
        if (isset($data->quantiteMin) && TypeVariable::is_not_null($data->quantiteMin) && $data->quantiteMin) {
            $query = $query
                ->andWhere('d.quantite >= :quantiteMin')
                ->setParameter('quantiteMin', $data->quantiteMin);
        }

        // Filtre par quantité maximale
        if (isset($data->quantiteMax) && TypeVariable::is_not_null($data->quantiteMax) && $data->quantiteMax) {
            $query = $query
                ->andWhere('d.quantite <= :quantiteMax')
                ->setParameter('quantiteMax', $data->quantiteMax);
        }

        // Filtre par prix minimal
        if (isset($data->prixMin) && TypeVariable::is_not_null($data->prixMin) && $data->prixMin) {
            $query = $query
                ->andWhere('d.prixUnitaire >= :prixMin')
                ->setParameter('prixMin', $data->prixMin);
        }

        // Filtre par prix maximal
        if (isset($data->prixMax) && TypeVariable::is_not_null($data->prixMax) && $data->prixMax) {
            $query = $query
                ->andWhere('d.prixUnitaire <= :prixMax')
                ->setParameter('prixMax', $data->prixMax);
        }

        // Filtre par date de début
        if (isset($data->dateD) && TypeVariable::is_not_null($data->dateD) && $data->dateD) {
            $dateD = new \DateTime($this->type->trim($data->dateD));
             $timePart = $dateD->format('H:i:s');
            if ($timePart === '00:00:00') {
                $dateD->setTime(0, 0, 0); // début de journée
            }
        
            $query = $query
                ->andWhere('d.createdAt >= :dateD')
                ->setParameter('dateD', $dateD);
        }

        // Filtre par date de fin
        if (isset($data->dateF) && TypeVariable::is_not_null($data->dateF) && $data->dateF) {
                $dateF = new \DateTime($this->type->trim($data->dateF));
                $timePart = $dateF->format('H:i:s');
                if ($timePart === '00:00:00') {
                 $dateF->setTime(23, 59, 59); // fin de journée
                }
        
                $query = $query
                ->andWhere('d.createdAt <= :dateF')
                ->setParameter('dateF', $dateF);
        }

        // Filtre par année
        if (isset($data->annee) && TypeVariable::is_not_null($data->annee) && $data->annee) {
            $start = new \DateTime($data->annee . '-01-01');
            $end = new \DateTime($data->annee . '-12-31 23:59:59');

            $query = $query
                ->andWhere('d.createdAt >= :start')
                ->andWhere('d.createdAt <= :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        // Filtre demandes en retard (plus de 7 jours et non récupérées/annulées)
        if (isset($data->enRetard) && TypeVariable::is_not_null($data->enRetard) && $data->enRetard) {
            $dateLimit = new \DateTime('-7 days');
            $query = $query
                ->andWhere('d.createdAt < :dateLimit')
                ->andWhere('d.statut NOT IN (:statutsFinis)')
                ->setParameter('dateLimit', $dateLimit)
                ->setParameter('statutsFinis', [Demande::STATUT_RECUPERE, Demande::STATUT_ANNULE]);
        }

        if(isset($data->count) && TypeVariable::is_not_null($data->count) && (int)$data->count !== 0){
            $query = $query
                ->setMaxResults($data->count)
            ;
        }

        $query = $query->orderBy('d.createdAt', 'DESC');

        return $query->getQuery()->getResult();
    }

 

    /**
     * Filtre des demandes
     * @param object $data
     * @param User|null $user
     * @return Demande[]
     */
    public function findFilter(object $data, User $user = null)
    {
        $query = $this->createQueryBuilder('d')
            ->leftJoin('d.patient', 'p')
            ->leftJoin('d.pharmacie', 'ph')
            ->leftJoin('d.pharmacien', 'phc')
            ->leftJoin('d.medicaments', 'm');

        // Filtre par référence
        if (isset($data->reference) && TypeVariable::is_not_null($data->reference) && $data->reference) {
            $query = $query
                ->andWhere('d.reference LIKE :reference')
                ->setParameter('reference', '%' . $data->reference . '%');
        }

        // Filtre par statut
        if (isset($data->statut) && TypeVariable::is_not_null($data->statut) && $data->statut !== null) {
            $query = $query
                ->andWhere('d.statut = :statut')
                ->setParameter('statut', $data->statut);
        }

        // Filtre par multiple statuts
        if (isset($data->statuts) && is_array($data->statuts) && count($data->statuts) > 0) {
            $query = $query
                ->andWhere('d.statut IN (:statuts)')
                ->setParameter('statuts', $data->statuts);
        }

        // Filtre par patient
        if (isset($data->patient) && TypeVariable::is_not_null($data->patient) && $data->patient) {
            $query = $query
                ->andWhere('p.nom LIKE :patient OR p.prenom LIKE :patient OR p.telephone LIKE :patient')
                ->setParameter('patient', '%' . $data->patient . '%');
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

        // Filtre par médicament
        if (isset($data->medicament) && TypeVariable::is_not_null($data->medicament) && $data->medicament) {
            $query = $query
                ->andWhere('m.nom LIKE :medicament')
                ->setParameter('medicament', '%' . $data->medicament . '%');
        }

        // Filtre par SMS envoyé
        if (isset($data->smsEnvoye) && TypeVariable::is_not_null($data->smsEnvoye)) {
            $query = $query
                ->andWhere('d.smsEnvoye = :smsEnvoye')
                ->setParameter('smsEnvoye', $data->smsEnvoye);
        }

        // Filtre par statut SMS
        if (isset($data->smsStatut) && TypeVariable::is_not_null($data->smsStatut) && $data->smsStatut) {
            $query = $query
                ->andWhere('d.smsStatut = :smsStatut')
                ->setParameter('smsStatut', $data->smsStatut);
        }

        // Filtre par date de début
        if (isset($data->dateD) && TypeVariable::is_not_null($data->dateD) && $data->dateD) {
            $dateD = new \DateTime($this->type->trim($data->dateD));
            $query = $query
                ->andWhere('d.createdAt >= :dateD')
                ->setParameter('dateD', $dateD);
        }

        // Filtre par date de fin
        if (isset($data->dateF) && TypeVariable::is_not_null($data->dateF) && $data->dateF) {
            $dateF = new \DateTime($this->type->trim($data->dateF));
            $query = $query
                ->andWhere('d.createdAt <= :dateF')
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
                ->andWhere('d.createdAt <= :fin')
                ->setParameter('fin', $fin);
        }

        // Filtre par année
        if (isset($data->annee) && TypeVariable::is_not_null($data->annee) && $data->annee) {
            $start = new \DateTime($data->annee . '-01-01');
            $end = new \DateTime($data->annee . '-12-31 23:59:59');

            $query = $query
                ->andWhere('d.createdAt >= :start')
                ->andWhere('d.createdAt <= :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        // Filtre par date de disponibilité
        if (isset($data->dateDisponibiliteD) && TypeVariable::is_not_null($data->dateDisponibiliteD) && $data->dateDisponibiliteD) {
            $dateD = new \DateTime($this->type->trim($data->dateDisponibiliteD));
            $query = $query
                ->andWhere('d.dateDisponibilite >= :dateDisponibiliteD')
                ->setParameter('dateDisponibiliteD', $dateD);
        }

        if (isset($data->dateDisponibiliteF) && TypeVariable::is_not_null($data->dateDisponibiliteF) && $data->dateDisponibiliteF) {
            $dateF = new \DateTime($this->type->trim($data->dateDisponibiliteF));
            $query = $query
                ->andWhere('d.dateDisponibilite <= :dateDisponibiliteF')
                ->setParameter('dateDisponibiliteF', $dateF);
        }

        // Filtre par date de récupération
        if (isset($data->dateRecuperationD) && TypeVariable::is_not_null($data->dateRecuperationD) && $data->dateRecuperationD) {
            $dateD = new \DateTime($this->type->trim($data->dateRecuperationD));
            $query = $query
                ->andWhere('d.dateRecuperation >= :dateRecuperationD')
                ->setParameter('dateRecuperationD', $dateD);
        }

        if (isset($data->dateRecuperationF) && TypeVariable::is_not_null($data->dateRecuperationF) && $data->dateRecuperationF) {
            $dateF = new \DateTime($this->type->trim($data->dateRecuperationF));
            $query = $query
                ->andWhere('d.dateRecuperation <= :dateRecuperationF')
                ->setParameter('dateRecuperationF', $dateF);
        }

        $query = $query->orderBy('d.createdAt', 'DESC');

        return $query->getQuery()->getResult();
    }

    /**
     * Trouver les demandes par date et statut
     * @param Pharmacie|null $pharmacie
     * @param \DateTime|null $dateDebut
     * @param \DateTime|null $dateFin
     * @param string|null $statut
     * @return Demande[]
     */
    public function findByDate(?Pharmacie $pharmacie, ?\DateTime $dateDebut, ?\DateTime $dateFin, ?string $statut = null)
    {
        $query = $this->createQueryBuilder('d');

        if ($pharmacie) {
            $query = $query
                ->andWhere('d.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        if ($dateDebut) {
            $query = $query
                ->andWhere('d.createdAt >= :dateDebut')
                ->setParameter('dateDebut', $dateDebut);
        }

        if ($dateFin) {
            $query = $query
                ->andWhere('d.createdAt <= :dateFin')
                ->setParameter('dateFin', $dateFin);
        }

        if ($statut) {
            $query = $query
                ->andWhere('d.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return $query
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les demandes par patient
     * @param object $patient
     * @return Demande[]
     */
    public function findByPatient($patient)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par statut
     */
    public function getStatsByStatut(?Pharmacie $pharmacie = null): array
    {
        $query = $this->createQueryBuilder('d')
            ->select('d.statut, COUNT(d.id) as total')
            ->groupBy('d.statut');

        if ($pharmacie) {
            $query = $query
                ->andWhere('d.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Demandes en attente depuis plus de X jours
     */
    public function findEnAttenteLongue(int $jours = 7, ?Pharmacie $pharmacie = null): array
    {
        $dateLimit = new \DateTime("-{$jours} days");
        
        $query = $this->createQueryBuilder('d')
            ->andWhere('d.statut = :statut')
            ->andWhere('d.createdAt < :dateLimit')
            ->setParameter('statut', Demande::STATUT_EN_ATTENTE)
            ->setParameter('dateLimit', $dateLimit);

        if ($pharmacie) {
            $query = $query
                ->andWhere('d.pharmacie = :pharmacie')
                ->setParameter('pharmacie', $pharmacie);
        }

        return $query
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

   
}