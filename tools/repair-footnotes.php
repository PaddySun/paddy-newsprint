<?php
/**
 * 1.0.2 脚注存量修复脚本（一次性运维工具，配合 wp-cli eval-file 使用）。
 *
 * 修复两类线上存量损伤（根因见 1.0.2 Changelog）：
 *   类 A：定义行标签被误转成角标 → 重复角标 + 定义文字游离 + 空 meta。
 *         由主题保存过滤器自动自愈（本脚本触发一次保存）。
 *   类 B：角标完好但 footnotes meta 缺失/为空、文末缺 wp:footnotes 区块。
 *         由本脚本用该文「原文 MD」（_paddysun_source_md）里的 [^id]: 定义重建。
 *
 * 用法（在受影响站点上，主题须为活动主题）：
 *   wp eval-file repair-footnotes.php            # 扫描全部公开文章/页面并修复
 *   wp eval-file repair-footnotes.php 5457 5492  # 只修指定 ID
 *
 * 幂等：重复运行无副作用。 Dry-run：加 --dry（只报告不写库）。
 * 注意：wp_update_post 会产生修订版本（revision），属正常行为。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( PHP_SAPI !== 'cli' ) { exit; }

$args = isset( $args ) && is_array( $args ) ? $args : array();
$dry  = in_array( '--dry', $args, true );
$ids  = array_values( array_filter( $args, 'is_numeric' ) );

if ( $ids ) {
	$posts = get_posts( array( 'post__in' => array_map( 'intval', $ids ), 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'numberposts' => - 1 ) );
} else {
	$posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'publish', 'numberposts' => - 1 ) );
}

$repaired = 0;
foreach ( $posts as $post ) {
	$content = $post->post_content;
	if ( false === strpos( $content, 'data-fn=' ) && false === strpos( $content, '[^' ) ) {
		continue; // 无脚注，跳过
	}
	$before_sups = preg_match_all( '/data-fn=/', $content );
	$meta_raw    = get_post_meta( $post->ID, 'footnotes', true );
	$actions     = array();

	/* 类 A 自愈：触发一次保存，主题过滤器完成还原 + 提取 + meta 同步 */
	if ( ! $dry ) {
		wp_update_post( array( 'ID' => $post->ID, 'post_content' => $content ) );
	}
	$content = get_post( $post->ID )->post_content;
	$after_sups = preg_match_all( '/data-fn=/', $content );
	if ( $after_sups < $before_sups ) {
		$actions[] = "重复角标 {$before_sups}→{$after_sups}";
	}

	/* 类 B：meta 空缺时用「原文 MD」的定义行重建 */
	$meta = json_decode( (string) get_post_meta( $post->ID, 'footnotes', true ), true );
	if ( ! is_array( $meta ) ) {
		$meta = array();
	}
	$empty_ids = array();
	foreach ( $meta as $entry ) {
		if ( empty( $entry['content'] ) && ! empty( $entry['id'] ) ) {
			$empty_ids[] = $entry['id'];
		}
	}
	$md = (string) get_post_meta( $post->ID, '_paddysun_source_md', true );
	$md_defs = array();
	if ( $md ) {
		preg_match_all( '/^\[\^([A-Za-z0-9_-]+)\]:\s*(.+)$/mu', $md, $mm, PREG_SET_ORDER );
		foreach ( $mm as $d ) {
			$md_defs[ $d[1] ] = trim( $d[2] );
		}
	}
	if ( $empty_ids && $md_defs ) {
		foreach ( $meta as $k => $entry ) {
			$id = $entry['id'];
			if ( empty( $entry['content'] ) && isset( $md_defs[ $id ] ) ) {
				$text = esc_html( $md_defs[ $id ] );
				$text = preg_replace( '/\[([^\]]+)\]\((https?:[^)]+)\)/', '<a href="$2">$1</a>', $text );
				$text = make_clickable( $text );
				$meta[ $k ]['content'] = $text;
				$actions[] = "meta[{$id}] 定义由原文 MD 重建";
			}
		}
		if ( ! $dry ) {
			update_post_meta( $post->ID, 'footnotes', wp_slash( wp_json_encode( array_values( $meta ) ) ) );
		}
	}

	/* 文末区块缺失：有角标而无 wp:footnotes → 补上 */
	if ( false === strpos( $content, 'wp:footnotes' ) ) {
		$content = rtrim( $content ) . "\n\n<!-- wp:footnotes /-->";
		if ( ! $dry ) {
			wp_update_post( array( 'ID' => $post->ID, 'post_content' => $content ) );
		}
		$actions[] = '补文末 wp:footnotes 区块';
	}

	if ( $actions ) {
		$repaired++;
		echo "post {$post->ID}（{$post->post_title}）: ", implode( '；', $actions ), "\n";
	} else {
		echo "post {$post->ID}: 无需修复\n";
	}
}
echo ( $dry ? '[dry-run] ' : '' ) . "扫描 " . count( $posts ) . " 篇，修复 {$repaired} 篇\n";
