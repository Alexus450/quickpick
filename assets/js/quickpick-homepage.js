/**
 * QuickPick - Set as Homepage functionality
 * 
 * @package QuickPick
 * @since 1.0.3
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		function showAdminNotice(message, type) {
			var noticeType = type || 'error';
			var $notice = $('<div class="notice notice-' + noticeType + ' is-dismissible"><p></p></div>');
			$notice.find('p').text(message);
			$('.wrap h1').first().after($notice);
		}

		function closeAllQuickPickMenus() {
			$('[data-qp-dropdown]').each(function() {
				var $dropdown = $(this);
				$dropdown.find('.qp-button').attr('aria-expanded', 'false');
				$dropdown.find('.qp-menu').attr('hidden', 'hidden');
			});
		}

		$(document).on('click', '.qp-button', function(e) {
			e.preventDefault();
			var $button = $(this);
			var $dropdown = $button.closest('[data-qp-dropdown]');
			var $menu = $dropdown.find('.qp-menu');
			var expanded = $button.attr('aria-expanded') === 'true';

			closeAllQuickPickMenus();

			if (!expanded) {
				$button.attr('aria-expanded', 'true');
				$menu.removeAttr('hidden');
			}
		});

		$(document).on('click', function(e) {
			if (!$(e.target).closest('[data-qp-dropdown]').length) {
				closeAllQuickPickMenus();
			}
		});

		$(document).on('keydown', function(e) {
			if (e.key === 'Escape') {
				closeAllQuickPickMenus();
			}
		});
		
		// Handle "Set as Homepage" click
		$(document).on('click', '.quickpick-set-homepage', function(e) {
			e.preventDefault();
			
			var $link = $(this);
			var pageId = $link.data('page-id');
			var nonce = $link.data('nonce');
			
			// Show loading state
			var $label = $link.find('.quickpick-label');
			var originalText = $label.text();
			$label.text(QuickPickHomepage.settingText);
			$link.css('pointer-events', 'none');
			
			// Send AJAX request
			$.ajax({
				url: QuickPickHomepage.ajaxUrl,
				type: 'POST',
				data: {
					action: 'quickpick_set_homepage',
					page_id: pageId,
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						showAdminNotice(response.data.message || QuickPickHomepage.successText, 'success');
						window.setTimeout(function() {
							window.location.href = QuickPickHomepage.successRedirect;
						}, 800);
					} else {
						showAdminNotice(response.data.message || QuickPickHomepage.errorGenericText, 'error');
						$label.text(originalText);
						$link.css('pointer-events', 'auto');
					}
				},
				error: function() {
					showAdminNotice(QuickPickHomepage.errorGenericText, 'error');
					$label.text(originalText);
					$link.css('pointer-events', 'auto');
				}
			});
		});
		
	});

})(jQuery);

