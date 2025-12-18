<?php

namespace App\Entity\Gestion;

use App\Repository\Gestion\PharmacieRepository;
use App\Traits\EntityTrait;
use App\Entity\Security\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PharmacieRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]

class Pharmacie
{
    use EntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['pharmacie:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['pharmacie:read', 'pharmacie:write', 'user:read'])]
    private ?string $nom = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['pharmacie:read', 'pharmacie:write', 'user:read'])]
    private ?string $adresse = null;

    #[ORM\Column(length: 20)]
    #[Groups(['pharmacie:read', 'pharmacie:write', 'user:read'])]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pharmacie:read', 'pharmacie:write', 'user:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['pharmacie:read', 'pharmacie:write'])]
    private ?string $numeroAutorisation = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pharmacie:read', 'pharmacie:write', 'user:read'])]
    private ?string $ville = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['pharmacie:read', 'pharmacie:write', 'user:read'])]
    private ?string $codePostal = null;

    #[ORM\OneToOne(inversedBy: 'pharmacie', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;



    /**
     * @var Collection<int, Patient>
     */
    #[ORM\OneToMany(targetEntity: Patient::class, mappedBy: 'pharmacie')]
    private Collection $patients;

    /**
     * @var Collection<int, Demande>
     */
    #[ORM\OneToMany(targetEntity: Demande::class, mappedBy: 'pharmacie')]
    private Collection $demandes;

    /**
     * @var Collection<int, Medicament>
     */
    #[ORM\OneToMany(targetEntity: Medicament::class, mappedBy: 'pharmacie')]
    private Collection $medicaments;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'pharmacie')]
    private Collection $users;

    /**
     * @var Collection<int, HistoriqueAbonnement>
     */
    #[ORM\OneToMany(targetEntity: HistoriqueAbonnement::class, mappedBy: 'pharmacie')]
    private Collection $historiqueAbonnements;

    #[ORM\OneToOne(mappedBy: 'pharmacie', cascade: ['persist', 'remove'])]
    private ?Abonnement $abonnement = null;

    public function __construct()
    {
        $this->patients = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->medicaments = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->historiqueAbonnements = new ArrayCollection();
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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;
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

    public function getNumeroAutorisation(): ?string
    {
        return $this->numeroAutorisation;
    }

    public function setNumeroAutorisation(?string $numeroAutorisation): static
    {
        $this->numeroAutorisation = $numeroAutorisation;
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

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;
        return $this;
    }


    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return Collection<int, Patient>
     */
    public function getPatients(): Collection
    {
        return $this->patients;
    }

    public function addPatient(Patient $patient): static
    {
        if (!$this->patients->contains($patient)) {
            $this->patients->add($patient);
            $patient->setPharmacie($this);
        }

        return $this;
    }

    public function removePatient(Patient $patient): static
    {
        if ($this->patients->removeElement($patient)) {
            // set the owning side to null (unless already changed)
            if ($patient->getPharmacie() === $this) {
                $patient->setPharmacie(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Demande>
     */
    public function getDemandes(): Collection
    {
        return $this->demandes;
    }

    public function addDemande(Demande $demande): static
    {
        if (!$this->demandes->contains($demande)) {
            $this->demandes->add($demande);
            $demande->setPharmacie($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): static
    {
        if ($this->demandes->removeElement($demande)) {
            // set the owning side to null (unless already changed)
            if ($demande->getPharmacie() === $this) {
                $demande->setPharmacie(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Medicament>
     */
    public function getMedicaments(): Collection
    {
        return $this->medicaments;
    }

    public function addMedicament(Medicament $medicament): static
    {
        if (!$this->medicaments->contains($medicament)) {
            $this->medicaments->add($medicament);
            $medicament->setPharmacie($this);
        }

        return $this;
    }

    public function removeMedicament(Medicament $medicament): static
    {
        if ($this->medicaments->removeElement($medicament)) {
            // set the owning side to null (unless already changed)
            if ($medicament->getPharmacie() === $this) {
                $medicament->setPharmacie(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setPharmacie($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getPharmacie() === $this) {
                $user->setPharmacie(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, HistoriqueAbonnement>
     */
    public function getHistoriqueAbonnements(): Collection
    {
        return $this->historiqueAbonnements;
    }

    public function addHistoriqueAbonnement(HistoriqueAbonnement $historiqueAbonnement): static
    {
        if (!$this->historiqueAbonnements->contains($historiqueAbonnement)) {
            $this->historiqueAbonnements->add($historiqueAbonnement);
            $historiqueAbonnement->setPharmacie($this);
        }

        return $this;
    }

    public function removeHistoriqueAbonnement(HistoriqueAbonnement $historiqueAbonnement): static
    {
        if ($this->historiqueAbonnements->removeElement($historiqueAbonnement)) {
            // set the owning side to null (unless already changed)
            if ($historiqueAbonnement->getPharmacie() === $this) {
                $historiqueAbonnement->setPharmacie(null);
            }
        }

        return $this;
    }

    public function getAbonnement(): ?Abonnement
    {
        return $this->abonnement;
    }

    public function setAbonnement(?Abonnement $abonnement): static
    {
        // unset the owning side of the relation if necessary
        if ($abonnement === null && $this->abonnement !== null) {
            $this->abonnement->setPharmacie(null);
        }

        // set the owning side of the relation if necessary
        if ($abonnement !== null && $abonnement->getPharmacie() !== $this) {
            $abonnement->setPharmacie($this);
        }

        $this->abonnement = $abonnement;

        return $this;
    }

    #[Groups(['pharmacie:read', 'pharmacie:write'])]
    public function getAbonnementActif(): bool
    {

       $actif =  $this->abonnement->isActif();
        return   $actif;
    }

}