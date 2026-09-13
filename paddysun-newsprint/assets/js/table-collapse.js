/**
 * 移动端宽表自动收起（V-09）：≤760px 且表格实际溢出容器时，
 * 收起为「展开全表」切换按钮；展开后恢复横向滚动。
 * 仅在正文含 <table> 的文章页加载；无 JS 时保持横向滚动（渐进增强）。
 */
(function () {
	'use strict';

	if (!window.matchMedia || !window.matchMedia('(max-width: 760px)').matches) return;

	var L = window.PADDYSUN_TABLE || {};
	var expandLabel = L.expand || '展开';
	var foldLabel = L.fold || '收起';

	var wraps = document.querySelectorAll('.np-table-wrap');
	Array.prototype.forEach.call(wraps, function (wrap) {
		// 表格实际宽度 vs 容器宽度（figure.wp-block-table 自带 overflow-x:auto，
		// 溢出发生在内层，wrap.scrollWidth 不反映真实溢出）
		var table = wrap.querySelector('table');
		if (!table || table.offsetWidth <= wrap.clientWidth + 8) return;
		var prev = wrap.previousElementSibling;
		if (prev && prev.classList && prev.classList.contains('np-table-toggle')) return;

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'np-table-toggle';
		btn.setAttribute('aria-expanded', 'false');
		btn.textContent = expandLabel;

		wrap.parentNode.insertBefore(btn, wrap);
		wrap.classList.add('is-collapsed');

		btn.addEventListener('click', function () {
			var folded = wrap.classList.toggle('is-collapsed');
			btn.setAttribute('aria-expanded', folded ? 'false' : 'true');
			btn.textContent = folded ? expandLabel : foldLabel;
		});
	});
})();
