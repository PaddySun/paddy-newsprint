/**
 * 代码块一键复制（V-10）：.np-code-copy 按钮把兄弟 <pre><code> 文本
 * 写入剪贴板。与「复制 MD」共用 np-toast 提示样式。
 * 仅在正文含（非 mermaid）代码块的文章页加载。
 */
(function () {
	'use strict';

	var L = window.PADDYSUN_CODE || {};
	var doneLabel = L.done || '代码已复制';
	var failedLabel = L.failed || '复制失败，请重试';

	var toastEl = null;
	function toast(msg) {
		if (!toastEl) {
			toastEl = document.createElement('div');
			toastEl.className = 'np-toast';
			toastEl.setAttribute('role', 'status');
			document.body.appendChild(toastEl);
		}
		toastEl.textContent = msg;
		toastEl.classList.add('is-visible');
		window.setTimeout(function () {
			toastEl.classList.remove('is-visible');
		}, 2000);
	}

	function fallbackCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'fixed';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		var ok = false;
		try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
		document.body.removeChild(ta);
		return ok;
	}

	Array.prototype.forEach.call(
		document.querySelectorAll('.np-code-copy'),
		function (btn) {
			btn.addEventListener('click', function () {
				var wrap = btn.closest('.np-code-wrap');
				var code = wrap && wrap.querySelector('pre code');
				if (!code) return;
				var text = code.textContent;

				function done(ok) {
					if (ok) toast(doneLabel);
					else toast(failedLabel);
				}

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(
						function () { done(true); },
						function () { done(fallbackCopy(text)); }
					);
				} else {
					done(fallbackCopy(text));
				}
			});
		}
	);
})();
