/**
 * QuickPick list dropdown and Set as Homepage.
 *
 * @package QuickPick
 * @since 1.0.8
 */

(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function getDropdowns() {
		return document.querySelectorAll('[data-qp-dropdown]');
	}

	function getMenuLinks(dropdown) {
		var menu = dropdown.querySelector('.qp-menu');
		if (!menu) {
			return [];
		}
		return Array.prototype.slice.call(menu.querySelectorAll('a[href]'));
	}

	function closeDropdown(dropdown, restoreFocus) {
		var button = dropdown.querySelector('.qp-button');
		var menu = dropdown.querySelector('.qp-menu');
		if (button) {
			button.setAttribute('aria-expanded', 'false');
			if (restoreFocus) {
				button.focus();
			}
		}
		if (menu) {
			menu.setAttribute('hidden', 'hidden');
		}
	}

	function closeAll(except) {
		Array.prototype.forEach.call(getDropdowns(), function (dropdown) {
			if (dropdown !== except) {
				closeDropdown(dropdown, false);
			}
		});
	}

	function openDropdown(dropdown) {
		closeAll(dropdown);
		var button = dropdown.querySelector('.qp-button');
		var menu = dropdown.querySelector('.qp-menu');
		if (button) {
			button.setAttribute('aria-expanded', 'true');
		}
		if (menu) {
			menu.removeAttribute('hidden');
		}
	}

	function isOpen(dropdown) {
		var button = dropdown.querySelector('.qp-button');
		return button && button.getAttribute('aria-expanded') === 'true';
	}

	function showAdminNotice(message, type) {
		var heading = document.querySelector('.wrap h1');
		if (!heading) {
			return;
		}
		var notice = document.createElement('div');
		notice.className = 'notice notice-' + (type || 'error') + ' is-dismissible';
		var p = document.createElement('p');
		p.textContent = message;
		notice.appendChild(p);
		heading.parentNode.insertBefore(notice, heading.nextSibling);
	}

	function postForm(url, params, onSuccess, onError) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', url, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		xhr.onload = function () {
			if (xhr.status >= 200 && xhr.status < 300) {
				try {
					onSuccess(JSON.parse(xhr.responseText));
				} catch (e) {
					onError();
				}
			} else {
				onError();
			}
		};
		xhr.onerror = onError;
		xhr.send(params);
	}

	function responseMessage(response, fallback) {
		if (response && response.data && response.data.message) {
			return response.data.message;
		}
		return fallback;
	}

	ready(function () {
		document.addEventListener('click', function (e) {
			var button = e.target.closest ? e.target.closest('.qp-button') : null;
			if (button) {
				e.preventDefault();
				var dropdown = button.closest('[data-qp-dropdown]');
				if (!dropdown) {
					return;
				}
				if (isOpen(dropdown)) {
					closeDropdown(dropdown, false);
				} else {
					openDropdown(dropdown);
				}
				return;
			}

			var homepageLink = e.target.closest ? e.target.closest('.quickpick-set-homepage') : null;
			if (homepageLink) {
				e.preventDefault();
				var pageId = homepageLink.getAttribute('data-page-id');
				var nonce = homepageLink.getAttribute('data-nonce');
				var label = homepageLink.querySelector('.quickpick-label');
				var originalText = label ? label.textContent : '';
				if (label) {
					label.textContent = QuickPickHomepage.settingText;
				}
				homepageLink.style.pointerEvents = 'none';

				postForm(
					QuickPickHomepage.ajaxUrl,
					'action=quickpick_set_homepage&page_id=' + encodeURIComponent(pageId) + '&nonce=' + encodeURIComponent(nonce),
					function (response) {
						if (response && response.success) {
							showAdminNotice(responseMessage(response, QuickPickHomepage.successText), 'success');
							window.setTimeout(function () {
								window.location.href = QuickPickHomepage.successRedirect;
							}, 800);
						} else {
							showAdminNotice(responseMessage(response, QuickPickHomepage.errorGenericText), 'error');
							if (label) {
								label.textContent = originalText;
							}
							homepageLink.style.pointerEvents = 'auto';
						}
					},
					function () {
						showAdminNotice(QuickPickHomepage.errorGenericText, 'error');
						if (label) {
							label.textContent = originalText;
						}
						homepageLink.style.pointerEvents = 'auto';
					}
				);
				return;
			}

			if (!e.target.closest || !e.target.closest('[data-qp-dropdown]')) {
				closeAll(null);
			}
		});

		document.addEventListener('keydown', function (e) {
			var dropdown = e.target.closest ? e.target.closest('[data-qp-dropdown]') : null;
			var key = e.key;

			if ('Escape' === key) {
				if (dropdown && isOpen(dropdown)) {
					e.preventDefault();
					closeDropdown(dropdown, true);
				} else {
					closeAll(null);
				}
				return;
			}

			if (!dropdown) {
				return;
			}

			var button = dropdown.querySelector('.qp-button');
			var links = getMenuLinks(dropdown);
			var onButton = button && (e.target === button || button.contains(e.target));

			if (onButton && ('ArrowDown' === key || 'ArrowUp' === key || 'Home' === key || 'End' === key)) {
				e.preventDefault();
				if (!isOpen(dropdown)) {
					openDropdown(dropdown);
					links = getMenuLinks(dropdown);
				}
				if (!links.length) {
					return;
				}
				if ('ArrowUp' === key || 'End' === key) {
					links[links.length - 1].focus();
				} else {
					links[0].focus();
				}
				return;
			}

			if (!isOpen(dropdown) || !links.length) {
				return;
			}

			var current = e.target.closest ? e.target.closest('a') : e.target;
			var index = links.indexOf(current);
			if (index < 0) {
				return;
			}

			if ('ArrowDown' === key) {
				e.preventDefault();
				links[(index + 1) % links.length].focus();
			} else if ('ArrowUp' === key) {
				e.preventDefault();
				links[(index - 1 + links.length) % links.length].focus();
			} else if ('Home' === key) {
				e.preventDefault();
				links[0].focus();
			} else if ('End' === key) {
				e.preventDefault();
				links[links.length - 1].focus();
			} else if ('Tab' === key && !e.shiftKey && index === links.length - 1) {
				closeDropdown(dropdown, false);
			} else if ('Tab' === key && e.shiftKey && 0 === index) {
				closeDropdown(dropdown, false);
			}
		});
	});
})();
