<?php

namespace App\Entity;

use App\Entity\Gestion\Pharmacie;
use App\Repository\ReponseRechercheRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReponseRechercheRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ReponseRecherche
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RecherchePublique::class, inversedBy: 'reponses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RecherchePublique $recherchePublique = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Gestion\Pharmacie::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?\App\Entity\Gestion\Pharmacie $pharmacie = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null; // 'DISPONIBLE', 'BIENTOT_DISPONIBLE', 'NON_DISPONIBLE'

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $delai = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $details = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPharmacie(): ?\App\Entity\Gestion\Pharmacie
    {
        return $this->pharmacie;
    }

    public function setPharmacie(?\App\Entity\Gestion\Pharmacie $pharmacie): static
    {
        $this->pharmacie = $pharmacie;

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

    public function getDelai(): ?string
    {
        return $this->delai;
    }

    public function setDelai(?string $delai): static
    {
        $this->delai = $delai;

        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): static
    {
        $this->details = $details;

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
}
