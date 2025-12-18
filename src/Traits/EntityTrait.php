<?php

namespace App\Traits;

use App\Entity\Security\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Symfony\Component\Uid\Uuid;

trait EntityTrait
{
    use SoftDeleteableEntity;

    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['entity', 'dashboard'])]
    private ?Uuid $uuid = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['entity', 'dashboard'])]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['entity', 'dashboard'])]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity:User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $updateBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $removeBy = null;

    public function getUuid(): ?string
    {
        return $this->uuid?->toRfc4122();
    }

    public function setUuid(Uuid $uuid): static
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCreateBy(): ?User
    {
        return $this->createBy;
    }

    #[Groups(['entity', 'dashboard'])]
    public function getCreate(): ?string
    {
        return $this->createBy?->getNomComplet();
    }

    public function setCreateBy(?User $createBy): static
    {
        $this->createBy = $createBy;
        return $this;
    }

    public function getUpdateBy(): ?User
    {
        return $this->updateBy;
    }

    #[Groups(['entity', 'dashboard'])]
    public function getUpdate(): ?string
    {
        return $this->updateBy?->getNomComplet();
    }

    public function setUpdateBy(?User $updateBy): static
    {
        $this->updateBy = $updateBy;
        return $this;
    }

    public function getRemoveBy(): ?User
    {
        return $this->removeBy;
    }

    #[Groups(['entity', 'dashboard'])]
    public function getRemove(): ?string
    {
        return $this->removeBy?->getNomComplet();
    }

    public function setRemoveBy(?User $removeBy): static
    {
        $this->removeBy = $removeBy;
        return $this;
    }

    /**
     * Vérifie si l'entité est supprimée (soft delete)
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Restaure une entité soft-deleted
     */
    public function restore(): static
    {
        $this->deletedAt = null;
        return $this;
    }
}