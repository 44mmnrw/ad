<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Counterparty;
use App\Models\CounterpartyType;
use App\Services\DaData\FindPartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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
        $counterparty->setRelation('typeRef', $types->first());
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

    public function store(Request $request): RedirectResponse
    {
        $activeTab = (string) $request->input('active_tab', 'general');

        if ($activeTab !== 'general') {
            return back()
                ->withErrors(['active_tab' => 'Для создания контрагента сначала заполните вкладку «Общие сведения».'])
                ->withInput();
        }

        $validated = $this->validateCounterpartyPayload($request);

        $counterparty = Counterparty::query()->create([
            'type' => $validated['type'],
            'short_name' => $validated['short_name'] ?: null,
            'full_name' => $validated['full_name'] ?: null,
            'inn' => $validated['inn'] ?: null,
            'kpp' => $validated['kpp'] ?: null,
            'ogrn' => $validated['ogrn'] ?: null,
            'legal_address' => $validated['legal_address'] ?: null,
            'actual_address' => $validated['actual_address'] ?: null,
            'ceo' => $validated['full_name'] ?: $validated['short_name'] ?: null,
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ]);

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
            $bik = trim((string) ($validated['bik'] ?? ''));
            $bankAccountNumber = trim((string) ($validated['bank_account'] ?? ''));
            $corrAccount = trim((string) ($validated['correspondent_account'] ?? ''));

            if ($bankName !== '' || $bik !== '' || $bankAccountNumber !== '' || $corrAccount !== '') {
                $bank = null;

                if ($bik !== '') {
                    $bank = Bank::query()->where('bik', $bik)->first();
                }

                if (! $bank) {
                    $bank = Bank::query()->firstOrCreate(
                        [
                            'name' => $bankName !== '' ? $bankName : 'Банк без названия',
                            'bik' => $bik !== '' ? $bik : null,
                        ],
                        [
                            'short_name' => null,
                            'correspondent_account' => $corrAccount !== '' ? $corrAccount : null,
                        ]
                    );
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

        $counterparty->update([
            'type' => $validated['type'],
            'short_name' => $validated['short_name'] ?: null,
            'full_name' => $validated['full_name'] ?: null,
            'inn' => $validated['inn'] ?: null,
            'kpp' => $validated['kpp'] ?: null,
            'ogrn' => $validated['ogrn'] ?: null,
            'legal_address' => $validated['legal_address'] ?: null,
            'actual_address' => $validated['actual_address'] ?: null,
            'ceo' => $validated['full_name'] ?: $validated['short_name'] ?: null,
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ]);

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
                    ->orWhere('actual_address', 'like', "%{$search}%")
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

            $address = (string) (
                data_get($data, 'address.unrestricted_value')
                ?? data_get($data, 'address.value')
                ?? ''
            );

            return response()->json([
                'message' => 'Данные по ИНН/ОГРН успешно загружены.',
                'data' => [
                    'type_kind' => $typeKind,
                    'short_name' => $shortName !== '' ? $shortName : null,
                    'full_name' => $fullName !== '' ? $fullName : null,
                    'inn' => (string) ($data['inn'] ?? $query),
                    'kpp' => (string) ($data['kpp'] ?? ''),
                    'ogrn' => (string) (($data['ogrn'] ?? $data['ogrnip'] ?? '')),
                    'legal_address' => $address !== '' ? $address : null,
                    'actual_address' => $address !== '' ? $address : null,
                ],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Не удалось получить данные из DaData: '.$exception->getMessage(),
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
            'legal_address' => ['nullable', 'string', 'max:255'],
            'actual_address' => ['nullable', 'string', 'max:255'],
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

    private function loadCounterpartyDetails(Counterparty $counterparty): Counterparty
    {
        $counterparty->load([
            'typeRef',
            'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
            'bankAccounts' => fn ($query) => $query->where('owner_type', 'counterparty')->with('bank')->orderByDesc('is_primary')->orderBy('id'),
        ]);

        return $counterparty;
    }
}
