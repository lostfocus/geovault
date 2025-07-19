<?php

declare(strict_types=1);

namespace App\Security\IndieAuth;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class IndieAuthAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly IndieAuth $indieAuth,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
        private readonly string $adminMe,
    ) {
    }

    public function supports(Request $request): bool
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $this->logger->debug('get', ['code' => $code, 'state' => $state]);
        if (null === $code) {
            return false;
        }

        return null !== $state;
    }

    /**
     * Create a passport for the current request.
     *
     * The passport contains the user, credentials and any additional information
     * that has to be checked by the Symfony Security system. For example, a login
     * form authenticator will probably return a passport containing the user, the
     * presented password and the CSRF token value.
     *
     * You may throw any AuthenticationException in this method in case of error (e.g.
     * a UserNotFoundException when the user cannot be found).
     *
     * @throws AuthenticationException
     */
    public function authenticate(Request $request): Passport
    {
        $code = $request->get('code');
        $state = $request->get('state');
        if (!is_string($code)) {
            throw new AuthenticationException('Wrong Authorization Code');
        }
        if (!is_string($state)) {
            throw new AuthenticationException('Wrong Authorization State');
        }
        try {
            $userData = $this->indieAuth->getUserData($code, $state);
        } catch (\Throwable $e) {
            $this->logger->error('authentication error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw new AuthenticationException($e->getMessage(), $e->getCode(), $e);
        }
        if (!is_string($userData['me'])) {
            throw new AuthenticationException('Wrong Authorization Me');
        }
        $user = $this->userRepository->findOneBy(['me' => $userData['me']]);
        if (null === $user) {
            $user = new User()
                ->setMe($userData['me']);
            $this->entityManager->persist($user);
        }

        if ($user->getMe() === $this->adminMe) {
            $roles = $user->getRoles();
            if (!in_array('ROLE_ADMIN', $roles, true)) {
                $roles[] = 'ROLE_ADMIN';
            }
            $user->setRoles($roles);
        }

        $this->entityManager->flush();

        return new SelfValidatingPassport(
            new UserBadge(
                (string) $user->getMe()
            ),
            [
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->logger->debug('onAuthenticationSuccess', [
            'token' => $token->getUserIdentifier(),
            'firewall' => $firewallName,
        ]);

        return null;
    }

    /**
     * Called when authentication executed, but failed (e.g. wrong username password).
     *
     * This should return the Response sent back to the user, like a
     * RedirectResponse to the login page or a 403 response.
     *
     * If you return null, the request will continue, but the user will
     * not be authenticated. This is probably not what you want to do.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response(null, 403);
    }
}
