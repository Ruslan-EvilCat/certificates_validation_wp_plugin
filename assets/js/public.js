(function () {
	'use strict';

	var config = window.cvpPublicConfig || {};
	var strings = config.strings || {};

	function createField(label, value) {
		var row = document.createElement('div');
		var term = document.createElement('span');
		var text = document.createElement('span');

		row.className = 'cvp-card-row';
		term.className = 'cvp-card-label';
		text.className = 'cvp-card-value';

		term.textContent = label;
		text.textContent = value;

		row.appendChild(term);
		row.appendChild(text);

		return row;
	}

	function renderCertificate(resultElement, certificate) {
		var card = document.createElement('div');
		var linkRow;
		var linkLabel;
		var link;

		card.className = 'cvp-card';
		card.appendChild(createField(strings.code || 'Certificate Code', certificate.code || ''));
		card.appendChild(createField(strings.name || 'Name', certificate.name || ''));
		card.appendChild(createField(strings.surname || 'Surname', certificate.surname || ''));
		card.appendChild(createField(strings.course || 'Course', certificate.course || ''));
		card.appendChild(createField(strings.hours || 'Hours', String(certificate.hours || 0)));
		card.appendChild(createField(strings.ects || 'ECTS Hours', String(certificate.ects_hours || 0)));
		card.appendChild(createField(strings.date || 'Issued Date', certificate.issued_date || ''));

		if (certificate.course_link) {
			linkRow = document.createElement('div');
			linkLabel = document.createElement('span');
			link = document.createElement('a');

			linkRow.className = 'cvp-card-row';
			linkLabel.className = 'cvp-card-label';
			link.className = 'cvp-card-link';

			linkLabel.textContent = strings.link || 'Course Link';
			link.textContent = certificate.course_link;
			link.href = certificate.course_link;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';

			linkRow.appendChild(linkLabel);
			linkRow.appendChild(link);
			card.appendChild(linkRow);
		}

		resultElement.innerHTML = '';
		resultElement.appendChild(card);
		resultElement.hidden = false;
	}

	function setStatus(statusElement, message, isLoading) {
		statusElement.textContent = message || '';
		statusElement.classList.toggle('is-loading', !!isLoading);
	}

	function initValidation(container) {
		var form = container.querySelector('.cvp-validation-form');
		var input = container.querySelector('.cvp-input');
		var status = container.querySelector('.cvp-status');
		var result = container.querySelector('.cvp-result');

		if (!form || !input || !status || !result) {
			return;
		}

		form.addEventListener('submit', function (event) {
			var code;
			var requestBody;

			event.preventDefault();

			code = input.value.trim().toUpperCase();
			input.value = code;
			result.hidden = true;
			result.innerHTML = '';

			if (!code) {
				setStatus(status, strings.empty || 'Please enter certificate number', false);
				return;
			}

			setStatus(status, strings.loading || 'Searching...', true);

			requestBody = new URLSearchParams();
			requestBody.append('action', 'cvp_validate_certificate');
			requestBody.append('nonce', config.nonce || '');
			requestBody.append('code', code);

			window.fetch(config.ajaxUrl || '', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: requestBody.toString()
			}).then(function (response) {
				return response.json();
			}).then(function (payload) {
				if (!payload || !payload.success || !payload.data || !payload.data.certificate) {
					throw payload;
				}

				setStatus(status, '', false);
				renderCertificate(result, payload.data.certificate);
			}).catch(function (error) {
				var message = strings.error || 'An unexpected error occurred. Please try again.';

				if (error && error.data && error.data.message) {
					message = error.data.message;
				}

				result.hidden = true;
				result.innerHTML = '';
				setStatus(status, message, false);
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var containers = document.querySelectorAll('.cvp-certificate-validation');

		containers.forEach(initValidation);
	});
}());
