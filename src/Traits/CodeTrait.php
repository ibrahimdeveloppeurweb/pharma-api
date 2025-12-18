<?php

namespace App\Traits;

use Symfony\Component\Serializer\Annotation\Groups;

trait CodeTrait
{
    protected $prefix;
    
    /**
     * @ORM\Column(type="string", length=255, unique=true, nullable=true)
     * @Groups({"entity"})
     */
    private $code;

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @return string
     * @Groups({"entity"})
     */
    abstract function getPrefix(): string;
}