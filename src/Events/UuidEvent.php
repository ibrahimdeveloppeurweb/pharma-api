<?php

namespace App\Events;

use Symfony\Component\Uid\Uuid;
use App\Service\QrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\Security\Core\Security;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class UuidEvent
{
    private $em;
    private $security;
    private $qrCodeService;
    
    public function __construct(
        Security $security,
        EntityManagerInterface $em, 
    ) {
        $this->em = $em;
        $this->security = $security;
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        
        // Générer UUID si la méthode existe et que l'UUID est null
        if (method_exists($entity, 'setUuid') && method_exists($entity, 'getUuid')) {
            if ($entity->getUuid() === null || empty($entity->getUuid())) {
                $entity->setUuid(Uuid::v4());
            }
        }
        
        // Générer QR Code si nécessaire
        if (method_exists($entity, 'setQrCode') && method_exists($entity, 'getQrCode')) {
            if (!$entity->getQrCode()) {
                $this->qrCodeService->qrcode($entity);
            }
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        // Logique de mise à jour si nécessaire
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        // Logique avant flush si nécessaire
    }
}