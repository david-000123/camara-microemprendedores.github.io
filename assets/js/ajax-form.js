$(function() {

	// Get the form.
	var form = $('#contact-form');

	// Get the messages div.
	var formMessages = $('.ajax-response');

	// Get the confirmation modal.
	var modal = $('#form-success-modal');
	var lastFocused = null;

	// Same rule as the server (assets/mailer.php).
	var EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

	var DEFAULT_ERROR = '¡Ups! Ocurrió un error y no se pudo enviar tu mensaje.';

	// Returns '' if the field is valid, 'required' if it is empty, 'format' if the email is malformed.
	function getError(field) {
		if (!field.is('[required]')) {
			return '';
		}
		// A checkbox is valid only when it is checked.
		if (field.is(':checkbox')) {
			return field.prop('checked') ? '' : 'required';
		}
		var value = $.trim(field.val());
		if (value === '') {
			return 'required';
		}
		if (field.attr('type') === 'email' && !EMAIL_REGEX.test(value)) {
			return 'format';
		}
		return '';
	}

	// Mark (or unmark) a field as invalid.
	function setFieldError(field, error) {
		field.toggleClass('is-invalid', error !== '');
		field.attr('aria-invalid', error !== '' ? 'true' : 'false');
		field.data('error', error);
	}

	// Build the message from the fields that are currently invalid.
	// When none is left, the error state of the message is removed.
	function renderMessages() {
		var missing = [];
		var lines = [];

		form.find('.is-invalid').each(function() {
			var field = $(this);
			if (field.data('error') === 'required') {
				missing.push(field.data('label'));
			} else if (field.data('error') === 'format') {
				lines.push('Formato de email inválido');
			}
		});

		if (missing.length) {
			lines.unshift((missing.length > 1 ? 'Campos obligatorios: ' : 'Campo obligatorio: ') + missing.join(', '));
		}

		formMessages.empty();
		if (!lines.length) {
			formMessages.removeClass('error success');
			return;
		}

		$.each(lines, function(i, line) {
			if (i) {
				formMessages.append('<br>');
			}
			formMessages.append(document.createTextNode(line));
		});
		formMessages.removeClass('success').addClass('error');
	}

	// Validate every required field. Returns true if all of them are valid.
	function validateForm() {
		var firstInvalid = null;

		form.find('[required]').each(function() {
			var field = $(this);
			var error = getError(field);
			setFieldError(field, error);
			if (error && !firstInvalid) {
				firstInvalid = field;
			}
		});

		renderMessages();
		if (firstInvalid) {
			firstInvalid.trigger('focus');
		}
		return firstInvalid === null;
	}

	// Once a field is marked as invalid, re-check it while the user types:
	// the error disappears as soon as the value is correct.
	form.on('input change', 'input, textarea', function() {
		var field = $(this);
		if (field.hasClass('is-invalid')) {
			setFieldError(field, getError(field));
			renderMessages();
		}
	});

	function openModal() {
		lastFocused = document.activeElement;
		modal.prop('hidden', false);
		$('body').css('overflow', 'hidden');
		modal.find('.form-modal-close').trigger('focus');
	}

	function closeModal() {
		modal.prop('hidden', true);
		$('body').css('overflow', '');
		if (lastFocused) {
			$(lastFocused).trigger('focus');
		}
	}

	// Close with the × button or by clicking outside the content.
	modal.find('.form-modal-close').on('click', closeModal);
	modal.on('click', function(e) {
		if (e.target === this) {
			closeModal();
		}
	});
	$(document).on('keydown', function(e) {
		if (modal.prop('hidden')) {
			return;
		}
		if (e.key === 'Escape') {
			closeModal();
		} else if (e.key === 'Tab') {
			// The close button is the only focusable element: keep the focus inside the dialog.
			e.preventDefault();
		}
	});

	// Set up an event listener for the contact form.
	$(form).submit(function(e) {
		// Stop the browser from submitting the form.
		e.preventDefault();

		// Client-side validation.
		if (!validateForm()) {
			return;
		}

		// Serialize the form data.
		var formData = $(form).serialize();
		var submitButton = form.find('button[type="submit"]').prop('disabled', true);

		// Submit the form using AJAX.
		$.ajax({
			type: 'POST',
			url: $(form).attr('action'),
			data: formData
		})
		.done(function() {
			// Clear the form and the messages.
			$('#contact-form input,#contact-form textarea').not(':checkbox').val('');
			$('#contact-form :checkbox').prop('checked', false);
			$('#contact-form input,#contact-form textarea').removeClass('is-invalid').removeAttr('aria-invalid');
			formMessages.empty().removeClass('error success');

			openModal();
		})
		.fail(function(xhr) {
			var data = xhr.responseJSON;

			// The server validates too: it reports which fields failed.
			if (data && data.fields) {
				$.each(data.fields, function(name, error) {
					setFieldError(form.find('[name="' + name + '"]'), error);
				});
				renderMessages();
				return;
			}

			// Any other error: show the server message, or a generic one.
			formMessages.removeClass('success').addClass('error');
			formMessages.text(data && data.message ? data.message : DEFAULT_ERROR);
		})
		.always(function() {
			submitButton.prop('disabled', false);
		});
	});

});
