<?php
/**
 * 块主题适配钩子。
 *
 * 原则：不注册自定义区块、不用短代码（审核手册「Do not include」），报纸版面的
 * 动态部分一律用「核心区块 + 类名标记 + 渲染过滤器」实现，站点编辑器里看到的
 * 仍是普通核心区块，站长可随意移动、改文案、删除。
 *
 * 类名标记一览（写在区块的「高级 → 附加 CSS 类」里）：
 *  - np-lead-body     文章内容区块（首页主稿）：纯文字段落 10 段 / 2200 字
 *                     先到为准（与侧栏 8 段/1400 字同机制对称）+ 阅读全文；
 *                     栏内超出高度由 CSS 逐行隐藏（见 style.css §5 底对齐）
 *  - np-aside-body    文章内容区块（首页侧栏）：全量正文（段落/引用/列表），
 *                     剔除表格/代码/图片/公式/Mermaid
 *  - np-content       文章内容区块（文章页）：h2 ≥ 3 时在正文前注入目录盒
 *  - np-first-term    文章分类/标签区块：只保留第一个词条（眉题用）
 *  - np-sections      分类查询区块：未手选分类时，按最近更新排序并排除「未分类」
 *  - np-aside-fallback 文章模板区块：偏移量超出文章总数时改取最早一篇补位
 *  （动态令牌 {issue}/{volume}/{est}/{date}/{weekday}/{year}/{site} 无需
 *   类名，任意段落内都会替换）
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 判断区块是否带某个附加类名。
 *
 * @param array|WP_Block $block 解析后的区块数组或 WP_Block 实例。
 * @param string         $class 类名。
 * @return bool
 */
function paddysun_ns_block_has_class( $block, $class ) {
	if ( $block instanceof WP_Block ) {
		$attrs = $block->parsed_block['attrs'] ?? array();
	} else {
		$attrs = $block['attrs'] ?? array();
	}
	if ( empty( $attrs['className'] ) || ! is_string( $attrs['className'] ) ) {
		return false;
	}
	return in_array( $class, preg_split( '/\s+/', trim( $attrs['className'] ) ), true );
}

/**
 * 替换渲染结果中容器标签之间的文本，保留外层包装标签（含其 class / style）。
 *
 * @param string $content 区块渲染结果（形如 <tag …>…</tag>）。
 * @param string $text    新的纯文本（函数内转义）。
 * @return string
 */
function paddysun_ns_replace_inner_text( $content, $text ) {
	$replaced = preg_replace_callback(
		'/^(\s*<[^>]+>).*(<\/[^>]+>\s*)$/s',
		static function ( $m ) use ( $text ) {
			return $m[1] . esc_html( $text ) . $m[2];
		},
		$content
	);
	return is_string( $replaced ) ? $replaced : $content;
}

/* ------------------------------------------------------------
 * 1. 动态令牌段落（报头卷期 / 日期 / 页脚年份）
 * ---------------------------------------------------------- */

/**
 * 当前请求的令牌表（已转义）。
 *
 * @return array<string,string>
 */
function paddysun_ns_dynamic_tokens() {
	static $tokens = null;
	if ( null !== $tokens ) {
		return $tokens;
	}

	$issue = (int) wp_count_posts( 'post' )->publish;

	// 创刊年份 = 最早一篇已发布文章的年份；无文章时取当年。
	$oldest = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'orderby'          => 'date',
			'order'            => 'ASC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	$year = (int) date_i18n( 'Y' );
	$est  = $oldest ? (int) get_the_date( 'Y', $oldest[0] ) : $year;

	$tokens = array(
		'{issue}'   => esc_html( number_format_i18n( $issue ) ),
		'{volume}'  => esc_html( number_format_i18n( max( 1, $year - $est + 1 ) ) ),
		'{est}'     => esc_html( (string) $est ),
		'{date}'    => esc_html( date_i18n( 'Y年n月j日' ) ),
		'{weekday}' => esc_html( date_i18n( 'l' ) ),
		'{year}'    => esc_html( (string) $year ),
		'{site}'    => esc_html( get_bloginfo( 'name' ) ),
	);
	return $tokens;
}

function paddysun_ns_render_dynamic_paragraph( $content, $block ) {
	if ( false === strpos( $content, '{' ) ) {
		return $content;
	}
	/* 任意段落都做令牌替换（不要求 np-dynamic 类，站长在编辑器里随手
	   写 {year} 即可生效）；只替换完全匹配的 7 个字面量，JSON 示例等
	   花括号文本不受影响。 */
	return strtr( $content, paddysun_ns_dynamic_tokens() );
}
add_filter( 'render_block_core/paragraph', 'paddysun_ns_render_dynamic_paragraph', 10, 2 );

