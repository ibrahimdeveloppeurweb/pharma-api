<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicWebController extends AbstractController
{
    #[Route("/", name: "app_home")]
    public function home(): Response
    {
        return $this->render('public/index.html.twig');
    }

    #[Route("/recherche-medicaments", name: "app_public_recherche")]
    public function index(): Response
    {
        return $this->render('public/recherche.html.twig');
    }

    #[Route("/estimation-prix", name: "app_public_prix")]
    public function prix(): Response
    {
        return $this->render('public/prix.html.twig');
    }

    #[Route("/pharmacies-garde", name: "app_public_garde")]
    public function garde(\App\Repository\Gestion\PharmacieRepository $pharmacieRepository): Response
    {
        $pharmacies = $pharmacieRepository->findBy(['isDeGarde' => true]);
        
        // Dates dynamiques
        $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        $debut = new \DateTime('monday this week');
        $fin = new \DateTime('sunday this week');
        
        $dateDebut = $debut->format('d') . ' ' . $mois[(int)$debut->format('n') - 1];
        $dateFin = $fin->format('d') . ' ' . $mois[(int)$fin->format('n') - 1] . ' ' . $fin->format('Y');

        return $this->render('public/garde.html.twig', [
            'pharmacies' => $pharmacies,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin
        ]);
    }
}
