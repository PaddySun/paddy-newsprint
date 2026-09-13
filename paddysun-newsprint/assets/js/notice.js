/**
 * 通用提醒确认：只增强前台标记的弹窗，不在编辑器画布中运行。
 * 每页仅开启第一个实例；关闭（含 Esc/遮罩）后当前标签页会话不再提醒。
 * 这是阅读提醒，不是法律同意或业务操作确认。无 JS 时 dialog 保持关闭。
 */
(function () {
	'use strict';
	function install() {
		if (document.body && (document.body.classList.contains('wp-admin') || document.body.classList.contains('editor-styles-wrapper'))) return;
		var d = document.querySelector('dialog.np-notice[data-np-notice-front="1"]');
		if (!d || !d.isConnected || d.hasAttribute('data-np-notice-ready') || typeof d.showModal !== 'function') return;
		d.setAttribute('data-np-notice-ready', '1');
		var key = 'npNoticeClosed';
		try {
			if (window.sessionStorage.getItem(key)) return;
		} catch (e) {} // 存储不可用时仍允许阅读和关闭。
		d.addEventListener('close', function () {
			try { window.sessionStorage.setItem(key, '1'); } catch (e) {}
		});
		d.addEventListener('click', function (e) {
			var target = e.target;
			var control = target && target.closest && target.closest('button.np-notice__close, .np-notice__close .wp-block-button__link, a.np-notice__close');
			var rect = d.getBoundingClientRect();
			var backdrop = target === d && (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom);
			if (control || backdrop) {
				e.preventDefault();
				d.close();
			}
		});
		if (!d.open) {
			try { d.showModal(); } catch (e) { return; }
		}
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', install, { once: true });
	} else {
		install();
	}
})();
