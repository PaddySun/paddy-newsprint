<?php
/**
 * Paddysun Newsprint — 报纸风格的极简中文博客块主题
 *
 * 设计基线：Typora Newsprint（文章页）+ Broadside（报纸版面）+ clreq（中文排印）。
 * 原则：零外部请求、零框架依赖、按需加载（公式/图表/高亮仅在内容存在时载入）；
 * 全部模板区块化，色板/字体/间距由 theme.json 提供，站点编辑器所见即所得。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PADDYSUN_NS_VERSION', '0.10.13' );
define( 'PADDYSUN_NS_DIR', get_template_directory() );
define( 'PADDYSUN_NS_URI', get_template_directory_uri() );

require_once PADDYSUN_NS_DIR . '/inc/content-filters.php';
require_once PADDYSUN_NS_DIR . '/inc/html-to-markdown.php';
require_once PADDYSUN_NS_DIR . '/inc/markdown-support.php';
require_once PADDYSUN_NS_DIR . '/inc/llms.php';
require_once PADDYSUN_NS_DIR . '/inc/llms-settings.php';
require_once PADDYSUN_NS_DIR . '/inc/schema.php';
require_once PADDYSUN_NS_DIR . '/inc/block-hooks.php';
require_once PADDYSUN_NS_DIR . '/inc/toc-settings.php';
require_once PADDYSUN_NS_DIR . '/inc/img-fallback.php';
require_once PADDYSUN_NS_DIR . '/inc/login-style.php';

/* ------------------------------------------------------------
 * 主题初始化
 * ---------------------------------------------------------- */
function paddysun_ns_setup() {
	load_theme_textdomain( 'paddysun-newsprint', PADDYSUN_NS_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);
	add_theme_support( 'custom-logo', array( 'height' => 120, 'width' => 480, 'flex-height' => true, 'flex-width' => true ) );

	// 编辑器所见即所得：整份前台样式 + 编辑器专属微调一并载入画布（theme.json 的令牌两端共用）。
	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'style.css', 'editor-style.css' ) );

	// 导航区块未指定菜单时，回退到分配在此位置的经典菜单（WP_Navigation_Fallback 读取 primary）。
	register_nav_menus( array( 'primary' => __( '主导航（报头下方）', 'paddysun-newsprint' ) ) );
}
add_action( 'after_setup_theme', 'paddysun_ns_setup' );

/* ------------------------------------------------------------
 * 资源加载：单 CSS；按需 JS；版本号双轨——主 CSS/login.css/vendor 库用
 * filemtime（文件变动自动换 URL），主题自有 JS 用 PADDYSUN_NS_VERSION 常量
 * （cache-buster：改 JS 必须升版本，0.9.2 教训）（审查 C-F21 表述精确化）
 * ---------------------------------------------------------- */
