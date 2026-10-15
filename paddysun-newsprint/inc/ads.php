<?php
/**
 * 广告位：后台可编辑的三个槽位（首页横幅 / 文章页侧栏 / 文章页读者来信上方）。
 *
 * 出厂形态是占位/收藏品（assets/img/placeholders/ad-*.png，或站长在后台按
 * 桌面端/平板端/手机端分别自选媒体库图片 + 选填跳转链接）；只有站长在后台
 * 填入广告代码后才输出真实广告。代码是 HTML+JS，**不做 kses 清洗**（会毁
 * <script>/<ins>），只允许 manage_options 修改、保存时去 NUL + 体积上限，
 * 输出时视为受信管理员内容（与「可编辑主题」能力同级，站长决策 2026-09-19）。
 *
 * 槽位与设备框（站长验收口径 2026-09-19，二轮反馈修订）：
 *  - home    首页横幅：订阅栏与本期要目之间，宽度与整版分隔线一致（自适应）；
 *            设备框 桌面 728×90 / 平板 468×60 / 手机 320×50，三端均输出。
 *  - article 文章页侧栏方形：**桌面端专属**（≥1240px）固定右栏随悬浮目录
 *            （有目录贴其下方、无目录替代其位，整体较目录再右移 24px）；
 *            手机/平板不输出（页末位由 article_bottom 承担）；设备框 300×250。
 *  - article_bottom 文章页读者来信上方：**三端均输出**，宽度一律与文章尾
 *            双规线一致（正文栏自适应）；设备框 桌面 728×90 / 平板 468×60 / 手机 320×50。
 * 每端自定义框独立配置：占位图 + 独立开关（关掉 = 该端不输出该槽位）+
 * 独立跳转链接（各端占位可指向不同地址）；槽位级总开关保留。
 * 占位图语义：在设备框内自动拉伸铺满（object-fit: cover），超出框比部分裁切。
 *
 * 边界（站长口径 2026-09-19）：
 *  - 主题**不做** head 自动注入及其适配；脚本如何加载由各站点自行决定，
 *    主题只负责槽位渲染与失效清理（超时/出错后按后台配置换占位或整块不输出）。
 *  - 失效探测用广告联盟写入的状态属性（data-ad-status）与容器内脚本的
 *    error 事件判定，不读取、不硬编码任何联盟专有标识。
 *  - 不外溢：llms / REST Markdown / Feed / JSON-LD / <meta> 一律不读本 option。
 *  - 首页 0 JS 基线：出厂/占位态零脚本；仅当首页槽位填了代码才加载清理脚本。
 *
 * 设置页做法与 llms-settings.php 同一套路：add_theme_page + Settings API +
 * options.php 落库；「键缺失 = 本次没渲染 = 保留原值」（0.10.13 实测教训）。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PADDYSUN_NS_AD_CODE_MAX = 65536;

/**
 * 槽位设备框规格（宽, 高, 内置占位图）。渲染与后台提示共用。
 *
 * @return array<string, array<string, array{0:int,1:int,2:string}>>
 */
function paddysun_ns_ad_device_boxes() {
	return array(
		'home'    => array(
			// 桌面端随整版分隔线宽度（≈1180），框高固定 100：避免 728/90 拉伸到 146px（站长验收四轮口径）
			'desktop' => array( 1180, 100, 'ad-728x90.png' ),
			'tablet'  => array( 468, 60, 'ad-468x60.png' ),
			'mobile'  => array( 320, 50, 'ad-320x50.png' ),
		),
		'article' => array(
			'desktop' => array( 300, 250, 'ad-300x250.png' ),
			'tablet'  => array( 300, 250, 'ad-300x250.png' ),
			'mobile'  => array( 300, 250, 'ad-300x250.png' ),
		),
		'article_bottom' => array(
			'desktop' => array( 728, 90, 'ad-728x90.png' ),
			'tablet'  => array( 468, 60, 'ad-468x60.png' ),
			'mobile'  => array( 320, 50, 'ad-320x50.png' ),
		),
	);
}

