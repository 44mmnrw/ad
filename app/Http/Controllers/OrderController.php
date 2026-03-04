<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $orders = array_values($this->orders());

        if ($statusFilter !== 'all') {
            $orders = array_values(array_filter(
                $orders,
                static fn (array $order): bool => ($order['status_code'] ?? null) === $statusFilter
            ));
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $orders = array_values(array_filter($orders, static function (array $order) use ($needle): bool {
                $haystack = implode(' ', [
                    (string) ($order['number'] ?? ''),
                    (string) ($order['route']['from']['city'] ?? ''),
                    (string) ($order['route']['to']['city'] ?? ''),
                    (string) ($order['driver']['name'] ?? ''),
                    (string) ($order['driver']['car'] ?? ''),
                ]);

                return str_contains(mb_strtolower($haystack), $needle);
            }));
        }

        return view('orders.index', [
            'orders' => $orders,
            'statusFilter' => $statusFilter,
            'search' => $search,
        ]);
    }

    public function show(int $order): View
    {
        $currentOrder = $this->orders()[$order] ?? $this->orders()[1];

        return view('orders.show', [
            'order' => $currentOrder,
            'routeMapUrl' => $this->buildStaticMapUrl($currentOrder),
        ]);
    }

    public function create(): View
    {
        return view('orders.form-page', [
            'order' => null,
            'pageTitle' => 'Новая заявка',
            'backRoute' => route('orders.index'),
            'backLabel' => '← Вернуться к списку',
            'metaTitle' => 'Создать заявку',
        ]);
    }

    public function edit(int $order): View
    {
        $currentOrder = $this->orders()[$order] ?? $this->orders()[1];

        return view('orders.form-page', [
            'order' => $currentOrder,
            'pageTitle' => 'Редактирование заявки '.$currentOrder['number'],
            'backRoute' => route('orders.show', $currentOrder['id']),
            'backLabel' => '← Назад к заявке',
            'metaTitle' => 'Редактирование заявки',
        ]);
    }

    /**
     * Demo-данные до подключения БД.
     *
     * @return array<int, array<string, mixed>>
     */
    private function orders(): array
    {
        return [
            1 => [
                'id' => 1,
                'number' => 'АД-20240315-001',
                'created_at' => '15.03.2024',
                'status' => 'В пути',
                'status_code' => 'in_transit',
                'distance' => '480 км',
                'route' => [
                    'from' => ['city' => 'Москва', 'address' => 'ул. Ленина, 12', 'lat' => 55.7558, 'lng' => 37.6176],
                    'to' => ['city' => 'Санкт-Петербург', 'address' => 'пр. Невский, 45', 'lat' => 59.9343, 'lng' => 30.3351],
                ],
                'progress' => [
                    ['title' => 'Загрузка', 'time' => '10:30', 'state' => 'done'],
                    ['title' => 'В пути', 'time' => '12:45', 'state' => 'active'],
                    ['title' => 'Разгрузка', 'time' => '—', 'state' => 'pending'],
                ],
                'driver_link' => 'https://avtodostavka.ru/d/abc123xyz',
                'driver_link_expires' => 'Ссылка активна до: 16.03.2024, 23:59',
                'driver' => [
                    'title' => 'Водитель',
                    'emoji' => '🚗',
                    'name' => 'Иванов Иван Иванович',
                    'car' => 'Газель, А123БВ 77',
                    'phone' => '+7 (999) 123-45-67',
                ],
                'sender' => [
                    'title' => 'Грузоотправитель',
                    'emoji' => '📦',
                    'name' => 'Петров Сергей Николаевич',
                    'phone' => '+7 (911) 000-11-22',
                ],
                'receiver' => [
                    'title' => 'Грузополучатель',
                    'emoji' => '📬',
                    'name' => 'Сидорова Анна Петровна',
                    'phone' => '+7 (921) 333-44-55',
                ],
            ],
            2 => [
                'id' => 2,
                'number' => 'АД-20240315-002',
                'created_at' => '15.03.2024',
                'status' => 'Загрузка',
                'status_code' => 'loading',
                'distance' => '820 км',
                'route' => [
                    'from' => ['city' => 'Казань', 'address' => 'ул. Баумана, 10', 'lat' => 55.7961, 'lng' => 49.1064],
                    'to' => ['city' => 'Москва', 'address' => 'Ленинградский проспект, 37', 'lat' => 55.7905, 'lng' => 37.5448],
                ],
                'progress' => [
                    ['title' => 'Загрузка', 'time' => '09:20', 'state' => 'active'],
                    ['title' => 'В пути', 'time' => '—', 'state' => 'pending'],
                    ['title' => 'Разгрузка', 'time' => '—', 'state' => 'pending'],
                ],
                'driver_link' => 'https://avtodostavka.ru/d/ktx620plm',
                'driver_link_expires' => 'Ссылка активна до: 16.03.2024, 23:59',
                'driver' => [
                    'title' => 'Водитель',
                    'emoji' => '🚗',
                    'name' => 'Смирнов Петр Васильевич',
                    'car' => 'MAN TGX, В456ГД 16',
                    'phone' => '+7 (977) 456-78-90',
                ],
                'sender' => [
                    'title' => 'Грузоотправитель',
                    'emoji' => '📦',
                    'name' => 'ГК Волга-Трейд',
                    'phone' => '+7 (843) 220-11-11',
                ],
                'receiver' => [
                    'title' => 'Грузополучатель',
                    'emoji' => '📬',
                    'name' => 'ООО Логистик Центр',
                    'phone' => '+7 (495) 123-12-12',
                ],
            ],
            3 => [
                'id' => 3,
                'number' => 'АД-20240314-015',
                'created_at' => '14.03.2024',
                'status' => 'Завершено',
                'status_code' => 'completed',
                'distance' => '1420 км',
                'route' => [
                    'from' => ['city' => 'Новосибирск', 'address' => 'ул. Фрунзе, 5', 'lat' => 55.0084, 'lng' => 82.9357],
                    'to' => ['city' => 'Екатеринбург', 'address' => 'ул. Малышева, 88', 'lat' => 56.8389, 'lng' => 60.6057],
                ],
                'progress' => [
                    ['title' => 'Загрузка', 'time' => '11:00', 'state' => 'done'],
                    ['title' => 'В пути', 'time' => '20:45', 'state' => 'done'],
                    ['title' => 'Разгрузка', 'time' => '09:15', 'state' => 'done'],
                ],
                'driver_link' => 'https://avtodostavka.ru/d/nsk-ekb-015',
                'driver_link_expires' => 'Срок действия ссылки истёк',
                'driver' => [
                    'title' => 'Водитель',
                    'emoji' => '🚗',
                    'name' => 'Козлов Дмитрий Андреевич',
                    'car' => 'Volvo FH, С789ЕЖ 54',
                    'phone' => '+7 (923) 700-55-44',
                ],
                'sender' => [
                    'title' => 'Грузоотправитель',
                    'emoji' => '📦',
                    'name' => 'СибТрансСервис',
                    'phone' => '+7 (383) 312-12-12',
                ],
                'receiver' => [
                    'title' => 'Грузополучатель',
                    'emoji' => '📬',
                    'name' => 'УралСклад',
                    'phone' => '+7 (343) 410-10-10',
                ],
            ],
            4 => [
                'id' => 4,
                'number' => 'АД-20240315-003',
                'created_at' => '15.03.2024',
                'status' => 'Разгрузка',
                'status_code' => 'unloading',
                'distance' => '285 км',
                'route' => [
                    'from' => ['city' => 'Краснодар', 'address' => 'ул. Северная, 201', 'lat' => 45.0355, 'lng' => 38.9753],
                    'to' => ['city' => 'Ростов-на-Дону', 'address' => 'Будённовский пр., 98', 'lat' => 47.2357, 'lng' => 39.7015],
                ],
                'progress' => [
                    ['title' => 'Загрузка', 'time' => '07:50', 'state' => 'done'],
                    ['title' => 'В пути', 'time' => '10:35', 'state' => 'done'],
                    ['title' => 'Разгрузка', 'time' => '13:10', 'state' => 'active'],
                ],
                'driver_link' => 'https://avtodostavka.ru/d/krd-rnd-003',
                'driver_link_expires' => 'Ссылка активна до: 16.03.2024, 23:59',
                'driver' => [
                    'title' => 'Водитель',
                    'emoji' => '🚗',
                    'name' => 'Соколов Андрей Николаевич',
                    'car' => 'Mercedes Actros, Т012МН 23',
                    'phone' => '+7 (918) 321-00-77',
                ],
                'sender' => [
                    'title' => 'Грузоотправитель',
                    'emoji' => '📦',
                    'name' => 'КубаньАгро',
                    'phone' => '+7 (861) 222-32-32',
                ],
                'receiver' => [
                    'title' => 'Грузополучатель',
                    'emoji' => '📬',
                    'name' => 'РостовЛогистик',
                    'phone' => '+7 (863) 303-03-03',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $order
     */
    private function buildStaticMapUrl(array $order): ?string
    {
        $from = $order['route']['from'] ?? null;
        $to = $order['route']['to'] ?? null;

        if (! is_array($from) || ! is_array($to)) {
            return null;
        }

        $fromLat = $from['lat'] ?? null;
        $fromLng = $from['lng'] ?? null;
        $toLat = $to['lat'] ?? null;
        $toLng = $to['lng'] ?? null;

        if (! is_numeric($fromLat) || ! is_numeric($fromLng) || ! is_numeric($toLat) || ! is_numeric($toLng)) {
            return null;
        }

        $fromPoint = number_format((float) $fromLng, 6, '.', '').','.number_format((float) $fromLat, 6, '.', '');
        $toPoint = number_format((float) $toLng, 6, '.', '').','.number_format((float) $toLat, 6, '.', '');

        $params = [
            'lang' => 'ru_RU',
            'size' => '650,280',
            'l' => 'map',
            'pt' => $fromPoint.',pm2blm~'.$toPoint.',pm2grm',
            'pl' => 'c:1d4ed8A0,w:4,'.$fromPoint.','.$toPoint,
        ];

        return 'https://static-maps.yandex.ru/1.x/?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
