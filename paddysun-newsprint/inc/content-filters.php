<?php
/**
 * 正文过滤器：标题锚点（TOC 用）、Mermaid 占位容器、表格响应式包裹。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1) 为 h2/h3 附加稳定锚点 ID（已有 ID 的保留），并收集目录结构。
 * 2) 把 ```mermaid 代码块（pre>code.language-mermaid）转换为渲染占位容器，
 *    源码经 esc_attr 存入 data-src，渲染失败时前端回退显示源码。
 * 3) 为 table/figure.wp-block-table 外包一层横向滚动容器。
 */
function paddysun_ns_filter_content( $content ) {
	if ( is_admin() || ! is_string( $content ) || '' === $content ) {
		return $content;
	}

	/* ---- 标题锚点 + 目录 ----
	   只处理正文里的内容标题（wp-block-heading / 无类），跳过区块动态
	   生成的标题（term-name / post-title / query-title / comments-title），
	   否则插入内容里的「本期要目」等版面区块的分类名也会被编进目录。 */
	global $paddysun_ns_toc;
	$paddysun_ns_toc = array();
	$n               = 0;
	$content         = preg_replace_callback(
		'/<(h[23])(\s[^>]*)?>(.*?)<\/\1>/is',
		function ( $m ) use ( &$paddysun_ns_toc, &$n ) {
			$tag   = $m[1];
			$attr  = $m[2] ? $m[2] : '';
			$inner = $m[3];
			if ( preg_match( '/wp-block-(term-name|post-title|query-title|comments-title)/', $attr ) ) {
				return $m[0];
			}
			$n++;
			if ( preg_match( '/\sid=["\']([^"\']+)["\']/i', $attr, $idm ) ) {
				$id = $idm[1];
			} else {
				$id = 'sec-' . $n;
				$attr .= ' id="' . esc_attr( $id ) . '"';
			}
			$paddysun_ns_toc[] = array(
				'level' => (int) $tag[1],
				'id'    => $id,
				'text'  => wp_strip_all_tags( $inner ),
			);
			return '<' . $tag . $attr . '>' . $inner . '</' . $tag . '>';
		},
		$content
	);

	/* ---- Mermaid 占位容器（语言类可能在 <pre> 或 <code> 上：规范化与
	      「附加 CSS 类」产生前者，粘贴产生后者，两者都认） ----
	      双保险：无类代码块若首行为 mermaid 关键字，先在渲染层补类
	      （存量/异形粘贴不再因缺类而丢容器）。注意服务端探测清单宽于
	      前端渲染白名单（mermaid-init.js 仅 flowchart/sequence/class/
	      state/er/mindmap/pie）：清单外图种补类后走失败回退显示源码，
	      不渲染成图（审查 C-F10：原「仍可渲染」表述失实）。 */
	$content = preg_replace_callback(
		'/<pre([^>]*)>\s*<code([^>]*)>(.*?)<\/code>\s*<\/pre>/is',
		function ( $m ) {
			$classes = $m[1] . ' ' . $m[2];
			if ( false === stripos( $classes, 'language-mermaid' ) ) {
				$first = '';
				if ( preg_match( '/^\s*(?:flowchart|graph\s+(?:TD|LR|TB|RL)|sequenceDiagram|classDiagram|stateDiagram(?:-v2)?|erDiagram|journey|gantt|pie|mindmap|timeline|quadrantChart|gitGraph|sankey-beta|sankey|xychart-beta|block-beta|architecture|packet)\b/i', html_entity_decode( $m[3], ENT_QUOTES | ENT_HTML5 ) ) ) {
					$classes .= ' language-mermaid';
				} else {
					return $m[0];
				}
			}
			$src = html_entity_decode( $m[3], ENT_QUOTES | ENT_HTML5 );
			return '<div class="np-mermaid" data-src="' . esc_attr( $src ) . '"><div class="np-mermaid__fallback"><code>' . $m[3] . '</code></div></div>';
		},
		$content
	);

	/* ---- 表格滚动容器 ---- */
	$content = preg_replace_callback(
		'/(<figure[^>]*class=["\'][^"\']*wp-block-table[^"\']*["\'][^>]*>.*?<table.*?<\/table>.*?<\/figure>|<table[^>]*>.*?<\/table>)/is',
		function ( $m ) {
			// 已包裹的不重复包
			if ( false !== strpos( $m[1], 'np-table-wrap' ) ) {
				return $m[1];
			}
			return '<div class="np-table-wrap">' . $m[1] . '</div>';
		},
		$content
	);

	/* ---- 图片惰性加载（原生 loading=lazy）---- */
	/* ---- 图片懒加载兜底 ----
	   核心只给自带 width/height 的 img 注入 loading（无尺寸则跳过，防 CLS 误判），
	   外站徽章 / 友链头像等无尺寸图因此漏网。此处在核心之后逐张补：
	   跳过内容前 N 张（首屏 LCP 候选，对齐核心 wp_omit_loading_attr_threshold
	   默认 3），已有 loading / fetchpriority 的不动；整段无图零开销。
	   Feed 分支保持 0.8.0 的旧全量行为不变（对 Feed 图片注入 lazy 属既有
   保留行为；全文/摘要/标题口径见 functions.php「Feed 输出」节。
   审查 C-F09：原「不动 feed 输出」与下方实际改写矛盾）。 */
	if ( is_feed() ) {
		if ( false === strpos( $content, 'loading=' ) ) {
			$content = str_replace( '<img ', '<img loading="lazy" decoding="async" ', $content );
		}
		return $content;
	}
	if ( preg_match_all( '/<img\s[^>]*>/i', $content, $imgs ) && count( $imgs[0] ) > 1 ) {
		$threshold = (int) apply_filters( 'wp_omit_loading_attr_threshold', 3 );
		$i         = 0;
		$content   = preg_replace_callback(
			'/<img\s[^>]*>/i',
			function ( $m ) use ( &$i, $threshold ) {
				$i++;
				$tag = $m[0];
				if ( $i <= $threshold || str_contains( $tag, 'loading=' ) || str_contains( $tag, 'fetchpriority=' ) ) {
					return $tag;
				}
				return '<img loading="lazy"' . substr( $tag, 4 );
			},
			$content
		);
	}

	return $content;
}
add_filter( 'the_content', 'paddysun_ns_filter_content', 12 );

