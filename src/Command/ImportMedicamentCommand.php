<?php

namespace App\Command;

use App\Entity\Extra\Medecine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use PhpOffice\PhpSpreadsheet\IOFactory;

#[AsCommand(
    name: 'app:import-medicaments',
    description: 'Importe des médicaments depuis un fichier Excel',
)]
class ImportMedicamentCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }
// php bin/console app:import-medicaments /Users/mac/Downloads/pharmaceutique.xls
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin du fichier Excel (ex: public/uploads/medicaments.xlsx)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '-1'); // Lever la limite de mémoire pour l'importation
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $io->error("Le fichier '$filePath' est introuvable !");
            return Command::FAILURE;
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $io->info('Importation des médicaments en cours...');
        $count = 0;

        // Désactiver le logging SQL pour économiser de la mémoire
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // Ignorer l'en-tête "PSLIB"

            // La colonne A contient le nom du médicament (PSLIB)
            $nomMedicament = $row[0]; // Colonne A = index 0

            if (empty($nomMedicament)) {
                $io->warning("Ligne " . ($index + 1) . " ignorée : nom vide");
                continue;
            }

            // Vérifier si le médicament existe déjà (optionnel)
            $existant = $this->em->getRepository(Medecine::class)
                ->findOneBy(['nom' => $nomMedicament]);
            
            if ($existant) {
                // $io->note("Médicament '$nomMedicament' déjà existant, ignoré.");
                continue;
            }

            // Créer le nouveau médicament
            $medecine = new Medecine();
            $medecine
                ->setNom($nomMedicament)
                ->setCreatedAt(new \DateTimeImmutable());

            $this->em->persist($medecine);
            $count++;

            // Flush et Clear tous les 100 enregistrements pour optimiser la mémoire
            if ($count % 100 === 0) {
                $this->em->flush();
                $this->em->clear(); // Indispensable pour libérer la mémoire
                $io->note("$count médicaments traités...");
            }
        }

        // Flush final
        $this->em->flush();
        $io->success("$count médicaments importés avec succès !");
        
        return Command::SUCCESS;
    }
}