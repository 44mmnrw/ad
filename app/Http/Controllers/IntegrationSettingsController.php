<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSetting;
use App\Services\DaData\FindPartyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntegrationSettingsController extends Controller
{
    public function editDadata(): View
    {
        return view('settings.dadata', [
            'activeMenu' => 'settings',
            'apiKey' => (string) IntegrationSetting::getValue('dadata', 'api_key', config('services.dadata.api_key')),
            'secretKey' => (string) IntegrationSetting::getValue('dadata', 'secret_key', config('services.dadata.secret_key')),
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

    public function testDadata(Request $request, FindPartyService $service): RedirectResponse
    {
        $validated = $request->validate([
            'test_query' => ['required', 'string', 'max:300'],
        ]);

        $result = $service->findByQuery($validated['test_query'], ['count' => 1]);

        return redirect()
            ->route('settings.dadata.edit')
            ->with('status', 'Тестовый запрос к DaData выполнен успешно.')
            ->with('dadata_test_result', $result['suggestions'][0]['value'] ?? 'Результаты получены.');
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