function paddysun_ns_assets() {
	$css = PADDYSUN_NS_DIR . '/style.css';
	wp_enqueue_style( 'paddysun-newsprint', PADDYSUN_NS_URI . '/style.css', array(), filemtime( $css ) );
	wp_style_add_data( 'paddysun-newsprint', 'path', $css );

	// 「桌面」色只在前台给 html：编辑器画布保持纸面色，与文章观感一致。
	wp_add_inline_style( 'paddysun-newsprint', 'html{background-color:var(--wp--preset--color--paper-shade,#e7dfce)}' );

	if ( is_singular() ) {
		$paddysun_post = get_post();
		if ( $paddysun_post instanceof WP_Post ) {
			paddysun_ns_maybe_enqueue_rich_renderers( $paddysun_post->post_content );

			// 移动端宽表收起（约 0.8KB，仅正文含表格的文章加载）
			if ( false !== strpos( $paddysun_post->post_content, '<table' ) ) {
				wp_enqueue_script(
					'paddysun-table-collapse',
					PADDYSUN_NS_URI . '/assets/js/table-collapse.js',
					array(),
					PADDYSUN_NS_VERSION,
					true
				);
				wp_localize_script(
					'paddysun-table-collapse',
					'PADDYSUN_TABLE',
					array(
						'expand' => __( '宽表已收起 · 展开全表', 'paddysun-newsprint' ),
						'fold'   => __( '收起全表', 'paddysun-newsprint' ),
					)
				);
			}

			// 复制 Markdown 按钮（defer，约 1KB）：密码文章 / 单篇关闭导出时不加载（站长裁决 2026-09-12）
			if ( '' === $paddysun_post->post_password && paddysun_ns_copy_enabled( $paddysun_post->ID ) ) {
				wp_enqueue_script(
					'paddysun-copymd',
					PADDYSUN_NS_URI . '/assets/js/copy-md.js',
					array(),
					PADDYSUN_NS_VERSION,
					true
				);
				wp_localize_script(
					'paddysun-copymd',
					'PADDYSUN_MD',
					array(
						'restUrl'   => esc_url_raw( rest_url( 'paddysun/v1/posts/' . $paddysun_post->ID . '/markdown' ) ),
						'hasSource' => (bool) get_post_meta( $paddysun_post->ID, '_paddysun_source_md', true ),
						'copying'   => __( '转换中…', 'paddysun-newsprint' ),
						'done'      => __( 'Markdown 已复制', 'paddysun-newsprint' ),
						'failed'    => __( '复制失败，请重试', 'paddysun-newsprint' ),
					)
				);
			}

			// 评论折叠：评论开放**或**存在评论的文章页加载（关闭评论后存量来信
			// 仍需折叠交互；审查 C-F03：原注释误写「且」）
			if ( comments_open( $paddysun_post ) || (int) get_comments_number( $paddysun_post ) > 0 ) {
				wp_enqueue_script(
					'paddysun-comments-fold',
					PADDYSUN_NS_URI . '/assets/js/comments-fold.js',
					array(),
					PADDYSUN_NS_VERSION,
					true
				);
				wp_localize_script(
					'paddysun-comments-fold',
					'PADDYSUN_COMMENTS',
					array(
						'expand' => __( '展开{n}条来信', 'paddysun-newsprint' ),
						'fold'   => __( '收起来信', 'paddysun-newsprint' ),
					)
				);
			}
		}
	}
}
add_action( 'wp_enqueue_scripts', 'paddysun_ns_assets' );

/**
 * 检测正文是否包含公式 / Mermaid / 代码块，按需加载对应渲染器。
 * 检测基于原始内容（KaTeX 在服务端尚未渲染，$...$ 原文仍在 HTML 中）。
 */
