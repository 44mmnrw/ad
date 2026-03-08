import './bootstrap';

const currentModuleOrigin = (() => {
	try {
		return new URL(import.meta.url).origin;
	} catch {
		return 'unknown-origin';
	}
})();

const routeMapDebugSignature = 'route-debug-v3-localhost-1';

const customSelectRegistry = new WeakMap();

const initCustomSelects = (root = document) => {
	const selectNodes = root.querySelectorAll('select[data-custom-select]');

	selectNodes.forEach((select) => {
		if (!(select instanceof HTMLSelectElement) || customSelectRegistry.has(select)) {
			return;
		}

		const wrapper = document.createElement('div');
		wrapper.className = 'custom-select';
		wrapper.dataset.customSelectWrapper = '1';

		const selectId = select.id || `custom-select-${Math.random().toString(36).slice(2, 10)}`;
		if (!select.id) {
			select.id = selectId;
		}

		const listboxId = `${selectId}-listbox`;

		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'custom-select__button';
		button.setAttribute('aria-haspopup', 'listbox');
		button.setAttribute('aria-expanded', 'false');
		button.setAttribute('aria-controls', listboxId);

		const buttonLabel = document.createElement('span');
		buttonLabel.className = 'custom-select__label';
		button.appendChild(buttonLabel);

		const chevron = document.createElement('span');
		chevron.className = 'custom-select__chevron';
		chevron.setAttribute('aria-hidden', 'true');
		chevron.textContent = '▾';
		button.appendChild(chevron);

		const listbox = document.createElement('div');
		listbox.className = 'custom-select__listbox';
		listbox.id = listboxId;
		listbox.setAttribute('role', 'listbox');
		listbox.setAttribute('tabindex', '-1');
		listbox.hidden = true;

		select.parentNode.insertBefore(wrapper, select);
		wrapper.appendChild(select);
		wrapper.appendChild(button);
		wrapper.appendChild(listbox);

		select.classList.add('custom-select__native');

		const state = {
			open: false,
			optionButtons: [],
		};

		const buildOptions = () => {
			listbox.innerHTML = '';
			state.optionButtons = [];

			Array.from(select.options).forEach((option, index) => {
				const optionButton = document.createElement('button');
				optionButton.type = 'button';
				optionButton.className = 'custom-select__option';
				optionButton.setAttribute('role', 'option');
				optionButton.dataset.value = option.value;
				optionButton.dataset.index = `${index}`;
				optionButton.textContent = option.textContent || '';
				if (option.disabled) {
					optionButton.disabled = true;
				}

				optionButton.addEventListener('click', () => {
					if (option.disabled) {
						return;
					}

					select.value = option.value;
					select.dispatchEvent(new Event('change', { bubbles: true }));
					closeListbox();
					button.focus();
				});

				listbox.appendChild(optionButton);
				state.optionButtons.push(optionButton);
			});

			syncFromSelect();
		};

		const getSelectedIndex = () => Math.max(select.selectedIndex, 0);

		const highlightIndex = (index) => {
			state.optionButtons.forEach((node, nodeIndex) => {
				const isSelected = nodeIndex === index;
				node.classList.toggle('is-selected', isSelected);
				node.setAttribute('aria-selected', isSelected ? 'true' : 'false');
			});

			const selectedNode = state.optionButtons[index];
			if (selectedNode) {
				selectedNode.scrollIntoView({ block: 'nearest' });
			}
		};

		const syncFromSelect = () => {
			const index = getSelectedIndex();
			const selectedOption = select.options[index];
			buttonLabel.textContent = selectedOption ? selectedOption.textContent || '' : '';
			highlightIndex(index);
		};

		const openListbox = () => {
			if (state.open || select.disabled) {
				return;
			}

			state.open = true;
			wrapper.classList.add('is-open');
			button.setAttribute('aria-expanded', 'true');
			listbox.hidden = false;
			highlightIndex(getSelectedIndex());
		};

		const closeListbox = () => {
			if (!state.open) {
				return;
			}

			state.open = false;
			wrapper.classList.remove('is-open');
			button.setAttribute('aria-expanded', 'false');
			listbox.hidden = true;
		};

		const focusByOffset = (offset) => {
			const current = getSelectedIndex();
			const max = state.optionButtons.length - 1;
			if (max < 0) {
				return;
			}

			let target = current + offset;
			if (target < 0) target = 0;
			if (target > max) target = max;

			const targetOption = select.options[target];
			if (!targetOption || targetOption.disabled) {
				return;
			}

			select.selectedIndex = target;
			select.dispatchEvent(new Event('change', { bubbles: true }));
			highlightIndex(target);
		};

		button.addEventListener('click', () => {
			if (state.open) {
				closeListbox();
				return;
			}

			openListbox();
		});

		button.addEventListener('keydown', (event) => {
			if (event.key === 'ArrowDown') {
				event.preventDefault();
				if (!state.open) openListbox();
				focusByOffset(1);
			}

			if (event.key === 'ArrowUp') {
				event.preventDefault();
				if (!state.open) openListbox();
				focusByOffset(-1);
			}

			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				if (state.open) {
					closeListbox();
				} else {
					openListbox();
				}
			}

			if (event.key === 'Escape') {
				event.preventDefault();
				closeListbox();
			}
		});

		document.addEventListener('click', (event) => {
			if (!wrapper.contains(event.target)) {
				closeListbox();
			}
		});

		select.addEventListener('change', syncFromSelect);

		if (select.disabled) {
			wrapper.classList.add('is-disabled');
			button.disabled = true;
		}

		buildOptions();
		customSelectRegistry.set(select, { rebuild: buildOptions });
	});
};

