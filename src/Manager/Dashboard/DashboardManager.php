<?php

namespace App\Manager\Dashboard;

use App\Entity\Security\User;
use App\Entity\Gestion\Demande;
use App\Entity\Gestion\Pharmacie;
use App\Repository\Gestion\MedicamentRepository;
use App\Repository\Gestion\DemandeRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DashboardManager
{
    private ?User $user;
    private MedicamentRepository $medicamentRepository;
    private DemandeRepository $demandeRepository;

    public function __construct(
        MedicamentRepository $medicamentRepository,
        DemandeRepository $demandeRepository,
        TokenStorageInterface $tokenStorage

    ) {
        $this->medicamentRepository = $medicamentRepository;
        $this->demandeRepository = $demandeRepository;
        
        if ($tokenStorage->getToken()) {
            $this->user = $tokenStorage->getToken()->getUser();
        }
    }

    



    public function dashboard(object $data, Pharmacie $pharmacie)
    {
        // 1️⃣ Définir les valeurs par défaut
        // $data->dateD = "2025-10-22";
        // $data->dateF = "2025-10-22";
        // $data->period = "today";
        // Dates de début et de fin personnalisées ou par défaut
        $dateD = $data->dateD ? new \DateTime($data->dateD) : new \DateTime((new \DateTime())->format('Y-01-01'));
        $dateF = $data->dateF ? new \DateTime($data->dateF) : new \DateTime((new \DateTime())->format('Y-12-31'));

        $en_attente = 0;
        $notifie = 0;
        $recupere = 0;

        $prcR = 0;
        $prcN = 0;
        $somR = 0;
        // 2️⃣ Déterminer les intervalles selon la période
        $periode = match ($data->period) {
            'today' => $this->intervalTwoHours($dateD, $dateF),
            'week'  => $this->weeksBetween($dateD, $dateF),
            'month' => $this->weeksOfMonths($dateD, $dateF),
            'year'  => $this->yearToMonths($dateD, $dateF),
            default => $this->yearToMonths($dateD, $dateF),
        };

        // 3️⃣ Initialiser le tableau de résultats
        $datas = ['graph' => ['nb_d' => [], 'time' => []]];
        $tmp = &$datas['graph']; // Référence pour simplifier l’accès
        $datas['medicaments'] = $this->medicamentRepository->findMostMedicaments($data, $pharmacie);
        $demandes = $this->demandeRepository->findIndex($data,null,$pharmacie);
        foreach ($demandes as $demande) {
            if ($demande->getStatut() === Demande::STATUT_NOTIFIE) {
                $prcN += 1;
            }
            if ($demande->getStatut() === Demande::STATUT_RECUPERE) {
                $prcR += 1;
            }
        }
        $datas['prc']['prcN'] = 0;
        $datas['prc']['prcR'] = 0;

        if (count($demandes) > 0) {
            $datas['prc']['prcN'] = round(($prcN * 100) / count($demandes), 2);
            $datas['prc']['prcR'] = round(($prcR * 100) / count($demandes), 2);
        }

     

          foreach ($demandes as $key => $item) {
            if ($item->getStatut() === Demande::STATUT_EN_ATTENTE) {
                $en_attente += 1;
            }
            if ($item->getStatut() === Demande::STATUT_NOTIFIE) {
                $notifie += 1;
            }
            if ($item->getStatut() === Demande::STATUT_RECUPERE) {
                $recupere += 1;
                foreach ($item->getDemandeMedicaments() as $dm) {  
                       $somR += $dm->getPrix() * $dm->getQuantite();
                   }  
             
            }
        }
         
        $datas['widget']['somR'] = $somR ;
        $datas['widget']['en_attente'] = $en_attente;
        $datas['widget']['notifie'] = $notifie;
        $datas['widget']['recupere'] = $recupere;

        // 4️⃣ Génération des données du graphique selon la période
        switch ($data->period) {
            case 'today':
                foreach ($periode as $interval) {
                    $data->dateD = $interval['start'];
                    $data->dateF = $interval['end'];

                    $results = $this->demandeRepository->findIndex($data, null, $pharmacie);
                    $tmp['nb_d'][] = count($results);
                    $tmp['time'][] = (new \DateTime($interval['end']))->format('H') . 'h';
                }
                break;

            case 'week':
                $jours = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
                $startOfWeek = (clone $dateD)->modify('monday this week');

                for ($i = 0; $i < 7; $i++) {
                    $currentDay = (clone $startOfWeek)->modify("+$i day");
                    $data->dateD = $currentDay->format('Y-m-d');
                    $data->dateF = $currentDay->format('Y-m-d');

                    $results = $this->demandeRepository->findIndex($data, null, $pharmacie);
                    $tmp['nb_d'][] = count($results);
                    $tmp['time'][] = $jours[$i];
                }
                break;

            case 'month':
                // Libellés pour les semaines
                $semaines = ['Sem1', 'Sem2', 'Sem3', 'Sem4', 'Sem5'];
                $index = 0;

                foreach ($periode as $interval) {
                    if (!isset($semaines[$index])) break; // Sécurité si plus de 5 semaines
                    $data->dateD = $interval['start'];
                    $data->dateF = $interval['end'];

                    $results = $this->demandeRepository->findIndex($data, null, $pharmacie);
                    $tmp['nb_d'][] = count($results);
                    $tmp['time'][] = $semaines[$index] ;
                    $index++;
                }
                break;

            case 'year':
                $mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

                $index = 0;
                foreach ($periode as $interval) {
                    $data->dateD = $interval['start'];
                    $data->dateF = $interval['end'];

                    $results = $this->demandeRepository->findIndex($data, null, $pharmacie);
                    $tmp['nb_d'][] = count($results);

                    $label = $mois[$index] ?? 'Mois' . ($index + 1);
                    $tmp['time'][] = $label;

                    $index++;
                }
                    break;
            default:
                $mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

                $index = 0;
                foreach ($periode as $interval) {
                    $data->dateD = $interval['start'];
                    $data->dateF = $interval['end'];

                    $results = $this->demandeRepository->findIndex($data, null, $pharmacie);
                    $tmp['nb_d'][] = count($results);

                    $label = $mois[$index] ?? 'Mois' . ($index + 1);
                    $tmp['time'][] = $label;

                    $index++;
                }
                    break;
            }

        return $datas;
    }




    public function intervalTwoHours(\DateTime $dateD, \DateTime $dateF)
    {
        $intervals = [];

        // On suppose que $dateD et $dateF sont le même jour
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

  public function weeksBetween(\DateTime $dateD, \DateTime $dateF)
    {
        $weeks = [];

        // Cloner les dates pour éviter de les modifier
        $start = clone $dateD;
        $end = clone $dateF;

        // Ajuster le début à lundi de la semaine courante
        if ($start->format('N') != 1) { // N = 1 (lundi)
            $start->modify('last monday');
        }

        // Ajuster la fin au dimanche de la semaine courante
        if ($end->format('N') != 7) { // N = 7 (dimanche)
            $end->modify('next sunday');
        }

        while ($start <= $end) {
            $weekStart = clone $start;
            $weekEnd = (clone $start)->modify('next sunday');

            // Corriger si la première semaine commence avant la date de début réelle
            if ($weekStart < $dateD) {
                $weekStart = clone $dateD;
            }

            // Corriger si la dernière semaine dépasse la date de fin réelle
            if ($weekEnd > $dateF) {
                $weekEnd = clone $dateF;
            }

            $weeks[] = [
                'start' => $weekStart->format('Y-m-d'),
                'end' => $weekEnd->format('Y-m-d'),
            ];

            // Passer à la semaine suivante
            $start->modify('+1 week');
        }

        return $weeks;
    }

   public function weeksOfMonths(\DateTime $dateD, \DateTime $dateF): array
    {
        $weeks = [];

        $start = (clone $dateD)->modify('first day of this month')->setTime(0, 0, 0);
        $end = (clone $dateF)->modify('last day of this month')->setTime(23, 59, 59);

        $cursor = clone $start;

        while ($cursor <= $end) {
            $weekStart = clone $cursor;
            $weekEnd = (clone $cursor)->modify('+6 days');

            // ✅ Ne pas dépasser la fin du mois
            if ($weekEnd > $end) {
                $weekEnd = clone $end;
            }

            $weeks[] = [
                'start' => $weekStart->format('Y-m-d'),
                'end'   => $weekEnd->format('Y-m-d'),
            ];

            // Avancer au jour suivant de la dernière fin
            $cursor = (clone $weekEnd)->modify('+1 day');
        }

        return $weeks;
    }



    public function yearToMonths(\DateTime $dateD)
    {
        $months = [];

        // Début au 1er janvier de l’année de $dateD
        $start = (new \DateTime($dateD->format('Y-01-01')))->setTime(0,0,0);
        // Fin au 31 décembre de la même année
        $end = (new \DateTime($dateD->format('Y-12-31')))->setTime(23,59,59);

        while ($start <= $end) {
            $monthStart = clone $start;
            $monthEnd = (clone $start)->modify('last day of this month')->setTime(23,59,59);

            $months[] = [
                'start' => $monthStart->format('Y-m-d'),
                'end' => $monthEnd->format('Y-m-d'),
            ];

            // Passer au mois suivant
            $start->modify('first day of next month');
        }

        return $months;
    }














  

  
}