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

const initCounterpartyLiveSearch = () => {
	const forms = document.querySelectorAll('[data-counterparty-search-form]');

	forms.forEach((form) => {
		const input = form.querySelector('#counterparties-search');
		const resultsNode = form.querySelector('[data-counterparty-suggest-results]');
		const statusNode = form.querySelector('[data-counterparty-suggest-status]');
		const suggestUrl = form.dataset.counterpartySuggestUrl;
		let debounceTimer = null;

		if (!(input instanceof HTMLInputElement) || !(resultsNode instanceof HTMLElement) || !(statusNode instanceof HTMLElement) || !suggestUrl) {
			return;
		}

		const hideResults = () => {
			resultsNode.hidden = true;
			resultsNode.innerHTML = '';
		};

		const showStatus = (message, state = 'info') => {
			statusNode.hidden = !message;
			statusNode.textContent = message;
			statusNode.classList.remove('is-success', 'is-error');

			if (state === 'success') {
				statusNode.classList.add('is-success');
			}

			if (state === 'error') {
				statusNode.classList.add('is-error');
			}
		};

		const renderResults = (suggestions) => {
			resultsNode.innerHTML = '';

			if (!Array.isArray(suggestions) || suggestions.length === 0) {
				hideResults();
				return;
			}

			suggestions.forEach((item) => {
				const counterparty = item?.counterparty || {};
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'cp-search-suggest__item';
				button.innerHTML = `
					<span class="cp-search-suggest__title">${counterparty.label || counterparty.name || 'Контрагент'}</span>
					<span class="cp-search-suggest__meta">${counterparty.phone || 'Телефон не указан'}${item.source === 'dadata' ? ' · DaData · создать карточку' : ' · Открыть карточку'}</span>
				`;

				button.addEventListener('click', () => {
					if (typeof item?.action_url === 'string' && item.action_url !== '') {
						window.location.href = item.action_url;
					}
				});

				resultsNode.appendChild(button);
			});

			resultsNode.hidden = false;
		};

		const executeSearch = async () => {
			const query = input.value.trim();

			if (query.length < 2) {
				hideResults();
				showStatus('');
				return;
			}

			showStatus('Ищу в базе и через DaData...');

			try {
				const url = new URL(suggestUrl, window.location.origin);
				url.searchParams.set('query', query);

				const response = await fetch(url.toString(), {
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				const json = await response.json();

				if (!response.ok) {
					throw new Error(json?.message || 'Не удалось выполнить поиск контрагентов.');
				}

				renderResults(json?.suggestions || []);

				const hasSuggestions = Array.isArray(json?.suggestions) && json.suggestions.length > 0;
				const emptyMessage = json?.dadata_error
					? `DaData недоступен: ${json.dadata_error}`
					: 'Ничего не найдено.';

				showStatus(hasSuggestions ? 'Выберите подходящий вариант.' : emptyMessage, hasSuggestions ? 'success' : 'error');
			} catch (error) {
				hideResults();
				showStatus(error instanceof Error ? error.message : 'Не удалось выполнить поиск контрагентов.', 'error');
			}
		};

		input.addEventListener('input', () => {
			window.clearTimeout(debounceTimer);
			debounceTimer = window.setTimeout(executeSearch, 300);
		});

		input.addEventListener('focus', () => {
			if (input.value.trim().length >= 2) {
				executeSearch();
			}
		});

		document.addEventListener('click', (event) => {
			if (!form.contains(event.target)) {
				hideResults();
			}
		});
	});
};

initCounterpartyLiveSearch();

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

const initOrderParticipantPanels = () => {
	const panels = document.querySelectorAll('[data-participant-panel]');

	panels.forEach((panel) => {
		const toggle = panel.querySelector('[data-participant-toggle]');
		const body = panel.querySelector('[data-participant-body]');

		if (!(toggle instanceof HTMLButtonElement) || !(body instanceof HTMLElement)) {
			return;
		}

		const setOpenState = (isOpen) => {
			panel.classList.toggle('is-open', isOpen);
			toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			body.hidden = !isOpen;
		};

		setOpenState(panel.classList.contains('is-open'));

		toggle.addEventListener('click', () => {
			setOpenState(!panel.classList.contains('is-open'));
		});
	});
};

initOrderParticipantPanels();

const initOrderParticipantLookup = () => {
	const forms = document.querySelectorAll('form.order-edit');

	forms.forEach((form) => {
		const searchUrl = form.dataset.participantSearchUrl;
		const resolveUrl = form.dataset.participantResolveUrl;
		const csrfToken = form.querySelector('input[name="_token"]')?.value || '';

		if (!searchUrl || !resolveUrl) {
			return;
		}

		const participantPanels = Array.from(form.querySelectorAll('[data-participant-panel]'));

		const setBadgeState = (panel, isFilled) => {
			const badge = panel.querySelector('[data-participant-badge]');
			if (!(badge instanceof HTMLElement)) {
				return;
			}

			badge.textContent = isFilled ? 'Заполнен' : 'Не указан';
			badge.classList.toggle('order-participant-panel__status--filled', isFilled);
			badge.classList.toggle('order-participant-panel__status--empty', !isFilled);
		};

		const updateParticipantsProgress = () => {
			const filledCount = participantPanels.filter((panel) => panel.dataset.participantFilled === '1').length;

			form.querySelectorAll('[data-participants-progress]').forEach((node) => {
				node.textContent = `${filledCount}/4`;
			});

			participantPanels.forEach((panel) => {
				const role = panel.dataset.participantRole;
				if (!role) {
					return;
				}

				const stateNode = form.querySelector(`[data-participant-side-state="${role}"]`);
				if (!(stateNode instanceof HTMLElement)) {
					return;
				}

				const isFilled = panel.dataset.participantFilled === '1';
				stateNode.textContent = isFilled ? '✓' : '—';
				stateNode.classList.toggle('is-ok', isFilled);
			});
		};

		const syncSelectOption = (selectName, counterparty) => {
			const select = form.querySelector(`select[name="${selectName}"]`);
			if (!(select instanceof HTMLSelectElement) || !counterparty?.id) {
				return;
			}

			let option = Array.from(select.options).find((item) => item.value === `${counterparty.id}`);

			if (!option) {
				option = document.createElement('option');
				option.value = `${counterparty.id}`;
				select.appendChild(option);
			}

			option.textContent = counterparty.label || counterparty.name || `Контрагент #${counterparty.id}`;
			option.dataset.counterpartyName = counterparty.name || '';
			option.dataset.counterpartyPhone = counterparty.phone || '—';
			option.dataset.counterpartyInn = counterparty.inn || '';
			select.value = `${counterparty.id}`;
			customSelectRegistry.get(select)?.rebuild?.();
			select.dispatchEvent(new Event('change', { bubbles: true }));
		};

		const clearSelectOption = (selectName) => {
			const select = form.querySelector(`select[name="${selectName}"]`);
			if (!(select instanceof HTMLSelectElement)) {
				return;
			}

			select.value = '';
			customSelectRegistry.get(select)?.rebuild?.();
			select.dispatchEvent(new Event('change', { bubbles: true }));
		};

		const applyCounterpartyToPanel = (panel, counterparty) => {
			const name = counterparty?.name?.trim() || 'Не указан';
			const phone = counterparty?.phone?.trim() || '—';
			const summary = panel.querySelector('[data-participant-summary="meta"]');
			const nameField = panel.querySelector('[data-participant-field="name"]');
			const phoneField = panel.querySelector('[data-participant-field="phone"]');

			if (summary instanceof HTMLElement) {
				summary.textContent = name;
			}

			if (nameField instanceof HTMLElement) {
				nameField.textContent = name;
			}

			if (phoneField instanceof HTMLElement) {
				phoneField.textContent = phone;
			}

			const isFilled = name !== 'Не указан' || phone !== '—';
			panel.dataset.participantFilled = isFilled ? '1' : '0';
			setBadgeState(panel, isFilled);
			updateParticipantsProgress();
		};

		const showLookupStatus = (lookup, message, state = 'info') => {
			const node = lookup.querySelector('[data-participant-search-status]');
			if (!(node instanceof HTMLElement)) {
				return;
			}

			node.hidden = !message;
			node.textContent = message;
			node.classList.remove('is-success', 'is-error');

			if (state === 'success') {
				node.classList.add('is-success');
			}

			if (state === 'error') {
				node.classList.add('is-error');
			}
		};

		const hideLookupResults = (lookup) => {
			const node = lookup.querySelector('[data-participant-search-results]');
			if (!(node instanceof HTMLElement)) {
				return;
			}

			node.hidden = true;
			node.innerHTML = '';
		};

		const renderLookupResults = (lookup, suggestions, onSelect) => {
			const node = lookup.querySelector('[data-participant-search-results]');
			if (!(node instanceof HTMLElement)) {
				return;
			}

			node.innerHTML = '';

			if (!Array.isArray(suggestions) || suggestions.length === 0) {
				node.hidden = true;
				return;
			}

			suggestions.forEach((item) => {
				const counterparty = item?.counterparty || {};
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'order-participant-lookup__result';
				button.innerHTML = `
					<span class="order-participant-lookup__result-title">${counterparty.label || counterparty.name || 'Контрагент'}</span>
					<span class="order-participant-lookup__result-meta">${counterparty.phone || 'Телефон не указан'}${item.source === 'dadata' ? ' · DaData' : ' · База'}</span>
				`;

				button.addEventListener('click', () => onSelect(item));
				node.appendChild(button);
			});

			node.hidden = false;
		};

		const lookupHandlers = Array.from(form.querySelectorAll('[data-participant-lookup]'));

		lookupHandlers.forEach((lookup) => {
			const panel = lookup.closest('[data-participant-panel]');
			const input = lookup.querySelector('[data-participant-search-input]');
			const clearButton = lookup.querySelector('[data-participant-clear]');
			const role = panel?.dataset.participantRole || '';
			const targetMode = lookup.dataset.targetMode || 'select';
			const targetSelectName = lookup.dataset.targetSelectName || '';
			const targetInputName = lookup.dataset.targetInputName || '';
			let debounceTimer = null;

			if (!(panel instanceof HTMLElement) || !(input instanceof HTMLInputElement)) {
				return;
			}

			const resolveSelection = async (payload) => {
				const body = new URLSearchParams();
				body.append('_token', csrfToken);
				body.append('source', payload?.source || 'local');
				body.append('role', role);

				Object.entries(payload?.counterparty || {}).forEach(([key, value]) => {
					if (value === null || value === undefined) {
						return;
					}

					body.append(key, `${value}`);
				});

				const response = await fetch(resolveUrl, {
					method: 'POST',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: body.toString(),
				});

				const json = await response.json();
				if (!response.ok) {
					throw new Error(json?.message || 'Не удалось сохранить контрагента.');
				}

				return json?.counterparty || null;
			};

			const executeSearch = async () => {
				const query = input.value.trim();
				if (query.length < 2) {
					hideLookupResults(lookup);
					showLookupStatus(lookup, '');
					return;
				}

				showLookupStatus(lookup, 'Ищу совпадения в базе и через DaData...');

				try {
					const url = new URL(searchUrl, window.location.origin);
					url.searchParams.set('query', query);
					url.searchParams.set('role', role);

					const response = await fetch(url.toString(), {
						headers: {
							Accept: 'application/json',
							'X-Requested-With': 'XMLHttpRequest',
						},
					});

					const json = await response.json();
					if (!response.ok) {
						throw new Error(json?.message || 'Не удалось выполнить поиск.');
					}

					renderLookupResults(lookup, json?.suggestions || [], async (item) => {
						try {
							showLookupStatus(lookup, 'Сохраняю выбранного контрагента...');
							const counterparty = await resolveSelection(item);
							if (!counterparty) {
								throw new Error('Контрагент не был получен от сервера.');
							}

							input.value = counterparty.name || '';
							applyCounterpartyToPanel(panel, counterparty);

							if (targetMode === 'select' && targetSelectName) {
								syncSelectOption(targetSelectName, counterparty);
							}

							if (targetMode === 'hidden' && targetInputName) {
								const hiddenInput = lookup.querySelector('[data-participant-hidden-input]')
									|| form.querySelector(`input[name="${targetInputName}"]`);

								if (hiddenInput instanceof HTMLInputElement) {
									hiddenInput.value = `${counterparty.id || ''}`;
								}
							}

							hideLookupResults(lookup);
							showLookupStatus(lookup, 'Контрагент выбран.', 'success');
						} catch (error) {
							showLookupStatus(lookup, error instanceof Error ? error.message : 'Не удалось выбрать контрагента.', 'error');
						}
					});

					const hasSuggestions = Array.isArray(json?.suggestions) && json.suggestions.length > 0;
					const emptyMessage = json?.dadata_error
						? `DaData недоступен: ${json.dadata_error}`
						: 'Ничего не найдено.';

					showLookupStatus(
						lookup,
						hasSuggestions ? 'Выберите подходящий вариант.' : emptyMessage,
						hasSuggestions ? 'success' : 'error',
					);
				} catch (error) {
					hideLookupResults(lookup);
					showLookupStatus(lookup, error instanceof Error ? error.message : 'Не удалось выполнить поиск.', 'error');
				}
			};

			input.addEventListener('input', () => {
				window.clearTimeout(debounceTimer);
				debounceTimer = window.setTimeout(executeSearch, 300);
			});

			input.addEventListener('focus', () => {
				if (input.value.trim().length >= 2) {
					executeSearch();
				}
			});

			clearButton?.addEventListener('click', () => {
				input.value = '';
				hideLookupResults(lookup);
				showLookupStatus(lookup, 'Выбор очищен.');
				applyCounterpartyToPanel(panel, { name: 'Не указан', phone: '—' });

				if (targetMode === 'select' && targetSelectName) {
					clearSelectOption(targetSelectName);
				}

				if (targetMode === 'hidden' && targetInputName) {
					const hiddenInput = lookup.querySelector('[data-participant-hidden-input]')
						|| form.querySelector(`input[name="${targetInputName}"]`);

					if (hiddenInput instanceof HTMLInputElement) {
						hiddenInput.value = '';
					}
				}
			});
		});

		form.querySelectorAll('select[data-sync-participant-role]').forEach((select) => {
			if (!(select instanceof HTMLSelectElement)) {
				return;
			}

			const role = select.dataset.syncParticipantRole;
			const panel = form.querySelector(`[data-participant-panel][data-participant-role="${role}"]`);
			if (!(panel instanceof HTMLElement)) {
				return;
			}

			select.addEventListener('change', () => {
				const option = select.selectedOptions[0];
				if (!(option instanceof HTMLOptionElement) || !option.value) {
					applyCounterpartyToPanel(panel, { name: 'Не указан', phone: '—' });
					return;
				}

				applyCounterpartyToPanel(panel, {
					name: option.dataset.counterpartyName || option.textContent || 'Не указан',
					phone: option.dataset.counterpartyPhone || '—',
				});
			});
		});

		updateParticipantsProgress();
	});
};

initOrderParticipantLookup();

const initOrderCustomerLookup = () => {
	const forms = document.querySelectorAll('form.order-edit');

	forms.forEach((form) => {
		const searchUrl = form.dataset.participantSearchUrl;
		const autofillUrl = form.dataset.customerAutofillUrl;
		const lookup = form.querySelector('[data-customer-lookup]');

		if (!(lookup instanceof HTMLElement) || !searchUrl) {
			return;
		}

		const input = lookup.querySelector('[data-customer-search-input]');
		const autofillButton = lookup.querySelector('[data-customer-autofill]');
		const clearButton = lookup.querySelector('[data-customer-clear]');
		const statusNode = lookup.querySelector('[data-customer-search-status]');
		const resultsNode = lookup.querySelector('[data-customer-search-results]');
		const customerIdInput = form.querySelector('input[name="customer_id"]');
		const customerCounterpartyIdInput = form.querySelector('input[name="customer_counterparty_id"]');
		const customerContactIdInput = form.querySelector('input[name="customer_contact_id"]');
		const customerNameInput = form.querySelector('input[name="customer_name"]');
		const customerContactNameInput = form.querySelector('input[name="customer_contact_name"]');
		const customerPhoneInput = form.querySelector('input[name="customer_phone"]');
		const customerEmailInput = form.querySelector('input[name="customer_email"]');
		const customerInnInput = form.querySelector('input[name="customer_inn"]');
		const customerLegalAddressInput = form.querySelector('input[name="customer_legal_address"]');
		const customerLegalPostalCodeInput = form.querySelector('input[name="customer_legal_postal_code"]');
		const customerLegalRegionInput = form.querySelector('input[name="customer_legal_region"]');
		const customerLegalCityInput = form.querySelector('input[name="customer_legal_city"]');
		const customerLegalSettlementInput = form.querySelector('input[name="customer_legal_settlement"]');
		const customerLegalStreetInput = form.querySelector('input[name="customer_legal_street"]');
		const customerLegalHouseInput = form.querySelector('input[name="customer_legal_house"]');
		const customerLegalBlockInput = form.querySelector('input[name="customer_legal_block"]');
		const customerLegalFlatInput = form.querySelector('input[name="customer_legal_flat"]');
		const customerLegalFiasIdInput = form.querySelector('input[name="customer_legal_fias_id"]');
		const customerLegalKladrIdInput = form.querySelector('input[name="customer_legal_kladr_id"]');
		const customerLegalGeoLatInput = form.querySelector('input[name="customer_legal_geo_lat"]');
		const customerLegalGeoLonInput = form.querySelector('input[name="customer_legal_geo_lon"]');
		const customerLegalQcInput = form.querySelector('input[name="customer_legal_qc"]');
		const customerLegalQcGeoInput = form.querySelector('input[name="customer_legal_qc_geo"]');
		const customerLegalAddressInvalidInput = form.querySelector('input[name="customer_legal_address_invalid"]');
		const customerLegalAddressDataInput = form.querySelector('input[name="customer_legal_address_data"]');
		const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || form.querySelector('input[name="_token"]')?.value || '';
		let debounceTimer = null;

		if (!(input instanceof HTMLInputElement) || !(statusNode instanceof HTMLElement) || !(resultsNode instanceof HTMLElement)) {
			return;
		}

		const serializeFieldValue = (value) => {
			if (value === null || value === undefined) {
				return '';
			}

			if (typeof value === 'object') {
				try {
					return JSON.stringify(value);
				} catch (error) {
					return '';
				}
			}

			if (typeof value === 'boolean') {
				return value ? '1' : '0';
			}

			return `${value}`;
		};

		const setInputValue = (inputNode, value, { preserveEmpty = false } = {}) => {
			if (!(inputNode instanceof HTMLInputElement)) {
				return;
			}

			const serialized = serializeFieldValue(value);

			if (serialized === '' && preserveEmpty) {
				return;
			}

			inputNode.value = serialized;
		};

		const applyCustomerLegalAddress = (payload = {}, { preserveEmpty = false } = {}) => {
			setInputValue(customerLegalAddressInput, payload.legal_address, { preserveEmpty });
			setInputValue(customerLegalPostalCodeInput, payload.legal_postal_code, { preserveEmpty });
			setInputValue(customerLegalRegionInput, payload.legal_region, { preserveEmpty });
			setInputValue(customerLegalCityInput, payload.legal_city, { preserveEmpty });
			setInputValue(customerLegalSettlementInput, payload.legal_settlement, { preserveEmpty });
			setInputValue(customerLegalStreetInput, payload.legal_street, { preserveEmpty });
			setInputValue(customerLegalHouseInput, payload.legal_house, { preserveEmpty });
			setInputValue(customerLegalBlockInput, payload.legal_block, { preserveEmpty });
			setInputValue(customerLegalFlatInput, payload.legal_flat, { preserveEmpty });
			setInputValue(customerLegalFiasIdInput, payload.legal_fias_id, { preserveEmpty });
			setInputValue(customerLegalKladrIdInput, payload.legal_kladr_id, { preserveEmpty });
			setInputValue(customerLegalGeoLatInput, payload.legal_geo_lat, { preserveEmpty });
			setInputValue(customerLegalGeoLonInput, payload.legal_geo_lon, { preserveEmpty });
			setInputValue(customerLegalQcInput, payload.legal_qc, { preserveEmpty });
			setInputValue(customerLegalQcGeoInput, payload.legal_qc_geo, { preserveEmpty });
			setInputValue(customerLegalAddressInvalidInput, payload.legal_address_invalid, { preserveEmpty });
			setInputValue(customerLegalAddressDataInput, payload.legal_address_data, { preserveEmpty });
		};

		const showStatus = (message, state = 'info') => {
			statusNode.hidden = !message;
			statusNode.textContent = message;
			statusNode.classList.remove('is-success', 'is-error');

			if (state === 'success') {
				statusNode.classList.add('is-success');
			}

			if (state === 'error') {
				statusNode.classList.add('is-error');
			}
		};

		const hideResults = () => {
			resultsNode.hidden = true;
			resultsNode.innerHTML = '';
		};

		const applyCustomer = (counterparty = {}) => {
			if (customerIdInput instanceof HTMLInputElement) {
				customerIdInput.value = `${counterparty.customer_id || ''}`;
			}

			if (customerCounterpartyIdInput instanceof HTMLInputElement) {
				customerCounterpartyIdInput.value = `${counterparty.id || ''}`;
			}

			if (customerContactIdInput instanceof HTMLInputElement) {
				customerContactIdInput.value = '';
			}

			if (customerNameInput instanceof HTMLInputElement) {
				customerNameInput.value = counterparty.name || counterparty.short_name || counterparty.full_name || '';
			}

			if (customerContactNameInput instanceof HTMLInputElement) {
				customerContactNameInput.value = counterparty.contact_name || '';
			}

			if (customerPhoneInput instanceof HTMLInputElement) {
				customerPhoneInput.value = counterparty.phone && counterparty.phone !== '—' ? counterparty.phone : '';
			}

			if (customerEmailInput instanceof HTMLInputElement) {
				customerEmailInput.value = counterparty.email || '';
			}

			if (customerInnInput instanceof HTMLInputElement) {
				customerInnInput.value = counterparty.inn || '';
			}

			applyCustomerLegalAddress(counterparty);
		};

		const applyCustomerAutofill = (payload = {}) => {
			if (customerIdInput instanceof HTMLInputElement) {
				customerIdInput.value = '';
			}

			if (customerCounterpartyIdInput instanceof HTMLInputElement) {
				customerCounterpartyIdInput.value = '';
			}

			if (customerContactIdInput instanceof HTMLInputElement) {
				customerContactIdInput.value = '';
			}

			const resolvedName = payload.name || payload.short_name || payload.full_name || '';

			if (input instanceof HTMLInputElement) {
				input.value = resolvedName;
			}

			if (customerNameInput instanceof HTMLInputElement) {
				customerNameInput.value = resolvedName;
			}

			if (customerContactNameInput instanceof HTMLInputElement && payload.contact_name) {
				customerContactNameInput.value = payload.contact_name;
			}

			if (customerPhoneInput instanceof HTMLInputElement && payload.phone) {
				customerPhoneInput.value = payload.phone;
			}

			if (customerEmailInput instanceof HTMLInputElement && payload.email) {
				customerEmailInput.value = payload.email;
			}

			if (customerInnInput instanceof HTMLInputElement) {
				customerInnInput.value = payload.inn || '';
			}

			applyCustomerLegalAddress(payload);
		};

		const renderResults = (suggestions) => {
			resultsNode.innerHTML = '';

			if (!Array.isArray(suggestions) || suggestions.length === 0) {
				hideResults();
				return;
			}

			suggestions.forEach((item) => {
				const counterparty = item?.counterparty || {};
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'order-participant-lookup__result';
				button.innerHTML = `
					<span class="order-participant-lookup__result-title">${counterparty.label || counterparty.name || 'Контрагент'}</span>
					<span class="order-participant-lookup__result-meta">${counterparty.phone || 'Телефон не указан'}${item.source === 'dadata' ? ' · DaData' : ' · База'}</span>
				`;

				button.addEventListener('click', () => {
					applyCustomer(counterparty);
					input.value = counterparty.name || counterparty.short_name || counterparty.full_name || '';
					hideResults();
					showStatus(item.source === 'dadata' ? 'Данные из DaData подставлены в форму. Сохранение произойдёт только после сохранения заявки.' : 'Заказчик выбран из базы.', 'success');
				});

				resultsNode.appendChild(button);
			});

			resultsNode.hidden = false;
		};

		const executeSearch = async () => {
			const query = input.value.trim();

			if (query.length < 2) {
				hideResults();
				showStatus('');
				return;
			}

			showStatus('Ищу совпадения в базе и через DaData...');

			try {
				const url = new URL(searchUrl, window.location.origin);
				url.searchParams.set('query', query);
				url.searchParams.set('role', 'customer');

				const response = await fetch(url.toString(), {
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				const json = await response.json();

				if (!response.ok) {
					throw new Error(json?.message || 'Не удалось выполнить поиск заказчика.');
				}

				renderResults(json?.suggestions || []);

				const hasSuggestions = Array.isArray(json?.suggestions) && json.suggestions.length > 0;
				const emptyMessage = json?.dadata_error
					? `DaData недоступен: ${json.dadata_error}`
					: 'Ничего не найдено.';

				showStatus(hasSuggestions ? 'Выберите подходящий вариант.' : emptyMessage, hasSuggestions ? 'success' : 'error');
			} catch (error) {
				hideResults();
				showStatus(error instanceof Error ? error.message : 'Не удалось выполнить поиск заказчика.', 'error');
			}
		};

		const executeAutofill = async () => {
			if (!autofillUrl) {
				showStatus('Маршрут автозаполнения не настроен.', 'error');
				return;
			}

			const rawQuery = (customerInnInput instanceof HTMLInputElement && customerInnInput.value.trim() !== '' ? customerInnInput.value : input.value).trim();
			const digits = rawQuery.replace(/\D+/g, '');

			if (![10, 12, 13, 15].includes(digits.length)) {
				showStatus('Для автозаполнения укажите ИНН или ОГРН.', 'error');
				return;
			}

			hideResults();
			showStatus('Запрашиваю данные заказчика из DaData...');

			if (autofillButton instanceof HTMLButtonElement) {
				autofillButton.disabled = true;
			}

			try {
				const response = await fetch(autofillUrl, {
					method: 'POST',
					headers: {
						'Accept': 'application/json',
						'Content-Type': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': csrfToken,
					},
					body: JSON.stringify({ query: digits }),
				});

				const json = await response.json();

				if (!response.ok) {
					throw new Error(json?.message || 'Не удалось получить данные заказчика из DaData.');
				}

				applyCustomerAutofill(json?.data || {});
				showStatus(json?.message || 'Данные заказчика подставлены из DaData.', 'success');
			} catch (error) {
				showStatus(error instanceof Error ? error.message : 'Не удалось получить данные заказчика из DaData.', 'error');
			} finally {
				if (autofillButton instanceof HTMLButtonElement) {
					autofillButton.disabled = false;
				}
			}
		};

		input.addEventListener('input', () => {
			window.clearTimeout(debounceTimer);
			debounceTimer = window.setTimeout(executeSearch, 300);
		});

		input.addEventListener('focus', () => {
			if (input.value.trim().length >= 2) {
				executeSearch();
			}
		});

		autofillButton?.addEventListener('click', () => {
			executeAutofill();
		});

		clearButton?.addEventListener('click', () => {
			input.value = '';
			hideResults();
			showStatus('Поиск очищен. Текущие значения полей можно отредактировать вручную.');

			if (customerIdInput instanceof HTMLInputElement) {
				customerIdInput.value = '';
			}

			if (customerCounterpartyIdInput instanceof HTMLInputElement) {
				customerCounterpartyIdInput.value = '';
			}

			if (customerContactIdInput instanceof HTMLInputElement) {
				customerContactIdInput.value = '';
			}

			if (customerContactNameInput instanceof HTMLInputElement) {
				customerContactNameInput.value = '';
			}

			if (customerPhoneInput instanceof HTMLInputElement) {
				customerPhoneInput.value = '';
			}

			if (customerEmailInput instanceof HTMLInputElement) {
				customerEmailInput.value = '';
			}

			if (customerInnInput instanceof HTMLInputElement) {
				customerInnInput.value = '';
			}

			applyCustomerLegalAddress({}, { preserveEmpty: false });
		});

		document.addEventListener('click', (event) => {
			if (!lookup.contains(event.target)) {
				hideResults();
			}
		});
	});
};

initOrderCustomerLookup();

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
		const getDistanceInput = () => form.querySelector('[data-route-distance-input]');

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

		const updateDistanceValue = (distanceKm) => {
			const distanceInput = getDistanceInput();

			if (!(distanceInput instanceof HTMLInputElement)) {
				return;
			}

			const distanceNumber = Number.parseFloat(`${distanceKm}`);

			distanceInput.value = Number.isFinite(distanceNumber)
				? `${Math.round(distanceNumber)}`
				: '';
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
			setStatus('Маршрут изменён. Определите точки заново или сохраните заявку — координаты и расстояние будут пересчитаны автоматически.');
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
				updateDistanceValue(null);
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
				updateDistanceValue(payload.distance_km);

				const distanceLabel = Number.isFinite(Number.parseFloat(`${payload.distance_km ?? ''}`))
					? ` Расстояние: ${Math.round(Number.parseFloat(`${payload.distance_km}`))} км.`
					: '';
				const distanceSourceLabel = payload.distance_source === 'yandex-routing'
					? ' Расчёт выполнен по дорогам.'
					: ' Router API недоступен, использовано расстояние по прямой.';

				setStatus(`Координаты маршрута успешно определены.${distanceLabel}${distanceSourceLabel}`, payload.distance_source === 'yandex-routing' ? 'success' : 'warning');
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
