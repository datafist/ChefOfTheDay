<?php

namespace App\Controller\Admin;

use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/profile')]
#[IsGranted('ROLE_ADMIN')]
class ProfileController extends AbstractController
{
    #[Route('/change-password', name: 'admin_change_password')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            
            // Prüfe aktuelles Passwort
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Das aktuelle Passwort ist falsch.');
                return $this->redirectToRoute('admin_change_password');
            }

            // Neues Passwort setzen
            $newPassword = $form->get('newPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            
            $entityManager->flush();

            $this->addFlash('success', 'Passwort erfolgreich geändert!');
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/profile/change_password.html.twig', [
            'changePasswordForm' => $form,
        ]);
    }
}
