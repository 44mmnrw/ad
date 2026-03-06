@extends('layouts.app')

@section('title', 'Превью иконок спрайта — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="icons-preview">
        <header class="icons-preview__head">
            <h1 class="ad-h1">Иконки из sprite.svg</h1>
            <p>Временная страница для контроля всех символов и их атрибутов.</p>
        </header>

        <div class="icons-preview__toolbar">
            <input id="icons-search" class="icons-preview__search" type="search" placeholder="Фильтр по id, названию или группе">
            <span id="icons-count" class="icons-preview__meta">Загрузка…</span>
        </div>

        <section id="icons-grid" class="icons-preview__grid" aria-label="Список иконок"></section>
    </div>

    <script>
        (() => {
            const grid = document.getElementById('icons-grid');
            const count = document.getElementById('icons-count');
            const search = document.getElementById('icons-search');

            if (!grid || !count || !search) {
                return;
            }

            const makeCard = ({ id, label, group }) => {
                const card = document.createElement('article');
                card.className = 'icons-preview__card';
                card.dataset.id = id.toLowerCase();
                card.dataset.label = label.toLowerCase();
                card.dataset.group = group.toLowerCase();

                card.innerHTML = `
                    <span class="icons-preview__glyph" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <use href="/icons/sprite.svg#${id}"></use>
                        </svg>
                    </span>
                    <h2 class="icons-preview__title">${label}</h2>
                    <p class="icons-preview__id">${id}</p>
                    <span class="icons-preview__group">${group}</span>
                `;

                return card;
            };

            const updateCounter = () => {
                const visible = Array.from(grid.children).filter((node) => node.style.display !== 'none').length;
                const total = grid.children.length;
                count.textContent = `Показано ${visible} из ${total}`;
            };

            const applyFilter = () => {
                const q = search.value.trim().toLowerCase();
                Array.from(grid.children).forEach((node) => {
                    const haystack = `${node.dataset.id} ${node.dataset.label} ${node.dataset.group}`;
                    node.style.display = q === '' || haystack.includes(q) ? '' : 'none';
                });
                updateCounter();
            };

            fetch('/icons/sprite.svg', { headers: { 'Accept': 'image/svg+xml,text/plain,*/*' } })
                .then((response) => response.text())
                .then((text) => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'image/svg+xml');
                    const symbols = Array.from(doc.querySelectorAll('symbol'));

                    symbols.forEach((symbol) => {
                        const id = symbol.getAttribute('id') || '';
                        if (!id) {
                            return;
                        }
                        const label = symbol.getAttribute('data-label') || id;
                        const group = symbol.getAttribute('data-group') || 'без группы';
                        grid.appendChild(makeCard({ id, label, group }));
                    });

                    updateCounter();
                })
                .catch(() => {
                    count.textContent = 'Не удалось загрузить sprite.svg';
                });

            search.addEventListener('input', applyFilter);
        })();
    </script>
@endsection
