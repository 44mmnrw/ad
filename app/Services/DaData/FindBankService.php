<?php

namespace App\Services\DaData;

use App\Models\IntegrationSetting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FindBankService
{
    private const INTEGRATION = 'dadata';

    /**
     * Находит банк по БИК.
     *
     * @param  array{count?: int}  $options
     */
    public function findByBik(string $bik, array $options = []): array
    {
        $bik = trim($bik);

        if ($bik === '') {
            throw new RuntimeException('Параметр bik не должен быть пустым.');
        }

        if (! preg_match('/^\d{9}$/', $bik)) {
            throw new RuntimeException('БИК должен состоять из 9 цифр.');
        }

        $payload = array_filter([
            'query' => $bik,
            'count' => $options['count'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $apiKey = trim((string) IntegrationSetting::getValue(self::INTEGRATION, 'api_key', ''));
        $secretKey = trim((string) IntegrationSetting::getValue(self::INTEGRATION, 'secret_key', ''));
        $timeout = $this->resolveTimeout();
        $url = (string) config('services.dadata.find_bank_url');

        if ($apiKey === '') {
            throw new RuntimeException('Не задан API-ключ DaData. Укажите его в настройках интеграции.');
        }

        $headers = [
            'Authorization' => 'Token '.$apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($secretKey !== '') {
            $headers['X-Secret'] = $secretKey;
        }

        try {
            $response = Http::timeout(max($timeout, 1))
                ->withHeaders($headers)
                ->asJson()
                ->post($url, $payload)
                ->throw();
        } catch (RequestException $exception) {
            $responseBody = $exception->response?->json();
            $message = is_array($responseBody)
                ? ($responseBody['message'] ?? json_encode($responseBody, JSON_UNESCAPED_UNICODE))
                : $exception->getMessage();

            throw new RuntimeException('Ошибка DaData find-bank: '.$message, previous: $exception);
        }

        return $response->json();
    }

    private function resolveTimeout(): int
    {
        $fallback = (int) config('services.dadata.timeout', 10);
        $rawTimeout = IntegrationSetting::getValue(self::INTEGRATION, 'timeout', $fallback);

        if (is_int($rawTimeout) || is_float($rawTimeout) || (is_string($rawTimeout) && is_numeric($rawTimeout))) {
            return (int) $rawTimeout;
        }

        return $fallback;
    }
}
