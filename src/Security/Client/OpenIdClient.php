<?php

namespace App\Security\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenIdClient
{
    public function __construct(
        private HttpClientInterface $keycloakClient,
        private string $clientId,
        private string $clientSecret,
        private string $tokenEndpoint,
        private string $logoutEndpoint,
    ) {}

    public function getTokenFromAuthorizationCode(string $authorizationCode): string
    {
        return $this->callTokenEntryPoint([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $authorizationCode,
        ]);
    }

    public function getTokenFromRefreshToken(string $refreshToken): string
    {
        return $this->callTokenEntryPoint([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    private function callTokenEntryPoint(array $body): string
    {
        $response = $this->keycloakClient->request('POST', $this->tokenEndpoint, [
            'body' => $body,
        ]);

        return $response->getContent();
    }

    public function logout(string $jwtToken, string $refreshToken): void
    {
        $this->keycloakClient->request('POST', $this->logoutEndpoint, [
            'headers' => ['Authorization' => sprintf('Bearer %s', $jwtToken)],
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
            ]
        ]);
    }
}