function paddysun_ns_maybe_enqueue_rich_renderers( $content ) {
	$has_math    = (bool) preg_match( '/\$\$[^$]+\$\$|\$[^\s$][^$]*[^\s$]\$|\\\\\(|\\\\\[/', $content );
	// mermaid 探测：显式类 + 无类代码块首行关键字（渲染层会补类，此处需同步认）
	$has_mermaid = false !== stripos( $content, 'language-mermaid' )
		|| false !== stripos( $content, 'np-mermaid' )
		|| (bool) preg_match( '/<code[^>]*>\s*(?:&lt;)?(?:flowchart|graph\s+(?:TD|LR|TB|RL)|sequenceDiagram|classDiagram|stateDiagram|erDiagram|journey|gantt|pie|mindmap|timeline|quadrantChart|gitGraph|sankey-beta|sankey|xychart-beta|block-beta|architecture|packet)\b/i', $content );

	// 长内容折叠（0.10.0 U2）：正文存在代码块或 Mermaid 才加载，
	// 渐进增强——无 JS 时不折叠、全文可读。
	if ( $has_mermaid || false !== stripos( $content, '<pre' ) || false !== stripos( $content, '<img' ) ) {
		wp_enqueue_script(
			'paddysun-content-fold',
			PADDYSUN_NS_URI . '/assets/js/content-fold.js',
			array(),
			PADDYSUN_NS_VERSION,
			true
		);
		wp_localize_script(
			'paddysun-content-fold',
			'PADDYSUN_FOLD',
			array(
				'expand'   => __( '展开', 'paddysun-newsprint' ),
				'collapse' => __( '收起', 'paddysun-newsprint' ),
			)
		);
	}

	if ( $has_math ) {
		$v = filemtime( PADDYSUN_NS_DIR . '/assets/vendor/katex/katex.min.js' );
		wp_enqueue_style( 'paddysun-katex', PADDYSUN_NS_URI . '/assets/vendor/katex/katex.min.css', array(), $v );
		wp_enqueue_script( 'paddysun-katex', PADDYSUN_NS_URI . '/assets/vendor/katex/katex.min.js', array(), $v, true );
		wp_enqueue_script( 'paddysun-katex-auto', PADDYSUN_NS_URI . '/assets/vendor/katex/contrib/auto-render.min.js', array( 'paddysun-katex' ), $v, true );
		wp_enqueue_script( 'paddysun-katex-init', PADDYSUN_NS_URI . '/assets/js/katex-init.js', array( 'paddysun-katex-auto' ), PADDYSUN_NS_VERSION, true );
	}

	if ( $has_mermaid ) {
		$v = filemtime( PADDYSUN_NS_DIR . '/assets/vendor/mermaid/mermaid.min.js' );
		wp_enqueue_script( 'paddysun-mermaid', PADDYSUN_NS_URI . '/assets/vendor/mermaid/mermaid.min.js', array(), $v, true );
		wp_enqueue_script( 'paddysun-mermaid-init', PADDYSUN_NS_URI . '/assets/js/mermaid-init.js', array( 'paddysun-mermaid' ), PADDYSUN_NS_VERSION, true );
	}

	// 代码高亮 + 一键复制：只要存在「非 mermaid」的 pre>code 就加载
	// （0.5.0 修正：混合文章——代码 + 图表——此前误判为无需高亮；
	//   mermaid 块渲染期已转为图表容器，不会被 hljs 误处理）
	$has_plain_code = (bool) preg_match( '#<pre(?![^>]*language-mermaid)[^>]*>\s*<code(?![^>]*language-mermaid)#i', $content );
	if ( $has_plain_code ) {
		$v = filemtime( PADDYSUN_NS_DIR . '/assets/vendor/highlight/highlight.min.js' );
		wp_enqueue_script( 'paddysun-highlight', PADDYSUN_NS_URI . '/assets/vendor/highlight/highlight.min.js', array(), $v, true );
		wp_enqueue_script( 'paddysun-highlight-init', PADDYSUN_NS_URI . '/assets/js/highlight-init.js', array( 'paddysun-highlight' ), PADDYSUN_NS_VERSION, true );
		wp_enqueue_style( 'paddysun-highlight-css', PADDYSUN_NS_URI . '/assets/vendor/highlight/ascetic.min.css', array(), $v );
		wp_enqueue_script(
			'paddysun-code-copy',
			PADDYSUN_NS_URI . '/assets/js/code-copy.js',
			array(),
			PADDYSUN_NS_VERSION,
			true
		);
		wp_localize_script(
			'paddysun-code-copy',
			'PADDYSUN_CODE',
			array(
				'done'   => __( '代码已复制', 'paddysun-newsprint' ),
				'failed' => __( '复制失败，请重试', 'paddysun-newsprint' ),
			)
		);
	}
}

/* 字体 preload（核心只输出 @font-face，不做 preload；正文拉丁衬线 + 报头/标题
   显示字体 Libre Caslon 两枚首屏必用。哥特体 UnifrakturMaguntia 仅 masthead
   大字使用且字体子集小，未 preload——审查 C-F08 原注释误标「哥特体」） */
function paddysun_ns_font_preload() {
	$fonts = array(
		'source-serif-4-normal.woff2',
		'libre-caslon-display-400.woff2',
	);
	foreach ( $fonts as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( PADDYSUN_NS_URI . '/assets/fonts/' . $font )
		);
	}
}
add_action( 'wp_head', 'paddysun_ns_font_preload', 1 );

