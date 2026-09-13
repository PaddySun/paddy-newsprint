<?php
/**
 * 轻量 HTML → Markdown 转换器（服务端，无第三方依赖）。
 *
 * 用途：未存原文 MD 的文章兜底（复制按钮 / REST / llms-full.txt）。
 * 覆盖 GFM 子集：段落、标题、粗斜删、行内代码、链接、图片、有序/无序列表、
 * 引用、围栏代码（含 language-mermaid）、表格（含对齐）、分隔线、Mermaid 占位容器。
 * 数学公式无需处理：KaTeX 仅在客户端渲染，服务端 HTML 中 $...$ 仍是原文。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 入口字节预算（站长裁决：超限戒断，调用方降级为摘要） */
const PADDYSUN_NS_MD_MAX_HTML_BYTES = 524288;
/* DOM 规模预算（0.9.1 审查整改）：节点数与嵌套深度双重戒断 */
const PADDYSUN_NS_MD_MAX_DOM_NODES  = 20000;
const PADDYSUN_NS_MD_MAX_DEPTH       = 50;
/* 表格预算（第三轮审查）：稀疏表按「行×最大列」补齐会放大工作量与输出，
 * 行数、单元格乘积、单格字节、表格输出总量四重戒断 */
const PADDYSUN_NS_MD_MAX_TABLE_ROWS  = 500;
const PADDYSUN_NS_MD_MAX_TABLE_CELLS = 10000;
const PADDYSUN_NS_MD_MAX_CELL_BYTES  = 4096;

/**
 * 出站 URL 白名单与结构转义：非 http/https/mailto 协议返回空（调用方降级为纯文本）。
 * 前置规范化（第三轮审查）：先实体解码并剥离控制字符/空白/反斜杠再取 scheme——
 * &#106;avascript / java\script 等下游解码形态不放行；
 * 空格/配对括号会截断或劫持 Markdown 链接目标，一律百分号编码。
 */
function paddysun_ns_md_safe_url( $url ) {
	$url = (string) $url;
	if ( '' === $url ) {
		return '';
	}
	$probe = html_entity_decode( $url, ENT_QUOTES | ENT_HTML5 );
	$probe = preg_replace( '/[\x00-\x20\x7f\\\\]+/', '', $probe );
	$scheme = strtolower( (string) parse_url( $probe, PHP_URL_SCHEME ) );
	// 无 scheme（含协议相对 //host/…）放行——导出方向处理的是已发布正文中
	// 作者自填链接，非新鲜不可信输入，与素材清单白名单（inc/img-fallback.php
	// R1-1 拒绝协议相对）威胁模型不同，两处口径差异有意为之（审查 D-F18）。
	if ( '' !== $scheme && ! in_array( $scheme, array( 'http', 'https', 'mailto' ), true ) ) {
		return '';
	}
	return str_replace( array( ' ', '(', ')' ), array( '%20', '%28', '%29' ), $url );
}

/**
 * 链接文本 / alt 的 Markdown 结构转义：方括号会劫持链接文本，反斜杠防转义链。
 */
function paddysun_ns_md_escape_text( $text ) {
	return str_replace( array( '\\', '[', ']', '`' ), array( '\\\\', '\\[', '\\]', '\\`' ), (string) $text );
}

/**
 * 将 WordPress 正文 HTML 转为 Markdown 文本。
 *
 * @param string $html 正文 HTML。
 * @return string Markdown；输入超预算时返回空串（调用方降级）。
 */
function paddysun_ns_html_to_markdown( $html ) {
	if ( ! is_string( $html ) || strlen( $html ) > PADDYSUN_NS_MD_MAX_HTML_BYTES ) {
		return '';
	}
	if ( ! class_exists( 'DOMDocument' ) ) {
		return wp_strip_all_tags( $html );
	}

	$dom = new DOMDocument();
	$prev_libxml = libxml_use_internal_errors( true );
	// UTF-8 声明前缀保证中文按 UTF-8 解析（HTML-ENTITIES 转码在 PHP 8.2+ 已移除）
	$dom->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOWARNING | LIBXML_NOERROR );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev_libxml );

	/* DOM 规模戒断：超预算节点数直接戒断，调用方降级摘要 */
	if ( $dom->getElementsByTagName( '*' )->length > PADDYSUN_NS_MD_MAX_DOM_NODES ) {
		return '';
	}

	$body = $dom->getElementsByTagName( 'body' )->item( 0 );
	if ( ! $body ) {
		return wp_strip_all_tags( $html );
	}

	$md = paddysun_ns_md_blocks( $body );
	$md = preg_replace( "/\n{3,}/", "\n\n", $md );
	return trim( $md ) . "\n";
}

