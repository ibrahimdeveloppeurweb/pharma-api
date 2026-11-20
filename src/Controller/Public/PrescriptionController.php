<?php

namespace App\Controller\Public;

use App\Repository\Extra\MedecineRepository;
use App\Service\PrescriptionExtractorService;
use App\Service\ExcelMedicineMatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class PrescriptionController extends AbstractController
{
    #[Route('/api/v1/public/prescription/extract', name: 'api_extract_prescription', methods: ['POST'])]
    public function extract(
        Request $request,
        PrescriptionExtractorService $extractor,
        MedecineRepository $medecineRepository,
        ExcelMedicineMatcher $excelMatcher
    ): JsonResponse {
        $file = $request->files->get('image');

        if (!$file) {
            return $this->json(['error' => 'Aucune image fournie'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Extraire les noms des médicaments avec l'IA
            $extractedNames = $extractor->extract($file->getRealPath());

            if ($request->query->get('mode') === 'exact') {
                $cleanString = function (string $str): string {
                    $str = str_replace(
                        ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ô', 'ö', 'î', 'ï', 'û', 'ü', 'ç'],
                        ['E', 'E', 'E', 'E', 'A', 'A', 'A', 'O', 'O', 'I', 'I', 'U', 'U', 'C'],
                        mb_strtolower($str, 'UTF-8')
                    );
                    return strtoupper(trim($str));
                };

                $upperNames = array_map($cleanString, $extractedNames);

                // Validation par rapport au fichier Excel
                $matchedNames = [];
                foreach ($upperNames as $name) {
                    $matchedNames[] = $excelMatcher->findBestMatch($name);
                }

                return $this->json([
                    'found' => [],
                    'not_found' => $matchedNames,
                    'extracted_raw' => $matchedNames
                ]);
            }

            $foundMedicines = [];
            $notFoundNames = [];

            // Fonction pour nettoyer la chaîne (Majuscules, sans accents)
            $cleanString = function (string $str): string {
                $str = str_replace(
                    ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ô', 'ö', 'î', 'ï', 'û', 'ü', 'ç'],
                    ['E', 'E', 'E', 'E', 'A', 'A', 'A', 'O', 'O', 'I', 'I', 'U', 'U', 'C'],
                    mb_strtolower($str, 'UTF-8')
                );
                return strtoupper(trim($str));
            };

            // Rechercher chaque médicament dans la base de données
            foreach ($extractedNames as $name) {
                $searchName = $cleanString($name);
                
                // Diviser le nom en mots pour une recherche ultra-souple
                $words = explode(' ', $searchName);
                
                $qb = $medecineRepository->createQueryBuilder('m');
                
                $wordCount = 0;
                foreach ($words as $i => $word) {
                    // On ignore les mots trop courts (ex: "DE", "ET")
                    if (strlen($word) > 2) {
                        $qb->andWhere('UPPER(m.nom) LIKE :word' . $i)
                           ->setParameter('word' . $i, '%' . $word . '%');
                        $wordCount++;
                    }
                }
                
                // Si tous les mots étaient trop courts, on fait une recherche simple
                if ($wordCount === 0) {
                     $qb->andWhere('UPPER(m.nom) LIKE :name')
                        ->setParameter('name', '%' . $searchName . '%');
                }

                $medecines = $qb->setMaxResults(1)
                    ->getQuery()
                    ->getResult();

                // FALLBACK UNIVERSEL : Si la recherche SQL stricte échoue (fautes de frappe, mots inversés, etc.)
                if (empty($medecines) && !empty($words)) {
                    // Trouver le mot le plus long (qui est statistiquement le nom de la marque)
                    $longestWord = '';
                    foreach ($words as $word) {
                        if (strlen($word) > strlen($longestWord)) {
                            $longestWord = $word;
                        }
                    }

                    if (strlen($longestWord) >= 3) {
                        // Récupérer un lot de candidats (médicaments commençant par les 3 mêmes lettres)
                        // Ex: Si IA dit "CILOXANE" (faute), on cherche "CIL%"
                        $prefix = substr($longestWord, 0, 3);
                        $candidates = $medecineRepository->createQueryBuilder('m')
                            ->where('UPPER(m.nom) LIKE :prefix')
                            ->setParameter('prefix', $prefix . '%')
                            ->getQuery()
                            ->getResult();

                        $bestMatch = null;
                        $highestScore = 0;

                        foreach ($candidates as $candidate) {
                            $dbName = $cleanString($candidate->getNom());
                            // Prendre le premier mot du nom en base de données (souvent la marque)
                            $dbFirstWord = explode(' ', $dbName)[0];
                            
                            $score = 0;
                            
                            // 1. Vérifier si le mot le plus long de l'IA est inclus tel quel
                            if (strpos($dbName, $longestWord) !== false) {
                                $score += 50; // Bonus massif
                            }
                            
                            // 2. Similarité entre le mot lu par l'IA et le PREMIER MOT de la base (pour ignorer le % et dosages)
                            similar_text($longestWord, $dbFirstWord, $percent);
                            
                            // 3. Similarité globale
                            similar_text($searchName, $dbName, $globalPercent);
                            
                            // Le score final est une combinaison intelligente
                            $score += max($percent, $globalPercent);

                            if ($score > $highestScore) {
                                $highestScore = $score;
                                $bestMatch = $candidate;
                            }
                        }

                        // Si le meilleur score est suffisamment élevé (> 55), on considère que c'est le bon médicament
                        if ($bestMatch !== null && $highestScore > 55) {
                            $medecines = [$bestMatch];
                        }
                    }
                }

                if (!empty($medecines)) {
                    $med = $medecines[0];
                    $foundMedicines[] = [
                        'uuid' => $med->getUuid(),
                        'libelle' => $med->getNom(),
                        'prix' => $med->getPrix(),
                        'original_name' => $name
                    ];
                } else {
                    $notFoundNames[] = $name;
                }
            }

            return $this->json([
                'found' => $foundMedicines,
                'not_found' => $notFoundNames,
                'extracted_raw' => $extractedNames
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
