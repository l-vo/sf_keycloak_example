<?php

namespace App\Security\Jwt;

use stdClass;

final class IdTokenData
{
    public function __construct(
        private int $exp,
        private string $subject,
        private string $email,
        private string $username,
        private string $name,
        private array $roles,
    ) {}

    public function getExpires(): int
    {
        return $this->exp;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }
}