initCustomSelects();

const fallbackCopyToClipboard = (text) => {
	const textarea = document.createElement('textarea');
	textarea.value = text;
	textarea.setAttribute('readonly', '');
	textarea.style.position = 'absolute';
	textarea.style.left = '-9999px';
	document.body.appendChild(textarea);
	textarea.select();
	const successful = document.execCommand('copy');
	document.body.removeChild(textarea);

	return successful;
};

const copyToClipboard = async (text) => {
	if (!text) {
		return false;
	}

	if (navigator.clipboard && window.isSecureContext) {
		try {
			await navigator.clipboard.writeText(text);
			return true;
		} catch {
			return fallbackCopyToClipboard(text);
		}
	}

	return fallbackCopyToClipboard(text);
};

document.addEventListener('click', async (event) => {
	const button = event.target.closest('.cp-copy-btn');

	if (!button) {
		return;
	}

	event.preventDefault();

	const copyText = button.dataset.copyText;
	const copyTarget = button.dataset.copyTarget;

	let textToCopy = copyText ?? '';

	if (!textToCopy && copyTarget) {
		const target = document.querySelector(copyTarget);
		textToCopy = target ? target.textContent.trim() : '';
	}

	const copied = await copyToClipboard(textToCopy);

	if (!copied) {
		return;
	}

	button.classList.add('is-copied');
	const originalLabel = button.getAttribute('aria-label') ?? '';
	button.setAttribute('aria-label', 'Скопировано');

	window.setTimeout(() => {
		button.classList.remove('is-copied');
		if (originalLabel) {
			button.setAttribute('aria-label', originalLabel);
		}
	}, 1200);
});

const initOrderEditTabs = () => {
	const tabContainers = document.querySelectorAll('.order-edit-form-card');

	tabContainers.forEach((container) => {
		const tabs = Array.from(container.querySelectorAll('[data-order-tab]'));
		const panels = Array.from(container.querySelectorAll('[data-order-tab-panel]'));

		if (tabs.length === 0 || panels.length === 0) {
			return;
		}

		const activateTab = (tabName) => {
			tabs.forEach((tab) => {
				const isActive = tab.dataset.orderTab === tabName;
				tab.classList.toggle('is-active', isActive);
				tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
			});

			panels.forEach((panel) => {
				panel.hidden = panel.dataset.orderTabPanel !== tabName;
			});
		};

		const initialTab = tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.orderTab ?? tabs[0].dataset.orderTab;
		activateTab(initialTab);

		tabs.forEach((tab) => {
			tab.addEventListener('click', () => {
				activateTab(tab.dataset.orderTab);
			});
		});
	});
};

initOrderEditTabs();