/**
 * 出厂默认：三槽位都开启（占位形态），各端启用、无自定义占位、无独立链接、
 * 清理=换占位图。
 *
 * @return array
 */
function paddysun_ns_ads_defaults() {
	$slots = array();
	foreach ( array_keys( paddysun_ns_ad_device_boxes() ) as $key ) {
		$slots[ $key ] = array(
			'enabled' => true,
			'code'    => '',
			'devices' => array(
				'desktop' => array( 'enabled' => true, 'ph_id' => 0, 'ph_link' => '' ),
				'tablet'  => array( 'enabled' => true, 'ph_id' => 0, 'ph_link' => '' ),
				'mobile'  => array( 'enabled' => true, 'ph_id' => 0, 'ph_link' => '' ),
			),
		);
	}
	return array( 'cleanup' => 'placeholder', 'slots' => $slots );
}

/**
 * 读侧归一化：形状补齐 + 白名单 + 夹取（防存量脏值/手工改库）；
 * 兼容两代旧形状：单 ph_id（迁到 desktop）、槽位级 ph_link（迁到各端）。
 *
 * @param mixed $raw 存储值。
 * @return array
 */
function paddysun_ns_ads_normalize( $raw ) {
	$defaults = paddysun_ns_ads_defaults();
	if ( ! is_array( $raw ) ) {
		return $defaults;
	}
	$out = $defaults;
	if ( in_array( $raw['cleanup'] ?? '', array( 'placeholder', 'remove' ), true ) ) {
		$out['cleanup'] = $raw['cleanup'];
	}
	foreach ( array_keys( $defaults['slots'] ) as $key ) {
		$slot = $raw['slots'][ $key ] ?? null;
		if ( ! is_array( $slot ) ) {
			continue;
		}
		$out['slots'][ $key ]['enabled'] = ! empty( $slot['enabled'] );
		$code                            = $slot['code'] ?? '';
		$out['slots'][ $key ]['code']    = is_string( $code ) ? $code : '';
		$legacy_link                     = esc_url_raw( trim( (string) ( $slot['ph_link'] ?? '' ) ) );
		$devices                         = $slot['devices'] ?? null;
		if ( ! is_array( $devices ) && isset( $slot['ph_id'] ) ) {
			$devices = array( 'desktop' => array( 'ph_id' => $slot['ph_id'] ) ); // 旧形状迁移。
		}
		if ( ! is_array( $devices ) ) {
			continue;
		}
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			$dev = $devices[ $device ] ?? null;
			$dev = is_array( $dev ) ? $dev : array();
			$out['slots'][ $key ]['devices'][ $device ]['enabled'] = ! isset( $dev['enabled'] ) || ! empty( $dev['enabled'] );
			$out['slots'][ $key ]['devices'][ $device ]['ph_id']   = absint( $dev['ph_id'] ?? 0 );
			$link                                                  = esc_url_raw( trim( (string) ( $dev['ph_link'] ?? '' ) ) );
			// 旧版槽位级链接迁移：各端无独立链接时继承。
			$out['slots'][ $key ]['devices'][ $device ]['ph_link'] = '' !== $link ? $link : $legacy_link;
		}
	}
	return $out;
}

/**
 * 清洗回调：仅 manage_options；键缺失 = 保留原值；代码去 NUL + 体积上限，
 * 故意不过 kses（保留 <script>/<ins>）。
 *
 * @param mixed $raw POST 原始值（斜杠转义）。
 * @return array
 */
