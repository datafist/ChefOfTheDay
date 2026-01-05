<?php

namespace App\Controller;

use App\Repository\PartyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok', 'timestamp' => time()]);
    }
}
