<form class="order-form" action="#" method="post">
    <section class="order-form__section">
        <h2>Основные данные</h2>
        <div class="order-form__grid">
            <label>
                № заявки
                <input type="text" name="number" value="{{ $order['number'] ?? '' }}" placeholder="АД-20240315-001">
            </label>
            <label>
                Дата создания
                <input type="text" name="created_at" value="{{ $order['created_at'] ?? '' }}" placeholder="15.03.2024">
            </label>
            <label>
                Статус
                <select name="status">
                    <option {{ (($order['status'] ?? '') === 'В пути') ? 'selected' : '' }}>В пути</option>
                    <option {{ (($order['status'] ?? '') === 'Завершена') ? 'selected' : '' }}>Завершена</option>
                    <option {{ (($order['status'] ?? '') === 'Создана') ? 'selected' : '' }}>Создана</option>
                </select>
            </label>
        </div>
    </section>

    <section class="order-form__section">
        <h2>Маршрут</h2>
        <div class="order-form__grid order-form__grid--two">
            <label>
                Город отправления
                <input type="text" name="from_city" value="{{ $order['route']['from']['city'] ?? '' }}" placeholder="Москва">
            </label>
            <label>
                Адрес отправления
                <input type="text" name="from_address" value="{{ $order['route']['from']['address'] ?? '' }}" placeholder="ул. Ленина, 12">
            </label>
            <label>
                Город доставки
                <input type="text" name="to_city" value="{{ $order['route']['to']['city'] ?? '' }}" placeholder="Санкт-Петербург">
            </label>
            <label>
                Адрес доставки
                <input type="text" name="to_address" value="{{ $order['route']['to']['address'] ?? '' }}" placeholder="пр. Невский, 45">
            </label>
        </div>
    </section>

    <section class="order-form__section">
        <h2>Контакты</h2>
        <div class="order-form__grid order-form__grid--two">
            <label>
                Водитель
                <input type="text" name="driver_name" value="{{ $order['driver']['name'] ?? '' }}" placeholder="Иванов Иван Иванович">
            </label>
            <label>
                Телефон водителя
                <input type="text" name="driver_phone" value="{{ $order['driver']['phone'] ?? '' }}" placeholder="+7 (999) 123-45-67">
            </label>
            <label>
                Грузоотправитель
                <input type="text" name="sender_name" value="{{ $order['sender']['name'] ?? '' }}" placeholder="Петров Сергей Николаевич">
            </label>
            <label>
                Телефон отправителя
                <input type="text" name="sender_phone" value="{{ $order['sender']['phone'] ?? '' }}" placeholder="+7 (911) 000-11-22">
            </label>
            <label>
                Грузополучатель
                <input type="text" name="receiver_name" value="{{ $order['receiver']['name'] ?? '' }}" placeholder="Сидорова Анна Петровна">
            </label>
            <label>
                Телефон получателя
                <input type="text" name="receiver_phone" value="{{ $order['receiver']['phone'] ?? '' }}" placeholder="+7 (921) 333-44-55">
            </label>
        </div>
    </section>

    <div class="order-form__actions">
        <button class="orders-btn orders-btn--primary" type="submit">Сохранить</button>
        <a class="orders-btn orders-btn--outline" href="{{ route('orders.index') }}">Отмена</a>
    </div>
</form>
