<?php

namespace App\Manager\Gestion;

use App\Entity\Gestion\Abonnement;
use App\Entity\Gestion\Demande;
use App\Utils\TypeUtil;
use App\Entity\Security\User;
use App\Entity\Gestion\Pharmacie;
use App\Exception\ExceptionApi;
use App\Manager\Security\UserManager;
use App\Repository\Gestion\DemandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\Security\UserRepository;
use App\Repository\Gestion\PharmacieRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class PharmacieManager
{
    private ?User $user;
    private EntityManagerInterface $em;
    private UserManager $userManager;
    private UserRepository $userRepository;
    private PharmacieRepository $pharmacieRepository;
    private DemandeRepository $demandeRepository;

    public function __construct(
        UserManager $userManager,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        PharmacieRepository $pharmacieRepository,
        DemandeRepository $demandeRepository,
        TokenStorageInterface $tokenStorage
    ) {
        $this->em = $em;
       // $this->excelHelper = $excelHelper;
        $this->userManager = $userManager;
        $this->userRepository = $userRepository;
        $this->pharmacieRepository = $pharmacieRepository;
        $this->demandeRepository = $demandeRepository;
        
        if ($tokenStorage->getToken()) {
            $this->user = $tokenStorage->getToken()->getUser();
        }
    }

    /**
     * Créer une pharmacie
     * @param object $data
     * @throws ExceptionApi
     * @return Pharmacie
     */
    public function create(object $data): Pharmacie
    {
        $this->requirements($data);
        
        $pharmacie = new Pharmacie();
       
         // Associer l'utilisateur si fourni
        $isExists = $this->pharmacieRepository->exists($data->telephone,$data->email);
        if ($isExists) {
              throw new ExceptionApi(
                "Ce numero telephone existe déjà pour une pharmacie.",
                ["msg" => "Ce numero telephone existe déjà pour une pharmacie.."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
        
       $user =   $this->userManager->create($data);
       $pharmacie->setUser($user);
       $user->setPharmacie($pharmacie);
        $this->add($data, $pharmacie);
        //$this->upload($data, $pharmacie);


        $debut = new \DateTime('today');
        $fin = (clone $debut)->modify('+30 days');
        $abonnement = new Abonnement();
        $abonnement->setDateDebut($debut);
        $abonnement->setDateFin($fin); 
        $abonnement->setActif(true);
        $abonnement->setPharmacie($pharmacie);

        $this->em->persist($abonnement);
        $pharmacie->setAbonnement($abonnement);
        $this->em->persist($pharmacie);
       
        
        $this->em->flush();
        return $pharmacie;
    }

    /**
     * Mettre à jour une pharmacie
     * @param object $data
     * @param string $uuid
     * @throws ExceptionApi
     * @return Pharmacie
     */
    public function update(object $data, string $uuid): Pharmacie
    {
        $pharmacie = $this->pharmacieRepository->findOneByUuid($uuid);
        
        if (!$pharmacie) {
            throw new ExceptionApi(
                "Pharmacie introuvable.",
                ["msg" => "Pharmacie introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->add($data, $pharmacie);
        
        $this->em->flush();
        return $pharmacie;
    }

    /**
     * Activer/Désactiver la garde d'une pharmacie
     * @param string $uuid
     * @throws ExceptionApi
     * @return Pharmacie
     */
    public function toggleGarde(string $uuid): Pharmacie
    {
        $pharmacie = $this->pharmacieRepository->findOneByUuid($uuid);
        
        if (!$pharmacie) {
            throw new ExceptionApi(
                "Pharmacie introuvable.",
                ["msg" => "Pharmacie introuvable."],
                Response::HTTP_NOT_FOUND
            );
        }

        $pharmacie->setIsDeGarde(!$pharmacie->getIsDeGarde());
        $this->em->persist($pharmacie);
        $this->em->flush();

        return $pharmacie;
    }

    /**
     * Activer/Désactiver la disponibilité de recherche d'une pharmacie
     * @param string $uuid
     * @throws ExceptionApi
     * @return Pharmacie
     */
    public function toggleDispoRecherche(string $uuid): Pharmacie
    {
        $pharmacie = $this->pharmacieRepository->findOneByUuid($uuid);
        
        if (!$pharmacie) {
            throw new ExceptionApi(
                "Pharmacie introuvable.",
                ["msg" => "Pharmacie introuvable."],
                Response::HTTP_NOT_FOUND
            );
        }

        $pharmacie->setIsDisponibleRecherche(!$pharmacie->getIsDisponibleRecherche());
        $this->em->persist($pharmacie);
        $this->em->flush();

        return $pharmacie;
    }

    /**
     * Activer/Désactiver une pharmacie
     * @param string $uuid
     * @throws ExceptionApi
     * @return Pharmacie
     */
    public function toggleStatus(string $uuid): Pharmacie
    {
        $pharmacie = $this->pharmacieRepository->findOneByUuid($uuid);
        
        if (!$pharmacie) {
            throw new ExceptionApi(
                "Pharmacie introuvable.",
                ["msg" => "Pharmacie introuvable."],
                Response::HTTP_NOT_FOUND
            );
        }

        $user = $pharmacie->getUser();
        if ($user) {
            $user->setIsActive(!$user->isActive());
            $this->em->persist($user);
            $this->em->flush();
        }

        return $pharmacie;
    }

    /**
     * Supprimer une pharmacie
     * @param string $uuid
     * @throws ExceptionApi
     * @return void
     */
    public function delete(string $uuid): void
    {
        $pharmacie = $this->pharmacieRepository->findOneByUuid($uuid);
        
        if (!$pharmacie) {
            throw new ExceptionApi(
                "Pharmacie introuvable.",
                ["msg" => "Pharmacie introuvable."],
                Response::HTTP_NOT_FOUND
            );
        }

        // Le cascade remove dans l'entité supprimera aussi l'utilisateur associé
        $this->em->remove($pharmacie);
        $this->em->flush();
    }

    /**
     * Ajouter/Modifier les données d'une pharmacie
     * @param object $data
     * @param Pharmacie $pharmacie
     * @return void
     */
    private function add(object $data, Pharmacie $pharmacie): void
    {
        if (isset($data->nom)) {
            $pharmacie->setNom($data->nom);
        }
        if (isset($data->adresse)) {
            $pharmacie->setAdresse($data->adresse);
        }
        if (isset($data->telephone)) {
            $pharmacie->setTelephone($data->telephone);
        }
        if (isset($data->email)) {
            $pharmacie->setEmail($data->email);
        }
        if (isset($data->ville)) {
            $pharmacie->setVille($data->ville);
        }
        if (isset($data->numero_autorisation) || isset($data->numero)) {
            $pharmacie->setNumeroAutorisation($data->numero_autorisation ?? $data->numero);
        }
        if (isset($data->code_postal) || isset($data->codePostal)) {
            $pharmacie->setCodePostal($data->code_postal ?? $data->codePostal);
        }
        if (isset($data->latitude) && $data->latitude !== null && $data->latitude !== '') {
            $pharmacie->setLatitude((string) $data->latitude);
        }
        if (isset($data->longitude) && $data->longitude !== null && $data->longitude !== '') {
            $pharmacie->setLongitude((string) $data->longitude);
        }
        if (isset($data->lieuExact) || isset($data->lieu_exact)) {
            $pharmacie->setLieuExact($data->lieuExact ?? $data->lieu_exact);
        }


        // Mettre à jour l'utilisateur associé
        if ($pharmacie->getUser()) {
            $user = $pharmacie->getUser();
            $userUpdated = false;
            
            if (isset($data->nom_pharmacien)) {
                $user->setNom($data->nom_pharmacien);
                $user->setPrenom($data->nom_pharmacien);
                $userUpdated = true;
            }
            if (isset($data->email)) {
                $user->setEmail($data->email);
                $userUpdated = true;
            }
            if (isset($data->telephone)) {
                $user->setTelephone($data->telephone);
                $userUpdated = true;
            }
            
            if ($userUpdated) {
                $this->em->persist($user);
            }
        }

        $this->em->persist($pharmacie);
    }

    /**
     * Upload de fichiers
     * @param object $data
     * @param Pharmacie $pharmacie
     * @return void
     */
    private function upload(object $data, Pharmacie $pharmacie): void
    {
        if (isset($data->folders) && $data->folders) {
            foreach ($data->folders as $item) {
             //   $this->uploaderManager->handleFileUpload($item, $pharmacie);
            }
        }
    }


 
    /**
     * Retourne les statistiques globales + la liste détaillée des pharmacies
     */
    public function statistics(object $data): array
    {
        $pharmacies = $this->pharmacieRepository->findAll();

        // Variables globales
        $totalPharmacies = count($pharmacies);
        $pharmaciesActives = 0;
        $totalDemandes = 0;
        $notificationsTotal = 0;

        $pharmaciesStats = [];

        foreach ($pharmacies as $pharmacie) {
            if ($pharmacie->getUser()->isActive()) {
                $pharmaciesActives++;
            }

            $demandes = $this->demandeRepository->findIndex($data, null, $pharmacie);

            $enAttente = 0;
            $notifie = 0;
            $recupere = 0;

            foreach ($demandes as $demande) {
                switch ($demande->getStatut()) {
                    case Demande::STATUT_EN_ATTENTE:
                        $enAttente++;
                        break;
                    case Demande::STATUT_NOTIFIE:
                        $notifie++;
                        $notificationsTotal++;
                        break;
                    case Demande::STATUT_RECUPERE:
                        $recupere++;
                        break;
                }
            }

            $total = count($demandes);
            $totalDemandes += $total;

            $tauxPerformance = $total > 0 ? round(($recupere * 100) / $total) : 0;

            $pharmaciesStats[] = [
                'id' => $pharmacie->getId(),
                'uuid' => $pharmacie->getUuid(),
                'nom' => $pharmacie->getNom(),
                'ville' => $pharmacie->getVille(),
                'adresse' => $pharmacie->getAdresse(),
                'telephone' => $pharmacie->getTelephone(),
                'email' => $pharmacie->getEmail(),
                'nom_pharmacien' => $pharmacie->getUser() ? $pharmacie->getUser()->getNom() : null,
                'demandes_notifiees' => $notifie,
                'total_demandes' => $total,
                'taux_performance' => $tauxPerformance,
                'actif' => $pharmacie->getUser() ? $pharmacie->getUser()->isActive() : false,
                'isDeGarde' => $pharmacie->getIsDeGarde(),
            ];
        }

        // 🔹 Trier par performance décroissante
        usort($pharmaciesStats, fn($a, $b) => $b['taux_performance'] <=> $a['taux_performance']);

        // 🔹 Ajouter le rang
        foreach ($pharmaciesStats as $i => &$pharma) {
            $pharma['rang'] = $i + 1;
        }

        // 🔹 Retour combiné avec "widget"
        return [
            'widget' => [
                'total_pharmacies' => $totalPharmacies,
                'pharmacies_actives' => $pharmaciesActives,
                'total_demandes' => $totalDemandes,
                'notifications_total' => $notificationsTotal,
            ],
            'pharmacies' => $pharmaciesStats,
        ];
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
                "Le nom de la pharmacie est requis.",
                ["msg" => "Le nom de la pharmacie est requis."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!isset($data->adresse) || !TypeUtil::is_not_null($data->adresse)) {
            throw new ExceptionApi(
                "L'adresse est requise.",
                ["msg" => "L'adresse est requise."],
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
    }

    /**
     * Génère les en-têtes pour l'export Excel
     */
    public function getExcelHeaders(): array
    {
        return [
            'Code',
            'Nom',
            'Adresse',
            'Ville',
            'Code Postal',
            'Téléphone',
            'Email',
            'N° Autorisation',
            'Utilisateur',
            'Créé le',
            'Modifié le'
        ];
    }

    /**
     * Prépare les données pour l'export Excel
     */
    public function prepareExcelData(array $pharmacies): array
    {
        $data = [];

        foreach ($pharmacies as $pharmacie) {
            $data[] = [
                $pharmacie->getUuid(),
                $pharmacie->getNom(),
                $pharmacie->getAdresse(),
                $pharmacie->getVille() ?? '',
                $pharmacie->getCodePostal() ?? '',
                $pharmacie->getTelephone(),
                $pharmacie->getEmail() ?? '',
                $pharmacie->getNumeroAutorisation() ?? '',
                $pharmacie->getUser() ? $pharmacie->getUser()->getNom() : '',
                $pharmacie->getCreatedAt() ? $pharmacie->getCreatedAt()->format('d/m/Y H:i') : '',
                $pharmacie->getUpdatedAt() ? $pharmacie->getUpdatedAt()->format('d/m/Y H:i') : ''
            ];
        }

        return $data;
    }

    /**
     * Prépare les données pour l'export PDF
     */
    public function prepareExportData(array $pharmacies): array
    {
        $data = [];

        foreach ($pharmacies as $pharmacie) {
            $data[] = [
                'uuid' => $pharmacie->getUuid(),
                'nom' => $pharmacie->getNom(),
                'adresse' => $pharmacie->getAdresse(),
                'ville' => $pharmacie->getVille() ?? '',
                'code_postal' => $pharmacie->getCodePostal() ?? '',
                'telephone' => $pharmacie->getTelephone(),
                'email' => $pharmacie->getEmail() ?? '',
                'numero_autorisation' => $pharmacie->getNumeroAutorisation() ?? '',
                'utilisateur' => $pharmacie->getUser() ? $pharmacie->getUser()->getNom() : '',
                'created_at' => $pharmacie->getCreatedAt() ? $pharmacie->getCreatedAt()->format('d/m/Y H:i:s') : '',
                'updated_at' => $pharmacie->getUpdatedAt() ? $pharmacie->getUpdatedAt()->format('d/m/Y H:i:s') : ''
            ];
        }

        return $data;
    }

    /**
     * Génère les statistiques pour l'export
     */
    public function generateExportStats(array $pharmacies): array
    {
        return [
            'total' => count($pharmacies),
            'avec_email' => count(array_filter($pharmacies, fn($p) => $p->getEmail())),
            'avec_autorisation' => count(array_filter($pharmacies, fn($p) => $p->getNumeroAutorisation())),
            'avec_utilisateur' => count(array_filter($pharmacies, fn($p) => $p->getUser()))
        ];
    }
}