/* ------------------------------------------------------------
 * 区块标记规范化（保存时）。
 *
 * 古腾堡的 Markdown 粘贴路径与 WP 7.1 核心的保存标记存在三处不对称，
 * 均会导致编辑器报「区块包含意外或无效内容」，此处统一改写为合法形态：
 *
 * 1) 代码块语言：粘贴输出 <code class="language-x">、Gutenberg 插件时代
 *    存量内容带 {"language":"x"} 属性，而核心 code 区块两者都不输出——
 *    语言类改落到 <pre> 的「附加 CSS 类」上（合法形态）。
 * 2) Mermaid 丢语言：粘贴常把 ```mermaid 围栏的语言信息丢掉，成为无类
 *    代码块——内容首行匹配 mermaid 图表关键字时补 language-mermaid。
 * 3) 平铺列表：旧粘贴形态 <ul><li>…（无 list-item 内层区块），WP 7.1
 *    列表区块要求内层标记——为每个 <li> 补上 wp:list-item 注释
 *    （含嵌套列表的复杂形态保持原样，交由编辑器自身迁移）。
 * 4) 表格等宽列：粘贴产生的 <table class="has-fixed-layout"> 剥离该类
 *    ——本主题三线表约定「列宽随内容自适应」（V-09）。
 * 5) Markdown 脚注（M-01）：正文 [^id] 引用 → sup.fn 角标，文末
 *    [^id]: 定义段 → footnotes meta + wp:footnotes 区块（协议见下）。
 *
 * 注意：本函数在 content_save_pre 上收到的是「斜杠转义」数据，输出必须
 * 保持转义形态；直接对数据库原始内容调用亦安全（反斜杠仅被原样
 * 保留不改写，转义进出平衡——审查 C-F22：原「正则不涉及反斜杠」
 * 论据不准确，操作结论不变）。
 * 幂等，可重复执行。
 * ---------------------------------------------------------- */