/**
 * 报头检索表单 action 可移植化（审查 E0-P3）：parts/header.html 是静态模板
 * 部件无法执行 PHP，检索表单 action="/" 硬编码在 wp:html 内——子目录安装
 * （siteurl 带路径）下会 404。渲染期把 action="/" 重写为 home_url('/')。
 */
function paddysun_ns_rewrite_search_action( $content ) {
	if ( false === strpos( $content, 'action="/"' ) ) {
		return $content;
	}
	return str_replace(
		'action="/"',
		'action="' . esc_url( home_url( '/' ) ) . '"',
		$content
	);
}
add_filter( 'render_block_core/html', 'paddysun_ns_rewrite_search_action', 10, 1 );

/* ------------------------------------------------------------
 * 2. 首页主稿正文：纯文字段落 10 段 / 2200 字先到为准，首字下沉双栏
 * ---------------------------------------------------------- */

/**
 * 取正文纯文字段落（跳过公式 / 代码 / 图片 / 引用），
 * 10 段或 2200 字先到为准——与侧栏（8 段/1400 字）同机制对称；
 * 栏内若仍超出高度，由 CSS overflow 逐行隐藏（0.8.4 站长裁决）。
 *
 * @param int $post_id 文章 ID。
 * @return string 段落 HTML；无合适段落返回空。
 */
function paddysun_ns_lead_body( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	if ( '' !== $post->post_password ) {
		return ''; // 密码文章不进首页摘要（站长裁决 2026-09-12）；「阅读全文」入口由调用方保留
	}
	if ( ! preg_match_all( '#<!-- wp:paragraph (.*?)-->(.*?)<!-- /wp:paragraph -->#s', $post->post_content, $m ) ) {
		// 经典内容：直接抓 <p>
		preg_match_all( '#<p>(.*?)</p>#s', $post->post_content, $m );
		$paras = $m[1];
	} else {
		$paras = array();
		foreach ( $m[2] as $inner ) {
			if ( preg_match( '#<p[^>]*>(.*?)</p>#s', $inner, $pm ) ) {
				$paras[] = $pm[1];
			}
		}
	}

	$out   = '';
	$count = 0;
	$chars = 0;
	foreach ( $paras as $p ) {
		if ( $count >= 10 || $chars >= 2200 ) {
			break;
		}
		// 跳过公式（KaTeX 原文）/图片/引用起头段；行内代码不再整段剔除（站长批注：正文被截得断续）
		if ( false !== strpos( $p, '$' ) || false !== strpos( $p, '<img' ) || false !== strpos( $p, '<blockquote' ) ) {
			continue;
		}
		$cls  = 0 === $count ? ' class="np-dropcap"' : '';
		$out .= '<p' . $cls . '>' . wp_kses_post( $p ) . '</p>';
		$count++;
		$chars += mb_strlen( wp_strip_all_tags( $p ) );
	}
	return $out;
}

function paddysun_ns_pre_render_lead_body( $pre_render, $parsed_block, $parent_block ) {
	if ( null !== $pre_render || 'core/post-content' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $pre_render;
	}
	if ( ! paddysun_ns_block_has_class( $parsed_block, 'np-lead-body' ) ) {
		return $pre_render;
	}
	$post_id = 0;
	if ( $parent_block instanceof WP_Block && ! empty( $parent_block->context['postId'] ) ) {
		$post_id = (int) $parent_block->context['postId'];
	}
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}

	$body = paddysun_ns_lead_body( $post_id );
	$more = '<p class="np-lead__more-wrap"><a class="np-lead__more" href="' . esc_url( get_permalink( $post_id ) ) . '">'
		. esc_html__( '阅读全文 →', 'paddysun-newsprint' ) . '</a></p>';
	if ( '' === $body ) {
		// 正文开头无纯文字段落（表格/图片起头）时，仍保留阅读全文入口
		return $more;
	}
	return '<div class="np-columns">' . $body . '</div>' . $more;
}
add_filter( 'pre_render_block', 'paddysun_ns_pre_render_lead_body', 10, 3 );

