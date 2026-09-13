<?php
/**
 * 结构化数据与 AI 可读性：JSON-LD BlogPosting、meta description。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether an SEO plugin owns the theme SEO output surface.
 *
 * This output surface is a future toolkit-plugin extraction candidate.
 *
 * @return bool
 */
function paddysun_ns_seo_active() {
	$active = defined( 'SLIM_SEO_VER' ) || defined( 'WPSEO_VERSION' );

	return (bool) apply_filters( 'paddysun_ns_seo_active', $active );
}

function paddysun_ns_json_ld() {
	if ( paddysun_ns_seo_active() ) {
		return;
	}
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$post = get_post();
	if ( ! $post ) {
		return;
	}
	if ( '' !== $post->post_password ) {
		return; // 密码文章不输出结构化数据（站长裁决 2026-09-12）
	}

	$data = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'BlogPosting',
		'headline'      => wp_specialchars_decode( get_the_title( $post ), ENT_QUOTES ),
		'url'           => get_permalink( $post ),
		'datePublished' => get_the_date( 'c', $post ),
		'dateModified'  => get_the_modified_date( 'c', $post ),
		'inLanguage'    => 'zh-CN',
		'author'        => array(
			'@type' => 'Person',
			'name'  => wp_specialchars_decode( get_the_author_meta( 'display_name', $post->post_author ), ENT_QUOTES ),
		),
		'mainEntityOfPage' => get_permalink( $post ),
	);

	$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 100, '…' );
	if ( '' !== $excerpt ) {
		$data['description'] = $excerpt;
	}
	if ( has_post_thumbnail( $post ) ) {
		$data['image'] = get_the_post_thumbnail_url( $post, 'large' );
	}

	/* P2-F：标签 → keywords；首个分类 → articleSection；字数 → wordCount */
	$tags = get_the_tags( $post );
	if ( $tags && ! is_wp_error( $tags ) ) {
		$data['keywords'] = implode( ',', wp_list_pluck( $tags, 'name' ) );
	}
	$cats = get_the_category( $post );
	if ( $cats ) {
		$data['articleSection'] = $cats[0]->name;
	}
	$plain = wp_strip_all_tags( $post->post_content );
	preg_match_all( '/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]/u', $plain, $cjk );
	preg_match_all( '/[A-Za-z0-9]+/', $plain, $latin );
	$data['wordCount'] = count( $cjk[0] ) + count( $latin[0] );

	$publisher = array(
		'@type' => 'Organization',
		'name'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
	);
	$site_icon = get_site_icon_url();
	if ( $site_icon ) {
		$publisher['logo'] = $site_icon;
	}
	$data['publisher'] = $publisher;

	/* JSON_HEX_*：HTML script 容器内 </script> 等闭合序列必须转义，wp_json_encode 的 JSON 模式不覆盖该上下文 */
	$json = wp_json_encode(
		$data,
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);
	echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
}
add_action( 'wp_head', 'paddysun_ns_json_ld', 5 );

/**
 * meta description（无 SEO 插件时的基线输出）。
 */
function paddysun_ns_meta_description() {
	if ( paddysun_ns_seo_active() ) {
		return;
	}
	$desc = '';
	if ( is_singular() ) {
		$post = get_post();
		if ( $post && '' === $post->post_password ) { // 密码文章不生成摘要
			$desc = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 100, '…' );
		}
	} elseif ( is_home() || is_front_page() ) {
		$desc = get_bloginfo( 'description' );
	} elseif ( is_archive() ) {
		$desc = wp_strip_all_tags( get_the_archive_description() );
		if ( '' === $desc ) {
			$desc = get_the_archive_title();
		}
	}
	if ( '' !== trim( (string) $desc ) ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_specialchars_decode( $desc, ENT_QUOTES ) ) );
	}
}
add_action( 'wp_head', 'paddysun_ns_meta_description', 2 );
