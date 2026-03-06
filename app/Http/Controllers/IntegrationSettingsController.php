<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSetting;
use App\Services\DaData\FindPartyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IntegrationSettingsController extends Controller
{
    public function editDadata(): View
    {
        return view('settings.dadata', [
            'activeMenu' => 'settings',
            'apiKey' => (string) IntegrationSetting::getValue('dadata', 'api_key', config('services.dadata.api_key')),
            'secretKey' => (string) IntegrationSetting::getValue('dadata', 'secret_key', config('services.dadata.secret_key')),
            'yandexStaticApiKey' => (string) IntegrationSetting::getValue('yandex_maps', 'static_api_key', config('services.yandex_maps.static_api_key')),
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
        ]);

        IntegrationSetting::setValue('yandex_maps', 'static_api_key', $validated['yandex_static_api_key'] ?: null);

        return redirect()
            ->route('settings.dadata.edit')
            ->with('status', 'Настройки Яндекс Карт сохранены.');
    }

    public function testDadata(Request $request, FindPartyService $service): RedirectResponse
    {
        $validated = $request->validate([
            'test_query' => ['required', 'string', 'max:32'],
        ]);

        $inn = preg_replace('/\D+/', '', (string) $validated['test_query']) ?? '';

        if (! in_array(strlen($inn), [10, 12], true)) {
            throw ValidationException::withMessages([
                'test_query' => 'Укажите корректный ИНН (10 или 12 цифр).',
            ]);
        }

        $result = $service->findByQuery($inn, ['count' => 1]);
        $payloadJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return redirect()
            ->route('settings.dadata.edit')
            ->with('status', 'Тестовый запрос к DaData выполнен успешно.')
            ->with('dadata_test_result', $result['suggestions'][0]['value'] ?? 'Результаты получены.')
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
}
