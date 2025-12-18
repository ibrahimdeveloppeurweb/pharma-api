<?php

namespace App\DataFixtures;

use App\Entity\Security\Admin;
use App\Entity\Security\User;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $passwordEncoder;
    public function __construct(
        UserPasswordHasherInterface $passwordEncoder
    )
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager): void
    {
        //USER
        $user = new User();                 
        $user
            ->setType('SUPER_ADMIN')
            ->setEmail('cisseibrahim@gmail.com')
            ->setPassword($this->passwordEncoder->hashPassword($user, 'ibrahim@1234'))
            ->setNom('Cisse')
            ->setPrenom("Ibrahim")
            ->setTelephone('0777777777')
            ->setCivilite('Mr')
            ->setIsActive(true)
            ->setFirst(true)
            ->setCreatedAt(new \DateTime('now'))
        ;
        $manager->persist($user);

        $admin = new Admin();
        $admin->setRelatedUser($user);
        $manager->persist($admin);

        $manager->flush();
    }
}
