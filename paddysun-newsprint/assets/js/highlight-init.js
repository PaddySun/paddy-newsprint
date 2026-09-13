/**
 * 代码高亮：ascetic（灰度）主题贴合新闻纸。
 * mermaid 代码块在服务端已被转换为图表占位容器，不会进入此处。
 */
(function () {
	'use strict';
	function init() {
		if (!window.hljs) return;
		document.querySelectorAll('pre:not(.language-mermaid) code:not(.language-mermaid)').forEach(function (block) {
			window.hljs.highlightElement(block);
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
