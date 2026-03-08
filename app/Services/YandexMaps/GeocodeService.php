<?php

namespace App\Services\YandexMaps;

use App\Models\IntegrationSetting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeocodeService
{
    private const INTEGRATION = 'yandex_maps';

    /**
     * Геокодирует адрес и возвращает координаты.
     *
     * @return array{lat: float, lng: float, text: string}
     */
    public function geocode(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            throw new RuntimeException('Параметр geocode не должен быть пустым.');
        }

        if (mb_strlen($query) > 255) {
            throw new RuntimeException('Параметр geocode не должен превышать 255 символов.');
        }

        $apiKey = $this->resolveFirstAvailableIntegrationValue([
            'http_geocoder_api_key',
            'geocoder_api_key',
            'js_http_geocoder_api_key',
            'static_api_key',
        ]);

        if ($apiKey === '') {
            throw new RuntimeException('Не задан API-ключ Яндекс Геокодера. Укажите его в настройках интеграции.');
        }

        $url = (string) config('services.yandex_maps.geocoder_url');
        $timeout = max($this->resolveTimeout(), 1);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($url, [
                    'apikey' => $apiKey,
                    'format' => 'json',
                    'lang' => 'ru_RU',
                    'results' => 1,
                    'geocode' => $query,
                ])
                ->throw();
        } catch (RequestException $exception) {
            $responseBody = $exception->response?->body() ?? '';

            if (str_contains($responseBody, 'Invalid api key')) {
                throw new RuntimeException('Ошибка Яндекс Геокодера: недействительный API-ключ. Проверьте поле «API key Яндекс Геокодер».', previous: $exception);
            }

            throw new RuntimeException('Ошибка Яндекс Геокодера: '.$exception->getMessage(), previous: $exception);
        }

        $payload = $response->json();
        $featureMembers = data_get($payload, 'response.GeoObjectCollection.featureMember', []);

        if (! is_array($featureMembers) || $featureMembers === []) {
            throw new RuntimeException('Адрес не найден в Яндекс Геокодере. Уточните город и адрес.');
        }

        $position = data_get($featureMembers, '0.GeoObject.Point.pos');

        if (! is_string($position) || trim($position) === '') {
            throw new RuntimeException('Яндекс Геокодер вернул некорректные координаты.');
        }

        [$lngRaw, $latRaw] = array_pad(preg_split('/\s+/', trim($position)) ?: [], 2, null);

        if (! is_numeric($latRaw) || ! is_numeric($lngRaw)) {
            throw new RuntimeException('Не удалось разобрать координаты ответа Яндекс Геокодера.');
        }

        $displayText = (string) data_get($featureMembers, '0.GeoObject.metaDataProperty.GeocoderMetaData.text', $query);

        return [
            'lat' => (float) $latRaw,
            'lng' => (float) $lngRaw,
            'text' => $displayText,
        ];
    }

    /**
     * Возвращает подсказки городов/населённых пунктов по строке поиска.
     *
     * @return list<string>
     */
    public function suggestCities(string $query, int $limit = 5): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        if (mb_strlen($query) > 255) {
            throw new RuntimeException('Параметр query не должен превышать 255 символов.');
        }

        $apiKey = $this->resolveFirstAvailableIntegrationValue([
            'geosuggest_api_key',
            'js_http_geocoder_api_key',
        ]);

        if ($apiKey === '') {
            throw new RuntimeException('Не задан API-ключ Яндекс Геосаджеста. Укажите его в настройках интеграции.');
        }

        $results = $this->requestGeosuggest($apiKey, [
            'types' => 'locality',
            'text' => $query,
        ], $limit);

        return $this->formatGeosuggestResults($results, false, $limit);
    }

    /**
     * Возвращает подсказки адресов с опциональным учётом выбранного города.
     *
     * @return list<string>
     */
    public function suggestAddresses(string $query, ?string $city = null, int $limit = 5): array
    {
        $query = trim($query);
        $city = trim((string) $city);

        if ($query === '') {
            return [];
        }

        if (mb_strlen($query) > 255 || mb_strlen($city) > 120) {
            throw new RuntimeException('Параметры адресной подсказки превышают допустимую длину.');
        }

        $apiKey = $this->resolveFirstAvailableIntegrationValue([
            'geosuggest_api_key',
            'js_http_geocoder_api_key',
        ]);

        if ($apiKey === '') {
            throw new RuntimeException('Не задан API-ключ Яндекс Геосаджеста. Укажите его в настройках интеграции.');
        }

        $text = $city !== '' ? $city.', '.$query : $query;
        $results = $this->requestGeosuggest($apiKey, [
            'text' => $text,
        ], $limit);

        return $this->formatGeosuggestResults($results, $city !== '', $limit);
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return list<mixed>
     */
    private function requestGeosuggest(string $apiKey, array $params, int $limit): array
    {
        $url = (string) config('services.yandex_maps.geosuggest_url');
        $timeout = max($this->resolveTimeout(), 1);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($url, array_filter([
                    'apikey' => $apiKey,
                    'lang' => 'ru_RU',
                    'results' => max(1, min($limit, 10)),
                    'print_address' => 1,
                    ...$params,
                ], static fn ($value): bool => $value !== null && $value !== ''))
                ->throw();
        } catch (RequestException $exception) {
            $responseBody = $exception->response?->body() ?? '';

            if (str_contains($responseBody, 'Invalid api key')) {
                throw new RuntimeException('Ошибка Яндекс Геосаджеста: недействительный API-ключ. Проверьте поле «API key Яндекс Геосаджест».', previous: $exception);
            }

            throw new RuntimeException('Ошибка подсказок Яндекс Геосаджеста: '.$exception->getMessage(), previous: $exception);
        }

        $payload = $response->json();
        $results = data_get($payload, 'results', []);

        if (! is_array($results) || $results === []) {
            return [];
        }

        return array_values($results);
    }

    /**
     * @param list<mixed> $results
     *
     * @return list<string>
     */
    private function formatGeosuggestResults(array $results, bool $preferTitleOnly, int $limit): array
    {
        $suggestions = [];

        foreach ($results as $item) {
            $title = trim((string) data_get($item, 'title.text', ''));
            $subtitle = trim((string) data_get($item, 'subtitle.text', ''));

            if ($title === '') {
                continue;
            }

            if ($preferTitleOnly) {
                $suggestions[] = $title;
                continue;
            }

            $suggestions[] = $subtitle !== '' ? $title.', '.$subtitle : $title;
        }

        return array_values(array_slice(array_unique($suggestions), 0, max(1, min($limit, 10))));
    }

    private function resolveTimeout(): int
    {
        $fallback = (int) config('services.yandex_maps.timeout', 10);
        $rawTimeout = IntegrationSetting::getValue(self::INTEGRATION, 'timeout', $fallback);

        if (is_int($rawTimeout) || is_float($rawTimeout) || (is_string($rawTimeout) && is_numeric($rawTimeout))) {
            return (int) $rawTimeout;
        }

        return $fallback;
    }

    private function resolveIntegrationValue(string $settingKey): string
    {
        return trim((string) IntegrationSetting::getValue(
            self::INTEGRATION,
            $settingKey,
            config("services.yandex_maps.{$settingKey}")
        ));
    }

    /**
     * @param list<string> $settingKeys
     */
    private function resolveFirstAvailableIntegrationValue(array $settingKeys): string
    {
        foreach ($settingKeys as $settingKey) {
            $value = $this->resolveIntegrationValue($settingKey);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
