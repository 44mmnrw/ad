<?php

namespace App\Services\DaData;

use App\Models\IntegrationSetting;
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

        $payload = array_filter([
            'query' => $query,
            'count' => $options['count'] ?? null,
            'kpp' => $options['kpp'] ?? null,
            'branch_type' => $options['branch_type'] ?? null,
            'type' => $options['type'] ?? null,
            'status' => $options['status'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $apiKey = (string) IntegrationSetting::getValue(self::INTEGRATION, 'api_key', config('services.dadata.api_key'));
        $secretKey = (string) IntegrationSetting::getValue(self::INTEGRATION, 'secret_key', config('services.dadata.secret_key'));
        $timeout = $this->resolveTimeout();
        $url = (string) config('services.dadata.find_party_url');

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

            throw new RuntimeException('Ошибка DaData find-party: '.$message, previous: $exception);
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
