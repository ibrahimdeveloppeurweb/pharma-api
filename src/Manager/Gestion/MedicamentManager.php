<?php

namespace App\Manager\Gestion;

use App\Utils\TypeUtil;
use App\Helpers\ExcelHelper;
use App\Entity\Security\User;
use App\Entity\Gestion\Medicament;
use App\Entity\Extra\Medecine;
use App\Entity\Gestion\Pharmacie;
use App\Exception\ExceptionApi;
use App\Manager\Security\UserManager;
use App\Manager\Extra\UploaderManager;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\Extra\FileRepository;
use App\Repository\Security\UserRepository;
use App\Repository\Gestion\MedicamentRepository;
use App\Repository\Gestion\PharmacieRepository;
use App\Repository\Extra\MedecineRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MedicamentManager
{
    private ?User $user;
    private EntityManagerInterface $em;
   // private ExcelHelper $excelHelper;
    private UserManager $userManager;
    private UserRepository $userRepository;
    // private UploaderManager $uploaderManager;
    // private FileRepository $fileRepository;
    private MedicamentRepository $medicamentRepository;
    private PharmacieRepository $pharmacieRepository;
    private MedecineRepository $medecineRepository;

    public function __construct(
        UserManager $userManager,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        MedicamentRepository $medicamentRepository,
        PharmacieRepository $pharmacieRepository,
        MedecineRepository $medecineRepository,
        TokenStorageInterface $tokenStorage
    ) {
        $this->em = $em;
        $this->userManager = $userManager;
        $this->userRepository = $userRepository;
        $this->medicamentRepository = $medicamentRepository;
        $this->pharmacieRepository = $pharmacieRepository;
        $this->medecineRepository = $medecineRepository;
        
        if ($tokenStorage->getToken()) {
            $this->user = $tokenStorage->getToken()->getUser();
        }
    }

    /**
     * Créer un médicament
     * @param object $data
     * @throws ExceptionApi
     * @return Medicament
     */
    public function create(object $data, Pharmacie $pharmacie): Medicament
    {
        $this->requirements($data);
        
        $medicament = new Medicament();
        $this->add($data, $medicament, $pharmacie);
        $this->upload($data, $medicament);
        
        $this->em->flush();
        return $medicament;
    }

    /**
     * Mettre à jour un médicament
     * @param object $data
     * @param string $uuid
     * @throws ExceptionApi
     * @return Medicament
     */
    public function update(object $data, string $uuid,Pharmacie $pharmacie): Medicament
    {
        $medicament = $this->medicamentRepository->findOneByUuid($uuid);
        
        if (!$medicament) {
            throw new ExceptionApi(
                "Médicament introuvable.",
                ["msg" => "Médicament introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->add($data, $medicament,$pharmacie);
       // $this->upload($data, $medicament);
        
        $this->em->flush();
        return $medicament;
    }

    /**
     * Supprimer un médicament
     * @param string $uuid
     * @throws ExceptionApi
     * @return void
     */
    public function delete(string $uuid): void
    {
        $medicament = $this->medicamentRepository->findOneByUuid($uuid);
        
        if (!$medicament) {
            throw new ExceptionApi(
                "Médicament introuvable.",
                ["msg" => "Médicament introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Vérifier s'il y a des demandes en cours
        if ($medicament->getDemandesEnAttente() > 0) {
            throw new ExceptionApi(
                "Impossible de supprimer ce médicament, il y a des demandes en attente.",
                ["msg" => "Impossible de supprimer ce médicament, il y a des demandes en attente."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->em->remove($medicament);
        $this->em->flush();
    }

 

    /**
     * Ajouter/Modifier les données d'un médicament
     * @param object $data
     * @param Medicament $medicament
     * @return void
     */
    private function add(object $data, Medicament $medicament,Pharmacie $pharmacie = null): void
    {

          $medecine = $this->medecineRepository->findOneByUuid($data->medicament);
            
            // Vérifier si le médicament existe déjà pour cette pharmacie
            $med = $this->medicamentRepository->findBy(['medecine' => $medecine,'pharmacie' => $pharmacie]);
         

            // Si n'existe pas, créer le médicament dans le stock de la pharmacie
        if (!$med) {
            $med = new Medicament();
            $med->setNom($medecine->getNom());
            $med->setPharmacie($pharmacie);
            $med->setMedecine($medecine);
            $this->em->persist($med);
        }

        $this->em->persist($medicament);
    }

    /**
     * Upload de fichiers (image du médicament)
     * @param object $data
     * @param Medicament $medicament
     * @return void
     */
    private function upload(object $data, Medicament $medicament): void
    {
        if (isset($data->folders) && $data->folders) {
            foreach ($data->folders as $item) {
              //  $this->uploaderManager->handleFileUpload($item, $medicament);
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
    
   
    }

  

    /**
     * Génère les en-têtes pour l'export Excel
     */
    public function getExcelHeaders(): array
    {
        return [
            'Code',
            'Nom',
            'Nom Commercial',
            'Dosage',
            'Forme',
            'Laboratoire',
            'DCI',
            'Code CIP',
            'Code Barre',
            'Stock',
            'Stock Minimum',
            'Disponible',
            'Prix',
            'Taux Remboursement',
            'Prescription Obligatoire',
            'Total Demandes',
            'Demandes en Attente',
            'Créé le'
        ];
    }

    /**
     * Prépare les données pour l'export Excel
     */
    public function prepareExcelData(array $medicaments): array
    {
        $data = [];

        foreach ($medicaments as $medicament) {
            $data[] = [
                $medicament->getUuid(),
                $medicament->getNom(),
                $medicament->getNomCommercial() ?? '',
                $medicament->getDosage(),
                $medicament->getForme(),
                $medicament->getLaboratoire() ?? '',
                $medicament->getDci() ?? '',
                $medicament->getCodeCIP() ?? '',
                $medicament->getCodeBarre() ?? '',
                $medicament->getStock(),
                $medicament->getStockMinimum() ?? '',
                $medicament->isDisponible() ? 'Oui' : 'Non',
                $medicament->getPrix() ?? '',
                $medicament->getTauxRemboursement() ? $medicament->getTauxRemboursement() . '%' : '',
                $medicament->isPrescriptionObligatoire() ? 'Oui' : 'Non',
                $medicament->getTotalDemandes(),
                $medicament->getDemandesEnAttente(),
                $medicament->getCreatedAt() ? $medicament->getCreatedAt()->format('d/m/Y H:i') : ''
            ];
        }

        return $data;
    }

    /**
     * Prépare les données pour l'export PDF
     */
    public function prepareExportData(array $medicaments): array
    {
        $data = [];

        foreach ($medicaments as $medicament) {
            $data[] = [
                'uuid' => $medicament->getUuid(),
                'nom' => $medicament->getNom(),
                'nom_complet' => $medicament->getNomComplet(),
                'nom_commercial' => $medicament->getNomCommercial() ?? '',
                'dosage' => $medicament->getDosage(),
                'forme' => $medicament->getForme(),
                'laboratoire' => $medicament->getLaboratoire() ?? '',
                'dci' => $medicament->getDci() ?? '',
                'code_cip' => $medicament->getCodeCIP() ?? '',
                'code_barre' => $medicament->getCodeBarre() ?? '',
                'stock' => $medicament->getStock(),
                'stock_minimum' => $medicament->getStockMinimum(),
                'stock_faible' => $medicament->hasStockFaible(),
                'en_rupture' => $medicament->isEnRupture(),
                'disponible' => $medicament->isDisponible(),
                'prix' => $medicament->getPrix(),
                'prix_apres_remboursement' => $medicament->getPrixApresRemboursement(),
                'taux_remboursement' => $medicament->getTauxRemboursement(),
                'prescription_obligatoire' => $medicament->isPrescriptionObligatoire(),
                'total_demandes' => $medicament->getTotalDemandes(),
                'demandes_en_attente' => $medicament->getDemandesEnAttente(),
                'pourcentage_stock' => $medicament->getPourcentageStock(),
                'created_at' => $medicament->getCreatedAt() ? $medicament->getCreatedAt()->format('d/m/Y H:i:s') : ''
            ];
        }

        return $data;
    }

    /**
     * Génère les statistiques pour l'export
     */
    public function generateExportStats(array $medicaments): array
    {
        $totalStock = 0;
        $enRupture = 0;
        $stockFaible = 0;
        $disponibles = 0;
        $prescriptionObligatoire = 0;
        $totalDemandes = 0;

        foreach ($medicaments as $medicament) {
            $totalStock += $medicament->getStock();
            $totalDemandes += $medicament->getTotalDemandes();
            
            if ($medicament->isEnRupture()) {
                $enRupture++;
            }
            if ($medicament->hasStockFaible()) {
                $stockFaible++;
            }
            if ($medicament->isDisponible()) {
                $disponibles++;
            }
            if ($medicament->isPrescriptionObligatoire()) {
                $prescriptionObligatoire++;
            }
        }

        return [
            'total_medicaments' => count($medicaments),
            'total_stock' => $totalStock,
            'en_rupture' => $enRupture,
            'stock_faible' => $stockFaible,
            'disponibles' => $disponibles,
            'prescription_obligatoire' => $prescriptionObligatoire,
            'total_demandes' => $totalDemandes,
            'stock_moyen' => count($medicaments) > 0 ? round($totalStock / count($medicaments), 2) : 0
        ];
    }

  
}