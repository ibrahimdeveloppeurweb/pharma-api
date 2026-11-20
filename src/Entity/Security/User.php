<?php

namespace App\Entity\Security;

use App\Entity\Gestion\Demande;
use App\Entity\Gestion\Medicament;
use App\Entity\Gestion\Patient;
use App\Entity\Gestion\Pharmacie;
use App\Repository\Security\UserRepository;
use App\Traits\EntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé')]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use EntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read', 'user:write', 'pharmacie:read'])]
    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Email(message: "L'email {{ value }} n'est pas valide")]
    private ?string $email = null;

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[Groups(['user:write'])]
    #[SerializedName('password')]
    #[Assert\Length(min: 6, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères')]
    private ?string $plainPassword = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['user:read', 'user:write', 'pharmacie:read'])]
    private ?string $prenom = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['user:read', 'user:write', 'pharmacie:read'])]
    private ?string $nom = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['user:read', 'user:write', 'pharmacie:read'])]
    private ?string $telephone = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read'])]
    private ?bool $emailVerified = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?bool $isActive = true;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Admin $admin = null;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    private Collection $droits;

    /**
     * @var Collection<int, Demande>
     */
    #[ORM\OneToMany(targetEntity: Demande::class, mappedBy: 'pharmacien')]
    private Collection $demandes;

    /**
     * @var Collection<int, Medicament>
     */
    #[ORM\OneToMany(targetEntity: Medicament::class, mappedBy: 'pharmacien')]
    private Collection $medicaments;

    /**
     * @var Collection<int, Patient>
     */
    #[ORM\OneToMany(targetEntity: Patient::class, mappedBy: 'pharmacien')]
    private Collection $patients;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $civilite = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read'])]
    private ?bool $first = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read'])]
    private ?bool $isOnline = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTime $lastLogin = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?Pharmacie $pharmacie = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $fcmToken = null;

    public function __construct()
    {
        $this->droits = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->medicaments = new ArrayCollection();
        $this->patients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        
        foreach ($this->droits as $droit) {
            if (method_exists($droit, 'getCode')) {
                //$roles[] = $droit->getCode();
            }
        }
        
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        return $this;
    }

    /**
     * Retourne le rôle principal pour l'API
     */
    #[Groups(['user:read'])]
    #[SerializedName('role')]
    public function getRole(): string
    {
        $roles = $this->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles)) {
            return 'admin';
        }
        
        if (in_array('ROLE_PHARMACIEN', $roles)) {
            return 'pharmacien';
        }
        
        return 'user';
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    /**
     * Retourne le nom complet (prenom + nom)
     */
    #[Groups(['user:read'])]
    #[SerializedName('nomComplet')]
    public function getNomComplet(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    public function isEmailVerified(): ?bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): static
    {
        $this->emailVerified = $emailVerified;
        
        if ($emailVerified && $this->emailVerifiedAt === null) {
            $this->emailVerifiedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * Alias pour compatibilité avec EntityTrait
     */
    public function getLibelle(): string
    {
        return $this->getNomComplet();
    }

    public function getAdmin(): ?Admin
    {
        return $this->admin;
    }

    public function setAdmin(?Admin $admin): static
    {
        $this->admin = $admin;
        return $this;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getDroits(): Collection
    {
        return $this->droits;
    }

    public function addDroit(Role $droit): static
    {
        if (!$this->droits->contains($droit)) {
            $this->droits->add($droit);
        }

        return $this;
    }

    public function removeDroit(Role $droit): static
    {
        $this->droits->removeElement($droit);
        return $this;
    }

    public function generateRefreshToken(): RefreshToken
    {
        $refreshToken = new RefreshToken();
        $now = new \DateTime();
        $expireAt = new \DateTime();
        //$expireAt->modify('+3600 seconds');
        $expireAt->modify('+7776000 seconds');
        $refreshToken->setCreateBy($this);
        $refreshToken->setExpireAt($expireAt);
        $refreshToken->setCreatedAt($now);

        return $refreshToken;
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
            $demande->setPharmacien($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): static
    {
        if ($this->demandes->removeElement($demande)) {
            if ($demande->getPharmacien() === $this) {
                $demande->setPharmacien(null);
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
            $medicament->setPharmacien($this);
        }

        return $this;
    }

    public function removeMedicament(Medicament $medicament): static
    {
        if ($this->medicaments->removeElement($medicament)) {
            if ($medicament->getPharmacien() === $this) {
                $medicament->setPharmacien(null);
            }
        }

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
            $patient->setPharmacien($this);
        }

        return $this;
    }

    public function removePatient(Patient $patient): static
    {
        if ($this->patients->removeElement($patient)) {
            if ($patient->getPharmacien() === $this) {
                $patient->setPharmacien(null);
            }
        }

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCivilite(): ?string
    {
        return $this->civilite;
    }

    public function setCivilite(?string $civilite): static
    {
        $this->civilite = $civilite;
        return $this;
    }

    public function isFirst(): ?bool
    {
        return $this->first;
    }

    public function setFirst(?bool $first): static
    {
        $this->first = $first;
        return $this;
    }

    public function isOnline(): ?bool
    {
        return $this->isOnline;
    }

    public function setIsOnline(?bool $isOnline): static
    {
        $this->isOnline = $isOnline;
        return $this;
    }

    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTime $lastLogin): static
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getPermissions(): array
    {
        $droits = $this->getDroits();
        $path = [];
        
        foreach ($droits as $droit) {
            if (method_exists($droit, 'getPaths')) {
                $roles = $droit->getPaths();
                foreach ($roles as $role) {
                    if (method_exists($role, 'getPermission')) {
                        $path[] = $role->getPermission();
                    }
                }
            }
        }
        
        return $path;
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

    public function getFcmToken(): ?string
    {
        return $this->fcmToken;
    }

    public function setFcmToken(?string $fcmToken): static
    {
        $this->fcmToken = $fcmToken;
        return $this;
    }
}