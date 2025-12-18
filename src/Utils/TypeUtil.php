<?php

namespace App\Utils;

class TypeUtil
{
    /**
     * Verifie si la valeur est null
     * @var $value
     * @return bool
     */
    public static function is_not_null($value): bool {
        if($value === 'undefined' or $value === null or $value === 'null' or $value === '' or $value === []) {
            return false;
        }
        return true;
    }


    /**
     * Forcer une chaine de caractere ou un entier en boolean
     * @param mixed $value
     * @return bool|null
     */
    public static function is_bool(mixed $value): ?bool
    {
        if(isset($value) && self::is_not_null($value)) {
            $value = $value === false ? $value : strtolower(trim($value));
            if($value === 'true' || $value === true || $value === '1' || $value === 'oui') {
                return true;
            } elseif ($value === 'false' || $value === false || $value === '0' || $value === 'non') {
                return false;
            } else {
                return null;
            }
        }
        return null;
    }
    
    /**
     * Decodeur de caractere specieaux
     * @param string $value
     * @return string
     */
    public static function decodeHtml(string $value= null)
    {
        $retour = html_entity_decode($value, ENT_COMPAT, 'UTF-8');
        $p2 = -1;
        for(;;) {
            $p = strpos($retour, '&#', $p2+1);
            if ($p === FALSE)
                break;
            $p2 = strpos($retour, ';', $p);
            if ($p2 === FALSE)
                break;
               
            if (substr($retour, $p+2, 1) == 'x')
                $char = hexdec(substr($retour, $p+3, $p2-$p-3));
            else
                $char = intval(substr($retour, $p+2, $p2-$p-2));
               
            //echo "$char\n";
            $newchar = iconv(
                'UCS-4', 'UTF-8',
                chr(($char>>24)&0xFF).chr(($char>>16)&0xFF).chr(($char>>8)&0xFF).chr($char&0xFF)
            );
            //echo "$newchar<$p<$p2<<\n";
            $retour = substr_replace($retour, $newchar, $p, 1+$p2-$p);
            $p2 = $p + strlen($newchar);
        }
        return $retour;
    }

    //calcul le nombre de jour ouvrable en retirant les weekends et les feriés
    public static function PeriodeJourOuvrable($dateDt, $dateFn)
    {
        $dateDebut = abs(strtotime($dateDt->format("Y-m-d")));
        $dateFin = abs(strtotime($dateFn->format("Y-m-d")));       
      
        $heureTotal = self::heureDiff($dateDt, $dateFn);     
        // Fonction permettant de compter le nombre de jours ouvrés entre deux dates    
        $arr_bank_holidays = array(); // Tableau des jours feriés
            
        // On boucle dans le cas où l'année de départ serait différente de l'année d'arrivée
        $diff_year = date('Y', $dateFin) - date('Y', $dateDebut);
        for ($i = 0; $i <= $diff_year; $i++) {
        $year = (int)date('Y', $dateDebut) + $i;
        // Liste des jours feriés
        $arr_bank_holidays[] = '1_1_'.$year; // Jour de l'an
        $arr_bank_holidays[] = '24_5_'.$year; // Fete du travail        
        $arr_bank_holidays[] = '13_5_'.$year; //L'Aïd al-Fitr
        $arr_bank_holidays[] = '24_5_'.$year; //Lundi de Pentecôte
        $arr_bank_holidays[] = '20_07_'.$year; //Fete de tabaski
        $arr_bank_holidays[] = '7_8_'.$year; // Fete nationale
        $arr_bank_holidays[] = '15_8_'.$year; // Assomption
        $arr_bank_holidays[] = '1_11_'.$year; // Toussaint        
        $arr_bank_holidays[] = '15_11_'.$year; // Journée de la Paix        
        $arr_bank_holidays[] = '25_12_'.$year; // Noel
      
        // Récupération de paques. Permet ensuite d'obtenir le jour de l'ascension et celui de la pentecote
        $easter = easter_date($year);
        $arr_bank_holidays[] = date('j_n_'.$year, $easter + 86400); // Paques
        $arr_bank_holidays[] = date('j_n_'.$year, $easter + (86400*39)); // Ascension
        $arr_bank_holidays[] = date('j_n_'.$year, $easter + (86400*50)); // Pentecote
        }       
        $nb_days_open = 1;

        if($dateDebut == $dateFin){
            $nb_days_open = 0;
        }else {
            while ($dateDebut < $dateFin) {
            // Si le jour suivant n'est ni un dimanche (0) ou un samedi (6), ni un jour férié, on incrémente les jours ouvrés
            if (!in_array(date('w', $dateDebut), array(0))
            && !in_array(date('j_n_'.date('Y', $dateDebut), $dateDebut), $arr_bank_holidays)) {         
                    $nb_days_open++;           
                }
                $dateDebut += 86400;
                }
        }
       
       
         $result ='';
         if($heureTotal == '0:00'){
             $result = $nb_days_open.' Jour(s)';
         }elseif($nb_days_open == 0){
            $result = $heureTotal;
         }
         else{
            $result = $nb_days_open.' Jour(s)  '.$heureTotal;
         }             
         return $result;
               
    }   

 
    /**
     * Calcule la différence d'heures entre deux dates
     * @param \DateTime $date1
     * @param \DateTime $date2
     * @return string
     */
    public static function heureDiff(\DateTime $date1, \DateTime $date2): string
    {
        $date1Minutes = self::transformToMinute($date1->format("H:i"));
        $date2Minutes = self::transformToMinute($date2->format("H:i"));
        $diff = $date2Minutes - $date1Minutes;

        return self::transformToHour($diff);
    }


     /**
     * Convertit une heure au format "H:i" en minutes
     * @param string $time
     * @return int
     */
    public static function transformToMinute(string $time): int
    {
        [$hours, $minutes] = explode(":", $time);
        return ((int)$hours * 60) + (int)$minutes;
    }

    /**
     * Convertit une durée en minutes en format "H h:M m"
     * @param int $minutes
     * @return string
     */
    public static function transformToHour(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $minutes = $minutes % 60;

        return ($minutes != 0) ? "{$hours} h:{$minutes} m" : "{$hours}:00";
    }

}