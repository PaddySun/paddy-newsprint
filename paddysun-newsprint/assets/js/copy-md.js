/**
 * 一键复制本文 Markdown。
 * 双轨：原文存档（REST origin=stored）优先；无存档由服务端转换（origin=converted）。
 * 全部工作在服务端完成，前端仅取结果写入剪贴板。
 */
(function () {
	'use strict';

	// 块主题：按钮是带 np-copymd 类的核心「按钮」区块（<a>）；兼容旧的 id 写法。
	var btn = document.querySelector('.np-copymd .wp-block-button__link') || document.getElementById('paddysun-copymd');
	if (!btn || !window.PADDYSUN_MD) return;

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

	function setBusy(busy) {
		btn.disabled = busy;
		btn.setAttribute('aria-disabled', busy ? 'true' : 'false');
		btn.classList.toggle('is-busy', busy);
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

	btn.addEventListener('click', function (e) {
		e.preventDefault();
		if (btn.classList.contains('is-busy')) return;
		setBusy(true);
		var label = btn.textContent;
		btn.textContent = window.PADDYSUN_MD.copying || '…';

		window.fetch(window.PADDYSUN_MD.restUrl, { credentials: 'same-origin' })
			.then(function (res) {
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.json();
			})
			.then(function (data) {
				var md = (data.markdown || '').replace(/\s+$/, '\n');
				var title = data.title ? '# ' + data.title + '\n\n' : '';
				var full = title + md;
				if (navigator.clipboard && navigator.clipboard.writeText) {
					return navigator.clipboard.writeText(full);
				}
				if (!fallbackCopy(full)) throw new Error('copy failed');
				return true;
			})
			.then(function () {
				toast(window.PADDYSUN_MD.done || 'OK');
			})
			.catch(function () {
				toast(window.PADDYSUN_MD.failed || 'ERROR');
			})
			.then(function () {
				setBusy(false);
				btn.textContent = label;
			});
	});
})();
