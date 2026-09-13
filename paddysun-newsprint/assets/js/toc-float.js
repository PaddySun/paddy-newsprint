/**
 * 悬浮目录滚动跟随（scroll-spy）：目录高亮「当前阅读章节」。
 * 仅在文章页渲染出 np-toc--float 时由 PHP 按需加载。
 * 键盘：目录项为原生锚点链接，Tab 可达；aria-current 向读屏播报当前章。
 * 0.7.1 站长要求：armed 类启用「下滚一屏才浮现」（scrollY ≥ 视口高），
 * 渐进增强——无 JS 时 CSS 不进 armed 分支，目录保持常显。
 */
(function () {
	'use strict';

	var nav = document.querySelector('.np-toc--float');
	if (!nav) return;

	var links = Array.prototype.slice.call(nav.querySelectorAll('a[href^="#"]'));
	if (!links.length) return;

	var targets = [];
	links.forEach(function (a) {
		var id;
		try {
			id = decodeURIComponent(a.getAttribute('href').slice(1));
		} catch (e) {
			return; // 孤立 % 等非法转义：跳过该锚点，不中断整个 scroll-spy（审查 D-F10）
		}
		var h = id && document.getElementById(id);
		if (h) targets.push({ link: a, heading: h });
	});
	if (!targets.length) return;

	nav.classList.add('np-toc--armed');
	var vh = window.innerHeight;
	var current = -1;

	function setCurrent(idx) {
		if (idx === current) return;
		current = idx;
		targets.forEach(function (t, i) {
			var on = i === idx;
			t.link.classList.toggle('is-current', on);
			if (on) {
				t.link.setAttribute('aria-current', 'true');
			} else {
				t.link.removeAttribute('aria-current');
			}
		});
		// 当前项滚进目录可视区（目录自身可能超高滚动）
		if (idx >= 0 && nav.scrollHeight > nav.clientHeight) {
			targets[idx].link.scrollIntoView({ block: 'nearest' });
		}
	}

	function update() {
		// 下滚一屏才浮现，回顶即隐（过渡由 CSS 走动效令牌）
		nav.classList.toggle('is-on', window.scrollY >= vh);
		var line = 110; // 与 CSS top 对齐的「阅读线」
		var idx = 0;
		for (var i = 0; i < targets.length; i++) {
			if (targets[i].heading.getBoundingClientRect().top <= line) idx = i;
		}
		// 全部标题都在阅读线之上（文末）时保持最后一章
		setCurrent(idx);
	}

	var ticking = false;
	window.addEventListener(
		'scroll',
		function () {
			if (ticking) return;
			ticking = true;
			window.requestAnimationFrame(function () {
				update();
				ticking = false;
			});
		},
		{ passive: true }
	);
	window.addEventListener('resize', function () {
		vh = window.innerHeight;
		update();
	}, { passive: true });
	update();
})();
