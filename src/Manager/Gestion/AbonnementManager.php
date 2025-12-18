<?php

namespace App\Manager\Gestion;

use App\Entity\Gestion\Abonnement;
use App\Entity\Gestion\HistoriqueAbonnement;
use App\Entity\Security\User;
use App\Exception\ExceptionApi;
use App\Repository\Gestion\AbonnementRepository;
use App\Repository\Gestion\PharmacieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AbonnementManager
{
    private ?User $user;
    private EntityManagerInterface $em;
    private AbonnementRepository $abonnementRepository;
    private PharmacieRepository $pharmacieRepository;

    public function __construct(
        EntityManagerInterface $em,
        AbonnementRepository $abonnementRepository,
        PharmacieRepository $pharmacieRepository,
        TokenStorageInterface $tokenStorage
    ) {
        $this->em = $em;
        $this->abonnementRepository = $abonnementRepository;
        $this->pharmacieRepository = $pharmacieRepository;
        
        if ($tokenStorage->getToken()) {
            $this->user = $tokenStorage->getToken()->getUser();
        }
    }

    /**
     * Créer un abonnement
     * @param object $data
     * @throws ExceptionApi
     * @return Abonnement
     */
    public function create(object $data): Abonnement
    {
        $this->requirements($data);
        
        $abonnement = new Abonnement();
        
        // Associer la pharmacie si fournie
        if (isset($data->pharmacie_uuid)) {
            $pharmacie = $this->pharmacieRepository->findOneByUuid($data->pharmacie_uuid);
            if (!$pharmacie) {
                throw new ExceptionApi(
                    "Pharmacie introuvable.",
                    ["msg" => "Pharmacie introuvable."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            
            // Vérifier si la pharmacie a déjà un abonnement actif
            if ($pharmacie->getAbonnement() && $pharmacie->getAbonnement()->isActif()) {
                throw new ExceptionApi(
                    "Cette pharmacie a déjà un abonnement actif.",
                    ["msg" => "Cette pharmacie a déjà un abonnement actif."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            
            $abonnement->setPharmacie($pharmacie);
        }
        
        $this->add($data, $abonnement);
        
        $this->em->persist($abonnement);
        $this->em->flush();
        
        return $abonnement;
    }

    /**
     * Mettre à jour un abonnement
     * @param object $data
     * @param string $uuid
     * @throws ExceptionApi
     * @return Abonnement
     */
    public function update(object $data, string $uuid): Abonnement
    {
        $abonnement = $this->abonnementRepository->findOneByUuid($uuid);
        
        if (!$abonnement) {
            throw new ExceptionApi(
                "Abonnement introuvable.",
                ["msg" => "Abonnement introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->add($data, $abonnement);
        
        $this->em->flush();
        return $abonnement;
    }

    /**
     * Renouveler un abonnement
     * @param string $uuid
     * @param int $duree Durée en jours (par défaut 30)
     * @throws ExceptionApi
     * @return Abonnement
     */
    public function renouveler(string $uuid, int $duree = 30): Abonnement
    {
        $abonnement = $this->abonnementRepository->findOneByUuid($uuid);
        
        if (!$abonnement) {
            throw new ExceptionApi(
                "Abonnement introuvable.",
                ["msg" => "Abonnement introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $dateDebut = new \DateTime('today');
        $dateFin = (clone $dateDebut)->modify("+{$duree} days");
        
        $abonnement->setDateDebut($dateDebut);
        $abonnement->setDateFin($dateFin);
        $abonnement->setActif(true);
        
        $this->em->flush();
        return $abonnement;
    }

    /**
     * Activer/Désactiver un abonnement
     * @param string $uuid
     * @param bool $actif
     * @throws ExceptionApi
     * @return Abonnement
     */
    public function toggleActif(string $uuid, bool $actif): Abonnement
    {
        $abonnement = $this->abonnementRepository->findOneByUuid($uuid);
        
        if (!$abonnement) {
            throw new ExceptionApi(
                "Abonnement introuvable.",
                ["msg" => "Abonnement introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $abonnement->setActif($actif);
        
        $this->em->flush();
        return $abonnement;
    }

    /**
     * Activer une faveur pour un abonnement
     * @param string $uuid
     * @param int $duree Durée de la faveur en jours
     * @throws ExceptionApi
     * @return Abonnement
     */
    public function activerFaveur(string $uuid, int $duree): Abonnement
    {
        $abonnement = $this->abonnementRepository->findOneByUuid($uuid);
        
        if (!$abonnement) {
            throw new ExceptionApi(
                "Abonnement introuvable.",
                ["msg" => "Abonnement introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $dateFinFaveur = (new \DateTime())->modify("+{$duree} days");
        
        $abonnement->setFaveurActive(true);
        $abonnement->setDateFinFaveur($dateFinFaveur);
            
        $this->em->flush();
        return $abonnement;
    }

    /**
     * Désactiver une faveur
     * @param string $uuid
     * @throws ExceptionApi
     * @return Abonnement
     */
    public function desactiverFaveur(string $uuid): Abonnement
    {
        $abonnement = $this->abonnementRepository->findOneByUuid($uuid);
        
        if (!$abonnement) {
            throw new ExceptionApi(
                "Abonnement introuvable.",
                ["msg" => "Abonnement introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $abonnement->setFaveurActive(false);
        $abonnement->setDateFinFaveur(null);
        
        $this->em->flush();
        return $abonnement;
    }

    /**
     * Supprimer un abonnement
     * @param string $uuid
     * @throws ExceptionApi
     * @return void
     */
    public function delete(string $uuid): void
    {
        $abonnement = $this->abonnementRepository->findOneByUuid($uuid);
        
        if (!$abonnement) {
            throw new ExceptionApi(
                "Abonnement introuvable.",
                ["msg" => "Abonnement introuvable."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->em->remove($abonnement);
        $this->em->flush();
    }

    /**
     * Vérifier et désactiver les abonnements expirés
     * @return int Nombre d'abonnements désactivés
     */
    public function desactiverAbonnementsExpires(): int
    {
        // Utiliser une requête avec jointure pour éviter les entités orphelines
        $abonnements = $this->abonnementRepository->createQueryBuilder('a')
            ->innerJoin('a.pharmacie', 'p')
            ->where('a.actif = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getResult();
            
        $count = 0;
        $today = new \DateTime('today');
      
        foreach ($abonnements as $abonnement) {
            if ($abonnement->getDateFin() && $abonnement->getDateFin() < $today) {
                $abonnement->setActif(false);
                $count++;

                // Créer l'historique
                $pharmacie = $abonnement->getPharmacie();

                $historique = new HistoriqueAbonnement();
                $historique->setPharmacie($pharmacie);
                $historique->setDateDebut($abonnement->getDateDebut());
                $historique->setDateFin($abonnement->getDateFin());
                $historique->setStatut('expiré');
               
                $this->em->persist($historique);
            }
            
            // Vérifier aussi les faveurs expirées
            if ($abonnement->isFaveurActive() && 
                $abonnement->getDateFinFaveur() && 
                $abonnement->getDateFinFaveur() < $today) {
                $abonnement->setFaveurActive(false);
            }
        }

        $this->em->flush();
        return $count;
    }

    /**
     * Obtenir les statistiques des abonnements
     * @return array
     */
    public function getStatistics(): array
    {
        $abonnements = $this->abonnementRepository->findAll();
        
        $total = count($abonnements);
        $actifs = 0;
        $expires = 0;
        $avecFaveur = 0;
        $today = new \DateTime('today');

        foreach ($abonnements as $abonnement) {
            if ($abonnement->isActif()) {
                $actifs++;
            }
            
            if ($abonnement->getDateFin() && $abonnement->getDateFin() < $today) {
                $expires++;
            }
            
            if ($abonnement->isFaveurActive()) {
                $avecFaveur++;
            }
        }

        return [
            'total' => $total,
            'actifs' => $actifs,
            'expires' => $expires,
            'avec_faveur' => $avecFaveur,
            'inactifs' => $total - $actifs
        ];
    }

    /**
     * Ajouter/Modifier les données d'un abonnement
     * @param object $data
     * @param Abonnement $abonnement
     * @return void
     */
    private function add(object $data, Abonnement $abonnement): void
    {
        if (isset($data->date_debut)) {
            $dateDebut = new \DateTime($data->date_debut);
            $abonnement->setDateDebut($dateDebut);
        }

        if (isset($data->date_fin)) {
            $dateFin = new \DateTime($data->date_fin);
            $abonnement->setDateFin($dateFin);
        }

        if (isset($data->actif)) {
            $abonnement->setActif($data->actif);
        }

        if (isset($data->faveur_active)) {
            $abonnement->setFaveurActive($data->faveur_active);
        }

        if (isset($data->date_fin_faveur)) {
            $dateFinFaveur = new \DateTime($data->date_fin_faveur);
            $abonnement->setDateFinFaveur($dateFinFaveur);
        }

        $this->em->persist($abonnement);
    }

    /**
     * Validation des données
     * @param object $data
     * @throws ExceptionApi
     * @return void
     */
    private function requirements(object $data): void
    {
        if (!isset($data->date_debut)) {
            throw new ExceptionApi(
                "La date de début est requise.",
                ["msg" => "La date de début est requise."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!isset($data->date_fin)) {
            throw new ExceptionApi(
                "La date de fin est requise.",
                ["msg" => "La date de fin est requise."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Validation des dates
        try {
            $dateDebut = new \DateTime($data->date_debut);
            $dateFin = new \DateTime($data->date_fin);
            
            if ($dateFin <= $dateDebut) {
                throw new ExceptionApi(
                    "La date de fin doit être postérieure à la date de début.",
                    ["msg" => "La date de fin doit être postérieure à la date de début."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        } catch (\Exception $e) {
            throw new ExceptionApi(
                "Format de date invalide.",
                ["msg" => "Format de date invalide."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

  
}