<?php

namespace App\Entity\Gestion;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\Extra\Medecine;
use App\Entity\Security\User;
use App\Repository\Gestion\MedicamentRepository;
use App\Traits\EntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity(repositoryClass: MedicamentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]

class Medicament
{
    use EntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['medicament:read', 'demande:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['medicament:read', 'medicament:write', 'demande:read'])]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dosage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $forme = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $laboratoire = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dci = null; // Dénomination Commune Internationale

    #[ORM\Column(length: 13, nullable: true)]
    private ?string $codeBarre = null; // Code EAN/UPC

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $codeCIP = null; // Code Identifiant de Présentation

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $disponible = true;

    #[ORM\Column]
    private ?int $stock = 0;

    #[ORM\Column(nullable: true)]
    private ?int $stockMinimum = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $prix = null;

    #[ORM\Column(nullable: true)]
    private ?float $tauxRemboursement = null;

    #[ORM\Column(nullable: true)]
    private ?bool $prescriptionObligatoire = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomCommercial = null;

    #[ORM\ManyToOne(inversedBy: 'medicaments')]
    private ?User $pharmacien = null;

    #[ORM\ManyToOne(inversedBy: 'medicaments')]
    private ?Medecine $medecine = null;

    #[ORM\ManyToOne(inversedBy: 'medicaments')]
    private ?Pharmacie $pharmacie = null;

    

    /**
     * @var Collection<int, DemandeMedicament>
     */
    #[ORM\OneToMany(targetEntity: DemandeMedicament::class, mappedBy: 'medicament')]
    private Collection $demandeMedicaments;

   

    public function __construct()
    {
        $this->disponible = true;
        $this->stock = 0;
        $this->prescriptionObligatoire = false;
        $this->demandeMedicaments = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDosage(): ?string
    {
        return $this->dosage;
    }

    public function setDosage(string $dosage): static
    {
        $this->dosage = $dosage;
        return $this;
    }

    public function getForme(): ?string
    {
        return $this->forme;
    }

    public function setForme(string $forme): static
    {
        $this->forme = $forme;
        return $this;
    }

    public function getLaboratoire(): ?string
    {
        return $this->laboratoire;
    }

    public function setLaboratoire(?string $laboratoire): static
    {
        $this->laboratoire = $laboratoire;
        return $this;
    }

    public function getDci(): ?string
    {
        return $this->dci;
    }

    public function setDci(?string $dci): static
    {
        $this->dci = $dci;
        return $this;
    }

    public function getCodeBarre(): ?string
    {
        return $this->codeBarre;
    }

    public function setCodeBarre(?string $codeBarre): static
    {
        $this->codeBarre = $codeBarre;
        return $this;
    }

    public function getCodeCIP(): ?string
    {
        return $this->codeCIP;
    }

    public function setCodeCIP(?string $codeCIP): static
    {
        $this->codeCIP = $codeCIP;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function isDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): static
    {
        $this->disponible = $disponible;
        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;
        
        // Mettre automatiquement disponible à false si stock = 0
        if ($stock === 0) {
            $this->disponible = false;
        }
        
        return $this;
    }

    public function getStockMinimum(): ?int
    {
        return $this->stockMinimum;
    }

    public function setStockMinimum(?int $stockMinimum): static
    {
        $this->stockMinimum = $stockMinimum;
        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    public function getTauxRemboursement(): ?float
    {
        return $this->tauxRemboursement;
    }

    public function setTauxRemboursement(?float $tauxRemboursement): static
    {
        $this->tauxRemboursement = $tauxRemboursement;
        return $this;
    }

    public function isPrescriptionObligatoire(): ?bool
    {
        return $this->prescriptionObligatoire;
    }

    public function setPrescriptionObligatoire(?bool $prescriptionObligatoire): static
    {
        $this->prescriptionObligatoire = $prescriptionObligatoire;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getNomCommercial(): ?string
    {
        return $this->nomCommercial;
    }

    public function setNomCommercial(?string $nomCommercial): static
    {
        $this->nomCommercial = $nomCommercial;
        return $this;
    }

    /**
     * Retourne le nom complet du médicament
     */
    #[Groups(['medicament:read', 'demande:read'])]
    #[SerializedName('nomComplet')]
    public function getNomComplet(): string
    {
        $parts = [$this->nom];
        
        if ($this->dosage) {
            $parts[] = $this->dosage;
        }
        
        if ($this->forme) {
            $parts[] = $this->forme;
        }
        
        return implode(' - ', $parts);
    }

    /**
     * Vérifie si le stock est faible
     */
    #[SerializedName('stockFaible')]
    public function hasStockFaible(): bool
    {
        if ($this->stockMinimum === null) {
            return $this->stock <= 5; // Par défaut, alerte si stock <= 5
        }

        return $this->stock <= $this->stockMinimum;
    }

    /**
     * Vérifie si le médicament est en rupture
     */
    #[SerializedName('enRupture')]
    public function isEnRupture(): bool
    {
        return $this->stock === 0;
    }

    /**
     * Calcule le prix après remboursement
     */
    #[SerializedName('prixApresRemboursement')]
    public function getPrixApresRemboursement(): ?string
    {
        if ($this->prix === null || $this->tauxRemboursement === null) {
            return $this->prix;
        }

        $montantRembourse = bcmul($this->prix, (string)($this->tauxRemboursement / 100), 2);
        return bcsub($this->prix, $montantRembourse, 2);
    }

    // /**
    //  * Retourne le nombre total de demandes
    //  */
    // #[Groups(['medicament:read'])]
    // #[SerializedName('totalDemandes')]
    // public function getTotalDemandes(): int
    // {
    //     return $this->demandes->count();
    // }

    // /**
    //  * Retourne le nombre de demandes en attente
    //  */
    // #[Groups(['medicament:read'])]
    // #[SerializedName('demandesEnAttente')]
    // public function getDemandesEnAttente(): int
    // {
    //     return $this->demandes->filter(function (Demande $demande) {
    //         return $demande->getStatut() === Demande::STATUT_EN_ATTENTE;
    //     })->count();
    // }

    /**
     * Retourne le pourcentage de stock restant
     */
    #[SerializedName('pourcentageStock')]
    public function getPourcentageStock(): ?float
    {
        if ($this->stockMinimum === null || $this->stockMinimum === 0) {
            return null;
        }

        return ($this->stock / $this->stockMinimum) * 100;
    }

    /**
     * Incrémente le stock
     */
    public function incrementStock(int $quantite = 1): static
    {
        $this->stock += $quantite;
        
        if ($this->stock > 0) {
            $this->disponible = true;
        }
        
        return $this;
    }

    /**
     * Décrémente le stock
     */
    public function decrementStock(int $quantite = 1): static
    {
        $this->stock = max(0, $this->stock - $quantite);
        
        if ($this->stock === 0) {
            $this->disponible = false;
        }
        
        return $this;
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

    public function getMedecine(): ?Medecine
    {
        return $this->medecine;
    }

    public function setMedecine(?Medecine $medecine): static
    {
        $this->medecine = $medecine;

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
            $demandeMedicament->setMedicament($this);
        }

        return $this;
    }

    public function removeDemandeMedicament(DemandeMedicament $demandeMedicament): static
    {
        if ($this->demandeMedicaments->removeElement($demandeMedicament)) {
            // set the owning side to null (unless already changed)
            if ($demandeMedicament->getMedicament() === $this) {
                $demandeMedicament->setMedicament(null);
            }
        }

        return $this;
    }

  
}