/* ------------------------------------------------------------
 * 2b. 首页侧栏（固定第 6 篇）：全量正文（段落/引用/列表），剔除表格/代码/
 *     图片/公式/Mermaid——侧栏窄幅放不下这些重元素（E-07）。
 *     复用 np-lead-body 的取段思路，预算为 8 段或 1400 字先到为准
 *     （审查 C-F07：「不限 4 段」是 0.6.0 前的旧残留）。
 * ---------------------------------------------------------- */
function paddysun_ns_aside_body( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	if ( '' !== $post->post_password ) {
		return ''; // 密码文章不进首页侧栏摘要
	}
	/* 长度上限（站长批注：与左中栏高度协调）：8 段或 1400 字先到为准；
	   截断发生在段落边界，末尾「读这篇文章 →」承接。 */
	$out   = '';
	$paras = 0;
	$chars = 0;
	foreach ( parse_blocks( $post->post_content ) as $b ) {
		$name = $b['blockName'] ?? '';
		if ( 'core/paragraph' === $name ) {
			$html = trim( $b['innerHTML'] ?? '' );
			if ( '' === $html ) {
				continue;
			}
			if ( false !== strpos( $html, '$' ) || false !== stripos( $html, '<img' ) || false !== stripos( $html, '<code' ) ) {
				continue; // 公式原文（KaTeX 前台才渲染）与段内行内图/行内代码——
				// 侧栏窄幅放不下重元素（审查 C-F04：剔除语义对齐 np-lead-body 的段落级判定）
			}
			$plain = wp_strip_all_tags( $html );
			if ( $paras >= 8 || $chars >= 1400 ) {
				break;
			}
			$out   .= do_blocks( $html );
			$paras++;
			$chars += mb_strlen( $plain );
		} elseif ( ( 'core/quote' === $name || 'core/list' === $name ) && $paras < 8 && $chars < 1400 ) {
			$out   .= do_blocks( serialize_block( $b ) );
			$chars += mb_strlen( wp_strip_all_tags( $b['innerHTML'] ?? '' ) );
		}
	}
	return $out;
}

function paddysun_ns_pre_render_aside_body( $pre_render, $parsed_block, $parent_block ) {
	if ( null !== $pre_render || 'core/post-content' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $pre_render;
	}
	if ( ! paddysun_ns_block_has_class( $parsed_block, 'np-aside-body' ) ) {
		return $pre_render;
	}
	$post_id = 0;
	if ( $parent_block instanceof WP_Block && ! empty( $parent_block->context['postId'] ) ) {
		$post_id = (int) $parent_block->context['postId'];
	}
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}
	$body = paddysun_ns_aside_body( $post_id );
	if ( '' === $body ) {
		// 无可展示段落（全文公式/表格/代码）时不占位
		return '';
	}
	return '<div class="np-aside__body">' . $body . '</div>';
}
add_filter( 'pre_render_block', 'paddysun_ns_pre_render_aside_body', 10, 3 );

/* ------------------------------------------------------------
 * 3. 文章页目录：注入到「文章内容」容器开头。
 *    形态由区块变体/附加类决定（V-07）：np-toc-off 关闭；
 *    np-toc-float 悬浮（≥1240px 固定右侧 + 滚动跟随）；其余
 *    （np-content / np-toc-head）头部目录盒。h2 ≥ 3 才注入。
 * ---------------------------------------------------------- */
function paddysun_ns_inject_toc( $content, $parsed_block, $block = null ) {
	if ( ! is_singular( 'post' ) ) {
		return $content;
	}
	/* 形态优先级：文章级设置（目录形态框）> 模板类（np-toc-float/np-toc-off/
	   np-content=头部）。文章级未设置时按模板走（single.html 默认悬浮）。 */
	$meta_form = function_exists( 'paddysun_ns_get_toc_form' ) ? paddysun_ns_get_toc_form() : '';
	if ( 'off' === $meta_form ) {
		return $content;
	}
	$float = 'float' === $meta_form ? true : ( 'head' === $meta_form ? false : paddysun_ns_block_has_class( $parsed_block, 'np-toc-float' ) );
	if ( ! $float && 'head' !== $meta_form ) {
		if ( paddysun_ns_block_has_class( $parsed_block, 'np-toc-off' ) ) {
			return $content;
		}
		if ( ! paddysun_ns_block_has_class( $parsed_block, 'np-content' ) && ! paddysun_ns_block_has_class( $parsed_block, 'np-toc-head' ) ) {
			return $content;
		}
	}
	$context_post_id = ( $block instanceof WP_Block ) ? (int) ( $block->context['postId'] ?? 0 ) : 0;
	if ( $context_post_id && $context_post_id !== get_queried_object_id() ) {
		return $content;
	}
	if ( ! function_exists( 'paddysun_ns_get_toc' ) ) {
		return $content;
	}
	$toc = paddysun_ns_get_toc( $float ? 'np-toc--float' : '' );
	if ( '' === $toc ) {
		return $content;
	}
	if ( $float ) {
		// 悬浮目录的滚动跟随脚本：仅在真正渲染出悬浮目录时加载（footer 输出）
		wp_enqueue_script(
			'paddysun-toc-float',
			PADDYSUN_NS_URI . '/assets/js/toc-float.js',
			array(),
			PADDYSUN_NS_VERSION,
			true
		);
	}
	$pos = strpos( $content, '>' );
	if ( false === $pos ) {
		return $toc . $content;
	}
	return substr( $content, 0, $pos + 1 ) . $toc . substr( $content, $pos + 1 );
}
add_filter( 'render_block_core/post-content', 'paddysun_ns_inject_toc', 10, 3 );

