(function ($) {
	"use strict";

	$(function () {

		////////////////////
		// Mark as read //
		////////////////////
		$('.eddstix-mark-read').on('click', function (event) {
			event.preventDefault();

			var btn = $(this),
				replyID = $(this).data('replyid'),
				data = {
					'action': 'eddstix_mark_reply_read',
					'reply_id': replyID
				};

			$.post(ajaxurl, data, function (response) {

				/* check if response is an integer */
				if (Math.floor(response) == response && $.isNumeric(response)) {
					btn.fadeOut('fast');
					$('#eddstix-unread-' + replyID).fadeOut('fast');
				} else {
					alert(response);
				}

			});

		});

		////////////////////////////////
		// Check if editor is empty //
		////////////////////////////////
		$('.eddstix-reply-actions').on('click', 'button', function () {
			var editorContent = tinyMCE.activeEditor.getContent();
			if (editorContent === '' || editorContent === null) {

				/* Highlight the active editor */
				$(tinyMCE.activeEditor.getBody()).css('background-color', '#ffeeee');

				/* Alert the user */
				alert('You can\'t submit an empty ticket reply.');
				$(tinyMCE.activeEditor.getBody()).css('background-color', '');

				/* Focus on editor */
				tinyMCE.activeEditor.focus();

				return false;
			}
		});

		////////////////////////////////
		// jQuery Select2
		// http://select2.github.io/select2/
		////////////////////////////////
		if (jQuery().select2 && $('select.eddstix-select2').length) {
			var select = $('select.eddstix-select2');

			select.find('option[value=""]').remove();
			select.prepend('<option></option>');
			select.select2({
				placeholder: 'Please Select'
			});
		}

		////////////////////////////////
		// Add custom status to Bulk Edit
		////////////////////////////////
		var ticketStatus = {"New": "ticket_queued",
			"In Progress": "ticket_processing",
			"On Hold": "ticket_hold",
			"Closed": "ticket_status_closed"
		};

		// Remove core status
		$("select[name=_status] option[value=publish]").remove();
		$("select[name=_status] option[value=private]").remove();
		$("select[name=_status] option[value=pending]").remove();
		$("select[name=_status] option[value=draft]").remove();

		var $el = $("select[name=_status]");

		$.each(ticketStatus, function(label,key) {
		  $el.append($("<option></option>")
		     .attr("value", key).text(label));
		});
	});
}(jQuery));
