/**
 * Mermaid 渲染：把服务端生成的 .np-mermaid 占位容器（data-src 存源码）
 * 渲染为 SVG。失败时保留原始代码回退显示。
 * neutral 灰度主题贴合新闻纸。
 *
 * 安全边界（0.9.1 站长裁决）：
 *  - securityLevel 固定 strict；
 *  - 图表类型白名单——gantt / xychart / radar / architecture 等公告命中或
 *    高资源消耗图种直接走失败回退（占位容器显示原始源码），不进渲染器。
 *
 * 与 content-fold.js 的事件时序契约（R1-4，双方注释同步）：
 *  - 每个容器的终态（渲染成功 / 白名单拒绝 / 解析失败）必然满足
 *    「data-src 已移除」或「np-mermaid--failed 已加」至少其一，
 *    并派发一次 np-mermaid:rendered（bubbles）；
 *  - 失败回退态保留 data-src 只显示原始源码，由 fold 侧以
 *    .np-mermaid--failed 类名扫描兜底（事件先于 fold 监听安装而丢失时）；
 *  - 渲染前容器两标记皆无，fold 绝不折叠。
 */
(function () {
	'use strict';
	/* 两套命名空间分离（第四轮修复：勿混用——
	 * 1) 源码关键词：预检用，匹配用户书写的首词（graph/xxxDiagram/-v2 变体）；
	 * 2) parse() 的 diagramType：最终判定用，经 -vN 归一化后比对；
	 * 3) 允许集合本身不变：flowchart/sequence/class/state/er/mindmap/pie。 */
	var SOURCE_KEYWORDS = /^(flowchart|graph|sequencediagram|classdiagram|statediagram|erdiagram|mindmap|pie)(-v\d+)?\b/;
	var PARSE_TYPES = {
		flowchart: 1, sequence: 1, class: 1, state: 1, statediagram: 1, er: 1, mindmap: 1, pie: 1
	};
	var DIRECTIVE_RE = /%%\{[\s\S]*?%%\}/g;

	function precheckAllowed(src) {
		// 快速预检：剥离 directive（含多行）后首个有效词须为白名单源码关键词；
		// 最终判定以 parse() 的 diagramType 归一化结果为准
		var lines = String(src).replace(DIRECTIVE_RE, '').split('\n');
		for (var i = 0; i < lines.length; i++) {
			var line = lines[i].trim();
			if (!line || line.slice(0, 2) === '%%') continue;
			return SOURCE_KEYWORDS.test(line.toLowerCase().split(/\s+/)[0]);
		}
		return false;
	}

	function parseTypeAllowed(type) {
		// flowchart-v2 → flowchart；stateDiagram → statediagram；state → state
		return PARSE_TYPES[String(type).toLowerCase().replace(/-v\d+$/, '')] === 1;
	}

	/**
	 * 结构化 SVG 清洗（DOM 级，非正则——0.9.1 审查整改；第五轮修正解析模式）：
	 * 用 text/html 解析——Mermaid 产出的 SVG 内嵌 foreignObject/&nbsp; 等
	 * 非 XML 合法构造，严格 image/svg+xml 解析会 parsererror 误杀正常图
	 * （archives/32 实测）；HTML 解析器天然容错且 DOMParser 不执行脚本。
	 * 清理：无 svg 根即拒绝；移除 script 节点；移除根节点与全部子节点上的
	 * on* 事件属性与 javascript: 伪协议引用。
	 * foreignObject 保留：strict 模式下 mermaid 已对标签文本转义，
	 * 流程图 htmlLabels 依赖它承载文字，移除会破坏正常渲染。
	 */
	function sanitizeSvg(svgText) {
		var doc;
		try {
			doc = new DOMParser().parseFromString(svgText, 'text/html');
		} catch (e) {
			return null;
		}
		if (!doc) return null;
		var svg = doc.querySelector('svg');
		if (!svg) return null;
		var scripts = svg.querySelectorAll('script');
		for (var i = 0; i < scripts.length; i++) {
			scripts[i].parentNode && scripts[i].parentNode.removeChild(scripts[i]);
		}
		var all = [svg].concat(Array.prototype.slice.call(svg.querySelectorAll('*'))); // 含根节点（第三轮审查）
		for (var j = 0; j < all.length; j++) {
			var attrs = all[j].attributes;
			for (var k = attrs.length - 1; k >= 0; k--) {
				var name = attrs[k].name;
				var value = String(attrs[k].value);
				if (/^on/i.test(name) || /^javascript:/i.test(value.trim())) {
					all[j].removeAttribute(name);
				}
			}
		}
		return svg.outerHTML; // HTML 序列化：innerHTML 插入合法，保留 mermaid 结构
	}

	function init() {
		var nodes = document.querySelectorAll('.np-mermaid[data-src]');
		if (!nodes.length || !window.mermaid) return;

		window.mermaid.initialize({
			startOnLoad: false,
			securityLevel: 'strict',
			theme: 'base',
			themeVariables: {
				primaryColor: '#f4efe4',
				primaryTextColor: '#1c1a17',
				primaryBorderColor: '#4a463f',
				lineColor: '#4a463f',
				secondaryColor: '#ece6d6',
				tertiaryColor: '#f4efe4',
				fontFamily: '"Source Serif 4", "Noto Serif SC", "Songti SC", serif',
				fontSize: '15px'
			},
			flowchart: { curve: 'basis' }
		});

			Array.prototype.forEach.call(nodes, function (el, i) {
				var src = el.getAttribute('data-src');
				if (!precheckAllowed(src)) {
					el.classList.add('np-mermaid--failed');
					el.dispatchEvent(new CustomEvent('np-mermaid:rendered', { bubbles: true })); // 失败回退态也通知重测（长图折叠）
					return;
				}
				var id = 'np-mermaid-svg-' + i;
				/* 先 parse：以 Mermaid 自己识别的 diagramType 做最终白名单判定（归一化 -vN） */
				window.mermaid.parse(src).then(function (res) {
					if (!parseTypeAllowed(res && res.diagramType)) {
						el.classList.add('np-mermaid--failed');
						el.dispatchEvent(new CustomEvent('np-mermaid:rendered', { bubbles: true }));
						return null;
					}
					return window.mermaid.render(id, src);
				}).then(function (rendered) {
					if (!rendered) return; // 白名单拒绝，保持回退态
					var clean = sanitizeSvg(rendered.svg);
					if (!clean) {
						el.classList.add('np-mermaid--failed');
						// 契约要求每个终态必然派发一次（审查 C-F01/D-F1：此分支原漏发）
						el.dispatchEvent(new CustomEvent('np-mermaid:rendered', { bubbles: true }));
						return;
					}
					el.innerHTML = clean;
					el.removeAttribute('data-src');
					el.dispatchEvent(new CustomEvent('np-mermaid:rendered', { bubbles: true })); // 渲染后测量（U2 长图折叠）
				}).catch(function () {
					// 渲染失败：保留回退源码显示
					el.classList.add('np-mermaid--failed');
					el.dispatchEvent(new CustomEvent('np-mermaid:rendered', { bubbles: true }));
				});
			});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
