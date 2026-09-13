/**
 * KaTeX 自动渲染：识别 $...$ / $$...$$ / \(...\) / \[...\]。
 * 仅在正文含公式时由主题载入（正文容器 .np-content）。
 */
(function () {
	'use strict';
	function init() {
		if (!window.renderMathInElement) return;
		window.renderMathInElement(document.querySelector('.np-content') || document.body, {
			delimiters: [
				{ left: '$$', right: '$$', display: true },
				{ left: '\\[', right: '\\]', display: true },
				{ left: '$', right: '$', display: false },
				{ left: '\\(', right: '\\)', display: false }
			],
			throwOnError: false
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
