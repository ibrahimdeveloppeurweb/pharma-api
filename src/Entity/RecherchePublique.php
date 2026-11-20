<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RecherchePubliqueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: RecherchePubliqueRepository::class)]
#[ApiResource]
class RecherchePublique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['recherche:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Groups(['recherche:read', 'recherche:write'])]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Groups(['recherche:read', 'recherche:write'])]
    private ?string $longitude = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['recherche:read', 'recherche:write'])]
    private array $medicaments = [];

    #[ORM\Column(length: 255)]
    #[Groups(['recherche:read', 'recherche:write'])]
    private ?string $clientIdentifier = null;

    #[ORM\Column(length: 50)]
    #[Groups(['recherche:read'])]
    private ?string $status = 'ACTIVE'; // ACTIVE, TERMINEE, ANNULEE

    #[ORM\Column]
    #[Groups(['recherche:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['recherche:read'])]
    private ?\DateTimeImmutable $expireAt = null;

    #[ORM\OneToMany(mappedBy: 'recherchePublique', targetEntity: ReponseRecherche::class, cascade: ['persist', 'remove'])]
    #[Groups(['recherche:read'])]
    private \Doctrine\Common\Collections\Collection $reponses;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'recherchePublique')]
    private Collection $reservations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->expireAt  = new \DateTimeImmutable('+60 seconds');
        $this->reponses  = new \Doctrine\Common\Collections\ArrayCollection();
        $this->reservations = new ArrayCollection();
    }

    /**
     * La recherche est active si statut = ACTIVE et non expirée
     */
    public function isActive(): bool
    {
        if ($this->status !== 'ACTIVE') {
            return false;
        }
        if ($this->expireAt !== null && new \DateTimeImmutable() > $this->expireAt) {
            return false;
        }
        return true;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;

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

    public function getClientIdentifier(): ?string
    {
        return $this->clientIdentifier;
    }

    public function setClientIdentifier(string $clientIdentifier): static
    {
        $this->clientIdentifier = $clientIdentifier;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpireAt(): ?\DateTimeImmutable
    {
        return $this->expireAt;
    }

    public function setExpireAt(?\DateTimeImmutable $expireAt): static
    {
        $this->expireAt = $expireAt;

        return $this;
    }


    /**
     * @return \Doctrine\Common\Collections\Collection<int, ReponseRecherche>
     */
    public function getReponses(): \Doctrine\Common\Collections\Collection

    {
        return $this->reponses;
    }

    public function addReponse(ReponseRecherche $reponse): static
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->setRecherchePublique($this);
        }

        return $this;
    }

    public function removeReponse(ReponseRecherche $reponse): static
    {
        if ($this->reponses->removeElement($reponse)) {
            // set the owning side to null (unless already changed)
            if ($reponse->getRecherchePublique() === $this) {
                // Cannot set null here because JoinColumn is nullable: false
                // But normally we'd handle it.
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setRecherchePublique($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getRecherchePublique() === $this) {
                $reservation->setRecherchePublique(null);
            }
        }

        return $this;
    }
}
