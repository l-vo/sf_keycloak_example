<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
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
        private UserRepository $userRepository,
        private string $publicKey,
    ) {}

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->userRepository->find($user->getId());
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

        $user = $this->userRepository->findOneByEmail($decoded->email);

        if ($user === null) {
            $user = new User();
        }

        $user->setEmail($decoded->email);
        $user->setFullName($decoded->name);
        $user->setUsername($decoded->preferred_username);
        $user->setRoles($decoded->realm_access->roles);
        $user->setPassword('');
        $this->userRepository->saveUser($user);

        return $user;
    }
}