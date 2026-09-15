<?php
/**
 * llms.txt / llms-full.txt 的后台开关与内容编辑（站长 2026-09-15 需求）。
 *
 * 入口：后台「外观 → llms.txt 设置」独立设置页（站长 2026-09-15 验收选定 D 方案）。
 * 这里不用「外观 → 菜单」页：WP 7.1 的 nav-menus.php 已不再调用 do_meta_boxes，
 * 页内没有能把独立表单放进内容容器、又同时覆盖两个标签页的钩子（do_accordion_sections
 * 的输出在外层 form#nav-menu-meta 内部，会形成嵌套表单；after_menu_locations_table 只在
 * 「管理位置」标签页；页脚输出位于 #wpcontent 之外，需自行补偿核心缩进）。标准设置页
 * 既没有这些约束，风格也与「设置 → 阅读」一致。
 *
 * 存储：单个 option `paddysun_ns_llms`（数组），经 register_setting() + options.php
 * 保存，nonce、权限、重定向与错误提示全部交由核心处理。默认值与 0.10.12 现有行为
 * 完全一致，升级不写迁移、不注册激活钩子。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PADDYSUN_NS_LLMS_OPTION          = 'paddysun_ns_llms';
const PADDYSUN_NS_LLMS_GROUP           = 'paddysun_ns_llms_group';
const PADDYSUN_NS_LLMS_SLUG            = 'paddysun-llms';
const PADDYSUN_NS_LLMS_TEXT_MAX_BYTES  = 1048576; // 手动文本上限 1 MiB，防失控

/* 输入框里的规范格式提示（中英双语，按 llms.txt v2 的结构）。 */
const PADDYSUN_NS_LLMS_INTRO_HINT  = "> 一句话简介 / One-line summary\n\n可选补充段落 / optional context paragraph";
const PADDYSUN_NS_LLMS_MANUAL_HINT = "# 站点名 / Site name\n\n> 一句话简介 / One-line summary\n\n"
	. "## 章节名 / Section\n\n- [标题 / Title](https://example.com/page)：说明 / note";

/**
 * 默认设置。
 *
 * 除 `full_enabled` 外，每一项都对应 0.10.12 的既有行为（升级不改公开输出）。
 * `full_enabled` 默认**关闭**是站长 2026-09-15 验收的明确要求：全量正文属可选功能，
 * 新站与升级站都要先到设置页开启，因此 /llms-full.txt 默认返回 403——这是相对
 * 0.10.12 的一次**有意的行为变更**，不属默认值回归范围。
 *
 * @return array
 */
function paddysun_ns_llms_defaults() {
	return array(
		'index_enabled' => true,
		'full_enabled'  => false,
		'index_mode'    => 'auto',
		'index_intro'   => '',
		'index_manual'  => '',
		'full_count'    => 100,
		'full_content'  => 'full',
	);
}

/**
 * 归一化：白名单与区间夹取，非法存量值一律回退默认，不抛异常。
 *
 * 两个开关是复选框，所以「键缺失」在两种语境里含义不同：
 *  - 读存量 option（默认）：缺失 = 尚未设置 = 取默认值（开启），升级后行为不变；
 *  - 读表单输入（$checkbox_absent_is_off = true）：缺失 = 未勾选 = 关闭。
 *
 * @param mixed $raw                    原始值（option 存量或表单输入）。
 * @param bool  $checkbox_absent_is_off 键缺失时两个开关是否按“关”处理。
 * @return array
 */