/* ------------------------------------------------------------
 * 块级遍历
 * ---------------------------------------------------------- */
function paddysun_ns_md_blocks( DOMNode $node, $depth = 0 ) {
	if ( $depth > PADDYSUN_NS_MD_MAX_DEPTH ) {
		return ''; // 嵌套深度戒断
	}
	$out = '';
	foreach ( $node->childNodes as $child ) {
		if ( $child instanceof DOMComment ) {
			continue; // 去掉 <!-- wp:xxx --> 区块注释
		}
		if ( $child instanceof DOMText ) {
			$t = trim( $child->wholeText );
			if ( '' !== $t ) {
				$out .= $t . "\n\n";
			}
			continue;
		}
		if ( ! ( $child instanceof DOMElement ) ) {
			continue;
		}
		$out .= paddysun_ns_md_block_element( $child, $depth );
	}
	return $out;
}

function paddysun_ns_md_block_element( DOMElement $el, $depth = 0 ) {
	if ( $depth > PADDYSUN_NS_MD_MAX_DEPTH ) {
		return '';
	}
	$tag = strtolower( $el->tagName );

	switch ( $tag ) {
		case 'p':
		case 'div':
			// div.np-mermaid → 还原 ```mermaid 源码
			if ( false !== strpos( $el->getAttribute( 'class' ), 'np-mermaid' ) ) {
				$src = $el->getAttribute( 'data-src' );
				return "```mermaid\n" . $src . "\n```\n\n";
			}
			$inner = paddysun_ns_md_inline_children( $el );
			$inner = trim( $inner );
			return '' === $inner ? '' : $inner . "\n\n";

		case 'h1':
		case 'h2':
		case 'h3':
		case 'h4':
		case 'h5':
		case 'h6':
			$level = (int) $tag[1];
			$text  = trim( paddysun_ns_md_inline_children( $el ) );
			$text  = preg_replace( '/\s*\n\s*/', ' ', $text );
			return str_repeat( '#', $level ) . ' ' . $text . "\n\n";

		case 'blockquote':
			$inner = paddysun_ns_md_blocks( $el, $depth + 1 );
			$inner = trim( $inner );
			$lines = preg_split( '/\n/', $inner );
			$quoted = '';
			foreach ( $lines as $line ) {
				$quoted .= ( '' === trim( $line ) ? '>' : '> ' . $line ) . "\n";
			}
			return $quoted . "\n";

		case 'pre':
			return paddysun_ns_md_fenced_code( $el ) . "\n\n";

		case 'ul':
		case 'ol':
			// core/footnotes 文末列表 → [^id]: 定义行（M-01 往返一致）
			if ( 'ol' === $tag && false !== strpos( $el->getAttribute( 'class' ), 'wp-block-footnotes' ) ) {
				return paddysun_ns_md_footnote_defs( $el );
			}
			return paddysun_ns_md_list( $el, $tag, 0 );

		case 'table':
			return paddysun_ns_md_table( $el ) . "\n\n";

		case 'figure':
			// wp-block-table / wp-block-image / 代码块
			$table = $el->getElementsByTagName( 'table' );
			if ( $table->length > 0 ) {
				return paddysun_ns_md_table( $table->item( 0 ) ) . "\n\n";
			}
			$pre = $el->getElementsByTagName( 'pre' );
			if ( $pre->length > 0 ) {
				return paddysun_ns_md_fenced_code( $pre->item( 0 ) ) . "\n\n";
			}
			$img = $el->getElementsByTagName( 'img' );
			if ( $img->length > 0 ) {
				return paddysun_ns_md_inline_node( $img->item( 0 ) ) . "\n\n";
			}
			return paddysun_ns_md_blocks( $el, $depth + 1 );

		case 'hr':
			return "---\n\n";

		case 'br':
			return "\n";

		case 'img':
			return paddysun_ns_md_inline_node( $el ) . "\n\n";

		case 'script':
		case 'style':
		case 'svg':
			return '';

		default:
			// 行内元素落到块上下文（如裸 <strong> 段）与其他内容合并
			$inner = trim( paddysun_ns_md_inline_children( $el ) );
			return '' === $inner ? '' : $inner . "\n\n";
	}
}