function paddysun_ns_ads_sanitize( $raw ) {
	$current = paddysun_ns_ads_normalize( get_option( 'paddysun_ns_ads', array() ) );
	if ( ! current_user_can( 'manage_options' ) ) {
		return $current; // 菜单声明的能力只影响菜单显示，这里再判一次。
	}
	if ( ! is_array( $raw ) ) {
		return $current;
	}
	$out = $current;
	if ( in_array( $raw['cleanup'] ?? '', array( 'placeholder', 'remove' ), true ) ) {
		$out['cleanup'] = $raw['cleanup'];
	}
	foreach ( array_keys( $current['slots'] ) as $key ) {
		$slot = $raw['slots'][ $key ] ?? null;
		if ( ! is_array( $slot ) ) {
			continue; // 本次没渲染该槽位 = 保留原值。
		}
		$out['slots'][ $key ]['enabled'] = ! empty( $slot['enabled'] );
		if ( array_key_exists( 'code', $slot ) ) {
			$code = str_replace( "\0", '', (string) wp_unslash( $slot['code'] ) );
			if ( strlen( $code ) > PADDYSUN_NS_AD_CODE_MAX ) {
				$code = substr( $code, 0, PADDYSUN_NS_AD_CODE_MAX );
			}
			$out['slots'][ $key ]['code'] = trim( $code );
		}
		$devices = $slot['devices'] ?? null;
		if ( is_array( $devices ) ) {
			foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
				$dev = $devices[ $device ] ?? null;
				if ( ! is_array( $dev ) ) {
					continue; // 该端本次没渲染 = 保留原值。
				}
				$out['slots'][ $key ]['devices'][ $device ]['enabled'] = ! empty( $dev['enabled'] );
				if ( array_key_exists( 'ph_id', $dev ) ) {
					$out['slots'][ $key ]['devices'][ $device ]['ph_id'] = absint( $dev['ph_id'] );
				}
				if ( array_key_exists( 'ph_link', $dev ) ) {
					$out['slots'][ $key ]['devices'][ $device ]['ph_link'] = esc_url_raw( trim( (string) wp_unslash( $dev['ph_link'] ) ) );
				}
			}
		}
	}
	return $out;
}

function paddysun_ns_ads_admin_menu() {
	add_theme_page(
		__( '广告位设置', 'paddysun-newsprint' ),
		__( '广告位', 'paddysun-newsprint' ),
		'manage_options',
		'paddysun-ns-ads',
		'paddysun_ns_ads_render_page'
	);
}
add_action( 'admin_menu', 'paddysun_ns_ads_admin_menu' );

function paddysun_ns_ads_admin_init() {
	register_setting(
		'paddysun_ns_ads_group',
		'paddysun_ns_ads',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'paddysun_ns_ads_sanitize',
			'default'           => paddysun_ns_ads_defaults(),
		)
	);
}
add_action( 'admin_init', 'paddysun_ns_ads_admin_init' );

/**
 * 后台页：标准 .wrap 版式（同 llms-settings 页），每槽位含 桌面端/平板端/手机端
 * 三个自定义占位框（媒体库选图，框规格提示）。
 *
 * @return void
 */
