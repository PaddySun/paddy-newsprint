/**
 * 正文图片／视频加载失败时，随机选取作品图片及作品名作为回退。
 * 只显示失效提示，不创建作品介绍链接；旧配置的 url 字段不使用。
 *
 * 边界：
 *  - 仅处理文章／页面正文容器内的 img 和 video[src]（.np-content / .wp-block-post-content）；
 *    data: URI（本地头像）、88×31 按钮、用户标记 data-np-keep 的图不动。
 *  - 不为检测而触发懒加载：只监听 error + 捕捉监听安装前已失败的图。
 *  - 替换时清 srcset/sizes、移除 picture 内 source，避免再次命中坏候选；
 *    保留原图 alt 说明，视频回退图使用作品 alt，另加含作品名的纯文字提示；
 *    不创建锚点，不篡改原文链接目标。
 *  - 替换图再次失败降级为文字提示，不循环重试。
 *  - 不使用内联 onerror；文案经 textContent 写入。
 */
(function () {
	'use strict';

	var CFG = window.PADDYSUN_ARTWORK || {};
	var ITEMS = CFG.items || [];
	if (!ITEMS.length) return;

	var HANDLED = 'data-np-artwork';

	function pickItem() {
		// 每张失败图独立随机一组；选定后（页面内）保持稳定
		return ITEMS[Math.floor(Math.random() * ITEMS.length)];
	}

	function isExcluded(img) {
		if (img.closest && img.closest('[data-np-keep]')) return true;
		/* 友链头像：属于站点标识类图标（stage6-plan §6.2 排除项），
		   失效时交给友链页自身的占位/自托管策略，不换成作品 */
		if (img.closest && img.closest('.np-link-card__avatar')) return true;
		var src = img.getAttribute('src') || '';
		if (src.indexOf('data:') === 0) return true; // 本地头像等
		// 88×31 按钮（原尺寸属性或当前渲染尺寸）
		var w = img.getAttribute('width');
		var h = img.getAttribute('height');
		if (w === '88' && h === '31') return true;
		var box = img.getBoundingClientRect();
		if (box.width > 0 && Math.round(box.width) === 88 && Math.round(box.height) === 31) return true;
		/* 0.10.5 站长反馈：小图（头像/徽章尺度，≤64px）不参与作品替换——
		   失联友链小图换 1600×900 作品图会破坏版面（links 页实测） */
		if (box.width > 0 && box.width <= 64 && box.height <= 64) return true;
		return false;
	}

	/* 保持占位宽高，减少替换时的布局跳动。
	   仅当原位是真实图槽（渲染高度可感知）才固定；失效图塌缩成
	   alt 文字小盒（高约一行）时不固定，让替换图按原尺寸展示。 */
	function pinSize(img) {
		if (img.getAttribute('width') && img.getAttribute('height')) return;
		var box = img.getBoundingClientRect();
		if (box.width > 1 && box.height > 60) {
			img.style.width = Math.round(box.width) + 'px';
			img.style.height = Math.round(box.height) + 'px';
			return;
		}
		img.style.maxWidth = '100%';
		img.style.height = 'auto';
	}

	function buildNote(item) {
		var note = document.createElement('p');
		note.className = 'np-img-fallback-note';
		var title = item.title || item.alt || '';
		note.textContent = (CFG.notice || '原图未能加载 · 已替换为作品《%s》').replace('%s', title);
		return note;
	}

	function replace(img) {
		if (img.getAttribute(HANDLED)) return;
		if (isExcluded(img)) return;
		img.setAttribute(HANDLED, '1');

		var item = pickItem();
		pinSize(img);

		/* 清掉可能再次命中坏候选的响应式候选源 */
		img.removeAttribute('srcset');
		img.removeAttribute('sizes');
		var pic = img.closest && img.closest('picture');
		if (pic) {
			var sources = pic.querySelectorAll('source');
			for (var i = 0; i < sources.length; i++) {
				sources[i].parentNode.removeChild(sources[i]);
			}
		}

		/* 含作品名的纯文字失效提示，不添加介绍链接 */
		var note = buildNote(item);
		var anchor = img.parentNode && img.parentNode.classList && img.parentNode.classList.contains('wp-block-image')
			? img.parentNode : img;
		if (anchor.insertAdjacentElement) {
			anchor.insertAdjacentElement('afterend', note);
		} else if (anchor.parentNode) {
			anchor.parentNode.insertBefore(note, anchor.nextSibling);
		}

		/* 二次失败：降级文字，不循环重试 */
		img.addEventListener('error', function () {
			img.style.display = 'none';
			note.classList.add('np-img-fallback-note--text');
		}, { once: true });

		img.style.objectFit = 'cover';
		img.src = item.image;
	}

	function check(img) {
		/* 监听安装前已失败的图：complete 且 naturalWidth=0 */
		if (img.complete && img.naturalWidth === 0 && (img.getAttribute('src') || '').length) {
			replace(img);
		}
	}

	/* 0.10.4 站长反馈：视频资源失效同走失效政策——换作品图 + 提示，
	 * 与失效图同一条配对链路（页内稳定、二次失败降级文字）。 */
	function replaceVideo(video) {
		if (video.getAttribute(HANDLED)) return;
		video.setAttribute(HANDLED, '1');
		var item = pickItem();
		var img = document.createElement('img');
		img.alt = item.alt || '';
		img.style.maxWidth = '100%';
		img.style.height = 'auto';
		var note = buildNote(item);
		/* 替换图再次失败：降级文字，不循环重试 */
		img.addEventListener('error', function () {
			img.style.display = 'none';
			note.classList.add('np-img-fallback-note--text');
		}, { once: true });
		video.parentNode.insertBefore(note, video.nextSibling);
		video.parentNode.insertBefore(img, video);
		if (video.parentNode) video.parentNode.removeChild(video);
		img.src = item.image; // 先安装二次失败监听，再开始加载回退图
	}

	function install() {
		var scope = document.querySelectorAll('.np-content img, .wp-block-post-content img');
		Array.prototype.forEach.call(scope, function (img) {
			if (img.getAttribute(HANDLED)) return;
			img.addEventListener('error', function (ev) {
				replace(ev.target || img);
			});
			check(img);
		});
		var videos = document.querySelectorAll('.np-content video[src], .wp-block-post-content video[src]');
		Array.prototype.forEach.call(videos, function (video) {
			if (video.getAttribute(HANDLED)) return;
			video.addEventListener('error', function () {
				replaceVideo(video);
			});
			/* 监听安装前已失败：error 对象已置位，或无可用码流 */
			if (video.error || (video.readyState === 0 && video.networkState === 3)) {
				replaceVideo(video);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', install);
	} else {
		install();
	}
})();
