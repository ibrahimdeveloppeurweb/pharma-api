<?php

namespace App\Entity\Extra;

use App\Entity\Gestion\Medicament;
use App\Repository\Extra\MedecineRepository;
use App\Traits\EntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity(repositoryClass: MedecineRepository::class)]
class Medecine
{
    use EntityTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['medecine:read'])]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, Medicament>
     */
    #[ORM\OneToMany(targetEntity: Medicament::class, mappedBy: 'medecine')]
    private Collection $medicaments;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['medecine:read'])]
    private ?int $prix = 0;

    public function __construct()
    {
        $this->medicaments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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
            $medicament->setMedecine($this);
        }

        return $this;
    }

    public function removeMedicament(Medicament $medicament): static
    {
        if ($this->medicaments->removeElement($medicament)) {
            // set the owning side to null (unless already changed)
            if ($medicament->getMedecine() === $this) {
                $medicament->setMedecine(null);
            }
        }

        return $this;
    }

    public function getPrix(): ?int
    { 

        return $this->prix ? $this->prix :0; 
    }

    public function setPrix(?int $prix): static
    {
        $this->prix = $prix;

        return $this;
    }
}
