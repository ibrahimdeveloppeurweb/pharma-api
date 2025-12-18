<?php

namespace App\Events;

use Doctrine\ORM\Events;
// use App\Entity\Security\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\Security\Core\User\User;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserEvent implements EventSubscriberInterface
{
    private $em;
    /** @var User $user */
    private $user;
    private $token;
    public function __construct(
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage
    )
    {
        $this->em = $em;
        $this->token = $tokenStorage;
    }
    
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preRemove,
            Events::preUpdate
        ];
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $this->getUser();
        $entity = $args->getObject();
        if ($this->user instanceof UserInterface && method_exists($entity, 'setCreateBy')) {
            $entity->setCreateBy($this->user);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $this->getUser();
        $entity = $args->getObject();
        if ($this->user instanceof UserInterface && method_exists($entity, 'setUpdateBy')) {
            $entity->setUpdateBy($this->user);
            $this->em->persist($entity);
        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $this->getUser();
        $entity = $args->getObject();
        if ($this->user instanceof UserInterface && method_exists($entity, 'setRemoveBy')) {
            $entity->setRemoveBy($this->user);
            // $this->em->persist($entity);
            // dd($this->user->getId(), $entity->getRemoveBy());
        }
    }

    public function getUser()
    {
        if ($this->token->getToken()) {
            $this->user = $this->token->getToken()->getUser();
        }
        return $this->user;
    }

}
