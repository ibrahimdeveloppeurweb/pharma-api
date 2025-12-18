<?php

namespace App\Manager\Dashboard;

use App\Entity\Security\User;
use App\Entity\Gestion\Demande;
use App\Repository\Gestion\DemandeRepository;
use App\Repository\Gestion\PharmacieRepository;

class AdminDashboardManager
{
    private PharmacieRepository $pharmacieRepository;
    private DemandeRepository $demandeRepository;

    public function __construct(
        PharmacieRepository $pharmacieRepository,
        DemandeRepository $demandeRepository
    ) {
        $this->pharmacieRepository = $pharmacieRepository;
        $this->demandeRepository = $demandeRepository;
    }

    public function dashboard(object $data)
    {
        // Dates de début et de fin

        //  $data->dateD = "2025-10-25";
        // $data->dateF = "2025-10-25";
        // $data->period = "today";
        $dateD = $data->dateD ? new \DateTime($data->dateD) : new \DateTime((new \DateTime())->format('Y-01-01'));
        $dateF = $data->dateF ? new \DateTime($data->dateF) : new \DateTime((new \DateTime())->format('Y-12-31'));

        // Déterminer les intervalles selon la période
        $periode = match ($data->period) {
            'today' => $this->intervalTwoHours($dateD, $dateF),
            'week'  => $this->weeksBetween($dateD, $dateF),
            'month' => $this->weeksOfMonths($dateD, $dateF),
            'year'  => $this->yearToMonths($dateD),
            default => $this->yearToMonths($dateD),
        };

        // Initialiser les données
        $datas = [
            'graph' => ['nb_inscriptions' => [], 'time' => []],
            'widgets' => [],
            'top_pharmacies' => [],
            'repartition_villes' => []
        ];

        // 1️⃣ WIDGETS - Statistiques globales
        $toutes_pharmacies = $this->pharmacieRepository->findAll();
        $total_pharmacies = count($toutes_pharmacies);
       
        // Pharmacies actives (ayant au moins 1 demande)
        $pharmacies_actives = $this->pharmacieRepository->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->leftJoin('p.demandes', 'd')
            ->where('d.id IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        // Nouvelles inscriptions dans la période
        $nouvelles_inscriptions = $this->pharmacieRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt BETWEEN :dateD AND :dateF')
            ->setParameter('dateD', $dateD)
            ->setParameter('dateF', $dateF)
            ->getQuery()
            ->getSingleScalarResult();

        // Taux d'activité
        $taux_activite = $total_pharmacies > 0 
            ? round(($pharmacies_actives * 100) / $total_pharmacies, 2) 
            : 0;    

        // Total demandes toutes pharmacies
        $total_demandes = $this->demandeRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.createdAt BETWEEN :dateD AND :dateF')
            ->setParameter('dateD', $dateD)
            ->setParameter('dateF', $dateF)
            ->getQuery()
            ->getSingleScalarResult();

        // Demandes traitées (notifiées + récupérées)
        $demandes_traitees = $this->demandeRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.createdAt BETWEEN :dateD AND :dateF')
            ->andWhere('d.statut IN (:statuts)')
            ->setParameter('dateD', $dateD)
            ->setParameter('dateF', $dateF)
            ->setParameter('statuts', [Demande::STATUT_NOTIFIE, Demande::STATUT_RECUPERE])
            ->getQuery()
            ->getSingleScalarResult();

        $datas['widgets'] = [
            'total_pharmacies' => $total_pharmacies,
            'pharmacies_actives' => $pharmacies_actives,
            'nouvelles_inscriptions' => $nouvelles_inscriptions,
            'taux_activite' => $taux_activite,
            'total_demandes' => $total_demandes,
            'demandes_traitees' => $demandes_traitees
        ];

        // 2️⃣ GRAPHIQUE - Nouvelles inscriptions par période
        switch ($data->period) {
            case 'today':
                foreach ($periode as $interval) {
                   
                    $count = $this->pharmacieRepository->createQueryBuilder('p')
                        ->select('COUNT(p.id)')
                         ->where('p.createdAt >= :start')
                        ->andWhere('p.createdAt <= :end')
                        ->setParameter('start', $interval['start'])
                        ->setParameter('end', $interval['end'])
                        ->getQuery()
                        ->getSingleScalarResult();

                    $datas['graph']['nb_inscriptions'][] = $count;
                    $datas['graph']['time'][] = (new \DateTime($interval['end']))->format('H') . 'h';
                }
                break;

            case 'week':
                $jours = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
                $startOfWeek = (clone $dateD)->modify('monday this week');

                for ($i = 0; $i < 7; $i++) {
                    $currentDay = (clone $startOfWeek)->modify("+$i day");
                    $dayStart = $currentDay->format('Y-m-d 00:00:00');
                    $dayEnd = $currentDay->format('Y-m-d 23:59:59');

                    $count = $this->pharmacieRepository->createQueryBuilder('p')
                        ->select('COUNT(p.id)')
                        ->where('p.createdAt BETWEEN :start AND :end')
                        ->setParameter('start', $dayStart)
                        ->setParameter('end', $dayEnd)
                        ->getQuery()
                        ->getSingleScalarResult();

                    $datas['graph']['nb_inscriptions'][] = $count;
                    $datas['graph']['time'][] = $jours[$i];
                }
                break;

            case 'month':
                $semaines = ['Sem1', 'Sem2', 'Sem3', 'Sem4', 'Sem5'];
                $index = 0;

                foreach ($periode as $interval) {
                    if (!isset($semaines[$index])) break;

                    $count = $this->pharmacieRepository->createQueryBuilder('p')
                        ->select('COUNT(p.id)')
                        ->where('p.createdAt BETWEEN :start AND :end')
                        ->setParameter('start', $interval['start'])
                        ->setParameter('end', $interval['end'])
                        ->getQuery()
                        ->getSingleScalarResult();

                    $datas['graph']['nb_inscriptions'][] = $count;
                    $datas['graph']['time'][] = $semaines[$index];
                    $index++;
                }
                break;

            case 'year':
            default:
                $mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
                $index = 0;

                foreach ($periode as $interval) {
                    $count = $this->pharmacieRepository->createQueryBuilder('p')
                        ->select('COUNT(p.id)')
                        ->where('p.createdAt BETWEEN :start AND :end')
                        ->setParameter('start', $interval['start'])
                        ->setParameter('end', $interval['end'])
                        ->getQuery()
                        ->getSingleScalarResult();

                    $datas['graph']['nb_inscriptions'][] = $count;
                    $label = $mois[$index] ?? 'Mois' . ($index + 1);
                    $datas['graph']['time'][] = $label;
                    $index++;
                }
                break;
        }

        // 3️⃣ TOP 5 PHARMACIES PERFORMANTES (nombre de demandes)
        $topPharmacies = $this->demandeRepository->createQueryBuilder('d')
            ->select('p.id, p.nom, p.ville, COUNT(d.id) as nb_demandes')
            ->join('d.pharmacie', 'p')
            ->where('d.createdAt BETWEEN :dateD AND :dateF')
            ->setParameter('dateD', $dateD)
            ->setParameter('dateF', $dateF)
            ->groupBy('p.id')
            ->orderBy('nb_demandes', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $datas['top_pharmacies'] = array_map(function($item) {
            return [
                'nom' => $item['nom'],
                'demandes' => $item['nb_demandes'],
                'ville' => $item['ville']
            ];
        }, $topPharmacies);

        // 4️⃣ RÉPARTITION GÉOGRAPHIQUE (pharmacies par ville)
        $repartition = $this->pharmacieRepository->createQueryBuilder('p')
            ->select('p.ville, COUNT(p.id) as nb_pharmacies')
            ->groupBy('p.ville')
            ->orderBy('nb_pharmacies', 'DESC')
            ->getQuery()
            ->getResult();

        $datas['repartition_villes'] = [];
        foreach ($repartition as $item) {
            $datas['repartition_villes'][$item['ville']] = $item['nb_pharmacies'];
        }
    
        return $datas;
    }

    // Méthodes utilitaires (identiques au DashboardManager)
    private function intervalTwoHours(\DateTime $dateD, \DateTime $dateF)
    {
        $intervals = [];
        $start = (clone $dateD)->setTime(0, 0, 0);
        $end = (clone $dateF)->setTime(23, 59, 59);

        while ($start < $end) {
            $intervalStart = clone $start;
            $start->modify('+2 hours');
            $intervalEnd = $start > $end ? clone $end : clone $start;

            $intervals[] = [
                'start' => $intervalStart->format('Y-m-d H:i:s'),
                'end' => $intervalEnd->format('Y-m-d H:i:s'),
            ];
        }

        return $intervals;
    }

    private function weeksBetween(\DateTime $dateD, \DateTime $dateF)
    {
        $weeks = [];
        $start = clone $dateD;
        $end = clone $dateF;

        if ($start->format('N') != 1) {
            $start->modify('last monday');
        }

        if ($end->format('N') != 7) {
            $end->modify('next sunday');
        }

        while ($start <= $end) {
            $weekStart = clone $start;
            $weekEnd = (clone $start)->modify('next sunday');

            if ($weekStart < $dateD) {
                $weekStart = clone $dateD;
            }

            if ($weekEnd > $dateF) {
                $weekEnd = clone $dateF;
            }

            $weeks[] = [
                'start' => $weekStart->format('Y-m-d'),
                'end' => $weekEnd->format('Y-m-d'),
            ];

            $start->modify('+1 week');
        }

        return $weeks;
    }

    private function weeksOfMonths(\DateTime $dateD, \DateTime $dateF): array
    {
        $weeks = [];
        $start = (clone $dateD)->modify('first day of this month')->setTime(0, 0, 0);
        $end = (clone $dateF)->modify('last day of this month')->setTime(23, 59, 59);
        $cursor = clone $start;

        while ($cursor <= $end) {
            $weekStart = clone $cursor;
            $weekEnd = (clone $cursor)->modify('+6 days');

            if ($weekEnd > $end) {
                $weekEnd = clone $end;
            }

            $weeks[] = [
                'start' => $weekStart->format('Y-m-d'),
                'end'   => $weekEnd->format('Y-m-d'),
            ];

            $cursor = (clone $weekEnd)->modify('+1 day');
        }

        return $weeks;
    }

    private function yearToMonths(\DateTime $dateD)
    {
        $months = [];
        $start = (new \DateTime($dateD->format('Y-01-01')))->setTime(0,0,0);
        $end = (new \DateTime($dateD->format('Y-12-31')))->setTime(23,59,59);

        while ($start <= $end) {
            $monthStart = clone $start;
            $monthEnd = (clone $start)->modify('last day of this month')->setTime(23,59,59);

            $months[] = [
                'start' => $monthStart->format('Y-m-d'),
                'end' => $monthEnd->format('Y-m-d'),
            ];

            $start->modify('first day of next month');
        }

        return $months;
    }
}