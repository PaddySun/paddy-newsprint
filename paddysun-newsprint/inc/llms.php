<?php
/**
 * AI 浏览支持：/llms.txt（站点索引）与 /llms-full.txt（全量正文 Markdown）。
 * 经 rewrite 规则动态生成，输出 text/plain，可被 CDN 缓存。
 *
 * 开关、内容来源与全量篇数/正文口径来自「外观 → 菜单」页底部面板
 * （inc/llms-settings.php，option `paddysun_ns_llms`）；默认值与 0.10.12 既有
 * 行为逐字节一致。按 llms.txt v2 惯例广播 rel="describedby"。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* llms-full 聚合字节预算（0.9.1 审查整改）：全量响应总量封顶，超出即止 */
const PADDYSUN_NS_LLMS_FULL_MAX_BYTES = 4194304;

function paddysun_ns_llms_rewrite() {
	add_rewrite_rule( '^llms\.txt$', 'index.php?paddysun_llms=index', 'top' );
	add_rewrite_rule( '^llms-full\.txt$', 'index.php?paddysun_llms=full', 'top' );
}
add_action( 'init', 'paddysun_ns_llms_rewrite' );

function paddysun_ns_llms_query_var( $vars ) {
	$vars[] = 'paddysun_llms';
	return $vars;
}
add_filter( 'query_vars', 'paddysun_ns_llms_query_var' );

/**
 * 端点是否对外可用（站长开关）。
 *
 * @param string $mode     index|full。
 * @param array  $settings 已归一化设置。
 * @return bool
 */
function paddysun_ns_llms_mode_available( $mode, $settings ) {
	if ( empty( $settings['index_enabled'] ) ) {
		return false;
	}
	return 'full' !== $mode || ! empty( $settings['full_enabled'] );
}

/**
 * 关闭态响应：403 明确“站长已关闭”，不泄露内容，且不吃公共缓存——
 * 开关切换必须立即生效（403 与 200 之间不能有 300s 缓存粘滞）。
 */
function paddysun_ns_llms_render_refusal() {
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Cache-Control: private, no-store' );
	echo "llms 索引已由站长关闭。\n";
}

/**
 * llms.txt v2：用 rel="describedby" 广播机器可读索引位置。
 *
 * 走 wp_headers 过滤器——只在 WP::send_headers() 的前台流程里触发，
 * 因此无需 is_admin 判断；同时覆盖 HTML 页面与两个 llms 端点自身。
 * type 按实际响应的 text/plain 声明（v2 允许 text/plain 服务方式）。
 */
function paddysun_ns_llms_broadcast( $headers ) {
	$settings = paddysun_ns_llms_settings();
	if ( ! empty( $settings['index_enabled'] ) ) {
		$headers['Link'] = '<' . home_url( '/llms.txt' ) . '>; rel="describedby"; type="text/plain"';
	}
	return $headers;
}
add_filter( 'wp_headers', 'paddysun_ns_llms_broadcast' );

/**
 * 同一关系的 HTML 形式（供不读响应头、只解析 HTML 的读者）。
 */
function paddysun_ns_llms_head_link() {
	$settings = paddysun_ns_llms_settings();
	if ( empty( $settings['index_enabled'] ) ) {
		return;
	}
	printf(
		'<link rel="describedby" href="%s" type="text/plain">' . "\n",
		esc_url( home_url( '/llms.txt' ) )
	);
}
add_action( 'wp_head', 'paddysun_ns_llms_head_link', 2 );

/**
 * llms.txt 正文。
 *
 * 三种内容来源：
 *  - auto：站点名 + 简介 + 固定说明 + 站点最后更新 + 按分类分组的文章列表（0.10.12 行为）
 *  - partial：站点名 + 站长填写的介绍文字 + 同一份自动列表；介绍留空则回退 auto
 *  - manual：原样输出站长填写的全文，不做任何改写；留空则回退 auto
 *
 * @param array $settings 已归一化设置。
 * @return string
 */
