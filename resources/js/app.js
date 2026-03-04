import './bootstrap';

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
