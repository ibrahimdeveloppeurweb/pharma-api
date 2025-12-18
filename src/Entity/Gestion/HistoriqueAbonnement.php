<?php

namespace App\Entity\Gestion;

use App\Repository\Gestion\HistoriqueAbonnementRepository;
use App\Traits\EntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: HistoriqueAbonnementRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]
class HistoriqueAbonnement
{
    use EntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'historiqueAbonnements')]
    private ?Pharmacie $pharmacie = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statut = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }
}
