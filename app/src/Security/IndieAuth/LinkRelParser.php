<?php

declare(strict_types=1);

namespace App\Security\IndieAuth;

use App\Security\IndieAuth\Exception\AuthorizationEndpointNotFoundException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class LinkRelParser
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * @throws AuthorizationEndpointNotFoundException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getAuthorizationEndpoint(string $url): string
    {
        $urlContent = $this->httpClient->request('GET', $url);
        $crawler = new Crawler($urlContent->getContent());
        $array = array_filter(
            $crawler->filter(selector: 'link')->each(function (Crawler $node) {
                if ('authorization_endpoint' === $node->attr('rel')) {
                    return $node->attr('href');
                }

                return null;
            })
        );

        if (count($array) < 1) {
            throw new AuthorizationEndpointNotFoundException('Authorization endpoint not found');
        }

        return reset($array);
    }
}
