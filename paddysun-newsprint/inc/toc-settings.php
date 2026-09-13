<?php
/**
 * 文章级目录形态设置（V-07 站长批注：放到「原文 MD」框下面的编辑选项）。
 *
 * 每篇文章可选：悬浮目录（默认，≥1240px 固定右侧滚动跟随）/ 目录盒（正文前）/
 * 无目录。存 post meta `_paddysun_toc_form`；未设置时回退模板类
 * （single.html 默认悬浮）。模板层的区块变体（变换菜单）仍然有效，
 * 文章级设置优先于模板。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PADDYSUN_NS_TOC_META = '_paddysun_toc_form';

/**
 * 取文章的目录形态（float / head / off；空表示未设置，回退模板）。
 *
 * @param int $post_id 文章 ID。
 * @return string
 */
function paddysun_ns_get_toc_form( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : get_queried_object_id();
	if ( ! $post_id ) {
		return '';
	}
	$form = get_post_meta( $post_id, PADDYSUN_NS_TOC_META, true );
	return in_array( $form, array( 'float', 'head', 'off' ), true ) ? $form : '';
}

function paddysun_ns_add_toc_meta_box() {
	add_meta_box(
		'paddysun_toc_form',
		__( '目录形态', 'paddysun-newsprint' ),
		'paddysun_ns_render_toc_meta_box',
		'post',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'paddysun_ns_add_toc_meta_box' );

function paddysun_ns_render_toc_meta_box( $post ) {
	wp_nonce_field( 'paddysun_ns_toc_save', 'paddysun_ns_toc_nonce' );
	$current = paddysun_ns_get_toc_form( $post->ID );
	if ( '' === $current ) {
		$current = 'float'; // 未设置 = 默认悬浮
	}
	$options = array(
		'float' => __( '悬浮目录（宽屏右侧跟随阅读，默认）', 'paddysun-newsprint' ),
		'head'  => __( '目录盒（正文前）', 'paddysun-newsprint' ),
		'off'   => __( '无目录', 'paddysun-newsprint' ),
	);
	echo '<p class="description">' . esc_html__( '二级标题 ≥ 3 个时生效。', 'paddysun-newsprint' ) . '</p>';
	foreach ( $options as $value => $label ) {
		printf(
			'<p style="margin:6px 0"><label><input type="radio" name="paddysun_toc_form" value="%s"%s> %s</label></p>',
			esc_attr( $value ),
			checked( $current, $value, false ),
			esc_html( $label )
		);
	}
}

function paddysun_ns_save_toc_meta( $post_id ) {
	if ( ! isset( $_POST['paddysun_ns_toc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['paddysun_ns_toc_nonce'] ), 'paddysun_ns_toc_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return; // 修订版不落 meta，与脚注/MD 存档通道口径一致（审查 D-F13）
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['paddysun_toc_form'] ) ) {
		return;
	}
	$form = sanitize_key( wp_unslash( $_POST['paddysun_toc_form'] ) );
	if ( in_array( $form, array( 'float', 'head', 'off' ), true ) ) {
		update_post_meta( $post_id, PADDYSUN_NS_TOC_META, $form );
	}
}
add_action( 'save_post', 'paddysun_ns_save_toc_meta' );
