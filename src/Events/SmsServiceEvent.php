<?php

namespace App\Events;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmsServiceEvent
{
    private HttpClientInterface $client;
    private string $apiUrl;
    private string $token;
    private string $expediteur;
    private string $reportUrl;

    public function __construct(
        HttpClientInterface $client,
        string $apiUrl,
        string $token,
        string $expediteur,
        string $reportUrl = 'https://the_url_to_recieve_delivery_report.com'
    ) {
        $this->client = $client;
        $this->apiUrl = $apiUrl;
        $this->token = $token;
        $this->expediteur = $expediteur;
        $this->reportUrl = $reportUrl;
    }

    /**
     * Envoie un SMS via l’API JSON (ex : D7 Networks)
     */
    public function sendSms(string $numero, string $message): array
    {
        $payload = [
            "messages" => [
                [
                    "channel" => "sms",
                    "recipients" => [$numero],
                    "content" => $message,
                    "msg_type" => "text",
                    "data_coding" => "text",
                ]
            ],
            "message_globals" => [
                "originator" => $this->expediteur,
                "report_url" => $this->reportUrl
            ]
        ];

        try {
            $response = $this->client->request('POST', $this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status_code' => $statusCode,
                'response' => json_decode($content, true),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