function paddysun_ns_ads_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = paddysun_ns_ads_normalize( get_option( 'paddysun_ns_ads', array() ) );
	$boxes    = paddysun_ns_ad_device_boxes();
	$slots    = array(
		'home'    => array(
			'title' => __( '首页横幅位', 'paddysun-newsprint' ),
			'desc'  => __( '输出在首页「订阅栏」与「本期要目」之间，宽度与整版分隔线一致（自适应缩放）；三端均输出。', 'paddysun-newsprint' ),
		),
		'article' => array(
			'title' => __( '文章页侧栏位（方形，桌面端专属）', 'paddysun-newsprint' ),
			'desc'  => __( '仅桌面端（≥1240px）：固定右栏，随悬浮目录（有目录贴其下方并整体较目录右移，未开启目录时替代其固定位）。手机/平板不输出——页末位由「读者来信上方」槽位承担。设备框 300×250。', 'paddysun-newsprint' ),
		),
		'article_bottom' => array(
			'title' => __( '文章页 · 读者来信上方', 'paddysun-newsprint' ),
			'desc'  => __( '三端均输出在「读者来信」上方，宽度一律与文章尾双规线一致（正文栏自适应）。', 'paddysun-newsprint' ),
		),
	);
	$labels   = array(
		'desktop' => __( '桌面端', 'paddysun-newsprint' ),
		'tablet'  => __( '平板端', 'paddysun-newsprint' ),
		'mobile'  => __( '手机端', 'paddysun-newsprint' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( '广告位设置', 'paddysun-newsprint' ); ?></h1>
		<?php settings_errors(); ?>
		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
			<?php settings_fields( 'paddysun_ns_ads_group' ); ?>
			<h2 class="title"><?php esc_html_e( '加载失败后的处理', 'paddysun-newsprint' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( '清理语义（全局）', 'paddysun-newsprint' ); ?></th>
					<td>
						<label><input type="radio" name="paddysun_ns_ads[cleanup]" value="placeholder" <?php checked( $settings['cleanup'], 'placeholder' ); ?> /> <?php esc_html_e( '换回占位图', 'paddysun-newsprint' ); ?></label><br />
						<label><input type="radio" name="paddysun_ns_ads[cleanup]" value="remove" <?php checked( $settings['cleanup'], 'remove' ); ?> /> <?php esc_html_e( '整块不输出（该槽位从页面移除）', 'paddysun-newsprint' ); ?></label>
						<p class="description"><?php esc_html_e( '对所有槽位统一生效：广告容器内脚本出错，或联盟状态标记显示未填充时，在超时检查点（加载完成后 2.5 秒，可经 paddysun_ns_ad_deadline_ms 过滤器调整）按此设置处理。', 'paddysun-newsprint' ); ?></p>
					</td>
				</tr>
			</table>
			<?php foreach ( $slots as $key => $meta ) : ?>
				<h2 class="title"><?php echo esc_html( $meta['title'] ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( '启用槽位', 'paddysun-newsprint' ); ?></th>
						<td>
							<label><input type="checkbox" name="paddysun_ns_ads[slots][<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $settings['slots'][ $key ]['enabled'] ); ?> /> <?php esc_html_e( '在前台输出该槽位（未启用 = 整块不输出）', 'paddysun-newsprint' ); ?></label>
							<p class="description"><?php echo esc_html( $meta['desc'] ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="paddysun-ns-ads-code-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '广告代码', 'paddysun-newsprint' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="6" id="paddysun-ns-ads-code-<?php echo esc_attr( $key ); ?>" name="paddysun_ns_ads[slots][<?php echo esc_attr( $key ); ?>][code]"><?php echo esc_textarea( $settings['slots'][ $key ]['code'] ); ?></textarea>
							<p class="description"><?php esc_html_e( '留空 = 只显示占位。填入的内容按受信管理员内容原样输出（不做标记清洗）；脚本的注入方式由站点自行决定，主题不代注入。各端占位框比例即该端预留高度（占位 ↔ 广告 ↔ 清理回占位零跳动），请填与框规格一致的固定尺寸单元以避免布局跳动。', 'paddysun-newsprint' ); ?></p>
						</td>
					</tr>
					<?php if ( 'article' === $key ) : ?>
						<?php // 桌面端专属槽位：单一配置即可，无需分端（站长验收三轮口径）。 ?>
						<tr>
							<th scope="row"><?php esc_html_e( '方形占位图（桌面端）', 'paddysun-newsprint' ); ?></th>
							<td>
								<?php
								list( $bw, $bh, $default_file ) = $boxes[ $key ]['desktop'];
								$dev  = $settings['slots'][ $key ]['devices']['desktop'];
								$asset = paddysun_ns_ad_placeholder_asset( $key, 'desktop', $settings['slots'][ $key ] );
								?>
								<input type="hidden" name="paddysun_ns_ads[slots][<?php echo esc_attr( $key ); ?>][devices][desktop][ph_id]" value="<?php echo esc_attr( (string) $dev['ph_id'] ); ?>" data-np-ph-input="<?php echo esc_attr( $key . ':desktop' ); ?>" />
								<span data-np-ph-preview="<?php echo esc_attr( $key . ':desktop' ); ?>"><?php if ( $asset['custom'] ) : ?><img src="<?php echo esc_url( $asset['url'] ); ?>" alt="" style="max-width:280px;max-height:110px;display:block;margin-bottom:6px;" /><?php endif; ?></span>
								<button type="button" class="button" data-np-ph-pick="<?php echo esc_attr( $key . ':desktop' ); ?>"><?php esc_html_e( '从媒体库选择', 'paddysun-newsprint' ); ?></button>
								<button type="button" class="button-link" data-np-ph-clear="<?php echo esc_attr( $key . ':desktop' ); ?>"><?php esc_html_e( '清除（回内置占位图）', 'paddysun-newsprint' ); ?></button>
								<p style="margin:6px 0;">
									<label><?php esc_html_e( '占位图跳转链接（选填）：', 'paddysun-newsprint' ); ?>
										<input type="url" class="regular-text code" name="paddysun_ns_ads[slots][<?php echo esc_attr( $key ); ?>][devices][desktop][ph_link]" value="<?php echo esc_attr( $dev['ph_link'] ); ?>" />
									</label>
								</p>
								<p class="description" style="margin:4px 0 0;"><?php echo esc_html( sprintf( /* translators: 1: width, 2: height */ __( '框规格 %1$d×%2$d；选图在框内自动拉伸铺满，超出比例部分裁切；未自定义时用内置占位图。', 'paddysun-newsprint' ), $bw, $bh ) ); ?></p>
							</td>
						</tr>
					<?php else : ?>
					<tr>
						<th scope="row"><?php esc_html_e( '分端自定义框（开关 / 占位图 / 独立链接）', 'paddysun-newsprint' ); ?></th>
						<td>
							<?php foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) : ?>
								<?php
								list( $bw, $bh, $default_file ) = $boxes[ $key ][ $device ];
								$dev  = $settings['slots'][ $key ]['devices'][ $device ];
								$asset = paddysun_ns_ad_placeholder_asset( $key, $device, $settings['slots'][ $key ] );
								?>
								<fieldset style="margin:10px 0;padding:8px 12px;border:1px solid #dcdcde;">
									<legend><strong><?php echo esc_html( $labels[ $device ] ); ?></strong></legend>
									<label style="display:block;margin-bottom:6px;">
										<input type="checkbox" name="paddysun_ns_ads[slots][<?php echo esc_attr( $key ); ?>][devices][<?php echo esc_attr( $device ); ?>][enabled]" value="1" <?php checked( $dev['enabled'] ); ?> />
										<?php esc_html_e( '在该端输出此槽位（关掉 = 该端整块不输出）', 'paddysun-newsprint' ); ?>
									</label>
									<input type="hidden" name="paddysun_ns_ads[slots][<?php echo esc_attr( $key ); ?>][devices][<?php echo esc_attr( $device ); ?>][ph_id]" value="<?php echo esc_attr( (string) $dev['ph_id'] ); ?>" data-np-ph-input="<?php echo esc_attr( $key . ':' . $device ); ?>" />
									<span data-np-ph-preview="<?php echo esc_attr( $key . ':' . $device ); ?>"><?php if ( $asset['custom'] ) : ?><img src="<?php echo esc_url( $asset['url'] ); ?>" alt="" style="max-width:280px;max-height:110px;display:block;margin-bottom:6px;" /><?php endif; ?></span>
									<button type="button" class="button" data-np-ph-pick="<?php echo esc_attr( $key . ':' . $device ); ?>"><?php esc_html_e( '从媒体库选择', 'paddysun-newsprint' ); ?></button>
									<button type="button" class="button-link" data-np-ph-clear="<?php echo esc_attr( $key . ':' . $device ); ?>"><?php esc_html_e( '清除（回内置占位图）', 'paddysun-newsprint' ); ?></button>
									<p style="margin:6px 0;">
										<label><?php esc_html_e( '占位图跳转链接（本端独立，选填）：', 'paddysun-newsprint' ); ?>
											<input type="url" class="regular-text code" name="paddysun_ns_ads[slots][<?php echo esc_attr( $key ); ?>][devices][<?php echo esc_attr( $device ); ?>][ph_link]" value="<?php echo esc_attr( $dev['ph_link'] ); ?>" />
										</label>
									</p>
									<p class="description" style="margin:4px 0 0;"><?php echo esc_html( sprintf( /* translators: 1: width, 2: height */ __( '框规格 %1$d×%2$d；选图在框内自动拉伸铺满，超出比例部分裁切；未自定义时用内置占位图。', 'paddysun-newsprint' ), $bw, $bh ) ); ?></p>
								</fieldset>
							<?php endforeach; ?>
						</td>
					</tr>
					<?php endif; ?>
				</table>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

function paddysun_ns_ads_admin_assets( $hook ) {
	if ( 'appearance_page_paddysun-ns-ads' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'jquery' );
	$media_title_js = esc_js( __( '选择占位图', 'paddysun-newsprint' ) );
	wp_add_inline_script( 'jquery', <<<JS
jQuery(function ($) {
	var frame;
	$(document).on('click', '[data-np-ph-pick]', function (e) {
		e.preventDefault();
		var key = $(this).data('np-ph-pick');
		frame = wp.media({ title: '{$media_title_js}', library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			$('[data-np-ph-input="' + key + '"]').val(att.id);
			$('[data-np-ph-preview="' + key + '"]').html(
				$('<img>', { src: (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url, css: { maxWidth: 280, maxHeight: 110, display: 'block', marginBottom: 6 } })
			);
		});
		frame.open();
	});
	$(document).on('click', '[data-np-ph-clear]', function (e) {
		e.preventDefault();
		var key = $(this).data('np-ph-clear');
		$('[data-np-ph-input="' + key + '"]').val('0');
		$('[data-np-ph-preview="' + key + '"]').empty();
	});
});
JS );
}
add_action( 'admin_enqueue_scripts', 'paddysun_ns_ads_admin_assets' );

/**
 * 单端占位资源：该端自选媒体库图片（含尺寸），缺省回该端内置占位图。
 *
 * @param string $key      槽位标识。
 * @param string $device   desktop | tablet | mobile。
 * @param array  $slot     归一化后的槽位配置。
 * @return array{url:string,w:int,h:int,custom:bool}
 */
function paddysun_ns_ad_placeholder_asset( $key, $device, $slot ) {
	list( $bw, $bh, $file ) = paddysun_ns_ad_device_boxes()[ $key ][ $device ];
	$ph_id = is_array( $slot ) ? absint( $slot['devices'][ $device ]['ph_id'] ?? 0 ) : 0;
	if ( $ph_id ) {
		$src = wp_get_attachment_image_src( $ph_id, 'full' );
		if ( is_array( $src ) && $src[1] > 0 && $src[2] > 0 ) {
			return array( 'url' => $src[0], 'w' => (int) $src[1], 'h' => (int) $src[2], 'custom' => true );
		}
	}
	return array(
		'url'    => PADDYSUN_NS_URI . '/assets/img/placeholders/' . $file,
		'w'      => $bw,
		'h'      => $bh,
		'custom' => false,
	);
}

/**
 * 占位形态内层标记（三端各一节点，CSS 按断点显示其一；各端独立跳转链接）；
 * 与失效回退的 <template> 共用同一份，保证换回占位时尺寸零跳动。
 *
 * @param string $key  槽位标识。
 * @param array  $slot 归一化后的槽位配置。
 * @return string
 */
function paddysun_ns_ad_placeholder_nodes( $key, $slot ) {
	$boxes = paddysun_ns_ad_device_boxes();
	$html  = '';
	foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
		$dev   = is_array( $slot ) ? ( $slot['devices'][ $device ] ?? array() ) : array();
		$asset = paddysun_ns_ad_placeholder_asset( $key, $device, $slot );
		$img   = sprintf(
			'<img src="%s" alt="%s" width="%d" height="%d" loading="lazy" />',
			esc_url( $asset['url'] ),
			esc_attr__( '广告位', 'paddysun-newsprint' ),
			$asset['w'],
			$asset['h']
		);
		$link = is_array( $dev ) ? (string) ( $dev['ph_link'] ?? '' ) : '';
		if ( '' !== $link ) {
			$img = '<a href="' . esc_url( $link ) . '" rel="nofollow sponsored noopener">' . $img . '</a>';
		}
		$html .= '<div class="np-ad__ph np-ad__ph--' . esc_attr( $device ) . '">' . $img . '</div>';
	}
	return $html;
}

/**
 * 槽位 HTML：占位态（无代码 / 后台编辑器预览）或已填码态。
 * 预留高度 = 各端框比例（--np-ad-ratio-d/-t/-m 内联，CSS 按断点取用），
 * 占位 ↔ 广告 ↔ 清理回占位三态零跳动；「整块不输出」为站长显式选择的
 * 失效语义，允许收起。
 *
 * @param string $key 'home' | 'article' | 'article_bottom'。
 * @return string
 */
function paddysun_ns_ad_slot( $key ) {
	$settings = paddysun_ns_ads_normalize( get_option( 'paddysun_ns_ads', array() ) );
	if ( ! isset( $settings['slots'][ $key ] ) || empty( $settings['slots'][ $key ]['enabled'] ) ) {
		return '';
	}
	$slot = $settings['slots'][ $key ];
	$boxes = paddysun_ns_ad_device_boxes();

	$vars = '';
	$off  = '';
	foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
		list( $bw, $bh ) = $boxes[ $key ][ $device ];
		$vars .= '--np-ad-ratio-' . $device[0] . ':' . $bw . '/' . $bh . ';';
		if ( empty( $slot['devices'][ $device ]['enabled'] ) ) {
			$off .= ' np-ad--no-' . $device; // 该端关掉 = 该端整块不输出（CSS 按断点隐藏）。
		}
	}

	$inner = paddysun_ns_ad_placeholder_nodes( $key, $slot );
	$class = 'np-ad np-ad--' . ( 'home' === $key ? 'band' : ( 'article' === $key ? 'rail' : 'bottom' ) ) . $off;
	/* 后台（站点编辑器画布）只预览占位，不输出受信代码。 */
	$show_code = '' !== trim( $slot['code'] ) && ! is_admin();

	$html  = '<div class="' . esc_attr( $class ) . '" data-np-ad="' . esc_attr( $key ) . '" style="' . esc_attr( $vars ) . '">';
	if ( $show_code ) {
		/* 受信管理员内容（manage_options 专写、去 NUL、体积上限），不过 kses——见文件头。 */
		$html .= '<div class="np-ad__code">' . $slot['code'] . '</div>';
		$html .= '<template class="np-ad__ph-tpl">' . $inner . '</template>';
	} else {
		$html .= $inner;
	}
	$html .= '</div>';
	return $html;
}

/**
 * 失效清理/定位脚本：仅在需要的请求加载。
 * 首页只在首页槽位填码时引入（占位态保持 0 JS）；文章页在侧栏位启用或
 * 任一文章页槽位填码时引入（侧栏固定位需要定位脚本，文章页本就不在
 * 0 JS 承诺内）。
 */
function paddysun_ns_ads_maybe_enqueue() {
	$settings = paddysun_ns_ads_normalize( get_option( 'paddysun_ns_ads', array() ) );
	$need     = false;
	if ( is_front_page() && '' !== trim( $settings['slots']['home']['code'] ) ) {
		$need = true;
	}
	if ( is_singular( 'post' ) && ( ! empty( $settings['slots']['article']['enabled'] )
		|| '' !== trim( $settings['slots']['article']['code'] )
		|| '' !== trim( $settings['slots']['article_bottom']['code'] ) ) ) {
		$need = true; // 占位态侧栏也需要定位逻辑（贴目录下方 / 替代目录位）。
	}
	if ( ! $need ) {
		return;
	}
	wp_enqueue_script(
		'paddysun-ad-slots',
		PADDYSUN_NS_URI . '/assets/js/ad-slots.js',
		array(),
		PADDYSUN_NS_VERSION,
		true
	);
	wp_localize_script(
		'paddysun-ad-slots',
		'PADDYSUN_AD_SLOTS',
		array(
			'cleanup'  => $settings['cleanup'],
			'deadline' => (int) apply_filters( 'paddysun_ns_ad_deadline_ms', 2500 ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'paddysun_ns_ads_maybe_enqueue' );
