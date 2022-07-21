<?php

namespace App\Security;

use App\Security\Client\OpenIdClient;
use App\Security\Dto\TokensBag;
use App\Security\Exception\InvalidStateException;
use App\Security\Exception\OpenIdServerException;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class OpenIdAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface, InteractiveAuthenticatorInterface
{
    private const STATE_QUERY_KEY = 'state';
    private const STATE_SESSION_KEY = 'openid_state';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private OpenIdClient $openIdClient,
        private RequestStack $requestStack,
        private string $authorizationEndpoint,
        private string $clientId,
    ) {}

    public function supports(Request $request): ?bool
    {
        return 'openid_redirecturi' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $sessionState = $request->getSession()->get(self::STATE_SESSION_KEY);
        $queryState = $request->get(self::STATE_QUERY_KEY);
        if ($queryState === null || $queryState !== $sessionState) {
            throw new InvalidStateException(sprintf(
                'query state (%s) is not the same as session state (%s)',
                $queryState ?? 'NULL',
                $sessionState ?? 'NULL',
            ));
        }

        $request->getSession()->remove(self::STATE_SESSION_KEY);

        try {
            $response = $this->openIdClient->getTokenFromAuthorizationCode($request->query->get('code', ''));
        } catch (HttpExceptionInterface $e) {
            throw new OpenIdServerException(sprintf(
                'Bad status code returned by openID server (%s)',
                $e->getResponse()->getStatusCode(),
            ), previous: $e);
        }

        $responseData = json_decode($response, true);
        if (false === $responseData) {
            throw new OpenIdServerException(sprintf('Can\'t parse json in response: %s', $response->getContent()));
        }

        $jwtToken = $responseData['id_token'] ?? null;
        if (null === $jwtToken) {
            throw new OpenIdServerException(sprintf('No access token found in response %s', $response->getContent()));
        }

        $refreshToken = $responseData['refresh_token'] ?? null;
        if (null === $refreshToken) {
            throw new RuntimeException(sprintf('No refresh token found in response %s', $response->getContent()));
        }

        $userBadge = new UserBadge($jwtToken);

        $passport = new SelfValidatingPassport($userBadge, [new PreAuthenticatedUserBadge()]);

        $passport->setAttribute(TokensBag::class, new TokensBag($responseData['access_token'] ?? null, $refreshToken));

        return $passport;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('profile'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add(
            'error',
            'An authentication error occured',
        );

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        $state = (string)Uuid::v4();
        $request->getSession()->set(self::STATE_SESSION_KEY, $state);

        $qs = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'state' => $state,
            'scope' => 'openid roles profile email',
            // Force http since working on localhost
            'redirect_uri' => 'http:'.$this->urlGenerator->generate('openid_redirecturi', [], UrlGeneratorInterface::NETWORK_PATH),
        ]);

        return new RedirectResponse(sprintf('%s?%s', $this->authorizationEndpoint, $qs));
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);

        if (!$passport instanceof Passport) {
            throw new \LogicException(sprintf('Passport must be a subclass of %s, %s given', Passport::class, get_class($passport)));
        }

        $currentRequest = $this->requestStack->getCurrentRequest();
        if (null === $currentRequest) {
            throw new LogicException(sprintf('%s can only be used in an http context', __CLASS__));
        }
        $jwtExpires = $currentRequest->attributes->get('_app_jwt_expires');
        if (null === $jwtExpires) {
            throw new \LogicException('Missing _app_jwt_expires in the session');
        }
        $currentRequest->attributes->remove('_app_jwt_expires');

        $tokens = $passport->getAttribute(TokensBag::class);
        if (null === $tokens) {
            throw new \LogicException(sprintf('Can\'t find %s in passport attributes', TokensBag::class));
        }
        $token->setAttribute(TokensBag::class, $tokens->withExpiration($jwtExpires));

        return $token;
    }

    public function isInteractive(): bool
    {
        return true;
    }
}
