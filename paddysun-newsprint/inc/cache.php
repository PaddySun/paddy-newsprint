<?php
/**
 * Paddysun Newsprint — 缓存层：CDN 边缘缓存头 + 档案馆/版签总目索引缓存。
 *
 * 三件事：
 * 1. 对匿名 GET 的公开视图（主页 / 归档 / 单篇 / 页面）输出标准缓存头，
 *    让 EdgeOne 等遵循源站 Cache-Control 的 CDN 可整页缓存：
 *      Cache-Control: public, max-age=0, s-maxage=600, stale-while-revalidate=3600
 *      Last-Modified: 全站最新已发布内容的时间（支持 If-Modified-Since → 304）
 *    不缓存的情形：登录态、评论者/登录/阅读密码 Cookie、带查询串的请求（搜索、
 *    replytocom、预览、统计参数等由 CDN 直通回源）、feed、404、预览、加密内容、
 *    已有其它 Cache-Control 头（让位给页面缓存/SEO 插件）、响应携带 Set-Cookie、
 *    DONOTCACHEPAGE 常量、paddysun_ns_edge_cache 过滤器返回 false。
 *    挂载在 template_redirect 优先级 99：晚于 redirect_canonical（301 先走）与
 *    多数插件标记 DONOTCACHEPAGE 的时机，早于模板输出。
 * 2. 「档案馆」「版签总目」两个区块模式的整段结果用 transient 缓存
 *    （生产站有 Redis Object Cache 时即 Redis；无持久对象缓存时落 options 表，
 *    固定键 + 值内版本比对，版本推进只原地覆盖、不产生孤儿行）。
 * 3. 内容/词表变动时推进版本号令两份索引失效（save_post 排除修订/自动保存与
 *    post/page 以外的类型；term 钩子仅认 category / post_tag）。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------
 * 1. CDN 边缘缓存头
 * ---------------------------------------------------------- */

/**
 * 当前请求是否可被 CDN 整页缓存。
 *
 * @return bool
 */