/* ------------------------------------------------------------
 * 4. 查询循环：分类模板内自动绑定当前分类；侧栏偏移越界兜底
 * ---------------------------------------------------------- */
function paddysun_ns_query_loop_vars( $query, $block, $page ) {
	$ctx = $block->context;

	// 分类查询（terms-query）→ 分类模板 → 内层查询循环：绑定到当前分类。
	if ( ! empty( $ctx['termId'] ) && ! empty( $ctx['taxonomy'] ) && empty( $ctx['query']['inherit'] ) ) {
		$query['tax_query'] = array(
			array(
				'taxonomy' => sanitize_key( $ctx['taxonomy'] ),
				'field'    => 'term_id',
				'terms'    => array( (int) $ctx['termId'] ),
			),
		);
	}

	// 首页侧栏「随笔」分类绑定（下方 np-aside 分支）：实际永不命中——
	// query_loop_block_query_vars 收到的是 post-template 块，np-aside 类
	// 在 query 块上，paddysun_ns_block_has_class 检查落空。侧栏实际行为
	// =「固定第 6 篇」（offset=5），恰与 patterns/lead-trio.php 描述一致。
	// 站长裁决（2026-09-13）：维持现状，行为即设计；本分支作为
	// 「标记移到 post-template 即可真正绑定」的存档保留，不改行为。
	if ( paddysun_ns_block_has_class( $block, 'np-aside' ) && empty( $query['tax_query'] ) ) {
		$essay = get_term_by( 'slug', 'essay', 'category' );
		if ( $essay && ! is_wp_error( $essay ) ) {
			$query['tax_query'] = array(
				array(
					'taxonomy' => 'category',
					'field'    => 'term_id',
					'terms'    => array( (int) $essay->term_id ),
				),
			);
			// 随笔池子小，原 offset=5 会取空——绑定时回到最新一篇
			$query['offset'] = 0;
		} else {
			$uncat = get_term_by( 'slug', 'uncategorized', 'category' );
			if ( $uncat && ! is_wp_error( $uncat ) ) {
				$query['category__not_in'] = array( (int) $uncat->term_id );
			}
		}
	}

	// 侧栏：稿件不足时取最早一篇补位，保持版面完整。
	if ( paddysun_ns_block_has_class( $block, 'np-aside-fallback' ) ) {
		$offset = isset( $query['offset'] ) ? (int) $query['offset'] : 0;
		$total  = (int) wp_count_posts( 'post' )->publish;
		if ( $offset > 0 && $total <= $offset ) {
			$query['offset'] = 0;
			$query['order']  = 'ASC';
		}
	}
	return $query;
}
add_filter( 'query_loop_block_query_vars', 'paddysun_ns_query_loop_vars', 10, 3 );

/* ------------------------------------------------------------
 * 5. 本期要目：分类按「最近更新」排序、排除未分类（站长手选分类时不干预）
 * ---------------------------------------------------------- */