/* ------------------------------------------------------------
 * 围栏代码
 * ---------------------------------------------------------- */
function paddysun_ns_md_fenced_code( DOMElement $pre ) {
	$code = $pre;
	$lang = '';
	$code_els = $pre->getElementsByTagName( 'code' );
	if ( $code_els->length > 0 ) {
		$code = $code_els->item( 0 );
	}
	// 语言类可能在 <code>（粘贴）或 <pre>（规范化 / 附加 CSS 类）上，先 code 后 pre
	$class_attr = $code->getAttribute( 'class' ) . ' ' . $pre->getAttribute( 'class' );
	if ( preg_match( '/language-([\w+-]+)/i', $class_attr, $m ) ) {
		$lang = strtolower( $m[1] );
	}
	$src = '';
	foreach ( $code->childNodes as $c ) {
		if ( $c->nodeType === XML_TEXT_NODE ) {
			$src .= $c->wholeText;
		} else {
			$src .= $c->ownerDocument->saveHTML( $c );
		}
	}
	$src = rtrim( $src, "\n\r" );
	if ( '' === trim( $src ) ) {
		return '';
	}
	/* 围栏自适应：代码里含 ``` 时加长围栏，防止提前闭合（结构逃逸） */
	$max_run = 0;
	if ( preg_match_all( '/`{3,}/', $src, $fm ) ) {
		foreach ( $fm[0] as $run ) {
			$max_run = max( $max_run, strlen( $run ) );
		}
	}
	$fence = str_repeat( '`', max( 3, $max_run + 1 ) );
	return $fence . $lang . "\n" . $src . "\n" . $fence;
}

/* ------------------------------------------------------------
 * 列表（支持嵌套）
 * ---------------------------------------------------------- */
function paddysun_ns_md_list( DOMElement $list, $tag, $depth ) {
	if ( $depth > PADDYSUN_NS_MD_MAX_DEPTH ) {
		return ''; // 嵌套深度戒断（第三轮审查：与块级/行内同帽）
	}
	$idx   = 0;
	$indent = str_repeat( '    ', $depth );
	$out   = '';
	foreach ( $list->childNodes as $li ) {
		if ( ! ( $li instanceof DOMElement ) || 'li' !== strtolower( $li->tagName ) ) {
			continue;
		}
		$idx++;
		$marker = ( 'ol' === $tag ) ? ( $idx . '. ' ) : '- ';
		$marker = $indent . $marker;

		// 拆出 li 的直接行内内容与嵌套列表
		$inline = '';
		$subs   = '';
		foreach ( $li->childNodes as $c ) {
			if ( $c instanceof DOMElement && in_array( strtolower( $c->tagName ), array( 'ul', 'ol' ), true ) ) {
				$subs .= paddysun_ns_md_list( $c, strtolower( $c->tagName ), $depth + 1 );
			} elseif ( $c instanceof DOMText ) {
				$inline .= $c->wholeText;
			} elseif ( $c instanceof DOMElement && 'p' === strtolower( $c->tagName ) ) {
				$inline .= paddysun_ns_md_inline_children( $c );
			} elseif ( $c instanceof DOMElement ) {
				$inline .= paddysun_ns_md_inline_node( $c );
			}
		}
		$inline = trim( preg_replace( '/\s*\n\s*/', ' ', $inline ) );
		$out   .= $marker . $inline . "\n" . $subs;
	}
	return $out . ( 0 === $depth ? "\n" : '' );
}

/* ------------------------------------------------------------
 * 表格 → GFM 管道表（含对齐）
 * ---------------------------------------------------------- */
