<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSetting;
use App\Services\DaData\FindPartyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class IntegrationSettingsController extends Controller
{
    public function editDadata(): View
    {
        return view('settings.dadata', [
            'activeMenu' => 'settings',
            'apiKey' => (string) IntegrationSetting::getValue('dadata', 'api_key', ''),
            'secretKey' => (string) IntegrationSetting::getValue('dadata', 'secret_key', ''),
            'yandexStaticApiKey' => $this->resolveYandexMapsSetting(['static_api_key']) ?? '',
            'yandexJsApiKey' => $this->resolveYandexMapsSetting(['js_api_key', 'js_http_geocoder_api_key']) ?? '',
            'yandexHttpGeocoderApiKey' => $this->resolveYandexMapsSetting(['http_geocoder_api_key', 'geocoder_api_key', 'js_http_geocoder_api_key', 'static_api_key']) ?? '',
            'yandexRouterApiKey' => $this->resolveYandexMapsSetting(['router_api_key']) ?? '',
            'yandexGeosuggestApiKey' => $this->resolveYandexMapsSetting(['geosuggest_api_key', 'js_http_geocoder_api_key']) ?? '',
            'timeout' => $this->resolveTimeout(),
        ]);
    }

    public function updateDadata(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'timeout' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        IntegrationSetting::setValue('dadata', 'api_key', $validated['api_key']);
        IntegrationSetting::setValue('dadata', 'secret_key', $validated['secret_key'] ?: null);
        IntegrationSetting::setValue('dadata', 'timeout', (string) $validated['timeout']);

        return redirect()
            ->route('settings.dadata.edit')
            ->with('status', 'Настройки DaData сохранены.');
    }

    public function updateYandexMaps(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'yandex_static_api_key' => ['nullable', 'string', 'max:255'],
            'yandex_js_api_key' => ['nullable', 'string', 'max:255'],
            'yandex_http_geocoder_api_key' => ['nullable', 'string', 'max:255'],
            'yandex_router_api_key' => ['nullable', 'string', 'max:255'],
            'yandex_geosuggest_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated): void {
            IntegrationSetting::setValue('yandex_maps', 'static_api_key', $validated['yandex_static_api_key'] ?: null);
            IntegrationSetting::setValue('yandex_maps', 'js_api_key', $validated['yandex_js_api_key'] ?: null);
            IntegrationSetting::setValue('yandex_maps', 'http_geocoder_api_key', $validated['yandex_http_geocoder_api_key'] ?: null);
            IntegrationSetting::setValue('yandex_maps', 'router_api_key', $validated['yandex_router_api_key'] ?: null);
            IntegrationSetting::setValue('yandex_maps', 'geosuggest_api_key', $validated['yandex_geosuggest_api_key'] ?: null);
        });

        return redirect()
            ->route('settings.dadata.edit')
            ->with('status', 'Настройки Яндекс Карт сохранены.');
    }

    public function testDadata(Request $request, FindPartyService $service): RedirectResponse
    {
        $validated = $request->validate([
            'test_query' => ['required', 'string', 'max:255'],
            'test_mode' => ['nullable', 'in:auto,inn,name'],
        ]);

        $rawQuery = trim((string) $validated['test_query']);
        $mode = (string) ($validated['test_mode'] ?? 'auto');
        $digits = preg_replace('/\D+/', '', $rawQuery) ?? '';
        $isInnLikeQuery = in_array(strlen($digits), [10, 12, 13, 15], true) && $digits === $rawQuery;

        if ($mode === 'inn' && ! in_array(strlen($digits), [10, 12, 13, 15], true)) {
            throw ValidationException::withMessages([
                'test_query' => 'Для режима ИНН укажите корректный ИНН/ОГРН (10, 12, 13 или 15 цифр).',
            ]);
        }

        $shouldRunFindById = $mode === 'inn' || ($mode === 'auto' && $isInnLikeQuery);
        $shouldRunSuggest = $mode === 'name' || ($mode === 'auto' && ! $isInnLikeQuery);

        $findByIdPayload = null;
        $suggestPayload = null;
        $findByIdError = null;
        $suggestError = null;

        if ($shouldRunFindById) {
            try {
                $findByIdPayload = $service->findByQuery($digits, ['count' => 5]);
            } catch (Throwable $exception) {
                $findByIdError = $exception->getMessage();
            }
        }

        if ($shouldRunSuggest) {
            try {
                $suggestPayload = $service->suggestByQuery($rawQuery, ['count' => 5]);
            } catch (Throwable $exception) {
                $suggestError = $exception->getMessage();
            }
        }

        $payload = [
            'query' => $rawQuery,
            'mode' => $mode,
            'findById_party' => [
                'executed' => $shouldRunFindById,
                'error' => $findByIdError,
                'response' => $findByIdPayload,
            ],
            'suggest_party' => [
                'executed' => $shouldRunSuggest,
                'error' => $suggestError,
                'response' => $suggestPayload,
            ],
            'hint' => 'В полном payload ищите блок suggestions[].data.management (name, post) для ФИО/должности руководителя.',
        ];

        $findByIdCount = is_array($findByIdPayload) ? count((array) ($findByIdPayload['suggestions'] ?? [])) : 0;
        $suggestCount = is_array($suggestPayload) ? count((array) ($suggestPayload['suggestions'] ?? [])) : 0;
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $resultLine = sprintf('findById/party: %d, suggest/party: %d', $findByIdCount, $suggestCount);

        return redirect()
            ->route('settings.dadata.edit')
            ->with('status', 'Тестовый запрос к DaData выполнен успешно.')
            ->with('dadata_test_result', $resultLine)
            ->with('dadata_test_mode', $mode)
            ->with('dadata_test_payload', $payloadJson ?: 'Не удалось сериализовать ответ DaData в JSON.');
    }

    private function resolveTimeout(): int
    {
        $fallback = (int) config('services.dadata.timeout', 10);
        $rawTimeout = IntegrationSetting::getValue('dadata', 'timeout', $fallback);

        if (is_int($rawTimeout) || is_float($rawTimeout) || (is_string($rawTimeout) && is_numeric($rawTimeout))) {
            return (int) $rawTimeout;
        }

        return $fallback;
    }

    /**
     * @param list<string> $keys
     */
    private function resolveYandexMapsSetting(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) IntegrationSetting::getValue('yandex_maps', $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
