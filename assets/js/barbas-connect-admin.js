(function () {
	'use strict';

	/**
	 * Show-once flash notices: strip bc_notice / bc_id / bc_msg from the URL
	 * after the page has rendered so refresh does not re-display them.
	 */
	function stripFlashQueryArgs() {
		if (!window.history || !window.history.replaceState) {
			return;
		}
		try {
			var url = new URL(window.location.href);
			var keys = ['bc_notice', 'bc_id', 'bc_msg'];
			var changed = false;
			keys.forEach(function (key) {
				if (url.searchParams.has(key)) {
					url.searchParams.delete(key);
					changed = true;
				}
			});
			if (!changed) {
				return;
			}
			var next = url.pathname + (url.search ? url.search : '') + url.hash;
			window.history.replaceState({}, document.title, next);
		} catch (e) {
			/* ignore */
		}
	}

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.position = 'absolute';
			ta.style.left = '-9999px';
			document.body.appendChild(ta);
			ta.select();
			try {
				document.execCommand('copy');
				document.body.removeChild(ta);
				resolve();
			} catch (err) {
				document.body.removeChild(ta);
				reject(err);
			}
		});
	}

	document.addEventListener('click', function (event) {
		var btn = event.target.closest('.barbas-connect-copy');
		if (!btn) {
			return;
		}
		event.preventDefault();
		var id = btn.getAttribute('data-target');
		var el = id ? document.getElementById(id) : null;
		if (!el) {
			return;
		}
		var text = (el.textContent || '').trim();
		var labels = window.barbasConnectAdmin || {};
		copyText(text)
			.then(function () {
				var prev = btn.textContent;
				btn.textContent = labels.copied || 'Copied!';
				btn.disabled = true;
				setTimeout(function () {
					btn.textContent = prev;
					btn.disabled = false;
				}, 1600);
			})
			.catch(function () {
				window.alert(labels.copyFail || 'Could not copy.');
			});
	});

	stripFlashQueryArgs();
})();