function paddysun_ns_normalize_block_markup( $content ) {
	if ( ! is_string( $content ) || '' === $content ) {
		return $content;
	}

	/* 1) 代码块语言 → pre 附加类 */
	if ( false !== strpos( $content, 'language-' ) ) {
		$content = preg_replace_callback(
			'/<!-- wp:code( \{.*?\})? -->\s*<pre class="wp-block-code">\s*<code class="(language-[\w+-]+)">(.*?)<\/code>\s*<\/pre>\s*<!-- \/wp:code -->/s',
			static function ( $m ) {
				$attrs = isset( $m[1] ) && '' !== trim( $m[1] ) ? json_decode( $m[1], true ) : array();
				if ( ! is_array( $attrs ) ) {
					$attrs = array();
				}
				unset( $attrs['language'] );
				$attrs['className'] = $m[2];
				return '<!-- wp:code ' . wp_json_encode( $attrs ) . ' -->'
					. '<pre class="wp-block-code ' . esc_attr( $m[2] ) . '">'
					. '<code>' . $m[3] . '</code></pre><!-- /wp:code -->';
			},
			$content
		);
	}

	/* 2) 无语言代码块 + mermaid 关键字首行 → 补 language-mermaid
	      （兼容 pre/code 上已带其他类、以及 wp:code 带属性 JSON 的形态——
	        站长实测有粘贴形态漏转，规则放宽为「纯内容首行关键字」判定） */
	if ( false !== strpos( $content, 'wp:code' ) ) {
		$content = preg_replace_callback(
			'/(<!-- wp:code(?: (\{.*?\}))? -->)\s*<pre([^>]*)>\s*<code([^>]*)>\s*((?:flowchart|graph TD|graph LR|graph TB|graph RL|sequenceDiagram|classDiagram|stateDiagram(?:-v2)?|erDiagram|journey|gantt|pie|mindmap|timeline|quadrantChart|gitGraph|sankey-beta|sankey|xychart-beta|block-beta|architecture|packet)(?:[^\n<]|&[a-z#0-9]+;)*)/i',
			static function ( $m ) {
				$attrs = isset( $m[2] ) && '' !== trim( $m[2] ) ? json_decode( $m[2], true ) : array();
				if ( ! is_array( $attrs ) ) {
					$attrs = array();
				}
				$attrs['className'] = 'language-mermaid';
				$pre_classes   = preg_split( '/\s+/', trim( (string) preg_replace( '/^.*class="([^"]*)".*$/', '$1', $m[3] ) ) );
				$code_classes  = preg_split( '/\s+/', trim( (string) preg_replace( '/^.*class="([^"]*)".*$/', '$1', $m[4] ) ) );
				$code_classes  = array_values( array_filter( array_diff( $code_classes, array( 'language-mermaid', 'language-python', 'language-js', 'language-javascript' ) ) ) );
				$pre_classes   = array_values( array_filter( array_unique( array_merge( array( 'wp-block-code', 'language-mermaid' ), $pre_classes ) ) ) );
				return '<!-- wp:code ' . wp_json_encode( $attrs ) . ' -->'
					. '<pre class="' . esc_attr( implode( ' ', $pre_classes ) ) . '">'
					. '<code' . ( $code_classes ? ' class="' . esc_attr( implode( ' ', $code_classes ) ) . '"' : '' ) . '>' . $m[5];
			},
			$content
		);
	}

	/* 3) 平铺列表 → list-item 内层区块；<ol> 无 ordered 属性 → 补 {"ordered":true} */
	if ( false !== strpos( $content, '<!-- wp:list -->' ) ) {
		// 有序列表：核心保存标记必须带 ordered 属性，否则编辑器校验失配
		$content = preg_replace(
			'/(<!-- wp:list) -->(\s*)<(ol\b)/',
			'$1 {"ordered":true} -->$2<$3',
			$content
		);
		$content = preg_replace_callback(
			'/(<!-- wp:list(?: \{.*?\})? -->)\s*<(ul|ol) class="wp-block-list">(.*?)<\/\2>\s*(<!-- \/wp:list -->)/s',
			static function ( $m ) {
				$inner = $m[3];
				if ( false !== strpos( $inner, 'wp:list-item' ) || false !== stripos( $inner, '<ul' ) || false !== stripos( $inner, '<ol' ) ) {
					return $m[0]; // 已是新形态或含嵌套列表，交由编辑器自身迁移
				}
				$inner = preg_replace( '/<li>(.*?)<\/li>/s', '<!-- wp:list-item --><li>$1</li><!-- /wp:list-item -->', $inner );
				return $m[1] . '<' . $m[2] . ' class="wp-block-list">' . $inner . '</' . $m[2] . '>' . $m[4];
			},
			$content
		);
	}

	/* 4) 表格等宽列 → 内容自适应：粘贴产生的 <table class="has-fixed-layout">
	   与本主题三线表的「列宽随内容」约定冲突（V-09），剥离该类（保留其他类）。 */
	if ( false !== strpos( $content, 'has-fixed-layout' ) ) {
		$content = preg_replace_callback(
			'/<table([^>]*)>/',
			static function ( $m ) {
				$attrs = preg_replace( '/\s*class="([^"]*)"/', '', $m[1], 1 );
				preg_match( '/class="([^"]*)"/', $m[1], $cm );
				$classes = preg_split( '/\s+/', trim( (string) ( $cm[1] ?? '' ) ) );
				$classes = array_filter(
					$classes,
					static function ( $c ) {
						return '' !== $c && 'has-fixed-layout' !== $c;
					}
				);
				$attrs  = trim( $attrs );
				$table  = '<table' . ( '' !== $attrs ? ' ' . $attrs : '' );
				if ( $classes ) {
					$table .= ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
				}
				return $table . '>';
			},
			$content
		);
	}

	/* 5) Markdown 脚注 → core/footnotes（M-01）：正文 [^id] 引用转为
	   sup.fn[data-fn] 角标，文末 [^id]: 定义段落提取入 footnotes meta，
	   并补 <!-- wp:footnotes /--> 区块。幂等；已有角标（含 0.5.0 的 *
	   存量与编辑器原生 uuid 形态）每次保存按出现序重编号。 */
	if ( false !== strpos( $content, '[^' ) ) {
		$content = paddysun_ns_normalize_footnotes( $content );
	} elseif ( false !== strpos( $content, 'data-fn=' ) ) {
		$content = paddysun_ns_renumber_footnote_refs( $content );
	}

	return $content;
}

