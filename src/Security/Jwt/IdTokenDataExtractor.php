<?php

namespace App\Security\Jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class IdTokenDataExtractor
{
    public function __construct(
        private string $keycloakBase,
        private string $keycloakClientName,
        private string $publicKey,
    ) {}

    public function extract(string $idToken): IdTokenData
    {
        $decoded = JWT::decode($idToken, new Key($this->publicKey, 'RS256'));

        $iat = $decoded->iat ?? PHP_INT_MAX;
        if (time() > $iat) {
            throw new IdTokenException(sprintf('IdToken iat (%d) must be greater than current time (%d)', $iat, time()));
        }

        $exp = $decoded->exp ?? 0;
        if ($exp < time()) {
            throw new IdTokenException(sprintf('IdToken exp (%d) must be lower than current time (%d)', $exp, time()));
        }

        $iss = $decoded->iss ?? null;
        if ($this->keycloakBase !== $iss) {
            throw new IdTokenException(sprintf('IdToken iss (%s) must be the same as %s', $iss, $this->keycloakBase));
        }

        $aud = $decoded->aud ?? null;
        if ($this->keycloakClientName !== $aud) {
            throw new IdTokenException(sprintf('IdToken aud (%s) must be the same as %s', $aud, $this->keycloakClientName));
        }

        $azp = $decoded->azp ?? null;
        if ($this->keycloakClientName !== $azp) {
            throw new IdTokenException(sprintf('IdToken azp (%s) must be the same as %s', $azp, $this->keycloakClientName));
        }

        if (!isset($decoded->email, $decoded->preferred_username, $decoded->name, $decoded->realm_access->roles)) {
            throw new IdTokenException('email, username, name, or roles is missing');
        }

        return new IdTokenData(
            $exp,
            $decoded->email,
            $decoded->preferred_username,
            $decoded->name,
            $decoded->realm_access->roles,
        );
    }
}