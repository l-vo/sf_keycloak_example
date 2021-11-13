<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class User implements UserInterface
{
     public function __construct(
        private string $uuid,
        private string $userIdentifier,
        private string $email,
        private string $fullname,
        private array  $roles,
    ) {}

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getUsername(): string
    {
        throw new \BadMethodCallException('Deprecated, should not be called');
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFullname(): string
    {
        return $this->fullname;
    }
}