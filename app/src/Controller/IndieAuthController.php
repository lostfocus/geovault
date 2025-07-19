<?php

namespace App\Controller;

use App\Security\IndieAuth\Exception\AuthorizationEndpointNotFoundException;
use App\Security\IndieAuth\Exception\IndieAuthException;
use App\Security\IndieAuth\FormDto;
use App\Security\IndieAuth\IndieAuth;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class IndieAuthController extends AbstractController
{
    public function __construct(private readonly IndieAuth $indieAuth)
    {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws AuthorizationEndpointNotFoundException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws IndieAuthException
     * @throws ClientExceptionInterface
     */
    #[Route('/login', name: 'indieauth', priority: 2)]
    public function index(Request $request): Response
    {
        $formDto = new FormDto();

        $form = $this->createFormBuilder($formDto)
            ->add('url', UrlType::class, ['default_protocol' => 'https'])
            ->add('save', SubmitType::class, ['label' => 'Login'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirect($this->indieAuth->getAuthorizationEndpoint($formDto->getUrl()));
        }

        return $this->render('indie_auth/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/login/redirect', name: 'indieauth_redirect', priority: 2)]
    public function redirectAction(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/logout', name: 'logout', priority: 2)]
    public function logoutAction(): Response
    {
        throw new \RuntimeException('Huch');
    }
}
