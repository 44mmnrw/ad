<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Counterparty;
use App\Models\CounterpartyType;
use App\Services\DaData\FindBankService;
use App\Services\DaData\FindPartyService;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

class CounterpartyController extends Controller
{
    public function create(Request $request): View
    {
        $types = CounterpartyType::query()->orderBy('name')->get();
        $legalTypeId = $types->first(fn ($type) => str_contains(mb_strtolower((string) $type->name), 'ооо'))?->id ?? $types->first()?->id;
        $personTypeId = $types->first(fn ($type) => str_contains(mb_strtolower((string) $type->name), 'физ') || str_contains(mb_strtolower((string) $type->name), 'самозан'))?->id ?? $types->first()?->id;

        $counterparty = new Counterparty();
        $counterparty->type = $legalTypeId;
        $counterparty = $this->applyCreatePrefill($request, $counterparty, $types, $legalTypeId);
        $counterparty->setRelation('typeRef', $types->firstWhere('id', $counterparty->type) ?? $types->first());
        $counterparty->setRelation('contacts', collect());
        $counterparty->setRelation('bankAccounts', collect());

        $activeTab = (string) $request->query('tab', 'general');

        return view('counterparties.show', [
            'mode' => 'create',
            'types' => $types,
            'legalTypeId' => $legalTypeId,
            'personTypeId' => $personTypeId,
            'counterparty' => $counterparty,
            'activeTab' => $activeTab,
            'contactsCount' => 0,
            'primaryBankAccount' => null,
            'bankCopyText' => "Контрагент\nИНН: —\nКПП: —\nОГРН: —\nБанк: —\nБИК: —\nр/с: —\nк/с: —",
        ]);
    }

