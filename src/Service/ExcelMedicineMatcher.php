<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ExcelMedicineMatcher
{
    private string $excelPath;
    private string $cachePath;
    private array $medicines = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->excelPath = $projectDir . '/public/pharmaceutique.xls';
        $this->cachePath = $projectDir . '/var/cache/medicines_list.php';
    }

    public function loadMedicines(): array
    {
        if (!empty($this->medicines)) {
            return $this->medicines;
        }

        if (file_exists($this->cachePath) && filemtime($this->cachePath) > filemtime($this->excelPath)) {
            $this->medicines = require $this->cachePath;
            return $this->medicines;
        }

        if (!file_exists($this->excelPath)) {
            return [];
        }

        $spreadsheet = IOFactory::load($this->excelPath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, true);

        $medicines = [];
        foreach ($data as $row) {
            if (!empty($row['A'])) {
                $medicines[] = strtoupper(trim($row['A']));
            }
        }

        // Save to cache for ultra-fast subsequent reads
        $export = var_export($medicines, true);
        file_put_contents($this->cachePath, "<?php\nreturn $export;\n");

        $this->medicines = $medicines;
        return $this->medicines;
    }

    public function findBestMatch(string $extractedName): string
    {
        $medicines = $this->loadMedicines();
        if (empty($medicines)) {
            return $extractedName;
        }

        $cleanString = function (string $str): string {
            $str = str_replace(
                ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ô', 'ö', 'î', 'ï', 'û', 'ü', 'ç', '/', '-'],
                ['E', 'E', 'E', 'E', 'A', 'A', 'A', 'O', 'O', 'I', 'I', 'U', 'U', 'C', ' ', ' '],
                mb_strtolower($str, 'UTF-8')
            );
            return strtoupper(trim($str));
        };

        $searchName = $cleanString($extractedName);
        $searchWords = array_filter(explode(' ', $searchName), fn($w) => strlen($w) > 2);

        $bestMatch = $extractedName;
        $highestScore = 0;

        foreach ($medicines as $medicine) {
            $medName = $cleanString($medicine);
            $medWords = explode(' ', $medName);
            
            $score = 0;
            
            // Check how many words from the search exist in the medicine name
            $wordsFound = 0;
            foreach ($searchWords as $word) {
                if (strpos($medName, $word) !== false) {
                    $wordsFound++;
                    $score += 10;
                }
            }

            // If we found at least one word, refine the score
            if ($wordsFound > 0) {
                // First word matching is usually the brand name, give it a big bonus
                $searchFirstWord = array_values($searchWords)[0] ?? '';
                $medFirstWord = $medWords[0] ?? '';
                
                if ($searchFirstWord !== '' && $medFirstWord !== '') {
                    similar_text($searchFirstWord, $medFirstWord, $percentFirst);
                    if ($percentFirst > 80) {
                        $score += 20;
                    }
                }

                similar_text($searchName, $medName, $percentGlobal);
                $score += ($percentGlobal / 5);

                if ($score > $highestScore) {
                    $highestScore = $score;
                    $bestMatch = $medicine;
                }
            }
        }

        // If the best match score is decent, we build a cleaned string
        // taking ONLY the words from the Excel string that match the AI string.
        if ($highestScore > 10) {
            $bestMatchWords = explode(' ', $cleanString($bestMatch));
            $finalWords = [];

            foreach ($bestMatchWords as $index => $medWord) {
                // Toujours garder le premier mot (le nom principal du médicament)
                if ($index === 0) {
                    $finalWords[] = $medWord;
                    continue;
                }

                // Ignore empty or very short noise words in Excel if they aren't explicitly matched
                if (strlen($medWord) < 2 && !in_array($medWord, $searchWords)) {
                    continue;
                }

                $keep = false;
                foreach ($searchWords as $searchWord) {
                    similar_text($medWord, $searchWord, $percent);
                    
                    // If highly similar OR if one is a substring of another (e.g. 40/240 and 240)
                    if ($percent > 60 || strpos($medWord, $searchWord) !== false || strpos($searchWord, $medWord) !== false) {
                        $keep = true;
                        break;
                    }
                }

                if ($keep) {
                    $finalWords[] = $medWord;
                }
            }

            // Fallback: if filtering removed everything, just return the AI string
            if (empty($finalWords)) {
                return $extractedName;
            }

            return implode(' ', array_unique($finalWords));
        }

        return $extractedName;
    }
}
