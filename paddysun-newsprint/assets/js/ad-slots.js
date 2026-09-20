/**
 * 广告位运行时：失效清理 + 文章页侧栏定位。
 *
 * 加载条件由服务端决定（inc/ads.php）：首页仅首页槽位填码时；文章页槽位
 * 启用即加载（侧栏固定位需要定位逻辑）。
 *
 * 失效判定（超时检查点 = window.load + deadline，默认 2500ms，可过滤）：
 *  - 容器内任一脚本 error 事件（网络失败）；
 *  - 检查点时存在联盟写入的 data-ad-status="unfilled" 状态元素。
 * 成功（data-ad-status="filled" 或已渲染出 iframe）则不动作；
 * 自定义代码不含上述状态时主题无从判定，保持原样。
 * 动作按后台配置：换回占位（template 内的同一份占位标记，尺寸零跳动）
 * 或整块不输出（容器移除，为站长显式选择的语义）。
 *
 * 侧栏定位（≥1240px）：有悬浮目录时贴其下方并按需收窄目录上限，
 * 目录收起/展开、窗口缩放时重排；无目录时停留在目录的默认固定位。
 * 显隐节奏与悬浮目录一致：下滚一屏才浮现（有目录时跟随其类，无目录自判）。
 */
(function () {
	'use strict';

	var cfg = window.PADDYSUN_AD_SLOTS || {};
	var deadline = parseInt(cfg.deadline, 10) || 2500;
	var containers = Array.prototype.slice.call(document.querySelectorAll('.np-ad[data-np-ad]'));
	if (!containers.length) {
		return;
	}

	var mqWide = window.matchMedia('(min-width: 1240px)');

	/* ---- 失效清理 ---- */

	function swapToPlaceholder(cont) {
		var tpl = cont.querySelector('template.np-ad__ph-tpl');
		var code = cont.querySelector('.np-ad__code');
		if (!tpl || !code) {
			return;
		}
		cont.insertBefore(tpl.content.cloneNode(true), code);
		code.remove();
	}

	function fail(cont) {
		if ('remove' === cfg.cleanup) {
			cont.remove();
		} else {
			swapToPlaceholder(cont);
		}
	}

	function inspect(cont) {
		if (cont.querySelector('[data-ad-status="unfilled"]')) {
			return true;
		}
		if ('1' === cont.getAttribute('data-np-script-error')) {
			return true;
		}
		return false;
	}

	/* 检查点二次探测：容器内脚本可能在监听器挂上之前就已加载失败
	   （正文脚本先于页脚脚本解析，快失败场景 error 事件错过）。
	   对每个 script[src] 重新发起 no-cors fetch：连接层拒绝/失败即判失败；
   能取回（含不透明响应）视为可达，交由状态标记决定。 */
	function probeScripts(cont) {
		var srcs = Array.prototype.slice.call(cont.querySelectorAll('script[src]'))
			.map(function (s) { return s.getAttribute('src'); })
			.filter(function (src) { return !!src && 0 !== src.indexOf('data:'); });
		if (!srcs.length || !window.fetch) {
			return Promise.resolve(false);
		}
		var probes = srcs.map(function (src) {
			return new Promise(function (resolve) {
				try {
					fetch(src, { method: 'GET', mode: 'no-cors', cache: 'no-store' })
						.then(function () { resolve(false); }, function () { resolve(true); });
				} catch (e) {
					resolve(true);
				}
			});
		});
		return Promise.all(probes).then(function (bad) {
			return -1 !== bad.indexOf(true);
		});
	}

	/* ---- 文章页侧栏：定位 + 显隐节奏 + 小视口整块摘除 ---- */

	function setupRail(cont) {
		var toc = document.querySelector('.np-toc--float');
		var GAP = 12;
		var TOP = 110;
		var TOC_MAX = 540;
		var anchorParent = cont.parentNode;
		var anchorNext = cont.nextSibling;

		/* 桌面端专属槽位：小视口整块摘除（仅 CSS 隐藏的话，隐藏态里的
		   广告代码仍会执行/请求）；回到桌面视口按原位回插。 */
		function syncViewport() {
			if (mqWide.matches) {
				if (!cont.parentNode && anchorParent) {
					anchorParent.insertBefore(cont, anchorNext);
				}
			} else if (cont.parentNode) {
				cont.parentNode.removeChild(cont);
			}
		}

		function layout() {
			if (!cont.parentNode) {
				return; /* 已摘除：无几何可排 */
			}
			if (!mqWide.matches) {
				cont.style.top = '';
				if (toc) {
					toc.style.maxHeight = '';
				}
				return;
			}
			if (!toc) {
				return; /* 替代目录位：CSS 默认 top 即目录位。 */
			}
			var avail = window.innerHeight - TOP - GAP - 16;
			var maxToc = Math.max(120, Math.min(TOC_MAX, avail - cont.offsetHeight));
			toc.style.maxHeight = maxToc + 'px';
			cont.style.top = (TOP + Math.min(toc.offsetHeight, maxToc) + GAP) + 'px';
		}

		cont.classList.add('np-ad--armed');
		if (toc) {
			/* 跟随悬浮目录的浮现节奏（toc-float.js 控制目录的 is-on）。 */
			var sync = function () {
				cont.classList.toggle('np-ad--on', toc.classList.contains('is-on'));
			};
			if (window.MutationObserver) {
				new MutationObserver(sync).observe(toc, { attributes: true, attributeFilter: ['class'] });
			}
			sync();
			var details = toc.querySelector('details');
			if (details) {
				details.addEventListener('toggle', layout);
			}
		} else {
			var onScroll = function () {
				cont.classList.toggle('np-ad--on', window.scrollY >= window.innerHeight);
			};
			window.addEventListener('scroll', onScroll, { passive: true });
			onScroll();
		}
		syncViewport();
		layout();
		var onResize = function () {
			syncViewport();
			layout();
		};
		window.addEventListener('resize', onResize);
		if (mqWide.addEventListener) {
			mqWide.addEventListener('change', onResize);
		} else if (mqWide.addListener) {
			mqWide.addListener(onResize);
		}
	}

	/* ---- 装配 ---- */

	containers.forEach(function (cont) {
		if (cont.classList.contains('np-ad--rail')) {
			setupRail(cont);
		}
		var code = cont.querySelector('.np-ad__code');
		if (!code) {
			return; /* 占位态：无失效事务。 */
		}
		cont.addEventListener(
			'error',
			function (e) {
				var t = e.target;
				if (t && ('SCRIPT' === t.tagName || 'IMG' === t.tagName)) {
					cont.setAttribute('data-np-script-error', '1');
				}
			},
			true
		);
		var check = function () {
			window.setTimeout(function () {
				if (inspect(cont)) {
					fail(cont);
					return;
				}
				probeScripts(cont).then(function (bad) {
					if (bad) {
						fail(cont);
					}
				});
			}, deadline);
		};
		if ('complete' === document.readyState) {
			check();
		} else {
			window.addEventListener('load', check);
		}
	});
})();