const initOrderRouteGeocoding = () => {
	const forms = document.querySelectorAll('.order-edit-form-card');

	forms.forEach((form) => {
		const geocodeButton = form.querySelector('[data-route-geocode-button]');
		const statusNode = form.querySelector('[data-route-status]');

		if (!geocodeButton) {
			return;
		}

		const getInput = (name) => form.querySelector(`[data-route-input="${name}"]`);
		const getCoord = (name) => form.querySelector(`[data-route-coord="${name}"]`);
		const getPointNode = (name) => form.querySelector(`[data-route-point="${name}"]`);

		const setStatus = (message, type = 'default') => {
			if (!statusNode) {
				return;
			}

			statusNode.textContent = message;
			statusNode.classList.remove('is-success', 'is-error');

			if (type === 'success') {
				statusNode.classList.add('is-success');
			}

			if (type === 'error') {
				statusNode.classList.add('is-error');
			}
		};

		const updatePointText = (pointKey, lat, lng) => {
			const pointNode = getPointNode(pointKey);

			if (!pointNode) {
				return;
			}

			const latNumber = Number.parseFloat(`${lat}`);
			const lngNumber = Number.parseFloat(`${lng}`);

			if (!Number.isFinite(latNumber) || !Number.isFinite(lngNumber)) {
				pointNode.textContent = 'Точка не определена';
				return;
			}

			pointNode.textContent = `Точка: ${latNumber.toFixed(6)}, ${lngNumber.toFixed(6)}`;
		};

		const clearPointCoordinates = (pointKey) => {
			const latInput = getCoord(`${pointKey}_lat`);
			const lngInput = getCoord(`${pointKey}_lng`);

			if (latInput) {
				latInput.value = '';
			}

			if (lngInput) {
				lngInput.value = '';
			}

			updatePointText(pointKey, null, null);
		};

		const clearIntermediateStopCoordinates = (row) => {
			if (!(row instanceof HTMLElement)) {
				return;
			}

			const latInput = row.querySelector('input[name="intermediate_lats[]"]');
			const lngInput = row.querySelector('input[name="intermediate_lngs[]"]');

			if (latInput instanceof HTMLInputElement) {
				latInput.value = '';
			}

			if (lngInput instanceof HTMLInputElement) {
				lngInput.value = '';
			}
		};

		const markRouteCoordinatesAsStale = () => {
			setStatus('Маршрут изменён. Определите точки заново или сохраните заявку — координаты будут пересчитаны автоматически.');
		};

		[
			['from_city', 'from'],
			['from_address', 'from'],
			['to_city', 'to'],
			['to_address', 'to'],
		].forEach(([inputName, pointKey]) => {
			const input = getInput(inputName);

			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			input.addEventListener('input', () => {
				clearPointCoordinates(pointKey);
				markRouteCoordinatesAsStale();
			});
		});

		form.addEventListener('input', (event) => {
			const target = event.target;

			if (!(target instanceof HTMLInputElement)) {
				return;
			}

			if (target.name !== 'intermediate_cities[]' && target.name !== 'intermediate_addresses[]') {
				return;
			}

			clearIntermediateStopCoordinates(target.closest('.order-route-stop'));
			markRouteCoordinatesAsStale();
		});

		geocodeButton.addEventListener('click', async () => {
			const fromCity = getInput('from_city')?.value?.trim() ?? '';
			const fromAddress = getInput('from_address')?.value?.trim() ?? '';
			const toCity = getInput('to_city')?.value?.trim() ?? '';
			const toAddress = getInput('to_address')?.value?.trim() ?? '';

			if (!fromCity || !toCity) {
				setStatus('Укажите города отправления и назначения.', 'error');
				return;
			}

			const query = new URLSearchParams({
				from_city: fromCity,
				from_address: fromAddress,
				to_city: toCity,
				to_address: toAddress,
			});

			geocodeButton.disabled = true;
			setStatus('Определяю координаты через Яндекс Геокодер...');

			try {
				const response = await fetch(`/orders/route/geocode?${query.toString()}`, {
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				const payload = await response.json();

				if (!response.ok) {
					throw new Error(payload.message ?? 'Не удалось определить координаты маршрута.');
				}

				const fromLatInput = getCoord('from_lat');
				const fromLngInput = getCoord('from_lng');
				const toLatInput = getCoord('to_lat');
				const toLngInput = getCoord('to_lng');

				if (fromLatInput) fromLatInput.value = `${payload.from?.lat ?? ''}`;
				if (fromLngInput) fromLngInput.value = `${payload.from?.lng ?? ''}`;
				if (toLatInput) toLatInput.value = `${payload.to?.lat ?? ''}`;
				if (toLngInput) toLngInput.value = `${payload.to?.lng ?? ''}`;

				updatePointText('from', payload.from?.lat, payload.from?.lng);
				updatePointText('to', payload.to?.lat, payload.to?.lng);

				setStatus('Координаты маршрута успешно определены.', 'success');
			} catch (error) {
				setStatus(error instanceof Error ? error.message : 'Ошибка геокодирования маршрута.', 'error');
			} finally {
				geocodeButton.disabled = false;
			}
		});
	});
};

initOrderRouteGeocoding();

const initOrderRouteIntermediateStops = () => {
	const forms = document.querySelectorAll('.order-edit-form-card');

	forms.forEach((form) => {
		const list = form.querySelector('[data-route-intermediate-list]');
		const addButton = form.querySelector('[data-route-intermediate-add]');
		const template = form.querySelector('template[data-route-intermediate-template]');

		if (!list || !addButton || !template) {
			return;
		}

		const removeEmptyState = () => {
			const emptyNode = list.querySelector('[data-route-intermediate-empty]');

			if (emptyNode) {
				emptyNode.remove();
			}
		};

		addButton.addEventListener('click', () => {
			removeEmptyState();
			const fragment = template.content.cloneNode(true);
			list.appendChild(fragment);
			initCustomSelects(list);
		});

		list.addEventListener('click', (event) => {
			const removeButton = event.target.closest('[data-route-intermediate-remove]');

			if (!removeButton) {
				return;
			}

			const row = removeButton.closest('.order-route-stop');

			if (row) {
				row.remove();
			}

			if (list.querySelectorAll('.order-route-stop').length === 0 && !list.querySelector('[data-route-intermediate-empty]')) {
				const emptyState = document.createElement('p');
				emptyState.className = 'order-route-stops__empty';
				emptyState.setAttribute('data-route-intermediate-empty', '');
				emptyState.textContent = 'Промежуточные пункты не добавлены.';
				list.appendChild(emptyState);
			}
		});
	});
};

initOrderRouteIntermediateStops();

const initOrderRouteCityAutocomplete = () => {
	const forms = document.querySelectorAll('.order-edit-form-card');

	forms.forEach((form) => {
		const attachAutocomplete = (input) => {
			if (!(input instanceof HTMLInputElement) || input.dataset.routeCityAutocompleteBound === '1') {
				return;
			}

			const listId = input.getAttribute('list');

			if (!listId) {
				return;
			}

			const datalist = form.querySelector(`#${listId}`);

			if (!(datalist instanceof HTMLDataListElement)) {
				return;
			}

			let timer = null;

			const renderSuggestions = (items) => {
				datalist.innerHTML = '';

				items.forEach((item) => {
					const option = document.createElement('option');
					option.value = item;
					datalist.appendChild(option);
				});
			};

			input.addEventListener('input', () => {
				const value = input.value.trim();

				if (timer) {
					window.clearTimeout(timer);
				}

				if (value.length < 2) {
					renderSuggestions([]);
					return;
				}

				timer = window.setTimeout(async () => {
					try {
						const query = new URLSearchParams({ query: value });
						const response = await fetch(`/orders/route/city-suggest?${query.toString()}`, {
							headers: {
								Accept: 'application/json',
								'X-Requested-With': 'XMLHttpRequest',
							},
						});

						if (!response.ok) {
							renderSuggestions([]);
							return;
						}

						const payload = await response.json();
						const suggestions = Array.isArray(payload.suggestions) ? payload.suggestions : [];
						renderSuggestions(suggestions);
					} catch {
						renderSuggestions([]);
					}
				}, 250);
			});

			input.dataset.routeCityAutocompleteBound = '1';
		};

		const bindAll = () => {
			form.querySelectorAll('[data-route-city-input]').forEach((input) => attachAutocomplete(input));
		};

		bindAll();

		form.addEventListener('click', (event) => {
			if (event.target.closest('[data-route-intermediate-add]')) {
				window.requestAnimationFrame(() => bindAll());
			}
		});
	});
};

initOrderRouteCityAutocomplete();

const initOrderRouteAddressAutocomplete = () => {
	const forms = document.querySelectorAll('.order-edit-form-card');

	forms.forEach((form) => {
		const resolveCityForAddressInput = (input) => {
			if (!(input instanceof HTMLInputElement)) {
				return '';
			}

			if (input.name === 'from_address') {
				return form.querySelector('[data-route-input="from_city"]')?.value?.trim() ?? '';
			}

			if (input.name === 'to_address') {
				return form.querySelector('[data-route-input="to_city"]')?.value?.trim() ?? '';
			}

			if (input.name === 'intermediate_addresses[]') {
				return input.closest('.order-route-stop')?.querySelector('input[name="intermediate_cities[]"]')?.value?.trim() ?? '';
			}

			return '';
		};

		const attachAutocomplete = (input) => {
			if (!(input instanceof HTMLInputElement) || input.dataset.routeAddressAutocompleteBound === '1') {
				return;
			}

			const listId = input.getAttribute('list');

			if (!listId) {
				return;
			}

			const datalist = form.querySelector(`#${listId}`);

			if (!(datalist instanceof HTMLDataListElement)) {
				return;
			}

			let timer = null;

			const renderSuggestions = (items) => {
				datalist.innerHTML = '';

				items.forEach((item) => {
					const option = document.createElement('option');
					option.value = item;
					datalist.appendChild(option);
				});
			};

			input.addEventListener('input', () => {
				const value = input.value.trim();
				const city = resolveCityForAddressInput(input);

				if (timer) {
					window.clearTimeout(timer);
				}

				if (value.length < 2) {
					renderSuggestions([]);
					return;
				}

				timer = window.setTimeout(async () => {
					try {
						const query = new URLSearchParams({ query: value });

						if (city !== '') {
							query.set('city', city);
						}

						const response = await fetch(`/orders/route/address-suggest?${query.toString()}`, {
							headers: {
								Accept: 'application/json',
								'X-Requested-With': 'XMLHttpRequest',
							},
						});

						if (!response.ok) {
							renderSuggestions([]);
							return;
						}

						const payload = await response.json();
						const suggestions = Array.isArray(payload.suggestions) ? payload.suggestions : [];
						renderSuggestions(suggestions);
					} catch {
						renderSuggestions([]);
					}
				}, 250);
			});

			input.dataset.routeAddressAutocompleteBound = '1';
		};

		const bindAll = () => {
			form.querySelectorAll('[data-route-address-input]').forEach((input) => attachAutocomplete(input));
		};

		bindAll();

		form.addEventListener('click', (event) => {
			if (event.target.closest('[data-route-intermediate-add]')) {
				window.requestAnimationFrame(() => bindAll());
			}
		});
	});
};

initOrderRouteAddressAutocomplete();

const initOrderShowMap = () => {
	const mapContainers = document.querySelectorAll('[data-order-route-map]');

	const ensureLeafletLoaded = () => new Promise((resolve, reject) => {
		if (typeof window.L !== 'undefined') {
			resolve(window.L);
			return;
		}

		let cssTag = document.querySelector('link[data-leaflet-css="1"]');
		if (!(cssTag instanceof HTMLLinkElement)) {
			cssTag = document.createElement('link');
			cssTag.rel = 'stylesheet';
			cssTag.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
			cssTag.setAttribute('data-leaflet-css', '1');
			document.head.appendChild(cssTag);
		}

		let scriptTag = document.querySelector('script[data-leaflet-js="1"]');
		if (!(scriptTag instanceof HTMLScriptElement)) {
			scriptTag = document.createElement('script');
			scriptTag.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
			scriptTag.async = true;
			scriptTag.setAttribute('data-leaflet-js', '1');
			document.head.appendChild(scriptTag);
		}

		const onLoad = () => {
			scriptTag.removeEventListener('load', onLoad);
			scriptTag.removeEventListener('error', onError);

			if (typeof window.L !== 'undefined') {
				resolve(window.L);
				return;
			}

			reject(new Error('Leaflet недоступен после загрузки скрипта'));
		};

		const onError = () => {
			scriptTag.removeEventListener('load', onLoad);
			scriptTag.removeEventListener('error', onError);
			reject(new Error('Не удалось загрузить Leaflet'));
		};

		scriptTag.addEventListener('load', onLoad, {once: true});
		scriptTag.addEventListener('error', onError, {once: true});
	});

	const waitForYmaps3 = () => new Promise((resolve, reject) => {
		if (typeof window.ymaps3 !== 'undefined') {
			resolve(window.ymaps3);
			return;
		}

		const script = document.querySelector('script[data-yandex-map-v3="1"]');

		if (!(script instanceof HTMLScriptElement)) {
			reject(new Error('Yandex Maps v3 script not found'));
			return;
		}

		const onLoad = () => {
			script.removeEventListener('load', onLoad);
			script.removeEventListener('error', onError);

			if (typeof window.ymaps3 !== 'undefined') {
				resolve(window.ymaps3);
				return;
			}

			reject(new Error('Yandex Maps v3 is unavailable after script load'));
		};

		const onError = () => {
			script.removeEventListener('load', onLoad);
			script.removeEventListener('error', onError);
			reject(new Error('Yandex Maps v3 script failed to load'));
		};

		script.addEventListener('load', onLoad, {once: true});
		script.addEventListener('error', onError, {once: true});
	});

	mapContainers.forEach(async (container) => {
		const canvas = container.querySelector('[data-order-route-map-canvas]');
		const errorNode = container.parentElement?.querySelector('[data-order-route-map-error]');
		const statusNode = container.parentElement?.querySelector('[data-order-route-map-status]');

		if (!(canvas instanceof HTMLElement) || canvas.dataset.routeMapInitialized === '1') {
			return;
		}

		let points = [];

		try {
			const raw = canvas.dataset.mapPoints ?? '[]';
			const decoded = JSON.parse(raw);
			points = Array.isArray(decoded)
				? decoded
					.filter((point) => Number.isFinite(Number.parseFloat(`${point?.lat ?? ''}`)) && Number.isFinite(Number.parseFloat(`${point?.lng ?? ''}`)))
					.map((point) => ({
						lat: Number.parseFloat(`${point.lat}`),
						lng: Number.parseFloat(`${point.lng}`),
						label: typeof point.label === 'string' ? point.label : 'Точка маршрута',
						role: typeof point.role === 'string' ? point.role : 'intermediate',
					}))
				: [];
		} catch {
			points = [];
		}

		if (points.length < 2) {
			if (statusNode instanceof HTMLElement) {
				statusNode.textContent = 'Режим карты: недостаточно координат для построения маршрута.';
				statusNode.classList.remove('route-map__status--success', 'route-map__status--warning');
				statusNode.classList.add('route-map__status--error');
			}

			container.hidden = true;

			if (errorNode instanceof HTMLElement) {
				errorNode.textContent = 'Недостаточно координат для отображения маршрута.';
				errorNode.hidden = false;
			}

			return;
		}

		const initLeafletFallbackMap = async () => {
			const L = await ensureLeafletLoaded();

			canvas.innerHTML = '';
			canvas.style.background = 'transparent';

			const fallbackMap = L.map(canvas, {
				scrollWheelZoom: true,
				doubleClickZoom: true,
			});

			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; OpenStreetMap contributors',
			}).addTo(fallbackMap);

			const latLngs = points.map((point) => [point.lat, point.lng]);
			const polyline = L.polyline(latLngs, {
				color: routeLinePrimary,
				weight: 6,
			}).addTo(fallbackMap);

			points.forEach((point) => {
				L.circleMarker([point.lat, point.lng], {
					radius: point.role === 'intermediate' ? 5 : 6,
					color: '#ffffff',
					weight: 2,
					fillColor: point.role === 'from' ? '#16a34a' : (point.role === 'to' ? '#dc2626' : '#2563eb'),
					fillOpacity: 1,
				})
					.addTo(fallbackMap)
					.bindTooltip(point.label, {direction: 'top'});
			});

			fallbackMap.fitBounds(polyline.getBounds(), {padding: [24, 24]});
			canvas.dataset.routeMapInitialized = '1';
			canvas.dataset.routeMapMode = 'leaflet';
			canvas.dataset.routeMapStrokeColor = routeLinePrimary;
			canvas.dataset.routeMapDebugSignature = routeMapDebugSignature;
		};

		const initOsmIframeFallback = async () => {
			const lngValues = points.map((point) => point.lng);
			const latValues = points.map((point) => point.lat);

			let minLng = Math.min(...lngValues);
			let maxLng = Math.max(...lngValues);
			let minLat = Math.min(...latValues);
			let maxLat = Math.max(...latValues);

			const lngSpan = Math.max(maxLng - minLng, 0.02);
			const latSpan = Math.max(maxLat - minLat, 0.02);
			const lngPad = lngSpan * 0.15;
			const latPad = latSpan * 0.15;

			minLng -= lngPad;
			maxLng += lngPad;
			minLat -= latPad;
			maxLat += latPad;

			const iframe = document.createElement('iframe');
			iframe.title = 'Резервная карта OpenStreetMap';
			iframe.loading = 'lazy';
			iframe.referrerPolicy = 'no-referrer-when-downgrade';
			iframe.style.width = '100%';
			iframe.style.height = '100%';
			iframe.style.border = '0';

			const bbox = `${minLng},${minLat},${maxLng},${maxLat}`;
			const centerLat = (minLat + maxLat) / 2;
			const centerLng = (minLng + maxLng) / 2;
			iframe.src = `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bbox)}&layer=mapnik&marker=${encodeURIComponent(`${centerLat},${centerLng}`)}`;

			canvas.innerHTML = '';
			canvas.appendChild(iframe);
			canvas.dataset.routeMapInitialized = '1';
			canvas.dataset.routeMapMode = 'osm-iframe';
			canvas.dataset.routeMapStrokeColor = routeLinePrimary;
			canvas.dataset.routeMapDebugSignature = routeMapDebugSignature;
		};

		const setMapStatus = (message, type = 'default') => {
			if (!(statusNode instanceof HTMLElement)) {
				return;
			}

			statusNode.textContent = message;
			statusNode.classList.remove('route-map__status--success', 'route-map__status--warning', 'route-map__status--error');

			if (type === 'success') {
				statusNode.classList.add('route-map__status--success');
			}

			if (type === 'warning') {
				statusNode.classList.add('route-map__status--warning');
			}

			if (type === 'error') {
				statusNode.classList.add('route-map__status--error');
			}
		};

		const showDebugMeta = () => `JS: ${routeMapDebugSignature} @ ${currentModuleOrigin}; stroke: ${routeLinePrimary}`;

		const showFallback = (reason) => {
			initLeafletFallbackMap()
				.then(() => {
					setMapStatus(`Режим карты: резервный Leaflet/OpenStreetMap. Причина: ${reason || 'Yandex Maps v3 недоступна.'}. ${showDebugMeta()}`, 'warning');

					if (errorNode instanceof HTMLElement) {
						errorNode.textContent = 'Карта Яндекс недоступна. Показан резервный режим карты.';
						errorNode.hidden = false;
					}
				})
				.catch((leafletError) => {
					initOsmIframeFallback()
						.then(() => {
							const leafletReason = leafletError instanceof Error ? leafletError.message : 'Leaflet недоступен';
							setMapStatus(`Режим карты: резервный iframe OpenStreetMap. Причина: ${reason || 'Yandex Maps v3 недоступна.'}; ${leafletReason}. ${showDebugMeta()}`, 'warning');

							if (errorNode instanceof HTMLElement) {
								errorNode.textContent = 'Карта Яндекс недоступна. Показан резервный режим OpenStreetMap.';
								errorNode.hidden = false;
							}
						})
						.catch(() => {
							setMapStatus(`Режим карты: интерактивная карта недоступна. ${showDebugMeta()}`, 'error');
							container.hidden = true;

							if (errorNode instanceof HTMLElement) {
								const details = [
									typeof reason === 'string' && reason.trim() !== '' ? `Яндекс: ${reason}` : null,
									leafletError instanceof Error ? `Leaflet: ${leafletError.message}` : null,
								].filter(Boolean).join('; ');

								errorNode.textContent = details !== ''
									? `Не удалось загрузить интерактивную карту (${details}).`
									: 'Не удалось загрузить интерактивную карту.';
								errorNode.hidden = false;
							}
						});
				});
		};

		const readCssVar = (name, fallback) => {
			const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

			return value !== '' ? value : fallback;
		};

		const routeLinePrimary = '#5a01ff';
		setMapStatus('Режим карты: инициализация Yandex Maps v3…');

		const createMarkerElement = (role, badgeText) => {
			const markerElement = document.createElement('div');
			markerElement.className = 'route-map-marker';

			if (role === 'from') {
				markerElement.classList.add('route-map-marker--from');
			}

			if (role === 'to') {
				markerElement.classList.add('route-map-marker--to');
			}

			if (role === 'intermediate') {
				markerElement.classList.add('route-map-marker--intermediate');
			}

			const pulse = document.createElement('span');
			pulse.className = 'route-map-marker__pulse';

			const core = document.createElement('span');
			core.className = 'route-map-marker__core';

			markerElement.appendChild(pulse);
			markerElement.appendChild(core);

			if (badgeText) {
				const badge = document.createElement('span');
				badge.className = 'route-map-marker__badge';
				badge.textContent = badgeText;
				markerElement.appendChild(badge);
			}

			return markerElement;
		};

		try {
			const ymaps3 = await waitForYmaps3();
			await ymaps3.ready;
			const {YMap, YMapDefaultSchemeLayer, YMapDefaultFeaturesLayer, YMapFeature, YMapFeatureDataSource, YMapLayer, YMapMarker} = ymaps3;

			if (!YMap || !YMapDefaultSchemeLayer || !YMapDefaultFeaturesLayer || !YMapFeature || !YMapFeatureDataSource || !YMapLayer || !YMapMarker) {
				throw new Error('Yandex Maps v3 classes are unavailable');
			}

			const coordinates = points.map((point) => [point.lng, point.lat]);
			const lngValues = points.map((point) => point.lng);
			const latValues = points.map((point) => point.lat);
			const minLng = Math.min(...lngValues);
			const maxLng = Math.max(...lngValues);
			const minLat = Math.min(...latValues);
			const maxLat = Math.max(...latValues);
			const lngSpan = Math.max(maxLng - minLng, 0.1);
			const latSpan = Math.max(maxLat - minLat, 0.1);
			const lngPadding = lngSpan * 0.08;
			const latPadding = latSpan * 0.12;

			const bounds = [
				[minLng - lngPadding, minLat - latPadding],
				[maxLng + lngPadding, maxLat + latPadding],
			];

			canvas.innerHTML = '';
			const routeLineSourceId = `order-route-line-${Math.random().toString(36).slice(2, 10)}`;

			const map = new YMap(
				canvas,
				{
					location: { bounds },
					mode: 'vector',
					behaviors: ['drag', 'pinchZoom', 'scrollZoom', 'dblClick', 'mouseRotate', 'mouseTilt'],
				},
				[
					new YMapDefaultSchemeLayer({}),
					new YMapDefaultFeaturesLayer({ zIndex: 1500 }),
					new YMapFeatureDataSource({ id: routeLineSourceId }),
					new YMapLayer({ source: routeLineSourceId, type: 'features', zIndex: 2500 }),
				],
			);

			map.addChild(new YMapFeature({
				source: routeLineSourceId,
				geometry: {
					type: 'LineString',
					coordinates,
				},
				style: {
					stroke: [{ width: 4, color: routeLinePrimary, opacity: 0.7 }],
					zIndex: 2600,
				},
			}));

			let intermediateIndex = 1;

			points.forEach((point) => {
				const badgeText = point.role === 'from'
					? 'A'
					: (point.role === 'to' ? 'B' : `${intermediateIndex++}`);
				const markerElement = createMarkerElement(point.role, badgeText);
				markerElement.title = point.label;

				map.addChild(new YMapMarker(
					{
						coordinates: [point.lng, point.lat],
					},
					markerElement,
				));
			});

			canvas.dataset.routeMapInitialized = '1';
			canvas.dataset.routeMapMode = 'yandex-v3';
			canvas.dataset.routeMapStrokeColor = routeLinePrimary;
			canvas.dataset.routeMapDebugSignature = routeMapDebugSignature;
			setMapStatus(`Режим карты: Yandex Maps v3 активна. ${showDebugMeta()}; actual stroke: ${routeLinePrimary}`, 'success');

			if (errorNode instanceof HTMLElement) {
				errorNode.hidden = true;
			}
		} catch (error) {
			showFallback(error instanceof Error ? error.message : 'ошибка инициализации');
		}
	});
};

initOrderShowMap();
