<?php

namespace App\Entity\Gestion;

use App\Entity\Security\User;
use App\Repository\Gestion\PatientRepository;
use App\Traits\EntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]

class Patient
{
    use EntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['patient:read', 'demande:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['patient:read', 'patient:write', 'demande:read'])]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['patient:read', 'patient:write', 'demande:read'])]
    private ?string $prenom = null;

    #[ORM\Column(length: 20)]
    #[Groups(['patient:read', 'patient:write', 'demande:read'])]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ville = null;

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: 'App\Entity\Gestion\Demande', orphanRemoval: true)]
    private Collection $demandes;

    #[ORM\ManyToOne(inversedBy: 'patients')]
    private ?User $pharmacien = null;

    #[ORM\ManyToOne(inversedBy: 'patients')]
    private ?Pharmacie $pharmacie = null;

    public function __construct()
    {
        $this->demandes = new ArrayCollection();
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;
        return $this;
    }

    public function getDemandes(): Collection
    {
        return $this->demandes;
    }

    public function addDemande($demande): static
    {
        if (!$this->demandes->contains($demande)) {
            $this->demandes->add($demande);
            $demande->setPatient($this);
        }
        return $this;
    }

    public function removeDemande($demande): static
    {
        if ($this->demandes->removeElement($demande)) {
            if ($demande->getPatient() === $this) {
                $demande->setPatient(null);
            }
        }
        return $this;
    }

    /**
     * Retourne le nom complet (prenom + nom)
     */
    #[SerializedName('nomComplet')]
    public function getNomComplet(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    /**
     * Retourne le nombre total de demandes
     */
    #[Groups(['patient:read'])]
    #[SerializedName('totalDemandes')]
    public function getTotalDemandes(): int
    {
        return $this->demandes->count();
    }

    /**
     * Retourne le nombre de demandes en attente
     */
    #[Groups(['patient:read'])]
    #[SerializedName('demandesEnAttente')]
    public function getDemandesEnAttente(): int
    {
        return $this->demandes->filter(function ($demande) {
            return $demande->getStatut() === 'en_attente';
        })->count();
    }

    /**
     * Retourne le nombre de demandes disponibles
     */
    #[SerializedName('demandesDisponibles')]
    public function getDemandesDisponibles(): int
    {
        return $this->demandes->filter(function ($demande) {
            return $demande->getStatut() === 'disponible';
        })->count();
    }

    /**
     * Retourne le nombre de demandes notifiées
     */
    #[Groups(['patient:read'])]
    #[SerializedName('demandesNotifiees')]
    public function getDemandesNotifiees(): int
    {
        return $this->demandes->filter(function ($demande) {
            return $demande->getStatut() === 'notifie';
        })->count();
    }

    /**
     * Retourne le nombre de demandes récupérées
     */
    #[Groups(['patient:read'])]
    #[SerializedName('demandesRecuperees')]
    public function getDemandesRecuperees(): int
    {
        return $this->demandes->filter(function ($demande) {
            return $demande->getStatut() === 'recupere';
        })->count();
    }

    /**
     * Vérifie si le patient a des demandes en attente
     */
    #[Groups(['patient:read'])]
    #[SerializedName('hasPendingDemandes')]
    public function hasPendingDemandes(): bool
    {
        return $this->getDemandesEnAttente() > 0;
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

}