/* ------------------------------------------------------------
 * 脚注归一化（与 core/footnotes 的 meta 协议对齐）
 *
 * core 协议：引用标记 <sup class="fn" data-fn="ID"><a href="#ID"
 * id="ID-link">*</a></sup>；定义存 post meta「footnotes」
 * （JSON：[{"id":"…","content":"…"}]，顺序=引用出现顺序）；
 * 文末动态区块 <!-- wp:footnotes /--> 按 meta 渲染 <ol> 列表。
 * 本转换用「脚注标签本身」作 ID（Typora 标签为字母数字，天然稳定，
 * 重复保存不漂移）；与编辑器原生插入的 uuid ID 互不冲突。
 * ---------------------------------------------------------- */

/**
 * 本次请求内暂存的脚注转换结果（content_save_pre → save_post 传递）。
 */
function paddysun_ns_footnote_stash( $set = null ) {
	static $stash = array();
	if ( null !== $set ) {
		$stash = $set;
	}
	return $stash;
}

/**
 * 转义形态内容上的脚注归一化（保持转义进出平衡：插入的标记不含反斜杠）。
 *
 * 角标文本写「出现序号」（与核心编辑器保存时重写锚文本为数字的行为一致，
 * 前台直接显示序号）；同一脚注多处引用共用同一序号，回链锚只在首次出现
 * 时生成（id 唯一，跳回首个引用位）。
 *
 * @param string $content 斜杠转义的正文。
 * @return string
 */
