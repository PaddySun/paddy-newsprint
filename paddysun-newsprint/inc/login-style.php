<?php
/**
 * 原生登录页视觉适配（0.10.0 U9 / 轨道 E）。
 *
 * 边界（stage6-plan §7）：
 *  - 仅通过标准 login_enqueue_scripts 钩子加载独立 CSS，不载入前台整份样式；
 *  - 覆盖登录 / 找回密码 / 重置密码 / 注册（启用时）四态，均为同页表单；
 *  - 不修改 wp-login.php 与任何核心文件，不改认证回调、密码、nonce、
 *    redirect_to，不隐藏错误提示、验证码或 Wordfence 附加控件；
 *  - 无第三方字体与脚本请求。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function paddysun_ns_login_assets() {
	$css = PADDYSUN_NS_DIR . '/assets/css/login.css';
	if ( ! is_readable( $css ) ) {
		return;
	}
	wp_enqueue_style( 'paddysun-login', PADDYSUN_NS_URI . '/assets/css/login.css', array(), filemtime( $css ) );
}
add_action( 'login_enqueue_scripts', 'paddysun_ns_login_assets' );