function paddysun_ns_md_table( DOMElement $table ) {
	$rows   = array();
	$aligns = array();
	$thead  = $table->getElementsByTagName( 'thead' );
	$bytes  = 0; // 全表累计输出量

	$collect = function ( DOMElement $tr ) use ( &$rows, &$bytes ) {
		if ( count( $rows ) >= PADDYSUN_NS_MD_MAX_TABLE_ROWS ) {
			return; // 行数戒断
		}
		$cells = array();
		foreach ( $tr->childNodes as $cell ) {
			if ( $cell instanceof DOMElement && in_array( strtolower( $cell->tagName ), array( 'td', 'th' ), true ) ) {
				/* 竖线是 GFM 表格结构符，单元格内必须转义；单格字节预算 */
				$cell_md = str_replace( '|', '\\|', paddysun_ns_md_inline_children( $cell ) );
				$cell_md = paddysun_ns_safe_cut_bytes( $cell_md, PADDYSUN_NS_MD_MAX_CELL_BYTES );
				$bytes  += strlen( $cell_md );
				$cells[] = $cell_md;
			}
		}
		$rows[] = $cells;
	};

	if ( $thead->length > 0 ) {
		foreach ( $thead->item( 0 )->getElementsByTagName( 'tr' ) as $tr ) {
			$collect( $tr );
			foreach ( $tr->childNodes as $cell ) {
				if ( $cell instanceof DOMElement ) {
					$cls = $cell->getAttribute( 'class' );
					if ( false !== strpos( $cls, 'has-text-align-right' ) ) {
						$aligns[] = 'right';
					} elseif ( false !== strpos( $cls, 'has-text-align-center' ) ) {
						$aligns[] = 'center';
					} else {
						$aligns[] = '';
					}
				}
			}
		}
	}
	$tbody = $table->getElementsByTagName( 'tbody' );
	$trs   = ( $tbody->length > 0 ) ? $tbody->item( 0 )->getElementsByTagName( 'tr' ) : $table->getElementsByTagName( 'tr' );
	foreach ( $trs as $tr ) {
		// 回退分支按全表收集时跳过 thead 内的行（审查 D-F12：thead 存在但
		// 无 tbody 的异常形态，表头行会被重复收集为正文）
		$in_head = false;
		for ( $p = $tr->parentNode; $p && $p !== $table; $p = $p->parentNode ) {
			if ( 'thead' === $p->nodeName ) {
				$in_head = true;
				break;
			}
		}
		if ( $in_head ) {
			continue;
		}
		$collect( $tr );
	}

	if ( empty( $rows ) ) {
		return '';
	}
	// 无 thead 时 rows[0]（首行）自然充当表头，GFM 输出以 rows[0] 为头

	$clean = function ( $s ) {
		return str_replace( array( '|', "\n" ), array( '\\|', ' ' ), trim( $s ) );
	};

	$width = 0;
	foreach ( $rows as $r ) {
		$width = max( $width, count( $r ) );
	}
	/* 行×最大列乘积戒断（第三轮审查）：稀疏表补齐前先量化工作量 */
	if ( count( $rows ) * $width > PADDYSUN_NS_MD_MAX_TABLE_CELLS || $bytes > PADDYSUN_NS_MD_MAX_HTML_BYTES ) {
		return '';
	}
	$sep = array();
	for ( $i = 0; $i < $width; $i++ ) {
		$a     = isset( $aligns[ $i ] ) ? $aligns[ $i ] : '';
		$sep[] = ( 'right' === $a ) ? '---:' : ( ( 'center' === $a ) ? ':---:' : '---' );
	}

	$out = '| ' . implode( ' | ', array_map( $clean, $rows[0] ) ) . " |\n";
	$out .= '| ' . implode( ' | ', $sep ) . " |\n";
	$body = array_slice( $rows, 1 );
	foreach ( $body as $r ) {
		$cells = array();
		for ( $i = 0; $i < $width; $i++ ) {
			$cells[] = $clean( isset( $r[ $i ] ) ? $r[ $i ] : '' );
		}
		$out .= '| ' . implode( ' | ', $cells ) . " |\n";
	}
	return $out;
}

/* ------------------------------------------------------------
 * 脚注定义列表 → Markdown 定义行（M-01）
 * ---------------------------------------------------------- */
