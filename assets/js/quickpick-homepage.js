/**
 * QuickPick - Set as Homepage functionality
 * 
 * @package QuickPick
 * @since 1.0.3
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		
		// Handle "Set as Homepage" click
		$(document).on('click', '.quickpick-set-homepage', function(e) {
			e.preventDefault();
			
			var $link = $(this);
			var pageId = $link.data('page-id');
			var nonce = $link.data('nonce');
			
			// Show loading state
			var originalText = $link.text();
			$link.text('Setting...');
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
						// Reload the page to update the UI
						location.reload();
					} else {
						alert(response.data.message || 'An error occurred');
						$link.text(originalText);
						$link.css('pointer-events', 'auto');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$link.text(originalText);
					$link.css('pointer-events', 'auto');
				}
			});
		});
		
	});

})(jQuery);

