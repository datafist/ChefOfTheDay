<?php

namespace App\Controller\Admin;

use App\Entity\Party;
use App\Form\PartyType;
use App\Repository\PartyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/party')]
#[IsGranted('ROLE_ADMIN')]
class PartyController extends AbstractController
{
    #[Route('/', name: 'admin_party_index', methods: ['GET'])]
    public function index(PartyRepository $partyRepository, EntityManagerInterface $entityManager): Response
    {
        $parties = $partyRepository->findAll();
        
        // Hole aktives Kita-Jahr
        $activeYear = $entityManager->getRepository(\App\Entity\KitaYear::class)
            ->findOneBy(['isActive' => true]);
        
        // Zähle Zuweisungen pro Familie für das aktive Jahr
        $assignmentCounts = [];
        if ($activeYear) {
            foreach ($parties as $party) {
                $count = $entityManager->getRepository(\App\Entity\CookingAssignment::class)
                    ->count([
                        'party' => $party,
                        'kitaYear' => $activeYear
                    ]);
                $assignmentCounts[$party->getId()] = $count;
            }
        }
        
        return $this->render('admin/party/index.html.twig', [
            'parties' => $parties,
            'activeYear' => $activeYear,
            'assignmentCounts' => $assignmentCounts,
        ]);
    }

    #[Route('/new', name: 'admin_party_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $party = new Party();
        
        // Beim ersten Laden: Vorausfüllen mit einem leeren Kind und einem leeren Elternteil
        if (!$request->isMethod('POST')) {
            $party->setChildren([
                ['name' => '', 'birthYear' => (int)date('Y') - 5] // Beispiel-Geburtsjahr
            ]);
            $party->setParentNames(['']);
        }
        
        $form = $this->createForm(PartyType::class, $party);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($party);
            $entityManager->flush();

            $this->addFlash('success', 'Familie erfolgreich angelegt.');
            return $this->redirectToRoute('admin_party_index');
        }

        return $this->render('admin/party/new.html.twig', [
            'party' => $party,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_party_show', methods: ['GET'])]
    public function show(Party $party, EntityManagerInterface $entityManager): Response
    {
        // Hole aktives Kita-Jahr
        $activeYear = $entityManager->getRepository(\App\Entity\KitaYear::class)
            ->findOneBy(['isActive' => true]);
        
        // Hole Zuweisungen für das aktive Jahr
        $assignments = [];
        $assignmentCount = 0;
        if ($activeYear) {
            $assignments = $entityManager->getRepository(\App\Entity\CookingAssignment::class)
                ->findBy(
                    [
                        'party' => $party,
                        'kitaYear' => $activeYear
                    ],
                    ['assignedDate' => 'ASC']
                );
            $assignmentCount = count($assignments);
        }
        
        return $this->render('admin/party/show.html.twig', [
            'party' => $party,
            'activeYear' => $activeYear,
            'assignments' => $assignments,
            'assignmentCount' => $assignmentCount,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_party_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Party $party, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PartyType::class, $party);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Familie erfolgreich aktualisiert.');
            return $this->redirectToRoute('admin_party_index');
        }

        return $this->render('admin/party/edit.html.twig', [
            'party' => $party,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_party_delete', methods: ['POST'])]
    public function delete(Request $request, Party $party, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$party->getId(), $request->request->get('_token'))) {
            $entityManager->remove($party);
            $entityManager->flush();
            $this->addFlash('success', 'Familie erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('admin_party_index');
    }
}
