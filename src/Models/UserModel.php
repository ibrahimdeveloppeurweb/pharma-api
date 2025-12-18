<?php

namespace App\Models;

class UserModel
{
    /**
     * @var string|null
     */
    private $nom;
    /**
     * @var string|null
     */
    private $contact;
    /**
     * @var string|null
     */
    private $residence;
    /**
     * @var string|null
     */
    private $adresse;

    /**
     * @var dateTime|null
     */
    private $naissance = false;

    /**
     * @var string|null
     */
    private $description;

    /**
     * @var string|null
     */
    private $email;

    /**
     * @var string|null
     */
    private $agent;

    /**
     * @var string|null
     */
    private $civilite;

    /**
     * @var string|null
     */
    private $refreshToken;

    /**
     * @var string|null
     */
    private $token;

    /**
     * @var string|null
     */
    private $uuid;

    /**
     * @var string
     */
    private $role;

    /**
     * @var dateTime|null
     */
    private $lastLogin = false;

    /**
     * @var null|[]
     */
    private $permissions = [];

    public function __construct()
    {
        $this->nom;
        $this->contact;
        $this->residence;
        $this->adresse;
        $this->naissance;
        $this->description;
        $this->agent;
        $this->civilite;
        $this->refreshToken;
        $this->token;
        $this->uuid;
        $this->role;
        $this->email;
        $this->lastLogin;
        $this->permissions;
    }

    public function getNom()
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getContact()
    {
        return $this->contact;
    }

    public function setContact(?string $contact): self
    {
        $this->contact = $contact;
        return $this;
    }

    public function getResidence()
    {
        return $this->residence;
    }

    public function setResidence(?string $residence): self
    {
        $this->residence = $residence;
        return $this;
    }

    public function getAdresse()
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getNaissance(): ?\DateTimeInterface
    {
        return $this->naissance;
    }

    public function setNaissance(?\DateTimeInterface $naissance): self
    {
        $this->naissance = $naissance;
        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getAgent()
    {
        return $this->agent;
    }

    public function setAgent(?string $agent): self
    {
        $this->agent = $agent;
        return $this;
    }


    public function getCivilite(): ?string
    {
        return $this->civilite;
    }

    public function setCivilite(?string $civilite): self
    {
        $this->civilite = $civilite;

        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getPermissions()
    {
        return $this->permissions;
    }

    public function setPermissions(?array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }


    public function getData()
    {
        return [
            'nom' => $this->nom,
            'contact' => $this->contact,
            'residence' => $this->residence,
            'adresse' => $this->adresse,
            'naissance' => $this->naissance,
            'description' => $this->description,
            'agent' => $this->agent,
            'civilite' => $this->civilite,
            'refreshToken' => $this->refreshToken,
            'token' => $this->token,
            'uuid' => $this->uuid,
            'email' => $this->email,
            'lastLogin' => $this->lastLogin,
            'role' => $this->role,
            'permissions' => $this->permissions

        ];
    }
}