<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Gestion\Pharmacie;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Traits\EntityTrait;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]
class Reservation
{
    use EntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['recherche:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['recherche:read'])]
    private ?string $patientNom = null;

    #[ORM\Column(length: 255)]
    #[Groups(['recherche:read'])]
    private ?string $patientTelephone = null;

    #[ORM\Column]
    #[Groups(['recherche:read'])]
    private array $medicaments = [];

    #[ORM\Column(length: 255)]
    #[Groups(['recherche:read'])]
    private ?string $status = 'EN_ATTENTE'; // EN_ATTENTE, RECUPERE, ANNULE

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['recherche:read'])]
    private bool $isRead = false;

    #[ORM\ManyToOne(targetEntity: Pharmacie::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pharmacie $pharmacie = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    private ?RecherchePublique $recherchePublique = null;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatientNom(): ?string
    {
        return $this->patientNom;
    }

    public function setPatientNom(string $patientNom): static
    {
        $this->patientNom = $patientNom;

        return $this;
    }

    public function getPatientTelephone(): ?string
    {
        return $this->patientTelephone;
    }

    public function setPatientTelephone(string $patientTelephone): static
    {
        $this->patientTelephone = $patientTelephone;

        return $this;
    }

    public function getMedicaments(): array
    {
        return $this->medicaments;
    }

    public function setMedicaments(array $medicaments): static
    {
        $this->medicaments = $medicaments;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getIsRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

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

    public function getRecherchePublique(): ?RecherchePublique
    {
        return $this->recherchePublique;
    }

    public function setRecherchePublique(?RecherchePublique $recherchePublique): static
    {
        $this->recherchePublique = $recherchePublique;

        return $this;
    }

    #[Groups(['recherche:read'])]
    public function getUuid(): ?string
    {
        return $this->uuid?->toRfc4122();
    }
}
