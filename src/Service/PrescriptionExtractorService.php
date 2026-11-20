<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PrescriptionExtractorService
{
    public function __construct(
        private HttpClientInterface $client,
        private string $openrouterApiKey
    ) {}

    public function extract(string $imagePath): array
    {
        $base64 = base64_encode(file_get_contents($imagePath));

        $response = $this->client->request(
            'POST',
            'https://openrouter.ai/api/v1/chat/completions',
            [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->openrouterApiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://pharmaalerte.net',
                    'X-Title' => 'PharmaAlert'
                ],
                'json' => [
                    // openai/gpt-4o-mini is fast, very cheap, and excellent at OCR
                    // On utilise Claude 3.5 Sonnet qui est actuellement le meilleur modèle au monde pour le déchiffrage d'écriture manuscrite (OCR)
                    'model' => 'anthropic/claude-sonnet-5',
                    'temperature' => 0.1,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => <<<PROMPT
Tu es un robot OCR ultra-précis. Prends tout ton temps pour observer l'image et lire très attentivement chaque ligne de cette ordonnance.
Ta mission STRICTE est de transcrire LITTÉRALEMENT les noms des médicaments tels qu'ils sont écrits, lettre par lettre.

PROCEDURE OBLIGATOIRE :
1. Analyse l'image attentivement.
2. Extrait LITTÉRALEMENT les noms de médicaments écrits sur le papier dans "medicaments". 

REGLES STRICTES POUR LE TABLEAU "medicaments" :
1. REPRODUCTION EXACTE : Si le médecin a écrit avec des fautes (ex: "Dolpran"), écris "DOLPRAN". Si c'est incomplet (ex: "Amoxi"), écris "AMOXI".
2. AUCUNE CORRECTION : Ne tente JAMAIS de deviner le vrai nom ou de corriger les fautes d'orthographe.
3. Écris TOUS LES NOMS EN MAJUSCULES.
4. NE METS PAS les posologies (ex: "1 matin et soir", "1 cuillère", "qsp", "1 bte", etc.).
5. CE QUE TU VOIS EST CE QUE TU ÉCRIS. AUCUNE INTERPRÉTATION.
Retourne obligatoirement un JSON pur ayant EXACTEMENT ce format :
{
  "analyse_etape_par_etape": [
    "Ligne 1 : Je lis 'Doliprane 500'",
    "Ligne 2 : Je lis 'Amoxil'"
  ],
  "medicaments": [
    "DOLIPRANE 500",
    "AMOXIL"
  ]
}
PROMPT
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:image/jpeg;base64,$base64"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        );

        try {
            $content = $response->toArray();
        } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
            $errorBody = $e->getResponse()->getContent(false);
            throw new \Exception("Erreur OpenRouter: " . $errorBody);
        }
        
        if (!isset($content['choices'][0]['message']['content'])) {
            throw new \Exception("Erreur lors de la réponse de l'IA");
        }

        $rawResponse = $content['choices'][0]['message']['content'];
        
        // Remove markdown JSON code blocks if Claude wrapped the answer
        $rawResponse = preg_replace('/```json/i', '', $rawResponse);
        $rawResponse = preg_replace('/```/i', '', $rawResponse);
        $rawResponse = trim($rawResponse);

        $jsonResult = json_decode($rawResponse, true);

        if (!$jsonResult) {
            throw new \Exception("Impossible de lire le format JSON renvoyé par l'IA");
        }

        return $jsonResult['medicaments'] ?? [];
    }
}