function paddysun_ns_edge_cacheable() {
	if ( defined( 'DONOTCACHEPAGE' ) || defined( 'DONOTCDN' ) ) {
		return false;
	}
	// 总开关：add_filter( 'paddysun_ns_edge_cache', '__return_false' ) 可整体停用本层。
	if ( ! apply_filters( 'paddysun_ns_edge_cache', true ) ) {
		return false;
	}
	if ( is_user_logged_in() ) {
		return false;
	}
	$np_request_method = isset( $_SERVER['REQUEST_METHOD'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
		: '';
	if ( '' !== $np_request_method && ! in_array( $np_request_method, array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}
	// 任意查询串一律不发缓存头：搜索 / replytocom / 预览 / 统计参数等全部由 CDN 直通。
	if ( ! empty( $_GET ) ) {
		return false;
	}
	if ( is_feed() || is_search() || is_404() || is_preview() || post_password_required() ) {
		return false;
	}
	// 评论者 / 登录 / 阅读密码 Cookie：页面含个性化内容，不缓存。
	foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
		if (
			0 === strpos( $cookie_name, 'comment_author_' )
			|| 0 === strpos( $cookie_name, 'wordpress_logged_in' )
			|| 0 === strpos( $cookie_name, 'wp-postpass_' )
		) {
			return false;
		}
	}
	return is_front_page() || is_home() || is_archive() || is_singular();
}

/**
 * 全站内容验证时间（Last-Modified 原料，请求内静态缓存）。
 *
 * 取三者最大：最新已发布 post/page 的修改时间、最新**已批准评论**时间、
 * 单调内容戳记（paddysun_ns_stamp，内容/词表/评论事件时推进，只增不减）。
 * 评论必须纳入：单篇页的评论区由服务端渲染，若仅看 post_modified，
 * 发文后的新评论会被条件请求 304 挡住（1.1.1 线上缺陷）。
 *
 * @return string GMT 格式时间，如 "Wed, 15 Oct 2026 15:34:50 GMT"。
 */
function paddysun_ns_edge_last_modified() {
	global $wpdb;
	static $last_modified = null;
	if ( null !== $last_modified ) {
		return $last_modified;
	}

	$post_max    = $wpdb->get_var(
		"SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
		 WHERE post_status = 'publish' AND post_type IN ('post', 'page')"
	);
	$comment_max = $wpdb->get_var(
		"SELECT MAX(comment_date_gmt) FROM {$wpdb->comments}
		 WHERE comment_approved = '1'"
	);
	$stamp       = get_option( 'paddysun_ns_stamp' );
	$candidates  = array_filter( array( $post_max, $comment_max, $stamp ) );
	$raw         = $candidates ? max( $candidates ) : gmdate( 'Y-m-d H:i:s' );
	$last_modified = mysql2date( 'D, d M Y H:i:s', $raw, false ) . ' GMT';
	return $last_modified;
}

/**
 * 模板路由后输出缓存头；命中条件请求时直接 304。
 *
 * 挂载优先级 99：redirect_canonical（p10）等重定向已处理完，模板尚未输出。
 */
function paddysun_ns_edge_cache_headers() {
	if ( ! paddysun_ns_edge_cacheable() ) {
		return;
	}

	// 已有其它 Cache-Control（页面缓存/SEO 插件先发的策略）→ 让位，不叠加重复头；
	// 响应已携带 Set-Cookie（会话/统计插件种 Cookie）→ 个性化响应，不缓存。
	foreach ( headers_list() as $sent_header ) {
		if ( 0 === stripos( $sent_header, 'Cache-Control:' ) || 0 === stripos( $sent_header, 'Set-Cookie:' ) ) {
			return;
		}
	}

	$last_modified = paddysun_ns_edge_last_modified();
	$control       = paddysun_ns_edge_cache_control();

	if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
		$since_parts = explode( ';', sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) );
		$since       = strtotime( trim( $since_parts[0] ) );
		if ( $since && strtotime( $last_modified ) <= $since ) {
			status_header( 304 );
			header( 'Cache-Control: ' . $control );
			header( 'Last-Modified: ' . $last_modified );
			exit;
		}
	}

	header( 'Cache-Control: ' . $control );
	header( 'Last-Modified: ' . $last_modified );
}

/**
 * Cache-Control 策略：最新文章发布 24 小时内 s-maxage 降至 60 秒
 * （新文评论热流量即时生效），其余 600 + SWR 3600。
 *
 * @return string
 */
function paddysun_ns_edge_cache_control() {
	global $wpdb;
	static $control = null;
	if ( null !== $control ) {
		return $control;
	}
	$fresh_window = (int) apply_filters( 'paddysun_ns_fresh_window', DAY_IN_SECONDS );
	$newest       = $wpdb->get_var(
		"SELECT MAX(post_date_gmt) FROM {$wpdb->posts}
		 WHERE post_type = 'post' AND post_status = 'publish'"
	);
	$short = 'public, max-age=0, s-maxage=60, stale-while-revalidate=300';
	$long  = 'public, max-age=0, s-maxage=600, stale-while-revalidate=3600';
	$control = ( $newest && ( time() - strtotime( $newest ) ) < $fresh_window ) ? $short : $long;
	return $control;
}
add_action( 'template_redirect', 'paddysun_ns_edge_cache_headers', 99 );

/* ------------------------------------------------------------
 * 2. 档案馆 / 版签总目 索引缓存（transient，固定键 + 值内版本）
 * ---------------------------------------------------------- */

/**
 * 当前索引缓存版本号（存进 transient 值内比对，变更即原地覆盖重建）。
 *
 * @return int
 */
function paddysun_ns_index_version() {
	return (int) get_option( 'paddysun_ns_idx_ver', 1 );
}

/**
 * 推进版本号，令两份索引 transient 在下次读取时重建。
 */
