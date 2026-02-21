<?php

declare(strict_types=1);

namespace App\Security\IndieAuth;

use App\Security\IndieAuth\Exception\IndieAuthException;
use RandomLib\Factory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class IndieAuth
{
    public function __construct(
        private LinkRelParser $linkRelParser,
        private string $host,
        private RouterInterface $router,
        private RequestStack $requestStack,
        private Factory $randomLibFactory,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws ClientExceptionInterface
     * @throws Exception\AuthorizationEndpointNotFoundException
     * @throws IndieAuthException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    public function getUserData(string $code, string $state): array
    {
        $session = $this->requestStack->getSession();

        if ($state !== $session->get('indieAuthState')) {
            throw new IndieAuthException('State mismatch');
        }

        /** @var string|null $url */
        $url = $session->get('indieAuthAuthorizationEndpoint');
        if (null === $url) {
            throw new IndieAuthException('Endpoint mismatch');
        }

        /** @var string|null $codeVerifier */
        $codeVerifier = $session->get('indieAuthVerifier');
        if (null === $codeVerifier) {
            throw new IndieAuthException('Empty code verifier');
        }

        /** @var string|null $me */
        $me = $session->get('indieAuthMe');
        if (null === $me) {
            throw new IndieAuthException('Missing me');
        }

        $urlParameters = [
            'response_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->host,
            'redirect_uri' => $this->router->generate('indieauth_redirect', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'code_verifier' => $codeVerifier,
        ];

        $return = $this->httpClient->request('POST', $url, [
            'body' => $urlParameters,
        ]);
        /** @var array<int|string, mixed> $decoded */
        $decoded = json_decode($return->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if ($decoded['me'] !== $me && is_string($decoded['me'])) {
            $checkUrl = $this->linkRelParser->getAuthorizationEndpoint($decoded['me']);
            if ($checkUrl !== $url) {
                throw new IndieAuthException('Wrong Authorization Endpoint');
            }
        }

        return $decoded;
    }

    /**
     * @throws Exception\AuthorizationEndpointNotFoundException
     * @throws IndieAuthException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getAuthorizationEndpoint(?string $me): string
    {
        if (null === $me) {
            throw new IndieAuthException('Missing me');
        }
        $session = $this->requestStack->getSession();
        $url = $this->linkRelParser->getAuthorizationEndpoint($me);

        $session->set('indieAuthMe', $me);
        $session->set('indieAuthAuthorizationEndpoint', $url);

        $state = uniqid('', true);
        $session->set('indieAuthState', $state);

        $generator = $this->randomLibFactory->getMediumStrengthGenerator();
        $verifier = $generator->generateString(50);

        $session->set('indieAuthVerifier', $verifier);

        $verifierHash = hash('sha256', $verifier, true);
        $verifierHashEncoded = rtrim(strtr(base64_encode($verifierHash), '+/', '-_'), '=');

        $urlParameters = [
            'response_type' => 'code',
            'client_id' => $this->host,
            'redirect_uri' => $this->router->generate('indieauth_redirect', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'state' => $state,
            'code_challenge' => $verifierHashEncoded,
            'code_challenge_method' => 'S256',
            'me' => $me,
            'scope' => 'profile',
        ];

        return $url.'?'.http_build_query($urlParameters);
    }
}
