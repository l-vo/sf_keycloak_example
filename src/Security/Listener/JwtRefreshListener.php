<?php

namespace App\Security\Listener;

use App\Security\Client\OpenIdClient;
use App\Security\Dto\TokensBag;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

final class JwtRefreshListener implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private OpenIdClient $openIdClient,
        private UrlGeneratorInterface $urlGenerator,
        private UserProviderInterface $userProvider,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return;
        }

        $tokens = $token->getAttribute(TokensBag::class);
        if (null === $tokens) {
            throw new \LogicException(sprintf('%s token attribute is empty', TokensBag::class));
        }

        if (time() < $tokens->getJwtExpires()) {
            return;
        }

        $refreshToken = $tokens->getRefreshToken();

        try {
            $response = $this->openIdClient->getTokenFromRefreshToken($refreshToken);
        } catch (HttpExceptionInterface $e) {
            $response = $e->getResponse();
            if (400 === $response->getStatusCode() && 'invalid_grant' === ($response->toArray(false)['error'] ?? null)) {
                // Logout when SSO session idle is reached
                $this->tokenStorage->setToken(null);
                $event->setResponse(new RedirectResponse($this->urlGenerator->generate('home')));

                return;
            }

            throw new RuntimeException(
                sprintf('Bad status code returned by openID server (%s)', $e->getResponse()->getStatusCode()),
                previous: $e,
            );
        }

        $responseData = json_decode($response, true);
        if (false === $responseData) {
            throw new RuntimeException(sprintf('Can\'t parse json in response: %s', $response->getContent()));
        }

        $jwtToken = $responseData['id_token'] ?? null;
        if (null === $jwtToken) {
            throw new RuntimeException(sprintf('No access token found in response %s', $response->getContent()));
        }

        $refreshToken = $responseData['refresh_token'] ?? null;
        if (null === $refreshToken) {
            throw new RuntimeException(sprintf('No refresh token found in response %s', $response->getContent()));
        }

        $user = $this->userProvider->loadUserByIdentifier($jwtToken);

        $request = $event->getRequest();
        $jwtExpires = $request->attributes->get('_app_jwt_expires');
        if (null === $jwtExpires) {
            throw new \LogicException('Missing _app_jwt_expires in the session');
        }
        $request->attributes->remove('_app_jwt_expires');

        $token->setAttribute(TokensBag::class, new TokensBag(
            $responseData['access_token'] ?? null,
            $refreshToken,
            $jwtExpires,
        ));

        $token->setUser($user);
    }

    public static function getSubscribedEvents(): array
    {
        return [RequestEvent::class => 'onKernelRequest'];
    }

}