function paddysun_ns_bump_index_version() {
	update_option( 'paddysun_ns_idx_ver', paddysun_ns_index_version() + 1, false );
	paddysun_ns_touch_content_stamp();
}

/**
 * 推进单调内容戳记（GMT）：Last-Modified 的组成部分，保证验证时间只增不减
 * （删除最新评论/文章导致 MAX 回退时，戳记守住验证时间不倒退）。
 */
function paddysun_ns_touch_content_stamp() {
	$gmt = gmdate( 'Y-m-d H:i:s' );
	// 某些持久化层（如个别 SQLite dropin）update_option 无法创建不存在的选项，先 add 兜底。
	if ( false === get_option( 'paddysun_ns_stamp' ) ) {
		add_option( 'paddysun_ns_stamp', $gmt, '', false );
		return;
	}
	update_option( 'paddysun_ns_stamp', $gmt, false );
}

/**
 * 主题更新/切换时推进索引版本：令旧格式 transient 立即失效重建
 * （1.1.1 起索引输出带区块封套，需淘汰 1.1.0 的无封套缓存）。
 */
function paddysun_ns_flush_index_cache_on_switch() {
	paddysun_ns_bump_index_version();
}
add_action( 'after_switch_theme', 'paddysun_ns_flush_index_cache_on_switch' );

/**
 * save_post：仅 post / page 的真实保存（排除修订、自动保存）推进索引版本。
 *
 * @param int          $post_id 文章 ID。
 * @param WP_Post|null $post    文章对象。
 */
function paddysun_ns_flush_index_cache_on_save( $post_id, $post = null ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( $post && isset( $post->post_type ) && ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	paddysun_ns_bump_index_version();
}
add_action( 'save_post', 'paddysun_ns_flush_index_cache_on_save', 10, 2 );

/**
 * 词表钩子：仅 category / post_tag 的增删改推进索引版本。
 *
 * created_term / edited_term 第 3 参、deleted_term 第 4 参为 taxonomy。
 *
 * @param int                      $term_id 词 ID。
 * @param int                      $tt_id   term_taxonomy ID。
 * @param WP_Term|string|int|null $arg3    词对象或 taxonomy 名。
 * @param string|null              $arg4    taxonomy 名（deleted_term）。
 */
function paddysun_ns_flush_index_cache_on_term( $term_id, $tt_id = 0, $arg3 = null, $arg4 = null ) {
	$taxonomy = null;
	if ( $arg3 instanceof WP_Term ) {
		$taxonomy = $arg3->taxonomy;
	} elseif ( is_string( $arg3 ) ) {
		$taxonomy = $arg3;
	} elseif ( is_string( $arg4 ) ) {
		$taxonomy = $arg4;
	}
	if ( null !== $taxonomy && ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
		return;
	}
	paddysun_ns_bump_index_version();
}
foreach ( array( 'created_term', 'edited_term', 'deleted_term' ) as $term_action ) {
	add_action( $term_action, 'paddysun_ns_flush_index_cache_on_term', 10, 4 );
}

add_action( 'comment_post', 'paddysun_ns_touch_content_stamp', 10 );
add_action( 'edit_comment', 'paddysun_ns_touch_content_stamp', 10 );
add_action( 'wp_set_comment_status', 'paddysun_ns_touch_content_stamp', 10, 2 );

/* ------------------------------------------------------------
 * 3. 多语言环境探测
 * ---------------------------------------------------------- */

/**
 * 是否运行在 Polylang / WPML 等按语言分库的内容插件下。
 *
 * 这类插件通过 filters 过滤 get_posts / get_terms，主题的直连聚合 SQL 会绕过
 * 语言过滤造成混列；两份索引在多语言环境下退回 WP API 取数路径。
 *
 * @return bool
 */
function paddysun_ns_is_multilingual() {
	$multilingual = defined( 'POLYLANG_VERSION' ) || defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'WPML_VERSION' );
	return (bool) apply_filters( 'paddysun_ns_multilingual', $multilingual );
}
