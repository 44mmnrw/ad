<?php

namespace App\Services\DaData;

use App\Models\IntegrationSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FindPartyService
{
    private const INTEGRATION = 'dadata';

    /**
     * Находит организацию/ИП по ИНН, ОГРН или ИНН/КПП.
     *
     * @param  array{
     *      count?: int,
     *      kpp?: string,
     *      branch_type?: 'MAIN'|'BRANCH',
     *      type?: 'LEGAL'|'INDIVIDUAL',
     *      status?: array<string>
     * }  $options
     */
    public function findByQuery(string $query, array $options = []): array
    {
        $query = trim($query);

        if ($query === '') {
            throw new RuntimeException('Параметр query не должен быть пустым.');
        }

        if (mb_strlen($query) > 300) {
            throw new RuntimeException('Параметр query не должен превышать 300 символов.');
        }

        return $this->sendPartyRequest(
            url: (string) config('services.dadata.find_party_url'),
            payload: array_filter([
                'query' => $query,
                'count' => $options['count'] ?? null,
                'kpp' => $options['kpp'] ?? null,
                'branch_type' => $options['branch_type'] ?? null,
                'type' => $options['type'] ?? null,
                'status' => $options['status'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            errorContext: 'find-party',
        );
    }

    /**
     * Ищет организации/ИП по названию, ИНН, телефону или части строки через suggest/party.
     *
     * @param array{
     *      count?: int,
     *      branch_type?: 'MAIN'|'BRANCH',
     *      type?: 'LEGAL'|'INDIVIDUAL',
     *      status?: array<string>,
     *      locations?: array<int, array<string, mixed>>
     * } $options
     */
    public function suggestByQuery(string $query, array $options = []): array
    {
        $query = trim($query);

        if ($query === '') {
            throw new RuntimeException('Параметр query не должен быть пустым.');
        }

        if (mb_strlen($query) > 300) {
            throw new RuntimeException('Параметр query не должен превышать 300 символов.');
        }

        return $this->sendPartyRequest(
            url: (string) config('services.dadata.suggest_party_url'),
            payload: array_filter([
                'query' => $query,
                'count' => $options['count'] ?? null,
                'branch_type' => $options['branch_type'] ?? null,
                'type' => $options['type'] ?? null,
                'status' => $options['status'] ?? null,
                'locations' => $options['locations'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            errorContext: 'suggest-party',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendPartyRequest(string $url, array $payload, string $errorContext): array
    {
        $apiKey = trim((string) IntegrationSetting::getValue(self::INTEGRATION, 'api_key', config('services.dadata.api_key', '')));
        $secretKey = trim((string) IntegrationSetting::getValue(self::INTEGRATION, 'secret_key', config('services.dadata.secret_key', '')));
        $timeout = $this->resolveTimeout();

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
            $response = $this->sendHttpRequest($url, $payload, $headers, $timeout);
        } catch (RequestException $exception) {
            $responseBody = $exception->response?->json();
            $message = is_array($responseBody)
                ? ($responseBody['message'] ?? json_encode($responseBody, JSON_UNESCAPED_UNICODE))
                : $exception->getMessage();

            throw new RuntimeException('Ошибка DaData '.$errorContext.': '.$message, previous: $exception);
        }

        return $response->json();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function sendHttpRequest(string $url, array $payload, array $headers, int $timeout)
    {
        $request = Http::timeout(max($timeout, 1))
            ->withHeaders($headers)
            ->asJson();

        try {
            return $request->post($url, $payload)->throw();
        } catch (ConnectionException $exception) {
            if (! $this->shouldRetryWithoutSslVerification($exception)) {
                throw $exception;
            }

            return Http::timeout(max($timeout, 1))
                ->withHeaders($headers)
                ->asJson()
                ->withoutVerifying()
                ->post($url, $payload)
                ->throw();
        }
    }

    private function shouldRetryWithoutSslVerification(ConnectionException $exception): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        return str_contains($exception->getMessage(), 'cURL error 77');
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
