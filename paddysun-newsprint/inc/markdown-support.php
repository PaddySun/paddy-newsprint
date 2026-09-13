<?php
/**
 * 双轨制 Markdown 支持：
 *  1) 后台「原文 Markdown」存档框（post meta _paddysun_source_md）；
 *  2) REST 只读端点 —— 复制按钮与 AI 抓取共用，原文优先、服务端转换兜底。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PADDYSUN_NS_MD_META           = '_paddysun_source_md';
const PADDYSUN_NS_COPY_META         = '_paddysun_copy_enabled';
const PADDYSUN_NS_MD_MAX_OUT_BYTES  = 262144; // 单次导出上限（256KB），超出截断并标注

/* ------------------------------------------------------------
 * 0. 公共导出口径（站长裁决 2026-09-12）：
 *    post 类型 + 已发布 + 无密码 + 单篇复制开关未关闭。
 *    密码文章一律排除 REST / llms.txt / 首页摘要 / SEO 输出。
 * ---------------------------------------------------------- */
function paddysun_ns_copy_enabled( $post_id ) {
	return '0' !== get_post_meta( $post_id, PADDYSUN_NS_COPY_META, true );
}

function paddysun_ns_is_public_content( $post ) {
	if ( ! $post instanceof WP_Post ) {
		$post = get_post( $post );
	}
	if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || '' !== $post->post_password ) {
		return false;
	}
	return paddysun_ns_copy_enabled( $post->ID );
}

/* ------------------------------------------------------------
 * 1. 后台存档框
 * ---------------------------------------------------------- */