function paddysun_ns_llms_normalize( $raw, $checkbox_absent_is_off = false ) {
	$defaults = paddysun_ns_llms_defaults();
	$raw      = is_array( $raw ) ? $raw : array();

	$flags = array();
	foreach ( array( 'index_enabled', 'full_enabled' ) as $key ) {
		if ( array_key_exists( $key, $raw ) ) {
			$flags[ $key ] = ! empty( $raw[ $key ] );
		} else {
			$flags[ $key ] = $checkbox_absent_is_off ? false : $defaults[ $key ];
		}
	}

	$modes   = array( 'auto', 'partial', 'manual' );
	$bodies  = array( 'full', 'excerpt' );
	$count   = isset( $raw['full_count'] ) ? (int) $raw['full_count'] : $defaults['full_count'];
	$settings = array(
		'index_enabled' => $flags['index_enabled'],
		'full_enabled'  => $flags['full_enabled'],
		'index_mode'    => isset( $raw['index_mode'] ) && in_array( $raw['index_mode'], $modes, true ) ? $raw['index_mode'] : $defaults['index_mode'],
		'index_intro'   => isset( $raw['index_intro'] ) && is_string( $raw['index_intro'] ) ? $raw['index_intro'] : '',
		'index_manual'  => isset( $raw['index_manual'] ) && is_string( $raw['index_manual'] ) ? $raw['index_manual'] : '',
		'full_count'    => min( 500, max( 1, $count ) ),
		'full_content'  => isset( $raw['full_content'] ) && in_array( $raw['full_content'], $bodies, true ) ? $raw['full_content'] : $defaults['full_content'],
	);
	/* 总开关关闭时全量端点必然不可用：存储层保持一致，避免出现“关了索引却开着全文”的悬空状态 */
	if ( ! $settings['index_enabled'] ) {
		$settings['full_enabled'] = false;
	}
	return $settings;
}

/**
 * 当前生效设置。所有读取都走这里。
 *
 * @return array
 */
function paddysun_ns_llms_settings() {
	return paddysun_ns_llms_normalize( get_option( PADDYSUN_NS_LLMS_OPTION, array() ) );
}

/**
 * 手动文本清洗：纯文本原样存档（与 post meta `_paddysun_source_md` 同口径），
 * 仅去 NUL 与超限截断；输出面是 text/plain，不做 HTML 解析。
 *
 * @param mixed $value 原始文本（来自 $_POST，已加斜杠）。
 * @return string
 */
function paddysun_ns_llms_clean_text( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$text = str_replace( "\0", '', wp_unslash( $value ) );
	if ( strlen( $text ) > PADDYSUN_NS_LLMS_TEXT_MAX_BYTES ) {
		$text = paddysun_ns_safe_cut_bytes( $text, PADDYSUN_NS_LLMS_TEXT_MAX_BYTES );
	}
	return $text;
}

/**
 * register_setting 的 sanitize_callback。
 *
 * @param mixed $input 表单输入。
 * @return array
 */
function paddysun_ns_llms_sanitize( $input ) {
	$input  = is_array( $input ) ? $input : array();
	$stored = paddysun_ns_llms_settings();
	foreach ( array( 'index_intro', 'index_manual' ) as $key ) {
		/* 输入框只在对应模式下渲染：键缺失 = 本次没有渲染 = 保留原值，
		 * 不能当成「清空」，否则切换模式会静默丢掉已存内容。 */
		$input[ $key ] = array_key_exists( $key, $input )
			? paddysun_ns_llms_clean_text( $input[ $key ] )
			: $stored[ $key ];
	}
	$settings = paddysun_ns_llms_normalize( $input, true );

	if ( ! $settings['index_enabled'] ) {
		add_settings_error(
			PADDYSUN_NS_LLMS_OPTION,
			'paddysun_llms_index_off',
			__( 'llms.txt 已关闭：/llms.txt 与 /llms-full.txt 均返回 403，全量开关一并关闭。', 'paddysun-newsprint' ),
			'warning'
		);
	} elseif ( $settings['index_mode'] === 'manual' && '' === trim( $settings['index_manual'] ) ) {
		add_settings_error(
			PADDYSUN_NS_LLMS_OPTION,
			'paddysun_llms_manual_empty',
			__( '全文手动模式的内容为空，当前回退为自动生成。', 'paddysun-newsprint' ),
			'warning'
		);
	}
	return $settings;
}

function paddysun_ns_llms_register_setting() {
	register_setting(
		PADDYSUN_NS_LLMS_GROUP,
		PADDYSUN_NS_LLMS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'paddysun_ns_llms_sanitize',
			'default'           => paddysun_ns_llms_defaults(),
		)
	);
}
add_action( 'admin_init', 'paddysun_ns_llms_register_setting' );