function paddysun_ns_section_category_ids( $limit ) {
	$cats = get_categories(
		array(
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => max( 8, $limit + 2 ),
			'parent'     => 0,
			'hide_empty' => true,
		)
	);
	$cats = array_filter(
		$cats,
		static function ( $c ) {
			return 'uncategorized' !== $c->slug;
		}
	);
	$latest = array();
	foreach ( $cats as $c ) {
		$ids                   = get_posts(
			array(
				'cat'              => $c->term_id,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		$latest[ $c->term_id ] = $ids ? get_post_field( 'post_date', $ids[0] ) : '0000';
	}
	usort(
		$cats,
		static function ( $a, $b ) use ( $latest ) {
			return strcmp( $latest[ $b->term_id ], $latest[ $a->term_id ] );
		}
	);
	return array_map( 'intval', wp_list_pluck( array_slice( $cats, 0, $limit ), 'term_id' ) );
}

function paddysun_ns_sections_terms_query( $parsed_block ) {
	if ( 'core/terms-query' !== ( $parsed_block['blockName'] ?? '' ) || ! paddysun_ns_block_has_class( $parsed_block, 'np-sections' ) ) {
		return $parsed_block;
	}
	if ( ! empty( $parsed_block['attrs']['termQuery']['include'] ) ) {
		return $parsed_block; // 站长在编辑器里手选了分类，尊重之。
	}
	$limit = (int) ( $parsed_block['attrs']['termQuery']['perPage'] ?? 6 );
	$ids   = paddysun_ns_section_category_ids( max( 1, $limit ) );
	if ( $ids ) {
		$parsed_block['attrs']['termQuery']['include'] = $ids;
	}
	return $parsed_block;
}
add_filter( 'render_block_data', 'paddysun_ns_sections_terms_query' );

/* ------------------------------------------------------------
 * 6. 阅读时间：中文按 400 字/分钟、拉丁按 200 词/分钟（核心区块按字符计，中英混排偏差大）
 * ---------------------------------------------------------- */
function paddysun_ns_render_time_to_read( $content, $parsed_block, $block = null ) {
	$post_id = ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) ? (int) $block->context['postId'] : get_the_ID();
	if ( ! $post_id || ! function_exists( 'paddysun_ns_reading_time' ) ) {
		return $content;
	}
	/* translators: %d: 分钟数 */
	$text = sprintf( __( '约 %d 分钟', 'paddysun-newsprint' ), paddysun_ns_reading_time( $post_id ) );
	return paddysun_ns_replace_inner_text( $content, $text );
}
add_filter( 'render_block_core/post-time-to-read', 'paddysun_ns_render_time_to_read', 10, 2 );

/* ------------------------------------------------------------
 * 7. 眉题只保留第一个词条
 * ---------------------------------------------------------- */
function paddysun_ns_render_first_term( $content, $block ) {
	if ( ! paddysun_ns_block_has_class( $block, 'np-first-term' ) ) {
		return $content;
	}
	if ( preg_match( '/^(\s*<[^>]+>)\s*(<a\b[^>]*>.*?<\/a>)/is', $content, $m ) && preg_match( '/(<\/[^>]+>\s*)$/', $content, $e ) ) {
		return $m[1] . $m[2] . $e[1];
	}
	return $content;
}
add_filter( 'render_block_core/post-terms', 'paddysun_ns_render_first_term', 10, 2 );

/* ------------------------------------------------------------
 * 8. 评论标题「读者来信（N）」
 * ---------------------------------------------------------- */
function paddysun_ns_render_comments_title( $content, $parsed_block, $block = null ) {
	if ( '' === trim( $content ) ) {
		return $content;
	}
	$post_id = ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) ? (int) $block->context['postId'] : get_the_ID();
	/* translators: %s: 评论数 */
	$text = sprintf( __( '读者来信（%s）', 'paddysun-newsprint' ), number_format_i18n( get_comments_number( $post_id ) ) );
	return paddysun_ns_replace_inner_text( $content, $text );
}
add_filter( 'render_block_core/comments-title', 'paddysun_ns_render_comments_title', 10, 2 );

/* ------------------------------------------------------------
 * 8b. 作者徽章（阶段三评论优化）：评论者与文章作者同人时，
 *     署名后缀描边小徽「作者」。上下文从第三参 WP_Block 读取
 *     （commentId 由评论模板提供）；编辑器画布不受 render 过滤影响。
 * ---------------------------------------------------------- */
