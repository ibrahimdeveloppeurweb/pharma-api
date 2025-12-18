<?php

namespace App\Utils;

use App\Entity\Security\User;
use App\Entity\Admin\Agency;
use App\Entity\Gestion\Pharmacie;

class PharmacyHelper
{
    public static function pharmacy(User $user): ?Pharmacie
    {
        /**  @var Pharmacie */
        $agency = null;
        if ($user->getPharmacie()) {
            $agency = $user->getPharmacie();
        } 
        return $agency;
    }
}