/**
 * 手动内容是否缺少规范要求的首个 H1（仅提示，不阻断保存、不改写站长内容）。
 *
 * @param string $text 手动全文。
 * @return bool
 */
function paddysun_ns_llms_manual_missing_h1( $text ) {
	$line = strtok( ltrim( (string) $text ), "\n" );
	$line = is_string( $line ) ? ltrim( $line ) : '';
	return '' !== $line && 0 !== strpos( $line, '# ' );
}

/* ------------------------------------------------------------
 * 设置页：外观 → llms.txt 设置
 * ---------------------------------------------------------- */

/**
 * 在「外观」下注册设置页（站长选定 D 方案，2026-09-15）。
 */
function paddysun_ns_llms_add_page() {
	add_theme_page(
		__( 'llms.txt 设置', 'paddysun-newsprint' ),
		__( 'llms.txt 设置', 'paddysun-newsprint' ),
		'manage_options',
		PADDYSUN_NS_LLMS_SLUG,
		'paddysun_ns_llms_render_page'
	);
}
add_action( 'admin_menu', 'paddysun_ns_llms_add_page' );

function paddysun_ns_llms_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = paddysun_ns_llms_settings();
	$index_url = home_url( '/llms.txt' );
	$full_url  = home_url( '/llms-full.txt' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'llms.txt 设置', 'paddysun-newsprint' ); ?></h1>
		<?php settings_errors(); ?>

		<p class="description">
			<?php esc_html_e( '面向 AI 读者的纯文本索引（llms.txt v2 惯例）。当前地址：', 'paddysun-newsprint' ); ?>
			<code><?php echo esc_html( $index_url ); ?></code>
			<?php esc_html_e( '与', 'paddysun-newsprint' ); ?>
			<code><?php echo esc_html( $full_url ); ?></code>。
			<?php esc_html_e( '若两者都返回 404，请到「设置 → 固定链接」点一次「保存更改」刷新重写规则。', 'paddysun-newsprint' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
			<?php settings_fields( PADDYSUN_NS_LLMS_GROUP ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'llms.txt', 'paddysun-newsprint' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( PADDYSUN_NS_LLMS_OPTION ); ?>[index_enabled]" value="1"<?php checked( $settings['index_enabled'] ); ?>>
							<?php esc_html_e( '开启 /llms.txt 索引', 'paddysun-newsprint' ); ?>
						</label>
						<p class="description"><?php esc_html_e( '关闭后 /llms.txt 与 /llms-full.txt 一律返回 403，并停止广播 rel="describedby"。', 'paddysun-newsprint' ); ?></p>
					</td>
				</tr>
				<?php if ( $settings['index_enabled'] ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( '内容来源', 'paddysun-newsprint' ); ?></th>
					<td>
						<?php
						$modes = array(
							'auto'    => __( '自动更新生成（站点名 + 简介 + 按分类的最新文章列表）', 'paddysun-newsprint' ),
							'partial' => __( '部分手动：自动列表不变，站点介绍文字由我填写', 'paddysun-newsprint' ),
							'manual'  => __( '全部手动：整份内容由我填写，不再自动生成', 'paddysun-newsprint' ),
						);
						foreach ( $modes as $value => $label ) :
							?>
							<p style="margin:6px 0">
								<label>
									<input type="radio" name="<?php echo esc_attr( PADDYSUN_NS_LLMS_OPTION ); ?>[index_mode]" value="<?php echo esc_attr( $value ); ?>"<?php checked( $settings['index_mode'], $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							</p>
							<?php
						endforeach;
						if ( 'auto' === $settings['index_mode'] ) :
							?>
							<p class="description"><?php esc_html_e( '当前为「自动更新生成」，不需要手填内容。改选「部分手动」或「全部手动」并保存后，这里会出现对应的输入框。', 'paddysun-newsprint' ); ?></p>
							<?php
						endif;
						?>
					</td>
				</tr>
				<?php if ( 'partial' === $settings['index_mode'] ) : ?>
					<tr>
						<th scope="row"><label for="paddysun-llms-intro"><?php esc_html_e( '站点介绍文字', 'paddysun-newsprint' ); ?></label></th>
						<td>
							<textarea id="paddysun-llms-intro" name="<?php echo esc_attr( PADDYSUN_NS_LLMS_OPTION ); ?>[index_intro]" rows="6" class="large-text code" placeholder="<?php echo esc_attr( PADDYSUN_NS_LLMS_INTRO_HINT ); ?>"><?php echo esc_textarea( $settings['index_intro'] ); ?></textarea>
							<p class="description"><?php esc_html_e( '原样替换站点名之后的介绍段落；留空则回退为站点的自动简介。', 'paddysun-newsprint' ); ?></p>
						</td>
					</tr>
				<?php elseif ( 'manual' === $settings['index_mode'] ) : ?>
					<tr>
						<th scope="row"><label for="paddysun-llms-manual"><?php esc_html_e( '手动全文', 'paddysun-newsprint' ); ?></label></th>
						<td>
							<textarea id="paddysun-llms-manual" name="<?php echo esc_attr( PADDYSUN_NS_LLMS_OPTION ); ?>[index_manual]" rows="10" class="large-text code" placeholder="<?php echo esc_attr( PADDYSUN_NS_LLMS_MANUAL_HINT ); ?>"><?php echo esc_textarea( $settings['index_manual'] ); ?></textarea>
							<p class="description"><?php esc_html_e( '内容原样输出、不做改写；留空则回退为自动生成。', 'paddysun-newsprint' ); ?></p>
							<?php if ( paddysun_ns_llms_manual_missing_h1( $settings['index_manual'] ) ) : ?>
								<p class="description" style="color:#b32d2e"><?php esc_html_e( '当前首行不是「# 」开头的一级标题；llms.txt 规范要求首个 H1，建议补上（保存不会被拒绝）。', 'paddysun-newsprint' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
				<?php endif; // 总开关关闭时，连「内容来源」与其输入框一并隐藏。 ?>
				<?php if ( $settings['index_enabled'] ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'llms-full.txt', 'paddysun-newsprint' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( PADDYSUN_NS_LLMS_OPTION ); ?>[full_enabled]" value="1"<?php checked( $settings['full_enabled'] ); ?>>
								<?php esc_html_e( '开启 /llms-full.txt 全量正文', 'paddysun-newsprint' ); ?>
							</label>
							<p class="description"><?php esc_html_e( '关闭后该地址返回 403；聚合总量仍有 4 MiB 上限，超出即止并附单篇接口指引。', 'paddysun-newsprint' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '全量篇数', 'paddysun-newsprint' ); ?></th>
						<td>
							<input type="number" min="1" max="500" step="1" name="<?php echo esc_attr( PADDYSUN_NS_LLMS_OPTION ); ?>[full_count]" value="<?php echo esc_attr( (string) $settings['full_count'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( '等于「设置 → 阅读」里的 Feed 篇数语义：取最新的 N 篇（1–500，默认 100）。', 'paddysun-newsprint' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '全量内容', 'paddysun-newsprint' ); ?></th>
						<td>
							<?php
							$bodies = array(
								'full'    => __( '全文（转换后的 Markdown）', 'paddysun-newsprint' ),
								'excerpt' => __( '仅摘要（与 Feed 摘要同口径）', 'paddysun-newsprint' ),
							);
							foreach ( $bodies as $value => $label ) :
								?>
								<p style="margin:6px 0">
									<label>
										<input type="radio" name="<?php echo esc_attr( PADDYSUN_NS_LLMS_OPTION ); ?>[full_content]" value="<?php echo esc_attr( $value ); ?>"<?php checked( $settings['full_content'], $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								</p>
								<?php
							endforeach;
							?>
						</td>
					</tr>
				<?php else : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'llms-full.txt', 'paddysun-newsprint' ); ?></th>
						<td><p class="description"><?php esc_html_e( 'llms.txt 关闭时全量端点一并不可用，因此不提供单独开关。', 'paddysun-newsprint' ); ?></p></td>
					</tr>
				<?php endif; ?>
			</table>
			<?php submit_button( __( '保存 llms 设置', 'paddysun-newsprint' ) ); ?>
		</form>
	</div><!-- /.wrap -->
	<?php
}