function paddysun_ns_comment_author_badge( $content, $parsed_block, $block = null ) {
	if ( ! $block instanceof WP_Block || empty( $block->context['commentId'] ) ) {
		return $content;
	}
	$comment = get_comment( (int) $block->context['commentId'] );
	if ( ! $comment instanceof WP_Comment || ! $comment->user_id ) {
		return $content;
	}
	$post_id = ! empty( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();
	if ( ! $post_id || (int) get_post_field( 'post_author', $post_id ) !== (int) $comment->user_id ) {
		return $content;
	}
	$badge = ' <span class="np-comment__badge">' . esc_html__( '作者', 'paddysun-newsprint' ) . '</span>';
	$pos   = strrpos( $content, '</div>' );
	// 徽章插入署名 div 内部（div 为块级，插在外部会掉行）
	return false === $pos
		? $content . $badge
		: substr_replace( $content, $badge . '</div>', $pos, strlen( '</div>' ) );
}
add_filter( 'render_block_core/comment-author-name', 'paddysun_ns_comment_author_badge', 10, 3 );

/* ------------------------------------------------------------
 * 9. 检索页标题「检索「关键词」」
 * ---------------------------------------------------------- */
function paddysun_ns_render_search_title( $content, $parsed_block, $block = null ) {
	$attrs = ( $block instanceof WP_Block ) ? (array) ( $block->attributes ?? array() ) : (array) ( $parsed_block['attrs'] ?? array() );
	if ( 'search' !== ( $attrs['type'] ?? '' ) || ! is_search() ) {
		return $content;
	}
	/* translators: %s: 检索词 */
	// get_search_query(false) 取原始值：下游 paddysun_ns_replace_inner_text 会
	// 统一 esc_html——默认转义形态再转义一次会让 & 显示为 &amp;（审查 E0-P3）
	$text = sprintf( __( '检索「%s」', 'paddysun-newsprint' ), get_search_query( false ) );
	return paddysun_ns_replace_inner_text( $content, $text );
}
add_filter( 'render_block_core/query-title', 'paddysun_ns_render_search_title', 10, 2 );

/* ------------------------------------------------------------
 * 11. 代码块一键复制（V-10）：前台文章页为代码块右上角注入「复制」按钮。
 *     走 render_block 过滤（非 the_content），REST / 复制 MD / llms-full
 *     的内容管线不受影响；mermaid 代码块（图表容器）不注入。
 * ---------------------------------------------------------- */
function paddysun_ns_code_copy_button( $content, $block ) {
	if ( ! is_singular() || '' === trim( $content ) ) {
		return $content;
	}
	if ( false !== stripos( $content, 'language-mermaid' ) ) {
		return $content; // mermaid 渲染为图表容器，无复制按钮
	}
	$button = '<button type="button" class="np-code-copy" aria-label="' . esc_attr__( '复制代码', 'paddysun-newsprint' ) . '">'
		. '<svg class="np-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" aria-hidden="true" focusable="false"><polyline points="168 168 216 168 216 40 88 40 88 88" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><rect x="40" y="88" width="128" height="128" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg></button>';
	return '<div class="np-code-wrap">' . $button . $content . '</div>';
}
add_filter( 'render_block_core/code', 'paddysun_ns_code_copy_button', 20, 2 );

/* ------------------------------------------------------------
 * 10. 摘要跳过公式段（V-02）：摘要若含未渲染的 $...$ LaTeX 原文，
 *     改取正文首个「纯文字段落」（无公式/代码/图/引用/表格）。
 *     仅动渲染层：入库摘要、RSS、REST 均不受影响。
 * ---------------------------------------------------------- */
function paddysun_ns_render_excerpt_skip_math( $content, $parsed_block, $block = null ) {
	if ( false === strpos( $content, '$' ) ) {
		return $content;
	}
	$post_id = ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) ? (int) $block->context['postId'] : get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post ) {
		return $content;
	}

	if ( ! preg_match_all( '#<!-- wp:paragraph (.*?)-->(.*?)<!-- /wp:paragraph -->#s', $post->post_content, $m ) ) {
		return $content;
	}
	$fallback = '';
	foreach ( $m[2] as $inner ) {
		if ( ! preg_match( '#<p[^>]*>(.*?)</p>#s', $inner, $pm ) ) {
			continue;
		}
		$p = $pm[1];
		if ( false !== strpos( $p, '$' ) || false !== strpos( $p, '<code' ) || false !== strpos( $p, '<img' )
			|| false !== strpos( $p, '<blockquote' ) || false !== strpos( $p, '<table' ) ) {
			continue;
		}
		$fallback = $p;
		break;
	}
	if ( '' === $fallback ) {
		return $content;
	}

	$attrs  = ( $block instanceof WP_Block ) ? (array) ( $block->attributes ?? array() ) : (array) ( $parsed_block['attrs'] ?? array() );
	$length = isset( $attrs['excerptLength'] ) ? (int) $attrs['excerptLength'] : 28;
	// 与核心摘要同一条截断路径（zh_CN 下按字符计），观感一致
	$text = wp_trim_words( wp_strip_all_tags( $fallback ), max( 10, $length ), '…' );

	$replaced = preg_replace_callback(
		'/(<p class="wp-block-post-excerpt__excerpt">).*?(<\/p>)/is',
		static function ( $mm ) use ( $text ) {
			return $mm[1] . esc_html( $text ) . $mm[2];
		},
		$content
	);
	return is_string( $replaced ) ? $replaced : $content;
}
add_filter( 'render_block_core/post-excerpt', 'paddysun_ns_render_excerpt_skip_math', 20, 2 );

