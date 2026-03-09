<?php

namespace Tests\Feature;

use App\Models\Counterparty;
use App\Models\CounterpartyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CounterpartySuggestSearchTest extends TestCase
{
    use RefreshDatabase;

    private function resolveLegalType(): CounterpartyType
    {
        return CounterpartyType::query()->firstOrCreate([
            'name' => 'ООО',
        ]);
    }

    public function test_it_returns_local_and_dadata_counterparty_suggestions(): void
    {
        $this->withoutMiddleware();
        config()->set('services.dadata.api_key', 'test-dadata-key');

        $legalType = $this->resolveLegalType();

        Counterparty::query()->create([
            'type' => $legalType->id,
            'short_name' => 'Ромашка Логистик',
            'full_name' => 'Общество с ограниченной ответственностью "Ромашка Логистик"',
            'inn' => '7701234567',
            'kpp' => '770101001',
            'ogrn' => '1027700132195',
            'legal_address' => 'Москва, ул. Ленина, 1',
            'actual_address' => 'Москва, ул. Ленина, 1',
            'ceo' => 'Иванов Иван Иванович',
            'phone' => '+7 (999) 111-22-33',
            'email' => 'info@romashka.test',
            'notes' => null,
        ]);

        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party' => Http::response([
                'suggestions' => [
                    [
                        'value' => 'ООО Ромашка Транс',
                        'data' => [
                            'type' => 'LEGAL',
                            'inn' => '7707654321',
                            'kpp' => '770701001',
                            'ogrn' => '1147746123456',
                            'name' => [
                                'short_with_opf' => 'ООО Ромашка Транс',
                                'full_with_opf' => 'Общество с ограниченной ответственностью "Ромашка Транс"',
                            ],
                            'address' => [
                                'unrestricted_value' => 'Москва, Ленинградский проспект, 10',
                            ],
                            'phones' => [
                                [
                                    'value' => '+7 999 555-44-33',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson(route('counterparties.search.suggest', [
            'query' => 'Ромаш',
        ]));

        $response->assertOk();
        $response->assertJsonPath('suggestions.0.source', 'local');
        $response->assertJsonPath('suggestions.0.counterparty.name', 'Ромашка Логистик');
        $response->assertJsonPath('suggestions.1.source', 'dadata');
        $response->assertJsonPath('suggestions.1.counterparty.inn', '7707654321');
        $response->assertJsonPath('suggestions.1.action_url', route('counterparties.create', [
            'prefill' => 1,
            'type_kind' => 'legal',
            'short_name' => 'ООО Ромашка Транс',
            'full_name' => 'Общество с ограниченной ответственностью "Ромашка Транс"',
            'inn' => '7707654321',
            'kpp' => '770701001',
            'ogrn' => '1147746123456',
            'phone' => '+7 999 555-44-33',
            'legal_address' => 'Москва, Ленинградский проспект, 10',
            'legal_address_invalid' => '0',
        ]));
    }

    public function test_create_page_is_prefilled_from_dadata_search_selection(): void
    {
        $this->withoutMiddleware();

        $this->resolveLegalType();

        $response = $this->get(route('counterparties.create', [
            'prefill' => 1,
            'type_kind' => 'legal',
            'short_name' => 'ООО Север Транс',
            'full_name' => 'Общество с ограниченной ответственностью "Север Транс"',
            'inn' => '7812345678',
            'kpp' => '781201001',
            'ogrn' => '1237800001112',
            'phone' => '+7 (812) 000-00-00',
            'legal_address' => 'Санкт-Петербург, Невский проспект, 1',
            'actual_address' => 'Санкт-Петербург, Невский проспект, 1',
        ]));

        $response->assertOk();
        $response->assertSee('ООО Север Транс');
        $response->assertSee('7812345678');
        $response->assertSee('Санкт-Петербург, Невский проспект, 1');
    }
}
