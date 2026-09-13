/**
 * 长内容折叠（0.10.0 U2）：超高内容才限高预览 + 展开/收起按钮。
 *
 * 协议：
 *  - 候选：.np-code-wrap 内的 pre（core/code）、裸 pre.wp-block-preformatted、
 *    .np-mermaid（渲染成功或失败回退态）。
 *  - 仅在内容实际超高时启用（代码 >480px 折为 320px；Mermaid >70vh 折为 45vh），
 *    超宽但不高的内容继续横向滚动，不受影响。
 *  - 渐进增强：无 JS 不加类、全文可读；打印时 CSS 强制展开、隐藏按钮。
 *  - Mermaid 在成功渲染（或失败回退）后经 np-mermaid:rendered 事件重测；
 *    失败回退容器（np-mermaid--failed，保留 data-src）另由类名扫描兜底，
 *    两脚本间事件时序契约见 evaluateAll() 内注释（R1-4）。
 *    渲染前绝不隐藏容器；字体加载与窗口变化后重测；
 *    不重复插按钮，用户手动展开后不再收起（data-np-fold-user）。
 *  - 原生 button + aria-expanded/aria-controls；无高度过渡（不做布局抖动）。
 */
(function () {
	'use strict';

	var L = window.PADDYSUN_FOLD || {};
	var EXPAND_LABEL = L.expand || '展开';
	var COLLAPSE_LABEL = L.collapse || '收起';

	var CODE_TRIGGER = 480;   // px：超过才折叠
	var CODE_MAX = 320;       // px：折叠态保留高度
	var MERMAID_TRIGGER = 0.7; // × 视口高
	var MERMAID_MAX = 0.45;    // × 视口高
	var TOLERANCE = 8;        // px：滚动高度容差
	var seq = 0;

	function maxFor(el, kind) {
		if (kind === 'mermaid') {
			return Math.round(window.innerHeight * MERMAID_MAX) + 'px';
		}
		return CODE_MAX + 'px';
	}

	function ensureId(el) {
		if (!el.id) {
			el.id = 'np-fold-content-' + (++seq);
		}
		return el.id;
	}

	/* 0.10.5 站长反馈：按钮悬浮在内容内部底部（图片上方内缘）。
	 * img 是置换元素不能包含子节点——包一层 span 宿主；其余候选直接
	 * 在自身上加 np-fold-host。折叠态按钮绝对定位于宿主内底（overlay），
	 * 展开态回归文档流（内容末尾）。 */
	function ensureHost(el) {
		if (el.tagName === 'IMG') {
			if (el.parentNode && el.parentNode.classList && el.parentNode.classList.contains('np-fold-host--wrap')) {
				return el.parentNode;
			}
			var wrap = document.createElement('span');
			wrap.className = 'np-fold-host np-fold-host--wrap';
			if (el.parentNode) {
				el.parentNode.insertBefore(wrap, el);
				wrap.appendChild(el);
			}
			return wrap;
		}
		el.classList.add('np-fold-host');
		return el;
	}

	function makeToggle(content) {
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'np-fold-toggle';
		btn.setAttribute('aria-expanded', 'true'); // 语义当前=已展开（视觉为展开态）
		btn.setAttribute('aria-controls', ensureId(content));
		btn.textContent = COLLAPSE_LABEL;
		btn.addEventListener('click', function () {
			var expanded = btn.getAttribute('aria-expanded') === 'true';
			if (expanded) {
				// 收起：撤销用户标记，恢复自动限高管理（视口变化重新计算限高）
				btn.setAttribute('aria-expanded', 'false');
				content.classList.add('np-fold--on');
				content.style.maxHeight = content.getAttribute('data-np-fold-max') || '';
				btn.textContent = EXPAND_LABEL;
				btn.classList.add('np-fold-toggle--overlay');
				content.removeAttribute('data-np-fold-user');
			} else {
				// 展开：置用户标记（R1-3，与文件头「用户手动展开后不再收起」对齐）——
				// 此后 evaluate() 不再收起、不再移除按钮
				btn.setAttribute('aria-expanded', 'true');
				content.classList.remove('np-fold--on');
				content.style.maxHeight = '';
				btn.textContent = COLLAPSE_LABEL;
				btn.classList.remove('np-fold-toggle--overlay');
				content.setAttribute('data-np-fold-user', '1');
			}
		});
		return btn;
	}

	/**
	 * 评估单个候选。kind: 'code' | 'mermaid'
	 */
	function evaluate(el, kind) {
		if (el.getAttribute('data-np-fold-ready') === '1' && el.getAttribute('data-np-fold-user') === '1') {
			return; // 用户已手动展开：不再收起
		}
		var trigger = kind === 'mermaid' ? window.innerHeight * MERMAID_TRIGGER : CODE_TRIGGER;
		var tall = el.scrollHeight > trigger + TOLERANCE;
		var btn = el.npFoldBtn;

		if (!tall) {
			// 内容不长（或窗口变宽后能容纳）：解除折叠
			if (btn) {
				if (el.getAttribute('data-np-fold-user') !== '1') {
					el.classList.remove('np-fold--on');
					el.style.maxHeight = '';
					btn.parentNode && btn.parentNode.removeChild(btn);
					el.npFoldBtn = null;
					el.removeAttribute('data-np-fold-ready');
				}
			}
			return;
		}

		if (!btn) {
			btn = makeToggle(el);
			el.npFoldBtn = btn;
			ensureHost(el).appendChild(btn);
		}
		var expanded = btn.getAttribute('aria-expanded') === 'true';
		if (el.getAttribute('data-np-fold-ready') !== '1') {
			// 首次折叠：默认收起
			btn.setAttribute('aria-expanded', 'false');
			btn.textContent = EXPAND_LABEL;
			el.setAttribute('data-np-fold-max', maxFor(el, kind));
			el.classList.add('np-fold--on');
			el.style.maxHeight = el.getAttribute('data-np-fold-max');
		} else if (!expanded) {
			// 仍处折叠态：随视口更新限高
			el.setAttribute('data-np-fold-max', maxFor(el, kind));
			el.style.maxHeight = el.getAttribute('data-np-fold-max');
		}
		el.setAttribute('data-np-fold-ready', '1');
		/* 折叠态=悬浮内容内底；展开态=文档流（按最终 aria 状态置类） */
		btn.classList.toggle('np-fold-toggle--overlay', btn.getAttribute('aria-expanded') === 'false');
	}

	function evaluateAll() {
		// 代码：np-code-wrap 内的 pre（core/code）与裸 preformatted
		var codes = document.querySelectorAll('.np-code-wrap > pre, pre.wp-block-preformatted');
		// 0.10.4 站长反馈：正文长图照长内容折叠处理（超 70vh 折为 45vh）
		var tallImgs = document.querySelectorAll('.np-content img, .wp-block-post-content img');
		Array.prototype.forEach.call(tallImgs, function (img) {
			evaluate(img, 'code');
		});
		Array.prototype.forEach.call(codes, function (pre) {
			evaluate(pre, 'code');
		});
		// Mermaid：已渲染成功（data-src 已移除）或失败回退态（np-mermaid--failed，
		// 保留 data-src）都纳入扫描（R1-4）。失败容器不依赖 data-src 消失——
		// 若 np-mermaid:rendered 事件先于本脚本监听安装而丢失（两脚本均为
		// footer 脚本、DOMContentLoaded 后各自 install，时序无保证），类名扫描兜底。
		// 事件时序契约（与 mermaid-init.js 共同遵守）：
		//  1) 渲染前容器保持 data-src 且无 np-mermaid--failed，本脚本绝不折叠；
		//  2) 每个容器的终态（渲染成功 / 白名单拒绝 / 解析失败）必然满足
		//     「data-src 已移除」或「np-mermaid--failed 已加」至少其一，
		//     并派发一次 np-mermaid:rendered（bubbles）；
		//  3) 本脚本对事件监听与类名扫描双通道覆盖，二者幂等。
		var mermaids = document.querySelectorAll('.np-mermaid:not([data-src]), .np-mermaid.np-mermaid--failed');
		Array.prototype.forEach.call(mermaids, function (el) {
			evaluate(el, 'mermaid');
		});
	}

	function install() {
		evaluateAll();
		if (document.fonts && document.fonts.ready && document.fonts.ready.then) {
			document.fonts.ready.then(function () { evaluateAll(); });
		}
		// Mermaid 渲染完成 / 失败回退后重测（mermaid-init.js 派发）
		document.addEventListener('np-mermaid:rendered', function (ev) {
			var el = ev.target;
			if (el && el.classList && el.classList.contains('np-mermaid')) {
				evaluate(el, 'mermaid');
			}
		});
		// 窗口变化重测（rAF 节流）
		var ticking = false;
		window.addEventListener('resize', function () {
			if (ticking) return;
			ticking = true;
			window.requestAnimationFrame(function () {
				evaluateAll();
				ticking = false;
			});
		}, { passive: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', install);
	} else {
		install();
	}
})();
