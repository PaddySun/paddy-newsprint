/**
 * 评论折叠（F-04）：任一评论的直接子回复超过 2 条时，第 3 条起折叠，
 * 点击「展开 N 条来信」显示全部。评论开放**或**存在评论的文章页加载
 * （审查 C-F03）；无 JS 时全部展开。
 * 结构依据：核心评论列表为嵌套 <ol class="wp-block-comment-template">，
 * 每个 li 是一条评论，其内层 ol 是该评论的回复串。
 * 选择器用「ol 的后代 ol」——只折叠回复串；一级评论列表自身永不折叠
 * （0.7.0 修复：此前 .np-comments ol 会把第 3 条起的一级评论也折进按钮）。
 */
(function () {
	'use strict';

	var L = window.PADDYSUN_COMMENTS || {};
	var expandLabel = L.expand || '展开{n}条来信';
	var foldLabel = L.fold || '收起来信';

	document.querySelectorAll('.np-comments ol ol').forEach(function (ol) {
		var lis = Array.prototype.filter.call(
			ol.children,
			function (li) { return li.tagName === 'LI'; }
		);
		if (lis.length <= 2) return;

		var extras = lis.slice(2);
		var n = extras.length;
		extras.forEach(function (li) { li.classList.add('np-reply--folded'); });

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'np-replies-toggle';
		btn.setAttribute('aria-expanded', 'false');
		btn.textContent = expandLabel.replace('{n}', String(n));
		lis[1].after(btn);

		btn.addEventListener('click', function () {
			var folded = extras[0].classList.contains('np-reply--folded');
			extras.forEach(function (li) { li.classList.toggle('np-reply--folded', !folded); });
			btn.setAttribute('aria-expanded', folded ? 'true' : 'false');
			btn.textContent = folded ? foldLabel : expandLabel.replace('{n}', String(n));
		});
	});
})();