/**
 * 编辑器保存普通核心区块，只有前台 HTML 渲染才增强为提醒确认弹窗。
 * 保留 maintenance-notice slug 及旧 dialog 内容；不递归渲染部件。
 */
function paddysun_ns_render_notice( $content, $parsed_block ) {
	if ( is_admin() || is_feed() || wp_doing_ajax() || wp_doing_cron()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
		return $content;
	}
	if ( 'maintenance-notice' !== ( $parsed_block['attrs']['slug'] ?? '' ) || '' === trim( $content ) ) {
		return $content;
	}
	$theme = $parsed_block['attrs']['theme'] ?? '';
	if ( $theme && ! in_array( $theme, array( get_stylesheet(), get_template() ), true ) ) {
		return $content;
	}

	$html = new WP_HTML_Tag_Processor( $content );
	$legacy = false;
	while ( $html->next_tag( 'DIALOG' ) ) {
		if ( $html->has_class( 'np-notice' ) ) {
			$legacy = true;
			break;
		}
	}
	if ( ! $legacy ) {
		$marker = new WP_HTML_Tag_Processor( $content );
		if ( ! $marker->next_tag( array( 'class_name' => 'np-notice-wrap' ) ) ) {
			return $content;
		}
		$content = '<dialog class="np-notice">' . $content . '</dialog>';
	}
	$html = new WP_HTML_Tag_Processor( $content );
	$title_id = wp_unique_id( 'paddysun-notice-title-' );
	$labelled = false;
	while ( $html->next_tag() ) {
		if ( 'DIALOG' === $html->get_tag() && $html->has_class( 'np-notice' ) ) {
			$html->set_attribute( 'data-np-notice-front', '1' );
			$html->set_attribute( 'aria-label', __( '提醒确认', 'paddysun-newsprint' ) );
			$html->set_attribute( 'aria-labelledby', $title_id );
			$html->remove_attribute( 'open' );
		} elseif ( ! $labelled && $html->has_class( 'np-notice__title' ) ) {
			$html->set_attribute( 'id', $title_id );
			$labelled = true;
		}
	}
	$content = $html->get_updated_html();
	if ( ! $labelled ) {
		$html = new WP_HTML_Tag_Processor( $content );
		while ( $html->next_tag( 'DIALOG' ) ) {
			if ( $html->has_class( 'np-notice' ) ) {
				$html->remove_attribute( 'aria-labelledby' );
			}
		}
		$content = $html->get_updated_html();
	}
	wp_enqueue_script( 'paddysun-notice', PADDYSUN_NS_URI . '/assets/js/notice.js', array(), PADDYSUN_NS_VERSION, true );
	return $content;
}
add_filter( 'render_block_core/template-part', 'paddysun_ns_render_notice', 10, 2 );

/* ------------------------------------------------------------
 * 7. 标题输出转义（0.9.1 审查整改；第三轮重做）：
 *    核心 post-title / 相邻文章导航把 get_the_title() 原样插入
 *    HTML（保存期 kses 只约束低权用户）。首版在渲染后正则转义
 *    整段 h 内部，误伤核心生成的 <a> 结构且可被提前闭合序列
 *    绕过——现改为 the_title 过滤器（字符串级、标记组装之前）
 *    转义：链接结构保留，闭合序列绕过不成立。
 *    Feed 内跳过（Feed 政策：不改 /feed/ 输出）；对不含 HTML
 *    特殊字符的正常标题输出逐字节不变。
 * ------------------------------------------------------------ */
function paddysun_ns_the_title_escape( $title ) {
	if ( is_feed() ) {
		return $title; // Feed 政策：输出保持核心默认
	}
	return esc_html( wp_specialchars_decode( $title, ENT_QUOTES ) );
}
add_filter( 'the_title', 'paddysun_ns_the_title_escape', PHP_INT_MAX );