/* ------------------------------------------------------------
 * 头部清理：仅移除展示性的 emoji 替换脚本/样式（中文站点用系统 emoji 即可）。
 * 审核手册 required 项禁止移除非展示性 hooks（wp_generator / rsd_link /
 * feed_links_extra / wp_shortlink_wp_head / adjacent_posts_rel_link_wp_head 等），
 * 故不再触碰它们。
 * ---------------------------------------------------------- */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
add_filter( 'emoji_svg_url', '__return_false' );

/* ------------------------------------------------------------
 * 本地头像徽章（F-04）：gravatar.com 在国内不可达且违背零外部请求
 * 承诺，前台一律以内联 SVG 首字母徽章替代（纸影底 + 墨字铅印感）。
 * 仅前台；后台（用户列表等）保持原生头像。
 * ------------------------------------------------------------ */
function paddysun_ns_local_avatar( $avatar, $id_or_email, $args ) {
	if ( is_admin() ) {
		return $avatar;
	}
	$name = '';
	if ( $id_or_email instanceof WP_Comment ) {
		$name = $id_or_email->comment_author;
	} elseif ( $id_or_email instanceof WP_User ) {
		$name = $id_or_email->display_name;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$name = get_the_author_meta( 'display_name', $id_or_email->post_author );
	} elseif ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', (int) $id_or_email );
		$name = $user ? $user->display_name : '';
	} elseif ( is_string( $id_or_email ) && str_contains( $id_or_email, '@' ) ) {
		$user = get_user_by( 'email', $id_or_email );
		$name = $user ? $user->display_name : strstr( $id_or_email, '@', true );
	}
	$initial = mb_substr( trim( (string) $name ), 0, 1 );
	if ( '' === $initial ) {
		$initial = '?';
	}

	$size = max( 24, (int) ( $args['size'] ?? 40 ) );
	$fs   = (int) round( $size * 0.5 );
	$svg  = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d">' .
		'<rect width="%1$d" height="%1$d" fill="#e7dfce"/>' .
		'<text x="50%%" y="54%%" text-anchor="middle" dominant-baseline="central" ' .
		'font-family="Georgia, \'Songti SC\', serif" font-size="%2$d" fill="#3a3630">%3$s</text></svg>',
		$size,
		$fs,
		esc_html( $initial )
	);
	$url = 'data:image/svg+xml;charset=utf-8,' . rawurlencode( $svg );

	$alt = $name ? sprintf( /* translators: %s: 作者名 */ __( '%s 的头像', 'paddysun-newsprint' ), $name ) : __( '访客头像', 'paddysun-newsprint' );
	// 注：data: URI 不可过 esc_url（会被协议白名单清空）；SVG 已整体 rawurlencode，属性内安全
	return sprintf(
		'<img src="%1$s" alt="%2$s" width="%3$d" height="%3$d" class="%4$s" loading="lazy" decoding="async" />',
		$url,
		esc_attr( $alt ),
		$size,
		esc_attr( $args['class'] ?? 'avatar local-avatar' )
	);
}
add_filter( 'pre_get_avatar', 'paddysun_ns_local_avatar', 10, 3 );

/* ------------------------------------------------------------
 * REST 输出中文原样（不转 \uXXXX），复制按钮与 AI 抓取可读性更好
 * ---------------------------------------------------------- */
add_filter(
	'rest_json_encode_options',
	static function ( $options ) {
		$options |= JSON_UNESCAPED_UNICODE;
		return $options;
	}
);

/* ------------------------------------------------------------
 * 区块模式分类 / 区块样式（替代原 Customizer「排印」开关）
 * ---------------------------------------------------------- */
function paddysun_ns_pattern_categories() {
	register_block_pattern_category(
		'paddysun-newsprint',
		array(
			'label'       => __( '报纸版面', 'paddysun-newsprint' ),
			'description' => __( 'Paddysun Newsprint 的报头、头条、要目、订阅框、文章头尾、评论区等版面组件。', 'paddysun-newsprint' ),
		)
	);
}
add_action( 'init', 'paddysun_ns_pattern_categories' );

