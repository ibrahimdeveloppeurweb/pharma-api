<?php

namespace App\Manager\Gestion;

use App\Utils\TypeUtil;
use App\Helpers\ExcelHelper;
use App\Entity\Security\User;
use App\Entity\Gestion\Patient;
use App\Exception\ExceptionApi;
use App\Manager\Security\UserManager;
use App\Manager\Extra\UploaderManager;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\Extra\FileRepository;
use App\Repository\Security\UserRepository;
use App\Repository\Gestion\PatientRepository;
use App\Repository\Gestion\PharmacieRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PatientManager
{
    private ?User $user;
    private EntityManagerInterface $em;
    private UserManager $userManager;
    private UserRepository $userRepository;
    private PatientRepository $patientRepository;
    private PharmacieRepository $pharmacieRepository;

    public function __construct(
        UserManager $userManager,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        PatientRepository $patientRepository,
        PharmacieRepository $pharmacieRepository,
        TokenStorageInterface $tokenStorage
    ) {
        $this->em = $em;
        $this->userManager = $userManager;
        $this->userRepository = $userRepository;
        $this->patientRepository = $patientRepository;
        $this->pharmacieRepository = $pharmacieRepository;
        
        if ($tokenStorage->getToken()) {
            $this->user = $tokenStorage->getToken()->getUser();
        }
    }

    /**
     * Créer un patient
     * @param object $data
     * @throws ExceptionApi
     * @return Patient
     */
    public function create(object $data): Patient
    {
        $this->requirements($data);
        
        $patient = new Patient();
        $this->add($data, $patient);
        $this->upload($data, $patient);
        
        $this->em->flush();
        return $patient;
    }

    /**
     * Mettre à jour un patient
     * @param object $data
     * @param string $uuid
     * @throws ExceptionApi
     * @return Patient
     */
    public function update(object $data, string $uuid): Patient
    {
        $patient = $this->patientRepository->findOneByUuid($uuid);
        
        if (!$patient) {
            throw new ExceptionApi(
                "Patient introuvable.",
                ["msg" => "Patient introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Supprimer les fichiers marqués pour suppression
        // if (isset($data->fileDelete) && $data->fileDelete && count($data->fileDelete) > 0) {
        //     foreach ($data->fileDelete as $item) {
        //         $file = $this->fileRepository->findOneById($item);
        //         if (!$file) {
        //             throw new ExceptionApi(
        //                 "Impossible de supprimer le fichier.",
        //                 ["msg" => "Impossible de supprimer le fichier."],
        //                 Response::HTTP_UNPROCESSABLE_ENTITY
        //             );
        //         }
        //         $this->uploaderManager->deleteFile($file);
        //         $this->em->remove($file);
        //     }
        // }

        $this->add($data, $patient);
        $this->upload($data, $patient);
        
        $this->em->flush();
        return $patient;
    }

    /**
     * Supprimer un patient
     * @param string $uuid
     * @throws ExceptionApi
     * @return void
     */
    public function delete(string $uuid): void
    {
        $patient = $this->patientRepository->findOneByUuid($uuid);
        
        if (!$patient) {
            throw new ExceptionApi(
                "Patient introuvable.",
                ["msg" => "Patient introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Vérifier s'il a des demandes en cours
        if ($patient->hasPendingDemandes()) {
            throw new ExceptionApi(
                "Impossible de supprimer ce patient, il a des demandes en attente.",
                ["msg" => "Impossible de supprimer ce patient, il a des demandes en attente."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->em->remove($patient);
        $this->em->flush();
    }

    /**
     * Ajouter/Modifier les données d'un patient
     * @param object $data
     * @param Patient $patient
     * @return void
     */
    private function add(object $data, Patient $patient): void
    {
        $patient->setNom($data->nom);
        $patient->setPrenom($data->prenom);
        $patient->setTelephone($data->telephone);
        $patient->setEmail($data->email ?? null);
        $patient->setAdresse($data->adresse ?? null);
        $patient->setVille($data->ville ?? null);
        $patient->setCodePostal($data->codePostal ?? null);

        // Date de naissance
        if (isset($data->dateNaissance) && $data->dateNaissance) {
            $dateNaissance = $data->dateNaissance instanceof \DateTimeInterface 
                ? $data->dateNaissance 
                : new \DateTime($data->dateNaissance);
            $patient->setDateNaissance($dateNaissance);
        }

        // Associer le pharmacien si fourni
        if (isset($data->pharmacienUuid) && $data->pharmacienUuid) {
            $pharmacien = $this->userRepository->findOneByUuid($data->pharmacienUuid);
            if (!$pharmacien) {
                throw new ExceptionApi(
                    "Pharmacien introuvable.",
                    ["msg" => "Pharmacien introuvable."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            $patient->setPharmacien($pharmacien);
        }

        // Associer la pharmacie si fournie
        if (isset($data->pharmacieUuid) && $data->pharmacieUuid) {
            $pharmacie = $this->pharmacieRepository->findOneByUuid($data->pharmacieUuid);
            if (!$pharmacie) {
                throw new ExceptionApi(
                    "Pharmacie introuvable.",
                    ["msg" => "Pharmacie introuvable."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            $patient->setPharmacie($pharmacie);
        }

        $this->em->persist($patient);
    }

    /**
     * Upload de fichiers
     * @param object $data
     * @param Patient $patient
     * @return void
     */
    private function upload(object $data, Patient $patient): void
    {
        if (isset($data->folders) && $data->folders) {
            foreach ($data->folders as $item) {
             //   $this->uploaderManager->handleFileUpload($item, $patient);
            }
        }
    }

    /**
     * Validation des données
     * @param object $data
     * @throws ExceptionApi
     * @return void
     */
    private function requirements(object $data): void
    {
        if (!isset($data->nom) || !TypeUtil::is_not_null($data->nom)) {
            throw new ExceptionApi(
                "Le nom du patient est requis.",
                ["msg" => "Le nom du patient est requis."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!isset($data->prenom) || !TypeUtil::is_not_null($data->prenom)) {
            throw new ExceptionApi(
                "Le prénom du patient est requis.",
                ["msg" => "Le prénom du patient est requis."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!isset($data->telephone) || !TypeUtil::is_not_null($data->telephone)) {
            throw new ExceptionApi(
                "Le téléphone est requis.",
                ["msg" => "Le téléphone est requis."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Validation email si fourni
        if (isset($data->email) && $data->email && !filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            throw new ExceptionApi(
                "L'email est invalide.",
                ["msg" => "L'email est invalide."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Validation date de naissance
        if (isset($data->dateNaissance) && $data->dateNaissance) {
            try {
                $date = new \DateTime($data->dateNaissance);
                $now = new \DateTime();
                if ($date > $now) {
                    throw new ExceptionApi(
                        "La date de naissance ne peut pas être dans le futur.",
                        ["msg" => "La date de naissance ne peut pas être dans le futur."],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
            } catch (\Exception $e) {
                throw new ExceptionApi(
                    "Format de date de naissance invalide.",
                    ["msg" => "Format de date de naissance invalide."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }
    }

    /**
     * Génère les en-têtes pour l'export Excel
     */
    public function getExcelHeaders(): array
    {
        return [
            'Code',
            'Nom',
            'Prénom',
            'Téléphone',
            'Email',
            'Date de naissance',
            'Adresse',
            'Code Postal',
            'Ville',
            'Pharmacie',
            'Pharmacien',
            'Total demandes',
            'Demandes en attente',
            'Créé le'
        ];
    }

    /**
     * Prépare les données pour l'export Excel
     */
    public function prepareExcelData(array $patients): array
    {
        $data = [];

        foreach ($patients as $patient) {
            $data[] = [
                $patient->getUuid(),
                $patient->getNom(),
                $patient->getPrenom(),
                $patient->getTelephone(),
                $patient->getEmail() ?? '',
                $patient->getDateNaissance() ? $patient->getDateNaissance()->format('d/m/Y') : '',
                $patient->getAdresse() ?? '',
                $patient->getCodePostal() ?? '',
                $patient->getVille() ?? '',
                $patient->getPharmacie() ? $patient->getPharmacie()->getNom() : '',
                $patient->getPharmacien() ? $patient->getPharmacien()->getNom() : '',
                $patient->getTotalDemandes(),
                $patient->getDemandesEnAttente(),
                $patient->getCreatedAt() ? $patient->getCreatedAt()->format('d/m/Y H:i') : ''
            ];
        }

        return $data;
    }

    /**
     * Prépare les données pour l'export PDF
     */
    public function prepareExportData(array $patients): array
    {
        $data = [];

        foreach ($patients as $patient) {
            $data[] = [
                'uuid' => $patient->getUuid(),
                'nom' => $patient->getNom(),
                'prenom' => $patient->getPrenom(),
                'nom_complet' => $patient->getNomComplet(),
                'telephone' => $patient->getTelephone(),
                'email' => $patient->getEmail() ?? '',
                'date_naissance' => $patient->getDateNaissance() ? $patient->getDateNaissance()->format('d/m/Y') : '',
                'adresse' => $patient->getAdresse() ?? '',
                'code_postal' => $patient->getCodePostal() ?? '',
                'ville' => $patient->getVille() ?? '',
                'pharmacie' => $patient->getPharmacie() ? $patient->getPharmacie()->getNom() : '',
                'pharmacien' => $patient->getPharmacien() ? $patient->getPharmacien()->getNom() : '',
                'total_demandes' => $patient->getTotalDemandes(),
                'demandes_en_attente' => $patient->getDemandesEnAttente(),
                'demandes_disponibles' => $patient->getDemandesDisponibles(),
                'demandes_notifiees' => $patient->getDemandesNotifiees(),
                'demandes_recuperees' => $patient->getDemandesRecuperees(),
                'has_pending' => $patient->hasPendingDemandes(),
                'created_at' => $patient->getCreatedAt() ? $patient->getCreatedAt()->format('d/m/Y H:i:s') : '',
                'updated_at' => $patient->getUpdatedAt() ? $patient->getUpdatedAt()->format('d/m/Y H:i:s') : ''
            ];
        }

        return $data;
    }

    /**
     * Génère les statistiques pour l'export
     */
    public function generateExportStats(array $patients): array
    {
        $totalDemandes = 0;
        $totalEnAttente = 0;
        $patientsAvecEmail = 0;
        $patientsAvecPharmacie = 0;

        foreach ($patients as $patient) {
            $totalDemandes += $patient->getTotalDemandes();
            $totalEnAttente += $patient->getDemandesEnAttente();
            if ($patient->getEmail()) {
                $patientsAvecEmail++;
            }
            if ($patient->getPharmacie()) {
                $patientsAvecPharmacie++;
            }
        }

        return [
            'total_patients' => count($patients),
            'total_demandes' => $totalDemandes,
            'total_demandes_en_attente' => $totalEnAttente,
            'patients_avec_email' => $patientsAvecEmail,
            'patients_avec_pharmacie' => $patientsAvecPharmacie,
            'moyenne_demandes_par_patient' => count($patients) > 0 ? round($totalDemandes / count($patients), 2) : 0
        ];
    }

    /**
     * Rechercher des patients par critères
     */
    public function searchPatients(object $criteria): array
    {
        $qb = $this->patientRepository->createQueryBuilder('p');

        if (isset($criteria->nom) && $criteria->nom) {
            $qb->andWhere('p.nom LIKE :nom')
               ->setParameter('nom', '%' . $criteria->nom . '%');
        }

        if (isset($criteria->prenom) && $criteria->prenom) {
            $qb->andWhere('p.prenom LIKE :prenom')
               ->setParameter('prenom', '%' . $criteria->prenom . '%');
        }

        if (isset($criteria->telephone) && $criteria->telephone) {
            $qb->andWhere('p.telephone LIKE :telephone')
               ->setParameter('telephone', '%' . $criteria->telephone . '%');
        }

        if (isset($criteria->pharmacieUuid) && $criteria->pharmacieUuid) {
            $qb->join('p.pharmacie', 'ph')
               ->andWhere('ph.uuid = :pharmacieUuid')
               ->setParameter('pharmacieUuid', $criteria->pharmacieUuid);
        }

        $qb->orderBy('p.nom', 'ASC')
           ->addOrderBy('p.prenom', 'ASC');

        return $qb->getQuery()->getResult();
    }
}