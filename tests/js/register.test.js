/**
 * Register document-number behavior.
 */

import { initDocumentNumbers } from '../../assets/js/admin/admin';

function mount() {
	document.body.innerHTML = `
		<select id="ean-to-pos-document_type">
			<option value="receipt">Receipt</option>
			<option value="invoice">Invoice</option>
			<option value="">None</option>
		</select>
		<p id="ean-to-pos-document-field">
			<input id="ean-to-pos-document" value="1" data-suggestions='{"receipt":"1","invoice":"S3/001/11/2026"}'>
		</p>
		<button id="ean-to-pos-next-number" type="button" data-nonce="abc" data-error="Could not get the next number. Try again." hidden>Use next number</button>
		<button id="ean-to-pos-reset-number" type="button" data-error="Could not reset the document number. Try again." hidden>Reset</button>
		<span id="ean-to-pos-number-status"></span>
	`;
	window.ajaxurl = '/wp-admin/admin-ajax.php';
}

describe('document numbers', () => {
	beforeEach(() => {
		mount();
	});

	test('remembers an edit when the document type changes and changes back', () => {
		initDocumentNumbers(document, jest.fn());
		const type = document.getElementById('ean-to-pos-document_type');
		const number = document.getElementById('ean-to-pos-document');

		number.value = '15';
		type.value = 'invoice';
		type.dispatchEvent(new Event('change'));
		expect(number.value).toBe('S3/001/11/2026');

		type.value = 'receipt';
		type.dispatchEvent(new Event('change'));
		expect(number.value).toBe('15');
	});

	test('hides the number field when no document type is selected', () => {
		initDocumentNumbers(document, jest.fn());
		const type = document.getElementById('ean-to-pos-document_type');

		type.value = '';
		type.dispatchEvent(new Event('change'));

		expect(document.getElementById('ean-to-pos-document-field').hidden).toBe(true);
		expect(document.getElementById('ean-to-pos-next-number').hidden).toBe(true);
	});

	test('replaces the number with the value returned for that request', async () => {
		const request = jest.fn().mockResolvedValue({
			json: async () => ({ success: true, data: { number: '2' } }),
		});
		initDocumentNumbers(document, request);

		document.getElementById('ean-to-pos-next-number').click();
		const response = await request.mock.results[0].value;
		await response.json();

		const { body } = request.mock.calls[0][1];
		expect(request.mock.calls[0][0]).toBe('/wp-admin/admin-ajax.php');
		expect(body.get('action')).toBe('ean_to_pos_next_number');
		expect(body.get('mode')).toBe('next');
		expect(body.get('_ajax_nonce')).toBe('abc');
		expect(document.getElementById('ean-to-pos-document').value).toBe('2');
	});

	test('shows the button error when the request fails', async () => {
		const request = jest.fn().mockResolvedValue({
			json: async () => ({ success: false, data: {} }),
		});
		initDocumentNumbers(document, request);

		document.getElementById('ean-to-pos-reset-number').click();
		const response = await request.mock.results[0].value;
		await response.json();

		expect(request.mock.calls[0][1].body.get('mode')).toBe('reset');
		expect(document.getElementById('ean-to-pos-number-status').textContent).toBe(
			'Could not reset the document number. Try again.',
		);
		expect(document.getElementById('ean-to-pos-document').value).toBe('1');
	});
});