/* ------------------------------------------------------------
 * 12. 评论字段常驻标签（0.10.0 U4）：
 *     显示名称 / 邮箱 / 网站；必填标记「*」随核心 require_name_email
 *     实际设置增减（核心已含 for/id 配对与 required 属性，此处只改
 *     label 文案，不动输入框标记）。placeholder 不替代 label。
 * ------------------------------------------------------------ */
function paddysun_ns_comment_field_labels( $fields ) {
	$map = array(
		'author' => __( '显示名称', 'paddysun-newsprint' ),
		'email'  => __( '邮箱', 'paddysun-newsprint' ),
		'url'    => __( '网站', 'paddysun-newsprint' ),
	);
	foreach ( $fields as $key => $html ) {
		if ( ! isset( $map[ $key ] ) || ! is_string( $html ) ) {
			continue;
		}
		/* 必填指示保留核心原样（核心仅在 require_name_email 开启时写入） */
		$required = false !== strpos( $html, 'class="required"' ) || false !== strpos( $html, "class='required'" );
		$inner    = esc_html( $map[ $key ] ) . ( $required ? wp_required_field_indicator() : '' );
		$replaced = preg_replace(
			'/(<label\b[^>]*>).*?(<\/label>)/s',
			'$1' . $inner . '$2',
			$html,
			1,
			$count
		);
		if ( $count ) {
			$fields[ $key ] = $replaced;
		}
	}
	return $fields;
}
add_filter( 'comment_form_default_fields', 'paddysun_ns_comment_field_labels' );

/* ------------------------------------------------------------
 * 13.（0.10.5 移除）首页卡片 follow 去重：近期文章板块已按站长裁决
 *     整体删除，np-displayed 收集 / np-cards-follow 消费随之退役；
 *     paddysun_ns_front_render_scope() 保留供 13b 使用。
 * ------------------------------------------------------------ */

/**
 * 是否处于「前台首页渲染」作用域（0.10.5 起 13b 置顶偏移补偿使用；
 * 原 13 去重协议随近期文章板块移除，此判定保留）。
 */
function paddysun_ns_front_render_scope() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
		return false;
	}
	return is_front_page();
}

/* 13b. 置顶与头条子查询（站长反馈 0.10.4）：置顶文经 WP 预置顶会同时
 * 占住主稿位与简报/侧栏的偏移位——主稿按设计吃置顶（阶段六验证），
 * 简报与侧栏改为忽略置顶并把偏移量减去置顶数：置顶时简报从日期第 1
 * 篇起（不再跳过被置顶顶掉的新文）、侧栏顺延，主稿不重复出现。 */
function paddysun_ns_lead_follow_vars( $query, $block, $page ) {
	if ( ! paddysun_ns_front_render_scope() ) {
		return $query;
	}
	$is_main = paddysun_ns_block_has_class( $block, 'np-lead-main-follow' );
	if ( ! $is_main && ! paddysun_ns_block_has_class( $block, 'np-lead-follow' ) ) {
		return $query;
	}
	$stickies = (array) get_option( 'sticky_posts' );
	if ( empty( $stickies ) ) {
		return $query;
	}
	$query['ignore_sticky_posts'] = 1;
	/* 主稿（perPage=1）：WP 置顶预置顶会让单篇查询返回「置顶+日期第 1 篇」
	 * 两篇（站长实测 0.10.4）——改为显式只取置顶。 */
	if ( $is_main ) {
		$query['post__in'] = $stickies;
		$query['orderby']  = 'post__in';
		$query['offset']   = 0;
		return $query;
	}
	/* 简报/侧栏：偏移量减去主稿吃掉的置顶数，内容顺延不重不漏。 */
	$offset = isset( $query['offset'] ) ? (int) $query['offset'] : 0;
	$query['offset'] = max( 0, $offset - 1 );
	return $query;
}
add_filter( 'query_loop_block_query_vars', 'paddysun_ns_lead_follow_vars', 30, 3 );

/* ------------------------------------------------------------
 * 14.（0.10.4 移除）无题图卡片占位题花：站长反馈空色块观感差，
 *     不再注入；无题图卡片只排文字，行高由网格拉伸对齐。
 * ------------------------------------------------------------ */

/* 15.（0.10.5 移除）卡片流「更多文章」出口：随近期文章板块退役。 */
