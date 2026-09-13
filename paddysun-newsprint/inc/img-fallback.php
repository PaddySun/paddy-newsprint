<?php
/**
 * 失效图片作品替换（0.10.0 U7 / 轨道 D-a）。
 *
 * 两阶段交付：本批交付机制与本地测试夹具；正式作品素材由站长
 * 在 assets/artwork-fallbacks.json 中录入后自动生效（D-b）。
 *
 * 行为约定（stage6-plan §6）：
 *  - 清单每项为「图片 URL ＋ 作品介绍 URL ＋ 替代文字／作品名」固定配对；
 *    失败时按图随机选一组，页面内保持稳定，不把作品 A 链接到作品 B。
 *  - 仅覆盖文章／页面正文图片；首页保持 0 JS。Logo、功能图标、
 *    评论头像（data: URI）、88×31 按钮不在替换范围。
 *  - 正常图片与原链接不变；替换失败二次出错降级文字，不循环重试。
 *  - URL scheme 白名单：显式 http/https 或站点相对路径（协议相对 //host/…
 *    一律拒绝，R1-1）；无内联 onerror，提示文案经 textContent 写入，
 *    无 HTML 注入面。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 读取并校验作品替换清单。
 *
 * 清单格式（assets/artwork-fallbacks.json）：
 * [ { "image": "https://…/artwork.webp", "url": "https://…/works/x", "alt": "…", "title": "作品名" }, … ]
 *
 * @return array[] 校验通过的配对列表。
 */
function paddysun_ns_artwork_fallbacks() {
	static $list = null;
	if ( null !== $list ) {
		return $list;
	}
	$list = array();
	$file = PADDYSUN_NS_DIR . '/assets/artwork-fallbacks.json';
	if ( is_readable( $file ) ) {
		$raw = json_decode( (string) file_get_contents( $file ), true );
		/**
		 * 允许测试夹具或其他集成点替换清单**来源**。
		 * 位于逐条校验之前：过滤进来的清单同样必须通过 scheme 白名单
		 * （审查 C-F16——原位置在校验后，替换来源可绕过 R1-1 白名单）。
		 *
		 * @param array[]|mixed $raw 原始清单数组（未校验）。
		 */
		$raw = apply_filters( 'paddysun_ns_artwork_fallback_list', $raw );
		if ( is_array( $raw ) ) {
			foreach ( $raw as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$image = isset( $entry['image'] ) ? trim( (string) $entry['image'] ) : '';
				$url   = isset( $entry['url'] ) ? trim( (string) $entry['url'] ) : '';
				$alt   = isset( $entry['alt'] ) ? (string) $entry['alt'] : '';
				$title = isset( $entry['title'] ) ? (string) $entry['title'] : '';
				if ( '' === $image || '' === $url ) {
					continue;
				}
				if ( ! paddysun_ns_artwork_url_allowed( $image ) || ! paddysun_ns_artwork_url_allowed( $url ) ) {
					continue;
				}
				$list[] = array(
					'image' => esc_url_raw( $image ),
					'url'   => esc_url_raw( $url ),
					'alt'   => $alt,
					'title' => $title,
				);
			}
		}
	}
	return $list;
}

/**
 * URL scheme 白名单：显式 http/https，或无 scheme 且无 host 的站点相对路径。
 *
 * 协议相对 URL（//host/…，R1-1）不带 scheme 但带 host，不属于相对路径——
 * 一律拒绝：清单 URL 必须显式 http/https，或纯站点相对路径。
 * 与导出方向 paddysun_ns_md_safe_url（html-to-markdown.php，放行协议相对）
 * 口径差异系威胁模型不同，有意为之（审查 D-F18）。
 *
 * @param string $url 待检 URL。
 * @return bool
 */
function paddysun_ns_artwork_url_allowed( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return false;
	}
	$parsed = parse_url( $url );
	if ( false === $parsed ) {
		return false; // 极端畸形输入 parse_url 返回 false（审查 D-F17 防御；PHP 8 下罕见）
	}
	if ( ! empty( $parsed['host'] ) && empty( $parsed['scheme'] ) ) {
		return false; // 协议相对 URL（//host/…）不在白名单内
	}
	$scheme = strtolower( (string) ( $parsed['scheme'] ?? '' ) );
	if ( '' === $scheme ) {
		return true; // 相对路径（本地优先）
	}
	return in_array( $scheme, array( 'http', 'https' ), true );
}

/**
 * 条件加载替换脚本：仅单篇正文含 <img> 且清单非空时；
 * 密码文章不加载（与公开内容边界一致）；首页 / Feed 不涉及。
 *
 * 首页排除（R1-2）必须显式：is_front_page() 覆盖「文章列表」与
 * 「静态页」两种形态（show_on_front=page 时静态首页同样 is_singular()，
 * 且其正文可能含 <img>——不显式排除会破坏「首页 0 JS」基线）。
 */
function paddysun_ns_maybe_enqueue_img_fallback() {
	if ( ! is_singular() || is_feed() || is_front_page() ) {
		return;
	}
	$post = get_post();
	if ( ! $post instanceof WP_Post || '' !== $post->post_password ) {
		return;
	}
	if ( false === stripos( (string) $post->post_content, '<img' ) && false === stripos( (string) $post->post_content, '<video' ) ) {
		return;
	}
	$list = paddysun_ns_artwork_fallbacks();
	if ( ! $list ) {
		return; // 素材未录入：机制待命，不加载脚本
	}
	wp_enqueue_script(
		'paddysun-img-fallback',
		PADDYSUN_NS_URI . '/assets/js/img-fallback.js',
		array(),
		PADDYSUN_NS_VERSION,
		true
	);
	wp_localize_script(
		'paddysun-img-fallback',
		'PADDYSUN_ARTWORK',
		array(
			'items'  => $list,
			/* translators: %s: 作品名 */
			'notice' => __( '原图未能加载 · 已替换为作品《%s》', 'paddysun-newsprint' ),
			'more'   => __( '作品介绍 →', 'paddysun-newsprint' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'paddysun_ns_maybe_enqueue_img_fallback', 20 );
