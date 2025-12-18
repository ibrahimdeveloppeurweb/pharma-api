<?php

namespace App\Entity\Gestion;

use App\Repository\Gestion\AbonnementRepository;
use App\Traits\EntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AbonnementRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]
class Abonnement
{
    use EntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['abonnement:read', 'abonnement:write', 'user:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['abonnement:read', 'abonnement:write', 'user:read'])]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['abonnement:read', 'abonnement:write', 'user:read'])]
    private ?\DateTime $dateFin = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['abonnement:read', 'abonnement:write', 'user:read'])]
    private ?bool $actif = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['abonnement:read', 'abonnement:write', 'user:read'])]
    private ?bool $faveurActive = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['abonnement:read', 'abonnement:write', 'user:read'])]
    private ?\DateTime $dateFinFaveur = null;

    #[ORM\OneToOne(inversedBy: 'abonnement', cascade: ['persist', 'remove'])]
    private ?Pharmacie $pharmacie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTime $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTime $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(?bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }


    public function isFaveurActive(): ?bool
    {
        return $this->faveurActive;
    }

    public function setFaveurActive(?bool $faveurActive): static
    {
        $this->faveurActive = $faveurActive;

        return $this;
    }

    public function getDateFinFaveur(): ?\DateTime
    {
        return $this->dateFinFaveur;
    }

    public function setDateFinFaveur(?\DateTime $dateFinFaveur): static
    {
        $this->dateFinFaveur = $dateFinFaveur;

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