function paddysun_ns_add_meta_box() {
	add_meta_box(
		'paddysun_source_md',
		__( '原文 MD（选填）', 'paddysun-newsprint' ),
		'paddysun_ns_render_meta_box',
		'post',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'paddysun_ns_add_meta_box' );

function paddysun_ns_render_meta_box( $post ) {
	wp_nonce_field( 'paddysun_ns_md_save', 'paddysun_ns_md_nonce' );
	$md = get_post_meta( $post->ID, PADDYSUN_NS_MD_META, true );
	printf(
		'<p class="description">%s</p><textarea id="paddysun-source-md" name="paddysun_source_md" rows="8" style="width:100%%;font-family:Consolas,monospace;font-size:12px;">%s</textarea>',
		esc_html__( '本地写完粘贴一份原文，复制按钮与 AI 将直接使用它；留空则自动从正文反推。', 'paddysun-newsprint' ),
		esc_textarea( $md )
	);
	echo '<p><label><input type="checkbox" name="paddysun_copy_enabled" value="1"' . checked( paddysun_ns_copy_enabled( $post->ID ), true, false ) . '> '
		. esc_html__( '允许公开复制与导出（REST / llms.txt）', 'paddysun-newsprint' )
		. '</label></p>';
}

function paddysun_ns_save_meta( $post_id ) {
	if ( ! isset( $_POST['paddysun_ns_md_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['paddysun_ns_md_nonce'] ), 'paddysun_ns_md_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return; // 修订版不落 meta，与脚注同步通道口径一致（审查 D-F13）
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['paddysun_source_md'] ) ) {
		$md = $_POST['paddysun_source_md'];
		if ( ! is_string( $md ) ) {
			$md = '';
		}
		$md = wp_unslash( $md );
		if ( '' === trim( (string) $md ) ) {
			delete_post_meta( $post_id, PADDYSUN_NS_MD_META );
		} else {
			// 原文按纯文本存档（Markdown 本身是惰性文本），输出处负责转义
			update_post_meta( $post_id, PADDYSUN_NS_MD_META, (string) $md );
		}
	}
	/* 单篇复制开关：勾选=默认开（不落 meta）；未勾=显式存 0 */
	$copy = isset( $_POST['paddysun_copy_enabled'] ) ? $_POST['paddysun_copy_enabled'] : '';
	if ( is_string( $copy ) && '1' === wp_unslash( $copy ) ) {
		delete_post_meta( $post_id, PADDYSUN_NS_COPY_META );
	} else {
		update_post_meta( $post_id, PADDYSUN_NS_COPY_META, '0' );
	}
}
add_action( 'save_post', 'paddysun_ns_save_meta' );

/* ------------------------------------------------------------
 * 2. REST 端点：GET /wp-json/paddysun/v1/posts/{id}/markdown
 *    原文优先；无原文时服务端 HTML→Markdown 反推（公式/图表天然保留原文形态）。
 * ---------------------------------------------------------- */
function paddysun_ns_register_rest_markdown() {
	register_rest_route(
		'paddysun/v1',
		'/posts/(?P<id>\d+)/markdown',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true', // 只读公开数据，无敏感输出
			'callback'            => 'paddysun_ns_rest_markdown',
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'paddysun_ns_register_rest_markdown' );

function paddysun_ns_rest_markdown( WP_REST_Request $request ) {
	$post_id = absint( $request['id'] );
	$post    = get_post( $post_id );

	if ( ! $post || ! paddysun_ns_is_public_content( $post ) ) {
		// 非公开（未发布/密码/类型不符/单篇关闭导出）一律 404，不区分登录态、不泄露存在性差异
		return new WP_Error( 'paddysun_not_found', __( '文章不存在或未公开', 'paddysun-newsprint' ), array( 'status' => 404 ) );
	}

	$source = get_post_meta( $post_id, PADDYSUN_NS_MD_META, true );
	if ( is_string( $source ) && '' !== trim( $source ) ) {
		$markdown = $source;
		$origin   = 'stored';
	} else {
		// 走公用包装：为 core/footnotes 顶层渲染补 postId 上下文
		$markdown = paddysun_ns_get_markdown( $post_id );
		$origin   = 'converted';
	}

	$result = array(
		'id'        => $post_id,
		'title'     => get_the_title( $post ),
		'slug'      => $post->post_name,
		'link'      => get_permalink( $post ),
		'date'      => get_the_date( 'c', $post ),
		'modified'  => get_the_modified_date( 'c', $post ),
		'origin'    => $origin,
		'markdown'  => paddysun_ns_truncate_markdown( $markdown ),
	);

	$response = new WP_REST_Response( $result, 200 );
	paddysun_ns_export_cache_header();

	return $response;
}

/**
 * 取文章 Markdown（服务端公用；与 REST 端点同源逻辑）。
 */
function paddysun_ns_get_markdown( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || ! paddysun_ns_is_public_content( $post ) ) {
		return '';
	}
	$source = get_post_meta( $post_id, PADDYSUN_NS_MD_META, true );
	if ( is_string( $source ) && '' !== trim( $source ) ) {
		return $source;
	}
	/* 入口前置预算（第三轮审查）：原始内容即超限时跳过 the_content 渲染，直接降级摘要 */
	if ( strlen( $post->post_content ) > PADDYSUN_NS_MD_MAX_HTML_BYTES * 2 ) {
		return wp_trim_words( wp_strip_all_tags( $post->post_content ), 100, '…' );
	}
	$prev_pid = function_exists( 'paddysun_ns_md_context_post_id' ) ? paddysun_ns_md_context_post_id() : 0;
	if ( function_exists( 'paddysun_ns_md_context_post_id' ) ) {
		paddysun_ns_md_context_post_id( $post_id ); // 供 core/footnotes 顶层渲染取上下文
	}
	/* 导出渲染期约束嵌套查询（第三轮审查）：嵌入的 Query Loop 只能看到
	 * 公开已发布、无密码、未关闭导出的文章——与访问者权限无关，
	 * 也防止借本篇导出带出其他篇目的受保护内容。 */
	add_filter( 'pre_get_posts', 'paddysun_ns_export_public_query', 999 );
	$md = paddysun_ns_html_to_markdown( apply_filters( 'the_content', $post->post_content ) );
	remove_filter( 'pre_get_posts', 'paddysun_ns_export_public_query', 999 );
	if ( function_exists( 'paddysun_ns_md_context_post_id' ) ) {
		paddysun_ns_md_context_post_id( $prev_pid ); // 恢复调用前上下文，不固定清零
	}
	if ( '' === trim( (string) $md ) ) {
		// 正文超预算戒断（站长裁决）：降级为摘要，不返回空/截断误导
		return wp_trim_words( wp_strip_all_tags( $post->post_content ), 100, '…' );
	}
	return $md;
}

/**
 * 导出期嵌套查询约束：仅公开内容可见（含其他篇目的复制开关）。
 */
function paddysun_ns_export_public_query( $query ) {
	if ( $query->is_main_query() ) {
		return $query; // 只约束导出转换期触发的嵌套查询
	}
	$query->set( 'post_status', 'publish' );
	$query->set( 'has_password', false );
	$query->set(
		'meta_query',
		array(
			'relation' => 'OR',
			array( 'key' => PADDYSUN_NS_COPY_META, 'compare' => 'NOT EXISTS' ),
			array( 'key' => PADDYSUN_NS_COPY_META, 'value' => '0', 'compare' => '!=' ),
		)
	);
	return $query;
}

/**
 * 导出响应缓存策略：响应与身份无关（导出渲染期已强制公开查询）是前提；
 * 登录态一律禁共享缓存（第三轮审查：未证明身份无关前不覆盖默认策略）。
 */
function paddysun_ns_export_cache_header() {
	if ( is_user_logged_in() ) {
		header( 'Cache-Control: private, no-store' );
	} else {
		header( 'Cache-Control: public, max-age=300, s-maxage=300, stale-while-revalidate=60' );
	}
}

/**
 * UTF-8 字符边界安全截断（第三轮审查：字节 substr 会截断半个中文）。
 */
function paddysun_ns_safe_cut_bytes( $s, $max ) {
	$s = (string) $s;
	if ( strlen( $s ) <= $max ) {
		return $s;
	}
	$i = $max;
	while ( $i > 0 && ( ord( $s[ $i ] ) & 0xC0 ) === 0x80 ) {
		$i--; // 回退到 UTF-8 字符起始字节
	}
	if ( 0 === $i ) {
		$i = $max; // 非 UTF-8 内容按字节截
	}
	return substr( $s, 0, $i );
}

/**
 * 单次导出上限截断：段落/围栏边界收尾，附截断标注（站长裁决：取摘要或戒断）。
 */
function paddysun_ns_truncate_markdown( $md, $max = PADDYSUN_NS_MD_MAX_OUT_BYTES ) {
	$md = (string) $md;
	if ( strlen( $md ) <= $max ) {
		return $md;
	}
	$cut = paddysun_ns_safe_cut_bytes( $md, $max );
	$pos = strrpos( $cut, "\n\n" );
	if ( false !== $pos && $pos > 0 ) {
		$cut = substr( $cut, 0, $pos );
	}
	/* 围栏闭合（第三轮审查修正）：按「围栏定界行」奇偶判断，
	 * 定界长度取内容内最长 run+1——四反引号围栏也能正确补齐 */
	if ( preg_match_all( '/^`{3,}/m', $cut, $fm ) && 1 === count( $fm[0] ) % 2 ) {
		$maxrun = 0;
		foreach ( $fm[0] as $run ) {
			$maxrun = max( $maxrun, strlen( $run ) );
		}
		$cut .= "\n" . str_repeat( '`', max( 3, $maxrun + 1 ) );
	}
	return rtrim( $cut ) . "\n\n" . __( '（内容过长，已截断）', 'paddysun-newsprint' ) . "\n";
}
