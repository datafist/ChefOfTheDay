<?php

namespace App\Security;

use App\Entity\Party;
use App\Repository\PartyRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class ParentAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly PartyRepository $partyRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'parent_login'
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $partyId = $request->request->get('party_id');
        $password = $request->request->get('password');

        return new Passport(
            new UserBadge($partyId, function($partyId) {
                return $this->partyRepository->find($partyId);
            }),
            new CustomCredentials(
                function($credentials, Party $party) {
                    // Passwort: Erster Buchstabe + Geburtsjahr
                    $expectedPassword = $party->getGeneratedPassword();
                    return $credentials === $expectedPassword;
                },
                $password
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('parent_availability'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', 'Ungültiges Passwort');
        return new RedirectResponse($this->urlGenerator->generate('parent_login'));
    }
}
