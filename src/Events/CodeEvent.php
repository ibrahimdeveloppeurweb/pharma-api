<?php

namespace App\Events;

use App\Entity\Extra\EtapeDossier;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class CodeEvent
{
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof EtapeDossier) {
            return;
        }

        if (method_exists($entity, 'setCode') && ($entity->getCode()==null || empty($entity->getCode())) && $entity->getPrefix()) {
            $prefix = $entity->getPrefix();
            $id = $entity->getId();
            $date = $entity->getCreatedAt()->format('Ymd');
            $entity->setCode(
                $prefix . '-'
                    . $this->aleatoire(10, null) . '-'
                    // . $id . '-'                    
                    . $date
            );
            
            // $entity->setCode(
            //     $prefix . '-'
            //         . $this->aleatoire(10, null) . '-'
            //         . $this->uniqueCodeByDate()
            // );
        }
    }

    public function uniqueCodeByDate()
    {
        return (date('Ymd'));
    }

    public function aleatoire($taille, $type = null)
    {
        $mdp = '';
        $cars = '';
        if ($type === 'C') {
            $cars = "6789012345";
        } elseif ($type === 'L') {
            $cars = "IOPQSDFGAZERTYHJKLMWXCVBN6789012345";
        } elseif ($type === null) {
            $cars = "IOPQSDFGAZERTYHJKLMWXCVBN6789012345qwertyuiopasdfghjklzxcvbnm";
        }
        srand((float)microtime() * 1000000);
        for ($i = 0; $i < $taille; $i++) $mdp = $mdp . substr($cars, rand(0, strlen($cars) - 1), 1);
        return $mdp;
    }

    public function exclus($entity)
    {
        if (true) {
            return $entity;
        }
    }
}
