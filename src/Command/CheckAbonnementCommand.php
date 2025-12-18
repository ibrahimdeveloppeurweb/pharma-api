<?php

namespace App\Command;

use App\Entity\Gestion\HistoriqueAbonnement;
use App\Repository\Gestion\AbonnementRepository;
use App\Events\SmsServiceEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:check-abonnement',
    description: 'Vérifie chaque jour les abonnements expirés et désactive les pharmacies concernées.',
)]
class CheckAbonnementCommand extends Command
{
    private AbonnementRepository $abonnementRepository;
    private EntityManagerInterface $em;
    private SmsServiceEvent $smsService;

    public function __construct(AbonnementRepository $abonnementRepository, EntityManagerInterface $em, SmsServiceEvent $smsService)
    {
        parent::__construct();
        $this->abonnementRepository = $abonnementRepository;
        $this->em = $em;
        $this->smsService = $smsService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTime('today');
       
        // Utiliser une requête avec jointure pour éviter les entités orphelines
        $abonnements = $this->abonnementRepository->createQueryBuilder('a')
            ->innerJoin('a.pharmacie', 'p')
            ->where('a.actif = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getResult();
      
        foreach ($abonnements as $abonnement) {
            $pharmacie = $abonnement->getPharmacie();
          
            if ($abonnement->getDateFin() < $today) {
                // Créer l'historique
                $historique = new HistoriqueAbonnement();
                $historique->setPharmacie($pharmacie);
                $historique->setDateDebut($abonnement->getDateDebut());
                $historique->setDateFin($abonnement->getDateFin());
                $historique->setStatut('expiré');

                $this->em->persist($historique);

                // Si faveur active et encore valide, on laisse la pharmacie active
                if ($abonnement->getDateFinFaveur() && $abonnement->getDateFinFaveur() > $today) {
                    $output->writeln("💡 Faveur active : {$pharmacie->getNom()} reste autorisée.");
                    continue;
                }
         
                // Sinon, abonnement expiré normal
                $abonnement->setFaveurActive(false);
                $abonnement->setActif(false);

                $this->em->persist($abonnement);
         
                $message = sprintf(
                    "Bonjour %s, votre abonnement est expiré. Veuillez le renouveler pour continuer à recevoir les demandes.",
                    $pharmacie->getNom()
                );

                try {
                    $this->smsService->sendSms($pharmacie->getTelephone(), $message);
                } catch (\Exception $e) {
                    $output->writeln("⚠️ Erreur d'envoi SMS pour {$pharmacie->getNom()}: {$e->getMessage()}");
                }

                $output->writeln("✅ Abonnement expiré pour : {$pharmacie->getNom()}");
            }
        }

        $this->em->flush();

        $output->writeln("Vérification terminée à " . date('Y-m-d H:i:s'));

        return Command::SUCCESS;
    }
}