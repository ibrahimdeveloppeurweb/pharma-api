<?php

namespace App\Entity\Security;

use App\Repository\Security\RefreshTokenRepository;
use App\Traits\EntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]
class RefreshToken
{

    use EntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $expireAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpireAt(): ?\DateTime
    {
        return $this->expireAt;
    }

    public function setExpireAt(?\DateTime $expireAt): static
    {
        $this->expireAt = $expireAt;

        return $this;
    }
}