function paddysun_ns_md_footnote_defs( DOMElement $ol ) {
	$out = '';
	foreach ( $ol->childNodes as $li ) {
		if ( ! ( $li instanceof DOMElement ) || 'li' !== strtolower( $li->tagName ) ) {
			continue;
		}
		$id   = $li->getAttribute( 'id' );
		$text = trim( paddysun_ns_md_inline_children( $li ) );
		// 去掉尾部的回链（裸 ↩︎ 或 [↩︎](#anchor) 形态）
		$text = preg_replace( '/\s*(?:\[↩︎\]\([^)]*\)|↩︎)\s*$/u', '', $text );
		if ( '' === $id || '' === $text ) {
			continue;
		}
		$out .= '[^' . $id . ']: ' . $text . "\n";
	}
	return '' === $out ? '' : $out . "\n";
}

/* ------------------------------------------------------------
 * 行内转换
 * ---------------------------------------------------------- */
function paddysun_ns_md_inline_children( DOMNode $node, $depth = 0 ) {
	if ( $depth > PADDYSUN_NS_MD_MAX_DEPTH ) {
		return ''; // 嵌套深度戒断
	}
	$out = '';
	foreach ( $node->childNodes as $child ) {
		if ( $child instanceof DOMText ) {
			$out .= $child->wholeText;
		} elseif ( $child instanceof DOMElement ) {
			$out .= paddysun_ns_md_inline_node( $child, $depth );
		}
	}
	return $out;
}

function paddysun_ns_md_inline_node( DOMElement $el, $depth = 0 ) {
	if ( $depth > PADDYSUN_NS_MD_MAX_DEPTH ) {
		return '';
	}
	$tag = strtolower( $el->tagName );
	switch ( $tag ) {
		case 'strong':
		case 'b':
			$t = trim( paddysun_ns_md_inline_children( $el, $depth + 1 ) );
			return '' === $t ? '' : '**' . $t . '**';
		case 'em':
		case 'i':
			$t = trim( paddysun_ns_md_inline_children( $el, $depth + 1 ) );
			return '' === $t ? '' : '*' . $t . '*';
		case 'del':
		case 's':
			$t = trim( paddysun_ns_md_inline_children( $el, $depth + 1 ) );
			return '' === $t ? '' : '~~' . $t . '~~';
		case 'code':
			$t = paddysun_ns_md_inline_children( $el, $depth + 1 );
			$t = str_replace( '`', '\`', trim( $t ) );
			return '`' . $t . '`';
		case 'a':
			$href = $el->getAttribute( 'href' );
			$text = trim( paddysun_ns_md_inline_children( $el, $depth + 1 ) );
			if ( '' === $text ) {
				return '';
			}
			// 空链接与纯锚点链接同口径：留文弃链（审查 D-F5：原第二子条件恒被下一分支覆盖）
			if ( '' === $href || 0 === strpos( $href, '#' ) ) {
				return $text;
			}
			$safe = paddysun_ns_md_safe_url( $href );
			if ( '' === $safe ) {
				return paddysun_ns_md_escape_text( $text ); // 协议不在白名单：留文弃链
			}
			return '[' . paddysun_ns_md_escape_text( $text ) . '](' . $safe . ')';
		case 'img':
			$src   = $el->getAttribute( 'src' );
			$alt   = $el->getAttribute( 'alt' );
			$safe  = paddysun_ns_md_safe_url( $src );
			if ( '' === $safe ) {
				return '';
			}
			return '![' . paddysun_ns_md_escape_text( $alt ) . '](' . $safe . ')';
		case 'br':
			return "\n";
		case 'sup':
			// 脚注角标 → [^id]（与粘贴形态一致，可再次粘贴回环）；ID 只留安全字符防结构逃逸
			if ( false !== strpos( $el->getAttribute( 'class' ), 'fn' ) ) {
				$fn = preg_replace( '/[^\w-]/', '', $el->getAttribute( 'data-fn' ) );
				if ( '' !== $fn ) {
					return '[^' . $fn . ']';
				}
			}
			return paddysun_ns_md_inline_children( $el, $depth + 1 );
		case 'sub':
		case 'span':
		case 'mark':
		case 'small':
		case 'abbr':
		case 'u':
			return paddysun_ns_md_inline_children( $el, $depth + 1 );
		default:
			// 未知标签：保留子内容
			return paddysun_ns_md_inline_children( $el, $depth + 1 );
	}
}
