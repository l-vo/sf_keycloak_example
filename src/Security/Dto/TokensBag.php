<?php

namespace App\Security\Dto;

final class TokensBag
{
    public function __construct(
        private string $jwt,
        private string $refreshToken,
        private ?int $jwtExpires = null,
    ) {}

    public function getJwt(): string
    {
        return $this->jwt;
    }

    public function getJwtExpires(): int
    {
        if (null === $this->jwtExpires) {
            throw new \LogicException('JWT expiration time is not set');
        }

        return $this->jwtExpires;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function withExpiration(int $jwtExpires): static
    {
        $static = new static($this->jwt, $this->refreshToken);
        $static->jwtExpires = $jwtExpires;

        return $static;
    }
}