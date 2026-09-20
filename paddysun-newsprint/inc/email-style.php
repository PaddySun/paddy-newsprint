<?php
/**
 * 朴素默认邮件样式。
 *
 * 站点邮件默认以 text/plain 发出，在纯文本客户端可读，但没有排版；本文件给
 * wp_mail 出站邮件套一层**朴素可用的 HTML 默认壳**：表格布局、全部行内样式、
 * 系统字体栈、零外部资源（无图片 / 无 webfont / 无远端 CSS），并附纯文本
 * AltBody 作安全降级。仅此而已——不提供定制 UI、不加设置项（站长决策
 * 2026-09-16，1.0.0 S1：模板定制交由插件生态）。
 *
 * 让位机制（与 SEO 插件让位同一思路，功能存在性探测、不硬编码插件 slug）：
 * `wp_loaded` 时点检查三个邮件扩展点——`pre_wp_mail`（整信短路）、`wp_mail`
 * （参数重写）、`wp_mail_content_type`（内容类型）。任一已被其他组件接管，
 * 说明存在邮件模板/重写类插件，主题默认壳**整体不注册**，效果以插件为准。
 * 主题自身钩子只在接管分支内注册，探测时不会探测到自己。
 *
 * 已知边界（如实记录）：在 `wp_loaded` 之后才注册邮件钩子的组件探测不到，
 * 此时双方钩子按注册顺序共存，后注册者的返回值生效（核心过滤器语义）。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_loaded', 'paddysun_ns_email_maybe_style' );

/**
 * 接管决策：三个邮件扩展点全部空闲才注册主题默认壳，否则整体让位。
 *
 * @return void
 */
function paddysun_ns_email_maybe_style() {
	if ( has_filter( 'pre_wp_mail' ) || has_filter( 'wp_mail' ) || has_filter( 'wp_mail_content_type' ) ) {
		return; // 邮件 HTML/内容类型已由其他组件管理，主题默认让位。
	}
	add_filter( 'wp_mail', 'paddysun_ns_email_wrap' );
	add_filter( 'wp_mail_content_type', 'paddysun_ns_email_content_type' );
	add_action( 'phpmailer_init', 'paddysun_ns_email_alt_body' );
}

/**
 * 出站内容类型：HTML。
 *
 * @return string
 */
function paddysun_ns_email_content_type() {
	return 'text/html';
}

/**
 * 纯文本安全降级：同步一份去标签纯文本 AltBody。
 *
 * @param PHPMailer $phpmailer PHPMailer 实例。
 * @return void
 */
function paddysun_ns_email_alt_body( $phpmailer ) {
	if ( ! empty( $phpmailer->AltBody ) || '' === (string) $phpmailer->Body ) {
		return;
	}
	$plain = trim( wp_strip_all_tags( html_entity_decode( (string) $phpmailer->Body, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	if ( '' !== $plain ) {
		$phpmailer->AltBody = $plain;
	}
}

/**
 * 把消息装进朴素默认壳。已整信成 HTML（含 <html>/<body>）的调用方原样放行，
 * 避免 double-wrap；HTML 片段按受信站点代码处理（只包壳不改写）；纯文本
 * 先转义再自动链接 URL、保留换行。
 *
 * @param array $atts wp_mail 参数。
 * @return array
 */
function paddysun_ns_email_wrap( $atts ) {
	if ( empty( $atts['message'] ) || ! is_string( $atts['message'] ) ) {
		return $atts;
	}
	$message        = $atts['message'];
	$is_full_letter = false !== stripos( $message, '<html' ) || false !== stripos( $message, '<body' );
	if ( $is_full_letter ) {
		return $atts; // 调用方自带完整信件：原样放行。
	}

	$looks_html = false !== stripos( $message, '<p' ) || false !== stripos( $message, '<br' )
		|| false !== stripos( $message, '<table' ) || false !== stripos( $message, '<div' );
	$body = $looks_html
		? $message
		: nl2br( make_clickable( esc_html( $message ) ) );

	$atts['message'] = paddysun_ns_email_shell( $body );
	return $atts;
}

/**
 * 朴素默认壳：表格布局、全部行内样式、系统字体栈、零外部资源。
 * 中性纸面配色，不读 theme.json（保持「朴素」口径，不随主题换肤联动）。
 *
 * @param string $body 正文（已处理为 HTML）。
 * @return string
 */
function paddysun_ns_email_shell( $body ) {
	$name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$home   = home_url( '/' );
	$font   = "-apple-system,'Segoe UI','PingFang SC','Hiragino Sans GB','Microsoft YaHei',sans-serif";
	$styles = array(
		'page'   => 'margin:0;padding:28px 12px;background:#f4f2ec;',
		'shell'  => 'width:600px;max-width:100%;margin:0 auto;background:#ffffff;border:1px solid #d8d4c8;',
		'head'   => 'padding:22px 28px 12px;border-bottom:3px double #1a1a1a;font-family:' . $font . ';',
		'title'  => 'margin:0 0 2px;font-size:17px;font-weight:700;letter-spacing:.12em;color:#1a1a1a;',
		'date'   => 'margin:0;font-size:11px;letter-spacing:.08em;color:#8a857a;',
		'body'   => 'padding:22px 28px;font-family:' . $font . ';font-size:14px;line-height:1.75;color:#26221c;',
		'foot'   => 'padding:14px 28px 18px;border-top:1px solid #d8d4c8;font-family:' . $font . ';'
			. 'font-size:11px;color:#8a857a;',
		'foot_a' => 'color:#8a857a;text-decoration:underline;',
	);

	$html  = '<!DOCTYPE html>' . "\n"
		. '<html xmlns="http://www.w3.org/1999/xhtml" lang="' . esc_attr( get_bloginfo( 'language' ) ) . '">' . "\n"
		. '<head><meta charset="utf-8" /><meta name="viewport" content="width=device-width,initial-scale=1" /></head>' . "\n"
		. '<body style="' . $styles['page'] . '">' . "\n"
		. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td align="center" style="' . $styles['page'] . '">'
		. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="np-mail" style="' . $styles['shell'] . '">'
		. '<tr><td style="' . $styles['head'] . '">'
		. '<p style="' . $styles['title'] . '">' . esc_html( $name ) . '</p>'
		. '<p style="' . $styles['date'] . '">' . esc_html( date_i18n( get_option( 'date_format' ) ) ) . '</p>'
		. '</td></tr>'
		. '<tr><td style="' . $styles['body'] . '">' . $body . '</td></tr>'
		. '<tr><td style="' . $styles['foot'] . '">'
		. '<a href="' . esc_url( $home ) . '" style="' . $styles['foot_a'] . '">' . esc_html( $name ) . '</a>'
		. '</td></tr>'
		. '</table></td></tr></table></body></html>';
	return $html;
}
