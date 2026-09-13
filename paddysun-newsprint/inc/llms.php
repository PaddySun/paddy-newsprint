<?php
/**
 * AI 浏览支持：/llms.txt（站点索引）与 /llms-full.txt（全量正文 Markdown）。
 * 经 rewrite 规则动态生成，输出 text/plain，可被 CDN 缓存。
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

function paddysun_ns_llms_template_redirect() {
	$mode = get_query_var( 'paddysun_llms' );
	if ( ! in_array( $mode, array( 'index', 'full' ), true ) ) {
		return;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, follow' );
	/* 输出面已过滤为纯公开内容；登录态禁共享缓存（第三轮审查），
	 * 匿名公共响应可进边缘缓存——TTL 口径见站长手册 purge 一节 */
	paddysun_ns_export_cache_header();

	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$desc = wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES );

	if ( 'index' === $mode ) {
		echo "# {$site}\n\n";
		echo "> {$desc}\n\n";
		echo "本站为中文文字博客。文章页提供一键复制 Markdown 与 REST 接口：\n";
		echo "- 单篇 Markdown：/wp-json/paddysun/v1/posts/{id}/markdown\n";
		echo "- 全量正文：/llms-full.txt\n\n";

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

		/* 站点最后更新：最新一篇的修改时间 */
		$latest = 0;
		foreach ( $posts as $p ) {
			$latest = max( $latest, get_post_modified_time( 'U', true, $p ) );
		}
		if ( $latest ) {
			echo '> 站点最后更新：' . gmdate( 'Y-m-d', $latest ) . "\n\n";
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
			echo "## {$catname}\n\n" . implode( "\n", $items ) . "\n\n";
		}
		exit;
	}

	// llms-full.txt：全量正文（最新 100 篇 + 聚合 4MB 双预算，防失控）；
	// 全部响应文本（标题/URL/日期/分隔线）统一计入预算（第三轮审查）
	echo "# {$site} — 全量正文\n\n";
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'has_password'   => false, // 密码文章一律排除（站长裁决 2026-09-12）
			'posts_per_page' => 100,
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
		$chunk = paddysun_ns_truncate_markdown( paddysun_ns_get_markdown( $p->ID ) );
		$entry = "\n\n====\n\n# {$title}\n\n{$url} · {$date}\n\n" . $chunk;
		if ( $bytes_out + strlen( $entry ) > PADDYSUN_NS_LLMS_FULL_MAX_BYTES ) {
			echo "\n\n（全量导出已达字节预算，其余文章请用单篇接口 /wp-json/paddysun/v1/posts/{id}/markdown 获取）\n";
			break;
		}
		echo $entry;
		$bytes_out += strlen( $entry );
	}
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