function paddysun_ns_normalize_footnotes( $content ) {
	$defs = array();

	/* 定义段：完整段落区块 [^id]: text → 移除并暂存 */
	$content = preg_replace_callback(
		'/<!-- wp:paragraph(?: \{.*?\})? -->\s*<p>\s*\[\^([A-Za-z0-9_-]+)\]:\s*(.*?)<\/p>\s*<!-- \/wp:paragraph -->/s',
		static function ( $m ) use ( &$defs ) {
			$def_content          = trim( wp_unslash( $m[2] ) );
			$def_content          = preg_replace( '/\s*<br\s*\/?>\s*/i', ' ', $def_content );
			$defs[ $m[1] ]        = is_string( $def_content ) ? $def_content : trim( wp_unslash( $m[2] ) );
			return '';
		},
		$content
	);

	/* 引用：[^id] → sup 角标（跳过 pre/code 片段，代码示例里的 [^] 不动） */
	$ref_ids   = array();
	$ordinals  = array();
	$seen      = array();
	$segments  = preg_split( '/(<pre[\s>].*?<\/pre>|<code[\s>].*?<\/code>)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	$converted = false;
	if ( is_array( $segments ) ) {
		foreach ( $segments as $i => $seg ) {
			if ( $i % 2 ) {
				continue; // 偶数索引为普通内容，奇数为捕获的分隔片段
			}
			$segments[ $i ] = preg_replace_callback(
				'/\[\^([A-Za-z0-9_-]+)\]/',
				static function ( $m ) use ( &$ref_ids, &$ordinals, &$seen, &$converted ) {
					$id        = $m[1];
					$ref_ids[] = $id;
					if ( ! isset( $seen[ $id ] ) ) {
						$seen[ $id ]     = count( $seen ) + 1;
						$ordinals[ $id ] = $seen[ $id ];
					}
					$converted = true;
					$esc       = esc_attr( $id );
					$num       = $seen[ $id ];
					// 回链锚只在首次出现时带 id（多处引用跳回首个引用位）
					static $first_done = array();
					$id_attr           = isset( $first_done[ $id ] ) ? '' : ' id="' . $esc . '-link"';
					$first_done[ $id ] = true;
					return '<sup class="fn" data-fn="' . $esc . '"><a href="#' . $esc . '"' . $id_attr . '>' . $num . '</a></sup>';
				},
				$seg
			);
		}
		$content = implode( '', $segments );
	}

	/* 既有角标重编号（0.5.0 存量 * 形态 / 引用增删后的序号漂移），幂等 */
	$renumbered = paddysun_ns_renumber_footnote_refs( $content );

	if ( ! $defs && ! $converted && ! $renumbered ) {
		return $content; // 纯文本里的 [^ 假阳性（如代码块内），不动
	}
	$content = $renumbered;

	/* 补文末脚注区块（已有则不重复） */
	if ( false === strpos( $content, 'wp:footnotes' ) && ( $converted || $defs ) ) {
		$content = rtrim( $content ) . "\n\n<!-- wp:footnotes /-->";
	}

	if ( $converted || $defs ) {
		paddysun_ns_footnote_stash(
			array(
				'defs' => $defs,
				'ids'  => $ref_ids,
			)
		);
	}
	return $content;
}

/**
 * 重编号正文脚注角标：按出现顺序为每个 data-fn 重新写序号锚文本，
 * 回链锚 id 只保留在每个脚注的首次出现（多处引用共用目标）。
 * 顺带把 0.5.0 存量的 `*` 锚文本升为序号。幂等。
 *
 * @param string $content 正文（转义或未转义形态皆可）。
 * @return string 重编号后的内容；无脚注时原样返回。
 */
function paddysun_ns_renumber_footnote_refs( $content ) {
	if ( false === strpos( $content, 'data-fn=' ) ) {
		return $content;
	}
	$order  = array();
	$counts = 0;
	$out    = preg_replace_callback(
		'/<sup\b([^>]*)>\s*<a href="#([^"]+)"([^>]*)>\s*(?:\*|\d+)\s*<\/a>\s*<\/sup>/',
		static function ( $m ) use ( &$order, &$counts ) {
			// 只处理脚注角标（sup 带 fn 类与 data-fn 属性，属性顺序两种形态都认）
			if ( false === strpos( $m[1], 'fn' ) || ! preg_match( '/data-fn="([^"]+)"/', $m[1], $dm ) ) {
				return $m[0];
			}
			$id     = $dm[1]; // data-fn 为准（href 应与之相等）
			$counts++;
			if ( ! isset( $order[ $id ] ) ) {
				$order[ $id ] = count( $order ) + 1;
			}
			$num = $order[ $id ];
			// 首次出现保留（或补上）回链 id，后续出现去掉
			static $first = array();
			if ( isset( $first[ $id ] ) ) {
				$attrs = preg_replace( '/\s*id="[^"]*"/', '', $m[3] );
			} else {
				$first[ $id ] = true;
				if ( preg_match( '/\s*id="[^"]*"/', $m[3] ) ) {
					$attrs = preg_replace( '/\s*id="[^"]*"/', ' id="' . esc_attr( $id ) . '-link"', $m[3] );
				} else {
					$attrs = $m[3] . ' id="' . esc_attr( $id ) . '-link"';
				}
			}
			return '<sup class="fn" data-fn="' . esc_attr( $id ) . '"><a href="#' . esc_attr( $id ) . '"' . $attrs . '>' . $num . '</a></sup>';
		},
		$content
	);
	if ( $counts ) {
		paddysun_ns_footnote_order_stash( $order );
	}
	return is_string( $out ) ? $out : $content;
}

/**
 * 重编号通道的顺序暂存（迁移脚本据此重建 footnotes meta 顺序）。
 */
function paddysun_ns_footnote_order_stash( $set = null ) {
	static $order = array();
	if ( null !== $set ) {
		$order = array_values( (array) $set );
	}
	return $order;
}

/**
 * 保存后同步 footnotes meta：引用顺序 + 定义内容合并既有条目
 * （编辑器原生插入的脚注不受影响）。本次请求发生过转换或重编号时执行。
 */
function paddysun_ns_sync_footnotes_meta( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	$stash       = paddysun_ns_footnote_stash();
	$order_stash = paddysun_ns_footnote_order_stash();
	if ( empty( $stash ) && empty( $order_stash ) ) {
		return;
	}

	/* 数据库已是未转义形态；按最终内容里的引用顺序编号 */
	if ( ! preg_match_all( '/data-fn="([^"]+)"/', $post->post_content, $m ) ) {
		paddysun_ns_footnote_stash( array() );
		paddysun_ns_footnote_order_stash( array() );
		return;
	}
	$order = $m[1];
	$defs  = isset( $stash['defs'] ) && is_array( $stash['defs'] ) ? $stash['defs'] : array();

	$existing = array();
	$raw      = get_post_meta( $post_id, 'footnotes', true );
	if ( is_string( $raw ) && '' !== $raw ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $entry ) {
				if ( ! empty( $entry['id'] ) ) {
					$existing[ $entry['id'] ] = is_array( $entry ) ? $entry : array();
				}
			}
		}
	}

	$list = array();
	$seen = array();
	foreach ( $order as $id ) {
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ]    = true;
		$entry_content  = isset( $defs[ $id ] ) ? $defs[ $id ] : ( isset( $existing[ $id ]['content'] ) ? $existing[ $id ]['content'] : '' );
		$list[]         = array(
			'id'      => $id,
			'content' => $entry_content,
		);
	}

	if ( $list ) {
		update_post_meta( $post_id, 'footnotes', wp_slash( wp_json_encode( $list ) ) );
	} else {
		delete_post_meta( $post_id, 'footnotes' );
	}
	paddysun_ns_footnote_stash( array() );      // 消费即清空，防串写
	paddysun_ns_footnote_order_stash( array() );
}
add_action( 'save_post', 'paddysun_ns_sync_footnotes_meta', 20, 2 );

