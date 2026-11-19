/**
 * Document number controls for the register screen.
 */

import '../../css/admin/admin.scss';

/**
 * Connects the document type, stored edits and next-number requests.
 *
 * @param {Document} doc       Document that contains the register form.
 * @param {Function} [request] Fetch implementation. Falls back to window.fetch.
 */
export function initDocumentNumbers(doc = document, request = undefined) {
	const type = doc.getElementById('ean-to-pos-document_type');
	const number = doc.getElementById('ean-to-pos-document');
	const field = doc.getElementById('ean-to-pos-document-field');
	const nextButton = doc.getElementById('ean-to-pos-next-number');
	const resetButton = doc.getElementById('ean-to-pos-reset-number');
	const status = doc.getElementById('ean-to-pos-number-status');

	if (!type || !number || !field || !nextButton || !resetButton || !status) {
		return;
	}

	const suggestions = JSON.parse(number.dataset.suggestions);
	const edits = new Map();
	let selected = type.value;

	const updateVisibility = () => {
		field.hidden = !selected;
		nextButton.hidden = !selected;
		resetButton.hidden = !selected;
	};

	type.addEventListener('change', () => {
		edits.set(selected, number.value);
		selected = type.value;
		status.textContent = '';
		number.value = edits.get(selected) ?? suggestions[selected] ?? '';
		updateVisibility();
	});

	const updateNumber = async (mode, button) => {
		const requestedType = selected;
		const requestedNumber = number.value;
		nextButton.disabled = true;
		resetButton.disabled = true;
		status.textContent = '';
		try {
			const send = request || window.fetch.bind(window);
			const response = await send(window.ajaxurl, {
				method: 'POST',
				body: new URLSearchParams({
					action: 'ean_to_pos_next_number',
					_ajax_nonce: nextButton.dataset.nonce,
					mode,
					document_type: requestedType,
					document_number: requestedNumber,
				}),
			});
			const result = await response.json();
			if (!result.success) {
				if (selected === requestedType) {
					status.textContent = result.data?.message || button.dataset.error;
				}
				return;
			}
			if (selected === requestedType && number.value === requestedNumber) {
				number.value = result.data.number;
				number.focus();
			}
		} catch {
			if (selected === requestedType) {
				status.textContent = button.dataset.error;
			}
		} finally {
			nextButton.disabled = false;
			resetButton.disabled = false;
		}
	};

	nextButton.addEventListener('click', () => updateNumber('next', nextButton));
	resetButton.addEventListener('click', () => updateNumber('reset', resetButton));

	updateVisibility();
}

if (typeof document !== 'undefined') {
	initDocumentNumbers();
}