    public function suggest(Request $request, FindPartyService $findPartyService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        $query = trim((string) $validated['query']);

        if (mb_strlen($query) < 2) {
            return response()->json([
                'suggestions' => [],
            ]);
        }

        $localSuggestions = $this->searchLocalCounterparties($query);
        $localInns = collect($localSuggestions)
            ->pluck('counterparty.inn')
            ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value) => trim($value))
            ->values()
            ->all();

        $suggestions = collect($localSuggestions);
        $dadataError = null;
        $digits = preg_replace('/\D+/', '', $query) ?? '';

        if (mb_strlen($query) >= 3 || in_array(strlen($digits), [10, 12, 13, 15], true)) {
            try {
                $result = $findPartyService->suggestByQuery($query, ['count' => 5]);
                $dadataSuggestions = collect((array) ($result['suggestions'] ?? []))
                    ->map(fn ($suggestion) => $this->mapDaDataCounterpartySuggestion($suggestion))
                    ->filter(static function (?array $suggestion) use ($localInns): bool {
                        if ($suggestion === null) {
                            return false;
                        }

                        $inn = trim((string) data_get($suggestion, 'counterparty.inn', ''));

                        return $inn === '' || ! in_array($inn, $localInns, true);
                    })
                    ->values();

                $suggestions = $suggestions->concat($dadataSuggestions);
            } catch (Throwable $exception) {
                $dadataError = $exception->getMessage();
            }
        }

        return response()->json([
            'suggestions' => $suggestions->values()->all(),
            'dadata_error' => $dadataError,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $activeTab = (string) $request->input('active_tab', 'general');

        if ($activeTab !== 'general') {
            return back()
                ->withErrors(['active_tab' => 'Для создания контрагента сначала заполните вкладку «Общие сведения».'])
                ->withInput();
        }

        $validated = $this->validateCounterpartyPayload($request);

        $counterparty = Counterparty::query()->create(array_merge([
            'type' => $validated['type'],
            'short_name' => $validated['short_name'] ?: null,
            'full_name' => $validated['full_name'] ?: null,
            'inn' => $validated['inn'] ?: null,
            'kpp' => $validated['kpp'] ?: null,
            'ogrn' => $validated['ogrn'] ?: null,
            'legal_address' => $validated['legal_address'] ?: null,
            'manager_name' => $validated['manager_name'] ?: null,
            'manager_post' => $validated['manager_post'] ?: null,
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ], $this->extractLegalAddressAttributesFromValidated($validated)));

        return redirect()
            ->route('counterparties.show', $counterparty)
            ->with('status', 'Контрагент успешно создан.');
    }

    public function edit(Counterparty $counterparty): View
    {
        $counterparty = $this->loadCounterpartyDetails($counterparty);
        $activeTab = request()->query('tab', 'general');
        $types = CounterpartyType::query()->orderBy('name')->get();
        $legalTypeId = $types->first(fn ($type) => str_contains(mb_strtolower((string) $type->name), 'ооо'))?->id ?? $types->first()?->id;
        $personTypeId = $types->first(fn ($type) => str_contains(mb_strtolower((string) $type->name), 'физ') || str_contains(mb_strtolower((string) $type->name), 'самозан'))?->id ?? $types->first()?->id;

        $primaryBankAccount = $counterparty->bankAccounts->firstWhere('is_primary', true) ?? $counterparty->bankAccounts->first();

        $bankName = $primaryBankAccount?->bank?->name;
        $bankBik = $primaryBankAccount?->bank?->bik;
        $bankCorr = $primaryBankAccount?->bank?->correspondent_account;
        $bankAccount = $primaryBankAccount?->account_number;

        $bankCopyLines = [
            (string) ($counterparty->full_name ?: $counterparty->short_name ?: 'Контрагент'),
            'ИНН: '.($counterparty->inn ?: '—'),
            'КПП: '.($counterparty->kpp ?: '—'),
            'ОГРН: '.($counterparty->ogrn ?: '—'),
            'Банк: '.($bankName ?: '—'),
            'БИК: '.($bankBik ?: '—'),
            'р/с: '.($bankAccount ?: '—'),
            'к/с: '.($bankCorr ?: '—'),
        ];

        return view('counterparties.show', [
            'mode' => 'edit',
            'types' => $types,
            'legalTypeId' => $legalTypeId,
            'personTypeId' => $personTypeId,
            'counterparty' => $counterparty,
            'activeTab' => $activeTab,
            'contactsCount' => $counterparty->contacts->count(),
            'primaryBankAccount' => $primaryBankAccount,
            'bankCopyText' => implode(PHP_EOL, $bankCopyLines),
        ]);
    }

    public function update(Request $request, Counterparty $counterparty): RedirectResponse
    {
        $activeTab = (string) $request->input('active_tab', 'general');

        if ($activeTab === 'banking') {
            $validated = $request->validate([
                'type' => ['required', 'integer', 'exists:counterparties_type,id'],
                'bank_name' => ['nullable', 'string', 'max:255'],
                'bik' => ['nullable', 'string', 'max:9'],
                'bank_account' => ['nullable', 'string', 'max:20'],
                'correspondent_account' => ['nullable', 'string', 'max:20'],
                'kpp' => ['nullable', 'string', 'max:9'],
            ]);

            $typeKind = $this->resolveTypeKindById((int) $validated['type']);
            $kpp = $typeKind === 'legal' ? ($validated['kpp'] ?: null) : null;

            $counterparty->update([
                'kpp' => $kpp,
            ]);

            $bankName = trim((string) ($validated['bank_name'] ?? ''));
            $bik = preg_replace('/\D+/', '', (string) ($validated['bik'] ?? '')) ?? '';
            $bankAccountNumber = trim((string) ($validated['bank_account'] ?? ''));
            $corrAccount = trim((string) ($validated['correspondent_account'] ?? ''));

            if ($bik !== '' && strlen($bik) !== 9) {
                throw ValidationException::withMessages([
                    'bik' => 'БИК должен содержать 9 цифр.',
                ]);
            }

            if ($bankName !== '' || $bik !== '' || $bankAccountNumber !== '' || $corrAccount !== '') {
                $bank = null;

                if ($bik !== '') {
                    $bank = Bank::query()->where('bik', $bik)->first();
                }

                if (! $bank) {
                    try {
                        if ($bik !== '') {
                            $bank = Bank::query()->create([
                                'name' => $bankName !== '' ? $bankName : 'Банк без названия',
                                'short_name' => null,
                                'bik' => $bik,
                                'correspondent_account' => $corrAccount !== '' ? $corrAccount : null,
                            ]);
                        } else {
                            $bank = Bank::query()->firstOrCreate(
                                [
                                    'name' => $bankName !== '' ? $bankName : 'Банк без названия',
                                    'bik' => null,
                                ],
                                [
                                    'short_name' => null,
                                    'correspondent_account' => $corrAccount !== '' ? $corrAccount : null,
                                ]
                            );
                        }
                    } catch (QueryException $exception) {
                        if ($bik !== '' && $this->isDuplicateBikException($exception)) {
                            $bank = Bank::query()->where('bik', $bik)->first();
                        }

                        if (! $bank) {
                            throw $exception;
                        }
                    }
                }

                if ($corrAccount !== '' && $bank->correspondent_account !== $corrAccount) {
                    $bank->update(['correspondent_account' => $corrAccount]);
                }

                $primaryBankAccount = BankAccount::query()
                    ->where('owner_type', 'counterparty')
                    ->where('owner_id', $counterparty->id)
                    ->where('is_primary', true)
                    ->first();

                if (! $primaryBankAccount) {
                    $primaryBankAccount = BankAccount::query()
                        ->where('owner_type', 'counterparty')
                        ->where('owner_id', $counterparty->id)
                        ->orderByDesc('id')
                        ->first();
                }

                if ($primaryBankAccount) {
                    $primaryBankAccount->update([
                        'bank_id' => $bank->id,
                        'account_number' => $bankAccountNumber !== '' ? $bankAccountNumber : null,
                        'is_primary' => true,
                        'is_active' => true,
                    ]);
                } else {
                    BankAccount::query()->create([
                        'owner_type' => 'counterparty',
                        'owner_id' => $counterparty->id,
                        'bank_id' => $bank->id,
                        'account_number' => $bankAccountNumber !== '' ? $bankAccountNumber : null,
                        'is_primary' => true,
                        'is_active' => true,
                    ]);
                }
            }

            return redirect()
                ->route('counterparties.edit', ['counterparty' => $counterparty, 'tab' => 'banking'])
                ->with('status', 'Банковские реквизиты сохранены.');
        }

        if ($activeTab === 'contacts') {
            $validated = $request->validate([
                'primary_contact_index' => ['nullable', 'integer'],
                'contacts' => ['nullable', 'array'],
                'contacts.*.id' => ['nullable', 'integer'],
                'contacts.*.full_name' => ['nullable', 'string', 'max:255'],
                'contacts.*.position' => ['nullable', 'string', 'max:255'],
                'contacts.*.phone_mobile' => ['nullable', 'string', 'max:20'],
                'contacts.*.email' => ['nullable', 'email', 'max:255'],
                'contacts.*.deleted' => ['nullable', 'in:0,1'],
            ]);

            $contactsPayload = $validated['contacts'] ?? [];
            $primaryIndex = isset($validated['primary_contact_index']) ? (int) $validated['primary_contact_index'] : 0;

            $existingIds = $counterparty->contacts()->pluck('id')->all();
            $touchedIds = [];

            foreach ($contactsPayload as $index => $contactRow) {
                $isDeleted = ((string) ($contactRow['deleted'] ?? '0')) === '1';
                $contactId = isset($contactRow['id']) && $contactRow['id'] !== '' ? (int) $contactRow['id'] : null;

                if ($isDeleted && $contactId) {
                    $counterparty->contacts()->whereKey($contactId)->delete();
                    continue;
                }

                $fullName = trim((string) ($contactRow['full_name'] ?? ''));
                $position = trim((string) ($contactRow['position'] ?? ''));
                $phone = trim((string) ($contactRow['phone_mobile'] ?? ''));
                $email = trim((string) ($contactRow['email'] ?? ''));

                if ($fullName === '' && $position === '' && $phone === '' && $email === '') {
                    continue;
                }

                $payload = [
                    'full_name' => $fullName !== '' ? $fullName : 'Контакт без имени',
                    'phone_mobile' => $phone !== '' ? $phone : null,
                    'email' => $email !== '' ? $email : null,
                    'notes' => $position !== '' ? $position : null,
                    'is_active' => true,
                    'is_primary' => $index === $primaryIndex,
                ];

                if ($contactId) {
                    $contact = $counterparty->contacts()->whereKey($contactId)->first();
                    if ($contact) {
                        $contact->update($payload);
                        $touchedIds[] = $contact->id;
                    }
                } else {
                    $contact = $counterparty->contacts()->create($payload);
                    $touchedIds[] = $contact->id;
                }
            }

            $idsToDelete = array_diff($existingIds, $touchedIds);
            if ($idsToDelete !== []) {
                $counterparty->contacts()->whereIn('id', $idsToDelete)->delete();
            }

            if ($counterparty->contacts()->exists() && ! $counterparty->contacts()->where('is_primary', true)->exists()) {
                $firstContact = $counterparty->contacts()->orderBy('id')->first();
                if ($firstContact) {
                    $firstContact->update(['is_primary' => true]);
                }
            }

            return redirect()
                ->route('counterparties.edit', ['counterparty' => $counterparty, 'tab' => 'contacts'])
                ->with('status', 'Контакты сохранены.');
        }

        $validated = $this->validateCounterpartyPayload($request);

        $counterparty->update(array_merge([
            'type' => $validated['type'],
            'short_name' => $validated['short_name'] ?: null,
            'full_name' => $validated['full_name'] ?: null,
            'inn' => $validated['inn'] ?: null,
            'kpp' => $validated['kpp'] ?: null,
            'ogrn' => $validated['ogrn'] ?: null,
            'legal_address' => $validated['legal_address'] ?: null,
            'manager_name' => $validated['manager_name'] ?: null,
            'manager_post' => $validated['manager_post'] ?: null,
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ], $this->extractLegalAddressAttributesFromValidated($validated)));

        return redirect()
            ->route('counterparties.edit', ['counterparty' => $counterparty, 'tab' => 'general'])
            ->with('status', 'Карточка контрагента обновлена.');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));

        $counterpartiesQuery = Counterparty::query()
            ->with('typeRef')
            ->withCount('contacts');

        if ($search !== '') {
            $counterpartiesQuery->where(function ($query) use ($search): void {
                $query->where('short_name', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('inn', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('manager_name', 'like', "%{$search}%")
                    ->orWhere('manager_post', 'like', "%{$search}%")
                    ->orWhere('legal_address', 'like', "%{$search}%");
            });
        }

        if ($type !== '' && ctype_digit($type)) {
            $counterpartiesQuery->where('type', (int) $type);
        }

        $counterparties = $counterpartiesQuery
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $types = CounterpartyType::query()->orderBy('name')->get();

        $individualTypeIds = CounterpartyType::query()
            ->where('name', 'like', 'Физ%')
            ->orWhere('name', 'like', 'Самозан%')
            ->pluck('id');

        $allCount = Counterparty::query()->count();

        $legalCount = Counterparty::query()
            ->when(
                $individualTypeIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('type', $individualTypeIds)
            )
            ->count();

        return view('counterparties.index', [
            'counterparties' => $counterparties,
            'types' => $types,
            'search' => $search,
            'selectedType' => $type,
            'allCount' => $allCount,
            'legalCount' => $legalCount,
        ]);
    }

    public function show(Request $request, Counterparty $counterparty): View
    {
        $counterparty = $this->loadCounterpartyDetails($counterparty);

        $activeTab = (string) $request->query('tab', 'general');

        $primaryBankAccount = $counterparty->bankAccounts->firstWhere('is_primary', true)
            ?? $counterparty->bankAccounts->first();

        $bankName = $primaryBankAccount?->bank?->name;
        $bankBik = $primaryBankAccount?->bank?->bik;
        $bankCorr = $primaryBankAccount?->bank?->correspondent_account;
        $bankAccount = $primaryBankAccount?->account_number;

        $bankCopyLines = [
            (string) ($counterparty->full_name ?: $counterparty->short_name ?: 'Контрагент'),
            'ИНН: '.($counterparty->inn ?: '—'),
            'КПП: '.($counterparty->kpp ?: '—'),
            'ОГРН: '.($counterparty->ogrn ?: '—'),
            'Банк: '.($bankName ?: '—'),
            'БИК: '.($bankBik ?: '—'),
            'р/с: '.($bankAccount ?: '—'),
            'к/с: '.($bankCorr ?: '—'),
        ];

        return view('counterparties.show', [
            'mode' => 'show',
            'types' => collect(),
            'legalTypeId' => null,
            'personTypeId' => null,
            'counterparty' => $counterparty,
            'activeTab' => $activeTab,
            'contactsCount' => $counterparty->contacts->count(),
            'primaryBankAccount' => $primaryBankAccount,
            'bankCopyText' => implode(PHP_EOL, $bankCopyLines),
        ]);
    }

    public function autofillByInn(Request $request, FindPartyService $findPartyService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:40'],
        ]);

        $query = preg_replace('/\D+/', '', (string) $validated['query']) ?? '';

        if (! in_array(strlen($query), [10, 12, 13, 15], true)) {
            return response()->json([
                'message' => 'Укажите корректный ИНН или ОГРН (10, 12, 13 или 15 цифр).',
            ], 422);
        }

        try {
            $result = $findPartyService->findByQuery($query, ['count' => 1]);
            $suggestion = data_get($result, 'suggestions.0');

            if (! is_array($suggestion)) {
                return response()->json([
                    'message' => 'По указанному ИНН/ОГРН данные не найдены.',
                ], 404);
            }

            $data = is_array($suggestion['data'] ?? null) ? $suggestion['data'] : [];

            $partyType = mb_strtoupper((string) ($data['type'] ?? 'LEGAL'));
            $typeKind = $partyType === 'INDIVIDUAL' ? 'entrepreneur' : 'legal';

            $shortName = (string) (
                data_get($data, 'name.short_with_opf')
                ?? data_get($data, 'name.short')
                ?? ''
            );

            $fullName = (string) (
                data_get($data, 'name.full_with_opf')
                ?? data_get($data, 'name.full')
                ?? ''
            );

            $phone = trim((string) (
                data_get($data, 'phones.0.value')
                ?? data_get($data, 'phones.0.data.value')
                ?? ''
            ));
            $addressAttributes = $this->extractLegalAddressAttributesFromDaDataParty($data);
            $managerAttributes = $this->extractManagerAttributesFromDaDataParty($data);

            return response()->json([
                'message' => 'Данные по ИНН/ОГРН успешно загружены.',
                'data' => array_merge([
                    'type_kind' => $typeKind,
                    'short_name' => $shortName !== '' ? $shortName : null,
                    'full_name' => $fullName !== '' ? $fullName : null,
                    'inn' => (string) ($data['inn'] ?? $query),
                    'kpp' => (string) ($data['kpp'] ?? ''),
                    'ogrn' => (string) (($data['ogrn'] ?? $data['ogrnip'] ?? '')),
                    'phone' => $phone !== '' ? $phone : null,
                ], $addressAttributes, $managerAttributes),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Не удалось получить данные из DaData: '.$exception->getMessage(),
            ], 422);
        }
    }

    public function autofillBankByBik(Request $request, FindBankService $findBankService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:20'],
        ]);

        $bik = preg_replace('/\D+/', '', (string) $validated['query']) ?? '';

        if (strlen($bik) !== 9) {
            return response()->json([
                'message' => 'Укажите корректный БИК (9 цифр).',
            ], 422);
        }

        try {
            $result = $findBankService->findByBik($bik, ['count' => 1]);
            $suggestion = data_get($result, 'suggestions.0');

            if (! is_array($suggestion)) {
                return response()->json([
                    'message' => 'Банк по указанному БИК не найден.',
                ], 404);
            }

            $data = is_array($suggestion['data'] ?? null) ? $suggestion['data'] : [];

            $bankName = (string) (
                data_get($data, 'name.payment')
                ?? data_get($data, 'name.short')
                ?? data_get($suggestion, 'value')
                ?? ''
            );

            $correspondentAccount = (string) (
                data_get($data, 'correspondent_account')
                ?? ''
            );

            $resolvedBik = (string) (
                data_get($data, 'bic')
                ?? data_get($data, 'bik')
                ?? $bik
            );

            return response()->json([
                'message' => 'Реквизиты банка по БИК успешно загружены.',
                'data' => [
                    'bank_name' => $bankName !== '' ? $bankName : null,
                    'bik' => $resolvedBik,
                    'correspondent_account' => $correspondentAccount !== '' ? $correspondentAccount : null,
                ],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Не удалось получить реквизиты банка из DaData: '.$exception->getMessage(),
            ], 422);
        }
    }

    private function counterpartyRules(): array
    {
        return [
            'type' => ['required', 'integer', 'exists:counterparties_type,id'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:12'],
            'kpp' => ['nullable', 'string', 'max:9'],
            'ogrn' => ['nullable', 'string', 'max:15'],
            'legal_address' => ['nullable', 'string', 'max:1000'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'manager_post' => ['nullable', 'string', 'max:255'],
            'legal_postal_code' => ['nullable', 'string', 'max:10'],
            'legal_region' => ['nullable', 'string', 'max:150'],
            'legal_city' => ['nullable', 'string', 'max:150'],
            'legal_settlement' => ['nullable', 'string', 'max:150'],
            'legal_street' => ['nullable', 'string', 'max:150'],
            'legal_house' => ['nullable', 'string', 'max:50'],
            'legal_block' => ['nullable', 'string', 'max:50'],
            'legal_flat' => ['nullable', 'string', 'max:50'],
            'legal_fias_id' => ['nullable', 'string', 'max:50'],
            'legal_kladr_id' => ['nullable', 'string', 'max:20'],
            'legal_geo_lat' => ['nullable', 'numeric'],
            'legal_geo_lon' => ['nullable', 'numeric'],
            'legal_qc' => ['nullable', 'integer', 'min:0', 'max:255'],
            'legal_qc_geo' => ['nullable', 'integer', 'min:0', 'max:255'],
            'legal_address_invalid' => ['nullable', 'boolean'],
            'legal_address_data' => ['nullable', 'string'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function validateCounterpartyPayload(Request $request): array
    {
        $validated = $request->validate($this->counterpartyRules());

        $typeKind = $this->resolveTypeKindById((int) $validated['type']);

        $errors = [];

        if ($typeKind === 'legal' && blank($validated['short_name'] ?? null) && blank($validated['full_name'] ?? null)) {
            $errors['short_name'] = 'Для юрлица укажите краткое или полное наименование.';
        }

        if (in_array($typeKind, ['entrepreneur', 'person'], true) && blank($validated['full_name'] ?? null)) {
            $errors['full_name'] = 'Для ИП/физлица укажите ФИО.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($typeKind !== 'legal') {
            $validated['kpp'] = null;
            $validated['short_name'] = null;
        }

        return $validated;
    }

    /**
     * @return list<array{source: string,counterparty: array<string, mixed>,action_url: string}>
     */
    private function searchLocalCounterparties(string $query): array
    {
        return Counterparty::query()
            ->with('typeRef')
            ->where(function ($builder) use ($query): void {
                $builder->where('short_name', 'like', "%{$query}%")
                    ->orWhere('full_name', 'like', "%{$query}%")
                    ->orWhere('inn', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('manager_name', 'like', "%{$query}%")
                    ->orWhere('manager_post', 'like', "%{$query}%")
                    ->orWhere('legal_address', 'like', "%{$query}%");
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (Counterparty $counterparty): array {
                $name = trim((string) ($counterparty->short_name ?: $counterparty->full_name ?: 'Без названия'));
                $inn = trim((string) ($counterparty->inn ?? ''));
                $phone = trim((string) ($counterparty->phone ?? ''));

                return [
                    'source' => 'local',
                    'action_url' => route('counterparties.show', $counterparty),
                    'counterparty' => [
                        'id' => $counterparty->id,
                        'name' => $name,
                        'label' => $inn !== '' ? $name.' (ИНН '.$inn.')' : $name,
                        'phone' => $phone !== '' ? $phone : '—',
                        'inn' => $inn,
                        'short_name' => trim((string) ($counterparty->short_name ?? '')),
                        'full_name' => trim((string) ($counterparty->full_name ?? '')),
                        'manager_name' => trim((string) ($counterparty->manager_name ?? '')),
                        'manager_post' => trim((string) ($counterparty->manager_post ?? '')),
                        'legal_address' => trim((string) ($counterparty->legal_address ?? '')),
                        'type_kind' => $this->resolveTypeKindById((int) $counterparty->type),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{source: string,counterparty: array<string, mixed>,action_url: string}|null
     */
    private function mapDaDataCounterpartySuggestion(mixed $suggestion): ?array
    {
        if (! is_array($suggestion)) {
            return null;
        }

        $data = is_array($suggestion['data'] ?? null) ? $suggestion['data'] : [];
        $partyType = mb_strtoupper((string) ($data['type'] ?? 'LEGAL'));
        $typeKind = $partyType === 'INDIVIDUAL' ? 'entrepreneur' : 'legal';
        $shortName = trim((string) (data_get($data, 'name.short_with_opf') ?? data_get($data, 'name.short') ?? ''));
        $fullName = trim((string) (data_get($data, 'name.full_with_opf') ?? data_get($data, 'name.full') ?? ''));
        $name = $shortName !== '' ? $shortName : ($fullName !== '' ? $fullName : trim((string) ($suggestion['value'] ?? 'Контрагент без названия')));
        $phone = trim((string) (data_get($data, 'phones.0.value') ?? data_get($data, 'phones.0.data.value') ?? ''));
        $inn = trim((string) ($data['inn'] ?? ''));
        $addressAttributes = $this->extractLegalAddressAttributesFromDaDataParty($data);
        $managerAttributes = $this->extractManagerAttributesFromDaDataParty($data);

        $query = [
            'prefill' => 1,
            'type_kind' => $typeKind,
            'short_name' => $shortName,
            'full_name' => $fullName,
            'inn' => $inn,
            'kpp' => trim((string) ($data['kpp'] ?? '')),
            'ogrn' => trim((string) (($data['ogrn'] ?? $data['ogrnip'] ?? ''))),
            'phone' => $phone,
        ];

        $query = array_merge($query, $this->buildLegalAddressQueryParameters($addressAttributes), $this->buildLegalAddressQueryParameters($managerAttributes));

        return [
            'source' => 'dadata',
            'action_url' => route('counterparties.create', array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== '')),
            'counterparty' => array_merge([
                'id' => null,
                'name' => $name,
                'label' => $inn !== '' ? $name.' (DaData, ИНН '.$inn.')' : $name.' (DaData)',
                'phone' => $phone !== '' ? $phone : '—',
                'inn' => $inn,
                'short_name' => $shortName,
                'full_name' => $fullName,
                'kpp' => trim((string) ($data['kpp'] ?? '')),
                'ogrn' => trim((string) (($data['ogrn'] ?? $data['ogrnip'] ?? ''))),
                'type_kind' => $typeKind,
            ], $addressAttributes, $managerAttributes),
        ];
    }

    private function resolveTypeKindById(int $typeId): string
    {
        $type = CounterpartyType::query()->find($typeId);
        $normalizedName = mb_strtolower(trim((string) $type?->name));

        if ($normalizedName === '') {
            return 'legal';
        }

        if (str_contains($normalizedName, 'физ') || str_contains($normalizedName, 'самозан')) {
            return 'person';
        }

        if (
            preg_match('/(^|\s|[^а-яa-z])ип($|\s|[^а-яa-z])/u', $normalizedName) === 1
            || str_contains($normalizedName, 'индивидуал')
            || str_contains($normalizedName, 'предприним')
        ) {
            return 'entrepreneur';
        }

        return 'legal';
    }

    private function resolveTypeIdByKind(string $kind, Collection $types, ?int $fallbackTypeId = null): ?int
    {
        $normalizedKind = trim($kind);

        $matchedType = match ($normalizedKind) {
            'person' => $types->first(fn ($type) => str_contains(mb_strtolower((string) $type->name), 'физ') || str_contains(mb_strtolower((string) $type->name), 'самозан')),
            'entrepreneur' => $types->first(function ($type) {
                $normalizedTypeName = mb_strtolower(trim((string) $type->name));

                return preg_match('/(^|\s|[^а-яa-z])ип($|\s|[^а-яa-z])/u', $normalizedTypeName) === 1
                    || str_contains($normalizedTypeName, 'индивидуал')
                    || str_contains($normalizedTypeName, 'предприним');
            }),
            default => $types->first(fn ($type) => str_contains(mb_strtolower((string) $type->name), 'ооо')),
        };

        return $matchedType?->id ?? $fallbackTypeId ?? $types->first()?->id;
    }

    private function applyCreatePrefill(Request $request, Counterparty $counterparty, Collection $types, ?int $fallbackTypeId = null): Counterparty
    {
        if (! $request->boolean('prefill')) {
            return $counterparty;
        }

        $counterparty->type = $this->resolveTypeIdByKind((string) $request->query('type_kind', 'legal'), $types, $fallbackTypeId);
        $counterparty->short_name = $this->normalizeQueryValue($request->query('short_name'));
        $counterparty->full_name = $this->normalizeQueryValue($request->query('full_name'));
        $counterparty->inn = $this->normalizeQueryValue($request->query('inn'));
        $counterparty->kpp = $this->normalizeQueryValue($request->query('kpp'));
        $counterparty->ogrn = $this->normalizeQueryValue($request->query('ogrn'));
        $counterparty->phone = $this->normalizeQueryValue($request->query('phone'));
        $counterparty->legal_address = $this->normalizeQueryValue($request->query('legal_address'));
        $counterparty->manager_name = $this->normalizeQueryValue($request->query('manager_name'));
        $counterparty->manager_post = $this->normalizeQueryValue($request->query('manager_post'));
        $counterparty->legal_postal_code = $this->normalizeQueryValue($request->query('legal_postal_code'));
        $counterparty->legal_region = $this->normalizeQueryValue($request->query('legal_region'));
        $counterparty->legal_city = $this->normalizeQueryValue($request->query('legal_city'));
        $counterparty->legal_settlement = $this->normalizeQueryValue($request->query('legal_settlement'));
        $counterparty->legal_street = $this->normalizeQueryValue($request->query('legal_street'));
        $counterparty->legal_house = $this->normalizeQueryValue($request->query('legal_house'));
        $counterparty->legal_block = $this->normalizeQueryValue($request->query('legal_block'));
        $counterparty->legal_flat = $this->normalizeQueryValue($request->query('legal_flat'));
        $counterparty->legal_fias_id = $this->normalizeQueryValue($request->query('legal_fias_id'));
        $counterparty->legal_kladr_id = $this->normalizeQueryValue($request->query('legal_kladr_id'));
        $counterparty->legal_geo_lat = $this->normalizeQueryValue($request->query('legal_geo_lat'));
        $counterparty->legal_geo_lon = $this->normalizeQueryValue($request->query('legal_geo_lon'));
        $counterparty->legal_qc = $this->normalizeQueryValue($request->query('legal_qc'));
        $counterparty->legal_qc_geo = $this->normalizeQueryValue($request->query('legal_qc_geo'));
        $counterparty->legal_address_invalid = $this->normalizeQueryValue($request->query('legal_address_invalid'));
        $counterparty->legal_address_data = $this->decodeJsonArray($request->query('legal_address_data'));

        return $counterparty;
    }

    private function normalizeQueryValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function extractLegalAddressAttributesFromDaDataParty(array $data): array
    {
        $addressData = is_array(data_get($data, 'address.data')) ? data_get($data, 'address.data') : [];
        $fullAddress = trim((string) (data_get($data, 'address.unrestricted_value') ?? data_get($data, 'address.value') ?? ''));
        $addressInvalidity = data_get($data, 'address.invalidity');
        $hasAddress = $fullAddress !== '' || $addressData !== [];

        return [
            'legal_address' => $fullAddress !== '' ? $fullAddress : null,
            'legal_postal_code' => $this->nullableString(data_get($addressData, 'postal_code')),
            'legal_region' => $this->nullableString(data_get($addressData, 'region_with_type')) ?? $this->nullableString(data_get($addressData, 'region')),
            'legal_city' => $this->nullableString(data_get($addressData, 'city_with_type')) ?? $this->nullableString(data_get($addressData, 'city')),
            'legal_settlement' => $this->nullableString(data_get($addressData, 'settlement_with_type')) ?? $this->nullableString(data_get($addressData, 'settlement')),
            'legal_street' => $this->nullableString(data_get($addressData, 'street_with_type')) ?? $this->nullableString(data_get($addressData, 'street')),
            'legal_house' => $this->nullableString(data_get($addressData, 'house')),
            'legal_block' => $this->nullableString(data_get($addressData, 'block')),
            'legal_flat' => $this->nullableString(data_get($addressData, 'flat')),
            'legal_fias_id' => $this->nullableString(data_get($addressData, 'fias_id')),
            'legal_kladr_id' => $this->nullableString(data_get($addressData, 'kladr_id')),
            'legal_geo_lat' => $this->nullableFloat(data_get($addressData, 'geo_lat')),
            'legal_geo_lon' => $this->nullableFloat(data_get($addressData, 'geo_lon')),
            'legal_qc' => $this->nullableInt(data_get($addressData, 'qc')),
            'legal_qc_geo' => $this->nullableInt(data_get($addressData, 'qc_geo')),
            'legal_address_invalid' => $hasAddress ? $addressInvalidity !== null : null,
            'legal_address_data' => $addressData !== [] ? $addressData : null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{manager_name: ?string, manager_post: ?string}
     */
    private function extractManagerAttributesFromDaDataParty(array $data): array
    {
        return [
            'manager_name' => $this->nullableString(data_get($data, 'management.name')),
            'manager_post' => $this->nullableString(data_get($data, 'management.post')),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function extractLegalAddressAttributesFromValidated(array $validated): array
    {
        return [
            'legal_postal_code' => $this->nullableString($validated['legal_postal_code'] ?? null),
            'legal_region' => $this->nullableString($validated['legal_region'] ?? null),
            'legal_city' => $this->nullableString($validated['legal_city'] ?? null),
            'legal_settlement' => $this->nullableString($validated['legal_settlement'] ?? null),
            'legal_street' => $this->nullableString($validated['legal_street'] ?? null),
            'legal_house' => $this->nullableString($validated['legal_house'] ?? null),
            'legal_block' => $this->nullableString($validated['legal_block'] ?? null),
            'legal_flat' => $this->nullableString($validated['legal_flat'] ?? null),
            'legal_fias_id' => $this->nullableString($validated['legal_fias_id'] ?? null),
            'legal_kladr_id' => $this->nullableString($validated['legal_kladr_id'] ?? null),
            'legal_geo_lat' => $this->nullableFloat($validated['legal_geo_lat'] ?? null),
            'legal_geo_lon' => $this->nullableFloat($validated['legal_geo_lon'] ?? null),
            'legal_qc' => $this->nullableInt($validated['legal_qc'] ?? null),
            'legal_qc_geo' => $this->nullableInt($validated['legal_qc_geo'] ?? null),
            'legal_address_invalid' => $this->nullableBoolean($validated['legal_address_invalid'] ?? null),
            'legal_address_data' => $this->decodeJsonArray($validated['legal_address_data'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, string>
     */
    private function buildLegalAddressQueryParameters(array $attributes): array
    {
        $result = [];

        foreach ($attributes as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $result[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                continue;
            }

            if (is_bool($value)) {
                $result[$key] = $value ? '1' : '0';
                continue;
            }

            $result[$key] = (string) $value;
        }

        return $result;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonArray(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function loadCounterpartyDetails(Counterparty $counterparty): Counterparty
    {
        $counterparty->load([
            'typeRef',
            'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
            'bankAccounts' => fn ($query) => $query->where('owner_type', 'counterparty')->with('bank')->orderByDesc('is_primary')->orderBy('id'),
        ]);

        return $counterparty;
    }

    private function isDuplicateBikException(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $sqlState = (string) ($errorInfo[0] ?? '');
        $driverErrorCode = (string) ($errorInfo[1] ?? '');
        $driverMessage = mb_strtolower((string) ($errorInfo[2] ?? $exception->getMessage()));

        return ($sqlState === '23000' && $driverErrorCode === '1062')
            || str_contains($driverMessage, 'duplicate entry')
            || str_contains($driverMessage, 'banks_bik_unique');
    }
}