function paddysun_ns_llms_render_index( $settings ) {
	if ( 'manual' === $settings['index_mode'] && '' !== trim( $settings['index_manual'] ) ) {
		return $settings['index_manual'];
	}

	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$desc = wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES );

	$posts = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'has_password'     => false, // 密码文章一律排除（站长裁决 2026-09-12）
			'posts_per_page'   => 200,
			'orderby'          => 'date',
			'order'            => 'DESC',
		)
	);

	$out = "# {$site}\n\n";

	$intro = 'partial' === $settings['index_mode'] ? trim( $settings['index_intro'] ) : '';
	if ( '' !== $intro ) {
		$out .= $settings['index_intro'] . "\n\n";
	} else {
		$out .= "> {$desc}\n\n";
		$out .= "本站为中文文字博客。文章页提供一键复制 Markdown 与 REST 接口：\n";
		$out .= "- 单篇 Markdown：/wp-json/paddysun/v1/posts/{id}/markdown\n";
		/* 全量端点关闭时不推荐它——那个地址此时返回 403（站长 2026-09-15 验收要求） */
		if ( ! empty( $settings['full_enabled'] ) ) {
			$out .= "- 全量正文：/llms-full.txt\n";
		}
		$out .= "\n";

		/* 站点最后更新：最新一篇的修改时间 */
		$latest = 0;
		foreach ( $posts as $p ) {
			$latest = max( $latest, get_post_modified_time( 'U', true, $p ) );
		}
		if ( $latest ) {
			$out .= '> 站点最后更新：' . gmdate( 'Y-m-d', $latest ) . "\n\n";
		}
	}

	/* 按分类分组；每篇附发布日，发布后有实质修订的附更新日 */
	$groups = array();
	foreach ( $posts as $p ) {
		if ( ! paddysun_ns_is_public_content( $p ) ) {
			continue; // 与 full 同口径：密码/单篇关闭导出均不进 AI 索引
		}
		$cats    = get_the_category( $p->ID );
		$catname = $cats ? $cats[0]->name : '其他';
		if ( '未分类' === $catname ) {
			$catname = '其他';
		}
		$title   = wp_specialchars_decode( get_the_title( $p ), ENT_QUOTES );
		$title   = '' !== trim( $title ) ? $title : '（无标题）'; // 空标题兜底
		$date    = get_the_date( 'Y-m-d', $p );
		$mod     = get_post_modified_time( 'Y-m-d', true, $p );
		$stamp   = $date;
		if ( $mod && $mod > $date && strtotime( $mod ) - strtotime( $date ) > DAY_IN_SECONDS ) {
			$stamp .= " · 更新 {$mod}";
		}
		$groups[ $catname ][] = "- [" . str_replace( ']', '\\]', $title ) . "](" . get_permalink( $p ) . ")（{$stamp}）";
	}
	foreach ( $groups as $catname => $items ) {
		$out .= "## {$catname}\n\n" . implode( "\n", $items ) . "\n\n";
	}
	return $out;
}

/**
 * llms-full.txt 正文：最新 N 篇（站长设置，默认 100）+ 聚合 4 MiB 预算，防失控。
 * 全部响应文本（标题/URL/日期/分隔线）统一计入预算（第三轮审查）。
 * 正文口径：全文走 Markdown 转换；摘要走核心 get_the_excerpt()，与 Feed 摘要同源。
 *
 * @param array $settings 已归一化设置。
 * @return string
 */
function paddysun_ns_llms_render_full( $settings ) {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$out  = "# {$site} — 全量正文\n\n";

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'has_password'   => false, // 密码文章一律排除（站长裁决 2026-09-12）
			'posts_per_page' => (int) $settings['full_count'],
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	$bytes_out = 0;
	foreach ( $posts as $p ) {
		if ( ! paddysun_ns_is_public_content( $p ) ) {
			continue; // 单篇复制开关关闭的跳过
		}
		$title = wp_specialchars_decode( get_the_title( $p ), ENT_QUOTES );
		$date  = get_the_date( 'Y-m-d', $p );
		$url   = get_permalink( $p );
		if ( 'excerpt' === $settings['full_content'] ) {
			$chunk = paddysun_ns_truncate_markdown( wp_strip_all_tags( get_the_excerpt( $p ) ) );
		} else {
			$chunk = paddysun_ns_truncate_markdown( paddysun_ns_get_markdown( $p->ID ) );
		}
		$entry = "\n\n====\n\n# {$title}\n\n{$url} · {$date}\n\n" . $chunk;
		if ( $bytes_out + strlen( $entry ) > PADDYSUN_NS_LLMS_FULL_MAX_BYTES ) {
			$out .= "\n\n（全量导出已达字节预算，其余文章请用单篇接口 /wp-json/paddysun/v1/posts/{id}/markdown 获取）\n";
			break;
		}
		$out .= $entry;
		$bytes_out += strlen( $entry );
	}
	return $out;
}

function paddysun_ns_llms_template_redirect() {
	$mode = get_query_var( 'paddysun_llms' );
	if ( ! in_array( $mode, array( 'index', 'full' ), true ) ) {
		return;
	}

	$settings = paddysun_ns_llms_settings();
	if ( ! paddysun_ns_llms_mode_available( $mode, $settings ) ) {
		paddysun_ns_llms_render_refusal();
		exit;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, follow' );
	/* 输出面已过滤为纯公开内容；登录态禁共享缓存（第三轮审查），
	 * 匿名公共响应可进边缘缓存——TTL 口径见站长手册 purge 一节 */
	paddysun_ns_export_cache_header();

	echo 'full' === $mode
		? paddysun_ns_llms_render_full( $settings )
		: paddysun_ns_llms_render_index( $settings );
	exit;
}
add_action( 'template_redirect', 'paddysun_ns_llms_template_redirect', 1 );

/**
 * 主题激活时静默刷新固定链接（llms.txt rewrite 规则需写入；无提示输出，审查 C-F20）。
 */
function paddysun_ns_after_switch_theme() {
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'paddysun_ns_after_switch_theme' );
