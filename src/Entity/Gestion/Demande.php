<?php

namespace App\Entity\Gestion;

use App\Repository\Gestion\DemandeRepository;
use App\Traits\EntityTrait;
use App\Entity\Security\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity(repositoryClass: DemandeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]

class Demande
{
    use EntityTrait;

    // Constantes pour les statuts
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_DISPONIBLE = 'disponible';
    public const STATUT_NOTIFIE = 'notifie';
    public const STATUT_RECUPERE = 'recupere';
    public const STATUT_ANNULE = 'annule';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'demandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Patient $patient = null;


    #[ORM\Column(length: 50)]
    #[Assert\Choice(
        choices: [
            self::STATUT_EN_ATTENTE,
            self::STATUT_DISPONIBLE,
            self::STATUT_NOTIFIE,
            self::STATUT_RECUPERE,
            self::STATUT_ANNULE
        ],
        message: 'Le statut {{ value }} n\'est pas valide'
    )]
    private ?string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(nullable: true)]
    private ?int $quantite = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateDisponibilite = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateNotification = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateRecuperation = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photoPath = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['demande:read','demande:write'])]
    private ?array $textractData = null;

    #[ORM\Column(nullable: true)]
    private ?bool $smsEnvoye = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $smsStatut = null;

    #[ORM\Column(nullable: true)]
    private ?int $tentativesNotification = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $prixUnitaire = null;

    #[ORM\Column(nullable: true)]
    private ?bool $prescriptionRequise = false;

    #[ORM\ManyToOne(inversedBy: 'demandes')]
    private ?User $pharmacien = null;

    #[ORM\ManyToOne(inversedBy: 'demandes')]
    private ?Pharmacie $pharmacie = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;


    /**
     * @var Collection<int, DemandeMedicament>
     */
    #[ORM\OneToMany( targetEntity: DemandeMedicament::class,mappedBy: 'demande',cascade: ['persist', 'remove'],orphanRemoval: true)]
    private Collection $demandeMedicaments;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mode_paiement = null;

    public function __construct()
    {
        $this->statut = self::STATUT_EN_ATTENTE;
        $this->quantite = 1;
        $this->smsEnvoye = false;
        $this->tentativesNotification = 0;
        $this->prescriptionRequise = false;
        $this->demandeMedicaments = new ArrayCollection();
    }



    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;
        return $this;
    }

  

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $oldStatut = $this->statut;
        $this->statut = $statut;

        // Mettre à jour automatiquement les dates selon le statut
        if ($statut === self::STATUT_DISPONIBLE && $oldStatut !== self::STATUT_DISPONIBLE) {
            $this->dateDisponibilite = new \DateTimeImmutable();
        }

        if ($statut === self::STATUT_NOTIFIE && $oldStatut !== self::STATUT_NOTIFIE) {
            $this->dateNotification = new \DateTimeImmutable();
        }

        if ($statut === self::STATUT_RECUPERE && $oldStatut !== self::STATUT_RECUPERE) {
            $this->dateRecuperation = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getDateDisponibilite(): ?\DateTimeImmutable
    {
        return $this->dateDisponibilite;
    }

    public function setDateDisponibilite(?\DateTimeImmutable $dateDisponibilite): static
    {
        $this->dateDisponibilite = $dateDisponibilite;
        return $this;
    }

    public function getDateNotification(): ?\DateTimeImmutable
    {
        return $this->dateNotification;
    }

    public function setDateNotification(?\DateTimeImmutable $dateNotification): static
    {
        $this->dateNotification = $dateNotification;
        return $this;
    }

    public function getDateRecuperation(): ?\DateTimeImmutable
    {
        return $this->dateRecuperation;
    }

    public function setDateRecuperation(?\DateTimeImmutable $dateRecuperation): static
    {
        $this->dateRecuperation = $dateRecuperation;
        return $this;
    }

    public function getPhotoPath(): ?string
    {
        return $this->photoPath;
    }

    public function setPhotoPath(?string $photoPath): static
    {
        $this->photoPath = $photoPath;
        return $this;
    }

    public function getTextractData(): ?array
    {
        return $this->textractData;
    }

    public function setTextractData(?array $textractData): static
    {
        $this->textractData = $textractData;
        return $this;
    }

    public function isSmsEnvoye(): ?bool
    {
        return $this->smsEnvoye;
    }

    public function setSmsEnvoye(?bool $smsEnvoye): static
    {
        $this->smsEnvoye = $smsEnvoye;
        return $this;
    }

    public function getSmsStatut(): ?string
    {
        return $this->smsStatut;
    }

    public function setSmsStatut(?string $smsStatut): static
    {
        $this->smsStatut = $smsStatut;
        return $this;
    }

    public function getTentativesNotification(): ?int
    {
        return $this->tentativesNotification;
    }

    public function setTentativesNotification(?int $tentativesNotification): static
    {
        $this->tentativesNotification = $tentativesNotification;
        return $this;
    }

    public function getPrixUnitaire(): ?string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(?string $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;
        return $this;
    }

    public function isPrescriptionRequise(): ?bool
    {
        return $this->prescriptionRequise;
    }

    public function setPrescriptionRequise(?bool $prescriptionRequise): static
    {
        $this->prescriptionRequise = $prescriptionRequise;
        return $this;
    }

    /**
     * Alias pour dateCreation (pour compatibilité Flutter)
     */
    #[Groups(['demande:read','demande:write'])]
    #[SerializedName('dateCreation')]
    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Retourne le prix total
     */
    #[Groups(['demande:read','demande:write'])]
    #[SerializedName('prixTotal')]
    public function getPrixTotal(): ?string
    {
        if ($this->prixUnitaire === null || $this->quantite === null) {
            return null;
        }

        return bcmul($this->prixUnitaire, (string)$this->quantite, 2);
    }

    /**
     * Retourne le délai d'attente en jours
     */
    #[SerializedName('delaiAttenteJours')]
    public function getDelaiAttenteJours(): ?int
    {
        if ($this->statut === self::STATUT_RECUPERE || $this->statut === self::STATUT_ANNULE) {
            return null;
        }

        if (!$this->createdAt) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->createdAt);
        
        return $interval->days;
    }

    /**
     * Vérifie si la demande est en retard (plus de 7 jours)
     */
    #[SerializedName('enRetard')]
    public function isEnRetard(): bool
    {
        $delai = $this->getDelaiAttenteJours();
        return $delai !== null && $delai > 7 && 
               $this->statut !== self::STATUT_RECUPERE && 
               $this->statut !== self::STATUT_ANNULE;
    }

    /**
     * Retourne tous les statuts disponibles
     */
    public static function getAvailableStatuts(): array
    {
        return [
            self::STATUT_EN_ATTENTE,
            self::STATUT_DISPONIBLE,
            self::STATUT_NOTIFIE,
            self::STATUT_RECUPERE,
            self::STATUT_ANNULE,
        ];
    }

    /**
     * Retourne le libellé du statut
     */
    #[SerializedName('statutLibelle')]
    public function getStatutLibelle(): string
    {
        return match($this->statut) {
            self::STATUT_EN_ATTENTE => 'En attente',
            self::STATUT_DISPONIBLE => 'Disponible',
            self::STATUT_NOTIFIE => 'Notifié',
            self::STATUT_RECUPERE => 'Récupéré',
            self::STATUT_ANNULE => 'Annulé',
            default => 'Inconnu',
        };
    }

    public function getPharmacien(): ?User
    {
        return $this->pharmacien;
    }

    public function setPharmacien(?User $pharmacien): static
    {
        $this->pharmacien = $pharmacien;

        return $this;
    }

    public function getPharmacie(): ?Pharmacie
    {
        return $this->pharmacie;
    }

    public function setPharmacie(?Pharmacie $pharmacie): static
    {
        $this->pharmacie = $pharmacie;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }


    /**
     * @return Collection<int, DemandeMedicament>
     */
    public function getDemandeMedicaments(): Collection
    {
        return $this->demandeMedicaments;
    }

    public function addDemandeMedicament(DemandeMedicament $demandeMedicament): static
    {
        if (!$this->demandeMedicaments->contains($demandeMedicament)) {
            $this->demandeMedicaments->add($demandeMedicament);
            $demandeMedicament->setDemande($this);
        }

        return $this;
    }

    public function removeDemandeMedicament(DemandeMedicament $demandeMedicament): static
    {
        if ($this->demandeMedicaments->removeElement($demandeMedicament)) {
            // set the owning side to null (unless already changed)
            if ($demandeMedicament->getDemande() === $this) {
                $demandeMedicament->setDemande(null);
            }
        }

        return $this;
    }



   #[Groups(['demande:read', 'demande:write'])]
    public function getInfo()
    {
        $tmp = [];
        
        // Informations de base avec valeurs par défaut
        $tmp['patient'] = $this->patient ? $this->patient->getNomComplet() : null;
        $tmp['telephone'] = $this->patient ? $this->patient->getTelephone() : null;
        $tmp['statut'] = $this->statut ?? 'en_attente';
        $tmp['mode_paiement'] = $this->mode_paiement ;
        
        // Historique - toujours un tableau
        $tmp['historique'] = [];

        // Si la date de createdAt existe
        if (!empty($this->createdAt)) {
            $tmp['historique'][] = [
                'date' => $this->createdAt->format('Y-m-d H:i:s'),
                'message' => 'Demande enregistrée',
            ];
        }

        // Si la date de dateNotification existe
        if (!empty($this->dateNotification)) {
            $tmp['historique'][] = [
                'date' => $this->dateNotification->format('Y-m-d H:i:s'),
                'message' => 'Sms envoyé',
            ];
        }

        // Si la date de dateRecuperation existe
        if (!empty($this->dateRecuperation)) {
            $tmp['historique'][] = [
                'date' => $this->dateRecuperation->format('Y-m-d H:i:s'),
                'message' => 'Medicament récupéré',
            ];
        }

        // Informations sur les demandeMedicaments - toujours un tableau
        $tmp['demandeMedicaments'] = [];
        foreach ($this->demandeMedicaments as $demandeMedicament) {
            $tmp['demandeMedicaments'][] = [
                'id' => $demandeMedicament->getId(),
                'quantite' => $demandeMedicament->getQuantite(),
                'prix' => $demandeMedicament->getPrix(),
                'medicament' => $demandeMedicament->getMedicament() 
                    ? $demandeMedicament->getMedicament()->getNom() 
                    : null,
            ];
        }

        return $tmp;
    }

   public function getModePaiement(): ?string
   {
       return $this->mode_paiement;
   }

   public function setModePaiement(?string $mode_paiement): static
   {
       $this->mode_paiement = $mode_paiement;

       return $this;
   }


}