/**
 * MD 转换路径（REST / 复制按钮 / llms-full）里 the_content 顶层渲染
 * core/footnotes 时没有 postId 上下文（模板里由 post-content 包装提供），
 * 区块会渲染为空——此处按转换目标文章补上下文。
 */
function paddysun_ns_md_context_post_id( $set = null ) {
	static $pid = 0;
	if ( null !== $set ) {
		$pid = (int) $set;
	}
	return $pid;
}

function paddysun_ns_footnotes_render_context( $context, $block ) {
	if ( 'core/footnotes' === ( $block['blockName'] ?? '' ) && empty( $context['postId'] ) ) {
		$pid = paddysun_ns_md_context_post_id();
		if ( $pid ) {
			$context['postId'] = $pid;
		}
	}
	return $context;
}
add_filter( 'render_block_context', 'paddysun_ns_footnotes_render_context', 10, 2 );
add_filter( 'content_save_pre', 'paddysun_ns_normalize_block_markup' );

/**
 * 取当前文章目录（须在 the_content 之后调用）。
 * h2 数量 ≥ 3 才值得显示目录盒。
 *
 * @param string $extra_class 附加类（悬浮形态传 np-toc--float）。
 */
function paddysun_ns_get_toc( $extra_class = '' ) {
	global $paddysun_ns_toc;
	if ( empty( $paddysun_ns_toc ) ) {
		return '';
	}
	$h2 = 0;
	foreach ( $paddysun_ns_toc as $item ) {
		if ( 2 === $item['level'] ) {
			$h2++;
		}
	}
	if ( $h2 < 3 ) {
		return '';
	}

	$class = trim( 'np-toc ' . $extra_class );
	/* 0.7.2 站长要求：details/summary 提供「收起/拉出」（原生开合零 JS、
	   键盘可达、读屏自动播报展开态）；两态文案都在 DOM 里由 CSS 切换显示。 */
	$html  = '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr__( '文章目录', 'paddysun-newsprint' ) . '">'
		. '<details class="np-toc__box" open>'
		. '<summary class="np-toc__title"><span class="np-toc__title-text">' . esc_html__( '目 录', 'paddysun-newsprint' ) . '</span>'
		. '<span class="np-toc__toggle" aria-hidden="true"><span class="np-toc__toggle-in">' . esc_html__( '收起', 'paddysun-newsprint' ) . '</span><span class="np-toc__toggle-out">' . esc_html__( '拉出', 'paddysun-newsprint' ) . '</span></span></summary>'
		. '<ol>';
	foreach ( $paddysun_ns_toc as $item ) {
		$cls  = 3 === $item['level'] ? ' class="np-toc__sub"' : '';
		$html .= '<li' . $cls . '><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a></li>';
	}
	$html .= '</ol></details></nav>';
	return $html;
}
