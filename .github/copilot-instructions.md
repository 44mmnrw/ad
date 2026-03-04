# Инструкции для AI-агентов в `avto_dost`

## Обзор проекта
- Направление проекта: **«Система управления перевозками»** на **Laravel 12** (`laravel/framework:^12`, PHP `^8.2`).
- Политика стилей: использовать только стандартный CSS (`resources/css/app.css` и обычные CSS-файлы).
- **Tailwind строго запрещён** для новых изменений (нельзя использовать Tailwind-классы, конфиг Tailwind и Tailwind-разметку).
- Точка входа приложения настроена в `bootstrap/app.php`: web-маршруты — `routes/web.php`, консольные маршруты — `routes/console.php`, health-check — `/up`.
- Доменная логика пока не реализована; основа проекта — стандартные файлы Laravel (`app/Models/User.php`, базовые миграции, стандартный `welcome`-шаблон).

## Архитектура и поток выполнения
- HTTP-поток: `public/index.php` → `bootstrap/app.php` → маршруты из `routes/web.php`.
- Текущая web-поверхность: один closure-маршрут (`/`), который возвращает `resources/views/welcome.blade.php`.
- Фронтенд-ассеты собираются Vite из `resources/js/app.js` и `resources/css/app.css` (см. `vite.config.js`).
- `resources/js/app.js` импортирует только `resources/js/bootstrap.js` (настройка Axios с `X-Requested-With`).
- Все служебные/автоматизационные скрипты хранить строго в `D:\laragon\www\avto_dost\script_ai`.

## Рабочие команды разработчика (использовать именно их)
- Первичная настройка: `composer run setup` (ставит PHP+Node зависимости, создаёт `.env` при отсутствии, генерирует ключ, применяет миграции, собирает ассеты).
- Локальная разработка (рекомендуется): `composer run dev`.
  - Одновременно запускает web-сервер, listener очереди, просмотр логов (`pail`) и Vite.
- Тесты: `composer test` (проверено в этом репозитории; очищает конфиг и запускает `php artisan test`).
- Только фронтенд: `npm run dev`; production-сборка: `npm run build`.

## Данные и окружение
- Подключение к БД по умолчанию — `mysql` (`config/database.php`), а тесты используют in-memory sqlite через `phpunit.xml`:
  - `DB_CONNECTION=mysql`
  - `DB_DATABASE=:memory:`
- Файловую БД `database/database.sqlite` не использовать для рабочих/персональных данных и не хранить такие дампы в каталоге сайта.
- При добавлении таблиц/миграций придерживаться структуры существующих миграций в `database/migrations/0001_01_01_*`.

## Доступы к продакшн серверу
- Хост (IP): `85.239.57.126`
- Корневая папка проекта: `/var/www/axecode_tech_usr/data/www/avto-dostavka.axecode.tech`

### SSH
- Пользователь: `axecode_tech_usr`

### База данных
- Имя БД: `avto_dostavk`
- Логин БД: `avto_dostavk`
- Пароль БД: `P38F=RTrj\e6WeO^`

## Проектные соглашения по коду
- Предпочитать стандартные Laravel-подходы и соглашения вместо кастомных абстракций, если иное не требуется задачей.
- Соблюдать PSR-4 namespace-структуру из `composer.json` (`App\\`, `Database\\Factories\\`, `Database\\Seeders\\`).
- При изменениях UI не менять Vite input-энтрипойнты без явной необходимости (`vite.config.js`).
- Стили писать обычным CSS (классы/селекторы); не добавлять Tailwind-классы и Tailwind-специфичные директивы в новый код.
- Структура CSS обязательна:
  - `resources/css/base/root.css` — только `:root` переменные (дизайн-токены).
  - `resources/css/base/reset.css` — только сброс браузерных стилей и базовые глобальные правила.
  - `resources/css/templates/*.css` — стили конкретных шаблонов/страниц (например, `dashboard.css`).
  - `resources/css/app.css` — только точка импорта слоёв (`@import ...`), без хранения объёмных стилей страницы.
- При добавлении новой страницы создавать отдельный файл в `resources/css/templates/` и подключать его через `resources/css/app.css`.
- В остальном строго соблюдать соглашения Laravel: маршруты в `routes/web.php`, шаблоны в `resources/views`, подключение ассетов через `@vite(...)`, бизнес-логику выносить в контроллеры/сервисы по мере необходимости.
- Любые новые служебные скрипты (PowerShell, batch, PHP-утилиты, помощники миграции, загрузчики данных) создавать только в `script_ai/`.
- Тесты оформлять в стиле PHPUnit в `tests/Unit` и `tests/Feature`, пока проект не переведён на Pest.

## Точки интеграции и границы
- Очередь и логи — часть стандартного dev-процесса (`composer run dev` поднимает queue + pail).
- В проект уже интегрирован внешний API **DaData**; не дублировать существующую интеграцию и не вызывать DaData напрямую из Blade/JS/контроллеров в обход сервисного слоя.
- Если добавляете auth/dashboard-маршруты, на которые ссылается `welcome.blade.php` (`login`, `register`, `/dashboard`), обязательно определить соответствующие маршруты.

## Работа с API `dadata.ru`
- Текущая интеграция реализована через сервис `App\Services\DaData\FindPartyService`.
- Для поиска контрагентов использовать endpoint `findById/party` (URL по умолчанию хранится в `config/services.php` в `services.dadata.find_party_url`).
- Конфиг DaData хранится в `config/services.php` (`services.dadata.*`) и поддерживает переменные окружения:
  - `DADATA_API_KEY`
  - `DADATA_SECRET_KEY`
  - `DADATA_TIMEOUT`
  - `DADATA_FIND_PARTY_URL`
- Приоритет источников настроек: **`integration_settings` (модель `IntegrationSetting`) → `.env`/`config/services.php`**.
- Секреты (API key / Secret) нельзя хардкодить в коде, шаблонах, тестах и документации.
- Таймаут запроса задаётся через `timeout` (границы UI-настроек: `1..60` секунд); при изменениях соблюдать эту же политику.
- Обработку ошибок DaData выполнять через исключения/валидные JSON-ответы без падения страницы; пользователь должен получать понятное сообщение на русском.
- Для автозаполнения контрагента использовать существующий маршрут `counterparties.dadata.autofill` и метод `CounterpartyController::autofillByInn`; не дублировать логику нормализации ИНН/ОГРН.
- При расширении интеграции придерживаться текущего подхода Laravel:
  - HTTP-вызовы — только в сервисе;
  - контроллеры — тонкие (валидация + orchestration);
  - UI — только отображение/вызов существующих маршрутов.