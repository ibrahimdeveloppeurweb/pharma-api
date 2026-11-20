<?php

namespace App\Manager\Gestion;

use App\Utils\TypeUtil;
use App\Helpers\ExcelHelper;
use App\Entity\Security\User;
use App\Entity\Gestion\Demande;
use App\Entity\Gestion\DemandeMedicament;
use App\Entity\Gestion\Patient;
use App\Entity\Gestion\Medicament;
use App\Entity\Gestion\Pharmacie;
use App\Events\SmsServiceEvent;
use App\Exception\ExceptionApi;
use App\Manager\Security\UserManager;
use App\Manager\Extra\UploaderManager;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\Extra\FileRepository;
use App\Repository\Extra\MedecineRepository;
use App\Repository\Security\UserRepository;
use App\Repository\Gestion\DemandeRepository;
use App\Repository\Gestion\PatientRepository;
use App\Repository\Gestion\MedicamentRepository;
use App\Repository\Gestion\PharmacieRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DemandeManager
{
    private ?User $user;
    private EntityManagerInterface $em;
    // private ExcelHelper $excelHelper;
    private UserManager $userManager;
    private UserRepository $userRepository;
    // private UploaderManager $uploaderManager;
    // private FileRepository $fileRepository;
    private DemandeRepository $demandeRepository;
    private PatientRepository $patientRepository;
    private MedicamentRepository $medicamentRepository;
    private PharmacieRepository $pharmacieRepository;
    private MedecineRepository $medecineRepository;
    private SmsServiceEvent $smsServiceEvent;

    public function __construct(
        UserManager $userManager,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        DemandeRepository $demandeRepository,
        PatientRepository $patientRepository,
        MedicamentRepository $medicamentRepository,
        PharmacieRepository $pharmacieRepository,
        TokenStorageInterface $tokenStorage,
        MedecineRepository $medecineRepository,
        SmsServiceEvent $smsServiceEvent
    ) {
        $this->em = $em;
       $this->userManager = $userManager;
        $this->userRepository = $userRepository;
        $this->demandeRepository = $demandeRepository;
        $this->patientRepository = $patientRepository;
        $this->medicamentRepository = $medicamentRepository;
        $this->pharmacieRepository = $pharmacieRepository;
         $this->medecineRepository = $medecineRepository;
        $this->smsServiceEvent = $smsServiceEvent;
        
        if ($tokenStorage->getToken()) {
            $this->user = $tokenStorage->getToken()->getUser();
        }
    }

    /**
     * Créer une demande
     * @param object $data
     * @throws ExceptionApi
     * @return Demande
     */
    public function create(object $data, Pharmacie $pharmacie): Demande
    {
        $this->requirements($data);
        $demande = new Demande();
        $this->add($data, $demande,$pharmacie);
        
        $this->em->flush();
        return $demande;
    }

    /**
     * Mettre à jour une demande
     * @param object $data
     * @param string $uuid
     * @throws ExceptionApi
     * @return Demande
     */
    public function update(object $data, string $uuid): Demande
    {
        $demande = $this->demandeRepository->findOneByUuid($uuid);
        
        if (!$demande) {
            throw new ExceptionApi(
                "Demande introuvable.",
                ["msg" => "Demande introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->add($data, $demande);
        $this->upload($data, $demande);
        
        $this->em->flush();
        return $demande;
    }

    /**
     * Changer le statut d'une demande
     * @param string $uuid
     * @param string $nouveauStatut
     * @throws ExceptionApi
     * @return Demande
     */
    public function changeStatut(string $uuid, string $nouveauStatut = Demande::STATUT_RECUPERE): Demande
    {
        $demande = $this->demandeRepository->findOneByUuid($uuid);
      
        if (!$demande) {
            throw new ExceptionApi(
                "Demande introuvable.",
                ["msg" => "Demande introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
   
        // Valider le statut
        if (!in_array($nouveauStatut, Demande::getAvailableStatuts())) {
            throw new ExceptionApi(
                "Statut invalide.",
                ["msg" => "Statut invalide. Valeurs acceptées: " . implode(', ', Demande::getAvailableStatuts())],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
     
        // Vérifier les transitions de statut autorisées
        $this->validateStatutTransition($demande->getStatut(), $nouveauStatut);
      
        $demande->setStatut($nouveauStatut);
        $this->em->persist($demande);
        $this->em->flush();

        return $demande;
    }


    /**
     * Notifier le patient
     * @param string $uuid
     * @throws ExceptionApi
     * @return Demande
     */
    public function notifierPatient(string $uuid , Pharmacie $pharmacie): Demande
    {
        $demande = $this->demandeRepository->findOneByUuid($uuid);
        if (!$demande) {
            throw new ExceptionApi(
                "Demande introuvable.",
                ["msg" => "Demande introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $telephone = $demande->getPatient()->getTelephone();
        $message = sprintf(
            "Bonjour %s, votre demande de medicament est disponible a la pharmacie.",
            $demande->getPatient()->getNom()
        );
        $telephone = "+225".$telephone;
        
        $result = $this->smsServiceEvent->sendSms($telephone, $message);
        
        if (empty($result['success']) || $result['success'] === false) {
            $errorMessage = $result['error'] ?? ($result['response']['error'] ?? 'Erreur inconnue lors de l’envoi du SMS.');
            throw new ExceptionApi("Échec d’envoi du SMS.", ["msg" => $errorMessage], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $demande->setSmsEnvoye(true);
        $demande->setSmsStatut('envoye');
        $demande->setTentativesNotification($demande->getTentativesNotification() + 1);
        $demande->setStatut(Demande::STATUT_NOTIFIE);

        $this->em->persist($demande);
        $this->em->flush();

        return $demande;
    }

    /**
     * Renotifier le patient (permet plusieurs envois)
     * @param string $uuid
     * @throws ExceptionApi
     * @return Demande
     */
    public function renotifierPatient(string $uuid , Pharmacie $pharmacie): Demande
    {
        $demande = $this->demandeRepository->findOneByUuid($uuid);
        if (!$demande) {
            throw new ExceptionApi("Demande introuvable.", ["msg" => "Demande introuvable."], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $telephone = $demande->getPatient()->getTelephone();
        $message = sprintf(
            "Bonjour %s, ceci est un rappel : votre demande de medicament est disponible a la pharmacie.",
            $demande->getPatient()->getNom()
        );
        $telephone = "+225".$telephone;
        
        $result = $this->smsServiceEvent->sendSms($telephone, $message);
        
        if (empty($result['success']) || $result['success'] === false) {
            $errorMessage = $result['error'] ?? ($result['response']['error'] ?? 'Erreur inconnue lors de l’envoi du SMS.');
            throw new ExceptionApi("Échec d’envoi du SMS de rappel.", ["msg" => $errorMessage], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $demande->setTentativesNotification($demande->getTentativesNotification() + 1);
        $demande->setStatut(Demande::STATUT_NOTIFIE);

        $this->em->persist($demande);
        $this->em->flush();

        return $demande;
    }


    /**
     * Marquer une demande comme récupérée
     * @param string $uuid
     * @throws ExceptionApi
     * @return Demande
     */
    public function marquerRecuperee(string $uuid): Demande
    {
        return $this->changeStatut($uuid, Demande::STATUT_RECUPERE);
    }

    /**
     * Annuler une demande
     * @param string $uuid
     * @param string|null $motif
     * @throws ExceptionApi
     * @return Demande
     */
    public function annuler(string $uuid, ?string $motif = null): Demande
    {
        $demande = $this->demandeRepository->findOneByUuid($uuid);
        
        if (!$demande) {
            throw new ExceptionApi(
                "Demande introuvable.",
                ["msg" => "Demande introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($demande->getStatut() === Demande::STATUT_RECUPERE) {
            throw new ExceptionApi(
                "Impossible d'annuler une demande déjà récupérée.",
                ["msg" => "Impossible d'annuler une demande récupérée."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Si était disponible, réincrémenter le stock
        if ($demande->getStatut() === Demande::STATUT_DISPONIBLE) {
            $medicament = $demande->getMedicament();
            if ($medicament) {
                $medicament->incrementStock($demande->getQuantite());
            }
        }

        if ($motif) {
            $notes = $demande->getNotes() ? $demande->getNotes() . "\n\n" : "";
            $demande->setNotes($notes . "Motif d'annulation: " . $motif);
        }

        $demande->setStatut(Demande::STATUT_ANNULE);
        $this->em->persist($demande);
        $this->em->flush();

        return $demande;
    }

    /**
     * Supprimer une demande
     * @param string $uuid
     * @throws ExceptionApi
     * @return void
     */
    public function delete(string $uuid): void
    {
        $demande = $this->demandeRepository->findOneByUuid($uuid);
        
        if (!$demande) {
            throw new ExceptionApi(
                "Demande introuvable.",
                ["msg" => "Demande introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Vérifier qu'on peut supprimer
        if ($demande->getStatut() === Demande::STATUT_RECUPERE) {
            throw new ExceptionApi(
                "Impossible de supprimer une demande récupérée.",
                ["msg" => "Suppression interdite."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->em->remove($demande);
        $this->em->flush();
    }

    /**
     * Ajouter/Modifier les données d'une demande
     * @param object $data
     * @param Demande $demande
     * @return void
     */
        private function add(object $data, Demande $demande, Pharmacie $pharmacie = null): void
        {

        

            $demande->setReference('DEM-' . date('Ymd-His'));
            $demande->setPharmacie($pharmacie);
            $demande->setModePaiement($data->mode_paiement);

        // Patient (obligatoire)
            if (isset($data->telephone_patient)) {
                $patient = $this->patientRepository->findOneBy(['telephone' => $data->telephone_patient,'pharmacie' => $pharmacie]);
                    $patient =  $patient?  $patient : new Patient();
                    $patient->setNom($data->nom_patient);
                    $patient->setPharmacie($pharmacie);
                    $patient->setTelephone($data->telephone_patient);
                    $this->em->persist($patient);
                    $demande->setPatient($patient);
            }
        

            if (isset($data->medicaments) && is_array($data->medicaments)) {
            // Parcourir les médicaments choisis
            foreach ($data->medicaments as $item) {

                  // Pour update : on peut nettoyer les anciens médicaments si besoin
                    if ($demande->getId()) {
                        foreach ($demande->getDemandeMedicaments() as $demandeMed) {
                            $this->em->remove($demandeMed);
                        }
                        $demande->getDemandeMedicaments()->clear();
                    }
                
                $medecine = $this->medecineRepository->findOneByUuid($item->medicament);
                if (!$medecine) continue;
                
                // Vérifier si le médicament existe déjà pour cette pharmacie
                $med = $this->medicamentRepository->findOneBy(['medecine' => $medecine,'pharmacie' => $pharmacie]);
            

                // Si n'existe pas, créer le médicament dans le stock de la pharmacie
                if (!$med) {
                    
                    $med = new Medicament();
                    $med->setNom($medecine->getNom());
                    $med->setPharmacie($pharmacie);
                    $med->setMedecine($medecine);
                    $this->em->persist($med);
                    
                }
            
                $demandeMed = new DemandeMedicament();
                $demandeMed->setMedicament($med);
                $demandeMed->setQuantite($item->quantite);
                $demandeMed->setPrix($item->prix);
    
                // Ajouter le médicament à la demande
                $demande->addDemandeMedicament($demandeMed);
            }
            }
        
            // Pharmacien
            $demande->setPharmacien($this->user);
            
            $this->em->persist($demande);
        }

    /**
     * Upload de fichiers (ordonnance, etc.)
     * @param object $data
     * @param Demande $demande
     * @return void
     */
    private function upload(object $data, Demande $demande): void
    {
        if (isset($data->folders) && $data->folders) {
            foreach ($data->folders as $item) {
                // $this->uploaderManager->handleFileUpload($item, $demande);
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
        // 🧍 Vérification des informations du patient
        if (empty($data->nom_patient)) {
            throw new ExceptionApi(
                "Le nom du patient est requis.",
                ["msg" => "Le nom du patient est requis."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (empty($data->telephone_patient)) {
            throw new ExceptionApi(
                "Le téléphone du patient est requis.",
                ["msg" => "Le téléphone du patient est requis."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // 💊 Vérification de la liste des médicaments
        if (!isset($data->medicaments) || !is_array($data->medicaments) || count($data->medicaments) === 0) {
            throw new ExceptionApi(
                "La liste des médicaments est requise.",
                ["msg" => "Aucun médicament n’a été fourni."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        foreach ($data->medicaments as $index => $medicament) {
            // Validation du nom du médicament
            if (empty($medicament->medicament)) {
                throw new ExceptionApi(
                    "Le nom du médicament à l’index $index est requis.",
                    ["msg" => "Nom du médicament manquant."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            // Validation quantité
            if (
                !isset($medicament->quantite) ||
                !is_numeric($medicament->quantite) ||
                intval($medicament->quantite) <= 0
            ) {
                throw new ExceptionApi(
                    "La quantité du médicament '{$medicament->medicament}' doit être un nombre positif.",
                    ["msg" => "Quantité invalide pour le médicament."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            // Validation prix
            if (
                isset($medicament->prix) &&
                (!is_numeric($medicament->prix) || floatval($medicament->prix) < 0)
            ) {
                throw new ExceptionApi(
                    "Le prix du médicament '{$medicament->medicament}' doit être un nombre positif.",
                    ["msg" => "Prix invalide pour le médicament."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        // 💰 Vérification du mode de paiement
        if (empty($data->mode_paiement)) {
            throw new ExceptionApi(
                "Le mode de paiement est requis.",
                ["msg" => "Le mode de paiement est requis."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }


    /**
     * Valider la transition de statut
     */
    private function validateStatutTransition(string $ancienStatut, string $nouveauStatut): void
    {
        $transitionsAutorisees = [
            Demande::STATUT_EN_ATTENTE => [Demande::STATUT_DISPONIBLE, Demande::STATUT_ANNULE],
            Demande::STATUT_DISPONIBLE => [Demande::STATUT_NOTIFIE, Demande::STATUT_ANNULE],
            Demande::STATUT_NOTIFIE => [Demande::STATUT_NOTIFIE, Demande::STATUT_RECUPERE, Demande::STATUT_ANNULE],
            Demande::STATUT_RECUPERE => [],
            Demande::STATUT_ANNULE => [],
        ];

        if (!isset($transitionsAutorisees[$ancienStatut]) || 
            !in_array($nouveauStatut, $transitionsAutorisees[$ancienStatut])) {
            throw new ExceptionApi(
                "Transition de statut non autorisée: {$ancienStatut} -> {$nouveauStatut}",
                ["msg" => "Transition de statut non autorisée."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Récupérer les demandes en retard
     */
    public function getDemandesEnRetard(): array
    {
        return $this->demandeRepository->createQueryBuilder('d')
            ->where('d.statut IN (:statuts)')
            ->andWhere('d.createdAt < :dateLimit')
            ->setParameter('statuts', [
                Demande::STATUT_EN_ATTENTE,
                Demande::STATUT_DISPONIBLE,
                Demande::STATUT_NOTIFIE
            ])
            ->setParameter('dateLimit', new \DateTime('-7 days'))
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Génère les en-têtes pour l'export Excel
     */
    public function getExcelHeaders(): array
    {
        return [
            'Code',
            'Patient',
            'Médicament',
            'Quantité',
            'Statut',
            'Prix Unitaire',
            'Prix Total',
            'Prescription Requise',
            'Date Création',
            'Date Disponibilité',
            'Date Notification',
            'Date Récupération',
            'Délai (jours)',
            'En Retard',
            'SMS Envoyé',
            'Pharmacie',
            'Notes'
        ];
    }

    /**
     * Prépare les données pour l'export Excel
     */
    public function prepareExcelData(array $demandes): array
    {
        $data = [];

        foreach ($demandes as $demande) {
            $data[] = [
                $demande->getUuid(),
                $demande->getPatient() ? $demande->getPatient()->getNomComplet() : '',
                $demande->getMedicament() ? $demande->getMedicament()->getNomComplet() : '',
                $demande->getQuantite(),
                $demande->getStatutLibelle(),
                $demande->getPrixUnitaire() ?? '',
                $demande->getPrixTotal() ?? '',
                $demande->isPrescriptionRequise() ? 'Oui' : 'Non',
                $demande->getCreatedAt() ? $demande->getCreatedAt()->format('d/m/Y H:i') : '',
                $demande->getDateDisponibilite() ? $demande->getDateDisponibilite()->format('d/m/Y H:i') : '',
                $demande->getDateNotification() ? $demande->getDateNotification()->format('d/m/Y H:i') : '',
                $demande->getDateRecuperation() ? $demande->getDateRecuperation()->format('d/m/Y H:i') : '',
                $demande->getDelaiAttenteJours() ?? '',
                $demande->isEnRetard() ? 'Oui' : 'Non',
                $demande->isSmsEnvoye() ? 'Oui' : 'Non',
                $demande->getPharmacie() ? $demande->getPharmacie()->getNom() : '',
                $demande->getNotes() ?? ''
            ];
        }

        return $data;
    }

    /**
     * Génère les statistiques pour l'export
     */
    public function generateExportStats(array $demandes): array
    {
        $stats = [
            'total' => count($demandes),
            'en_attente' => 0,
            'disponible' => 0,
            'notifie' => 0,
            'recupere' => 0,
            'annule' => 0,
            'en_retard' => 0,
            'total_prix' => '0.00'
        ];

        foreach ($demandes as $demande) {
            switch ($demande->getStatut()) {
                case Demande::STATUT_EN_ATTENTE:
                    $stats['en_attente']++;
                    break;
                case Demande::STATUT_DISPONIBLE:
                    $stats['disponible']++;
                    break;
                case Demande::STATUT_NOTIFIE:
                    $stats['notifie']++;
                    break;
                case Demande::STATUT_RECUPERE:
                    $stats['recupere']++;
                    break;
                case Demande::STATUT_ANNULE:
                    $stats['annule']++;
                    break;
            }

            if ($demande->isEnRetard()) {
                $stats['en_retard']++;
            }

            if ($demande->getPrixTotal()) {
                $stats['total_prix'] = bcadd($stats['total_prix'], $demande->getPrixTotal(), 2);
            }
        }

        return $stats;
    }



  
    public function statistics(object $data, Pharmacie $pharmacie)
    {
        $datas = [];
        $en_attente = 0;
        $notifie = 0;
        $recupere = 0;
        $annule = 0;
        $demandes = $this->demandeRepository->findIndex($data,null,$pharmacie);

        $data->count = 4;
        $demandesQ = $this->demandeRepository->findIndex($data,null,$pharmacie);

        foreach ($demandes as $key => $item) {
            if ($item->getStatut() === Demande::STATUT_EN_ATTENTE) {
                $en_attente += 1;
            }
            if ($item->getStatut() === Demande::STATUT_NOTIFIE) {
                $notifie += 1;
            }
            if ($item->getStatut() === Demande::STATUT_RECUPERE) {
                $recupere += 1;
            }
            if ($item->getStatut() === Demande::STATUT_ANNULE) {
                $annule += 1;
            }
        }
         
        $datas['widget']['total'] = (count($demandes));
         $datas['data'] =   $data->dateF;
        $datas['widget']['en_attente'] = $en_attente;
        $datas['widget']['notifie'] = $notifie;
        $datas['widget']['recupere'] = $recupere;
        $datas['widget']['annule'] = $annule;
        $datas['demandes'] = $demandesQ;
       
      
        return $datas;
    }
}