function paddysun_ns_block_styles() {
	register_block_style(
		'core/post-content',
		array(
			'name'  => 'np-book',
			'label' => __( '书卷式（段首缩进 + 两端对齐）', 'paddysun-newsprint' ),
		)
	);
	register_block_style(
		'core/separator',
		array(
			'name'  => 'np-double',
			'label' => __( '双规线', 'paddysun-newsprint' ),
		)
	);
	register_block_style(
		'core/group',
		array(
			'name'  => 'np-boxed',
			'label' => __( '框线盒', 'paddysun-newsprint' ),
		)
	);
}
add_action( 'init', 'paddysun_ns_block_styles' );

/* ------------------------------------------------------------
 * 「文章内容」区块变体：目录形态三选一（V-07）。
 * 站长在站点编辑器里选中文章内容区块 → 工具栏「变换/替换」即可切换：
 *  - 目录（头部）np-toc-head：正文前目录盒（默认，兼容裸 np-content 类）
 *  - 目录（悬浮）np-toc-float：≥1240px 固定右侧 + 滚动跟随当前章节，
 *    窄屏自动回退头部目录盒
 *  - 无目录 np-toc-off：不注入目录
 * ---------------------------------------------------------- */
function paddysun_ns_post_content_variations( $variations, $block_type ) {
	if ( 'core/post-content' !== $block_type->name ) {
		return $variations;
	}
	$variations[] = array(
		'name'        => 'np-toc-head',
		'title'       => __( '文章内容（目录 · 头部）', 'paddysun-newsprint' ),
		'description' => __( '正文前显示「目 录」盒（二级标题 ≥ 3 个时）。', 'paddysun-newsprint' ),
		'attributes'  => array( 'className' => 'np-toc-head' ),
		'scope'       => array( 'inserter', 'transform' ),
	);
	$variations[] = array(
		'name'        => 'np-toc-float',
		'title'       => __( '文章内容（目录 · 右侧悬浮）', 'paddysun-newsprint' ),
		'description' => __( '宽屏时目录固定右侧，滚动跟随当前阅读章节；窄屏回退头部目录盒。', 'paddysun-newsprint' ),
		'attributes'  => array( 'className' => 'np-toc-float' ),
		'scope'       => array( 'inserter', 'transform' ),
	);
	$variations[] = array(
		'name'        => 'np-toc-off',
		'title'       => __( '文章内容（无目录）', 'paddysun-newsprint' ),
		'description' => __( '不注入目录。', 'paddysun-newsprint' ),
		'attributes'  => array( 'className' => 'np-toc-off' ),
		'scope'       => array( 'inserter', 'transform' ),
	);
	return $variations;
}
add_filter( 'get_block_type_variations', 'paddysun_ns_post_content_variations', 10, 2 );

/* ------------------------------------------------------------
 * Feed 输出：主题不控制全文/摘要（0.6.1 起移除强制全文过滤器，由系统
 * 「设置 → 阅读」决定），标题转义在 Feed 内跳过；唯一保留的改动是
 * content-filters 对 Feed 图片注入 loading=lazy（0.8.0 旧全量行为，
 * 审查 C-F09：原「完全放弃控制」与该保留行为矛盾，统一口径）.
 * ------------------------------------------------------------ */

/* ------------------------------------------------------------
 * 阅读时间（中文 400 字/分钟，拉丁 200 词/分钟）
 * ---------------------------------------------------------- */
function paddysun_ns_reading_time( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$content = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
	preg_match_all( '/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]/u', $content, $cjk );
	preg_match_all( '/[A-Za-z0-9]+/', $content, $latin );
	$minutes = ( count( $cjk[0] ) / 400 ) + ( count( $latin[0] ) / 200 );
	return max( 1, (int) ceil( $minutes ) );
}
