<?php

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use LogicException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class OpenIdUserProvider implements UserProviderInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private string $publicKey,
    ) {}

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class;
    }

    public function loadUserByUsername(string $username): UserInterface
    {
        throw new \BadMethodCallException(sprintf('%s is depracated and should not be called', __METHOD__));
    }

    public function loadUserByIdentifier(string $jwtToken): UserInterface
    {
        $decoded = JWT::decode($jwtToken, new Key($this->publicKey, 'RS256'));

        $currentRequest = $this->requestStack->getCurrentRequest();
        if (null === $currentRequest) {
            throw new LogicException(sprintf('%s can only be used in an http context', __CLASS__));
        }
        $currentRequest->attributes->set('_app_jwt_expires', $decoded->exp);

        // Extra user information from local database can also be added here
        return new User(
            $decoded->sub,
            $decoded->preferred_username,
            $decoded->email,
            $decoded->name,
            $decoded->realm_access->roles,
        );
    }
}