<?php

namespace App\Security\Jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class IdTokenDataExtractor
{
    public function __construct(
        private string $keycloakBase,
        private string $keycloakClientId,
        private string $algo,
        private string $publicKey,
    ) {}

    public function extract(string $idToken): IdTokenData
    {
        $decoded = JWT::decode($idToken, new Key($this->publicKey, $this->algo));

        $iat = $decoded->iat ?? PHP_INT_MAX;
        if (time() > $iat) {
            throw new IdTokenException(sprintf('IdToken iat (%d) must be greater than current time (%d)', $iat, time()));
        }

        $exp = $decoded->exp ?? 0;
        if ($exp < time()) {
            throw new IdTokenException(sprintf('IdToken exp (%d) must be lower than current time (%d)', $exp, time()));
        }

        $sub = $decoded->sub ?? '';
        if (!$sub) {
            throw new IdTokenException(sprintf('IdToken sub (%s) must not be empty', $sub));
        }

        $iss = $decoded->iss ?? null;
        if ($this->keycloakBase !== $iss) {
            throw new IdTokenException(sprintf('IdToken iss (%s) must be the same as %s', $iss, $this->keycloakBase));
        }

        $aud = $decoded->aud ?? null;
        if ($this->keycloakClientId !== $aud) {
            throw new IdTokenException(sprintf('IdToken aud (%s) must be the same as %s', $aud, $this->keycloakClientId));
        }

        $azp = $decoded->azp ?? null;
        if ($this->keycloakClientId !== $azp) {
            throw new IdTokenException(sprintf('IdToken azp (%s) must be the same as %s', $azp, $this->keycloakClientId));
        }

        if (!isset($decoded->email, $decoded->preferred_username, $decoded->name, $decoded->realm_access->roles)) {
            throw new IdTokenException('email, username, name, or roles is missing');
        }

        return new IdTokenData(
            $exp,
            $sub,
            $decoded->email,
            $decoded->preferred_username,
            $decoded->name,
            $decoded->realm_access->roles,
        );
    }
}