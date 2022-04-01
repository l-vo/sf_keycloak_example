<?php

namespace App\Security\Dto;

final class TokensBag
{
    public function __construct(
        private string $accessToken,
        private string $refreshToken,
        private ?int   $jwtExpires = null,
    ) {}

    public function getAccessToken(): string
    {
        return $this->accessToken;
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
        $static = new static($this->accessToken, $this->refreshToken);
        $static->jwtExpires = $jwtExpires;

        return $static;
    }
}