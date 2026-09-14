=== Paddysun Newsprint ===
Contributors: paddysun
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.10.12
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, one-column, custom-colors, custom-menu, editor-style, block-styles, block-patterns, full-site-editing, style-variations, translation-ready

报纸风格的极简中文博客块主题：Broadside 报纸版面 + Typora Newsprint 阅读节奏。

== Description ==

Paddysun Newsprint 是一款面向中文文字博客的块主题（Block Theme）。首页是完整的报纸版面：工具条、哥特体报头与双耳、卷期/日期版面信息行、三栏头条（简报栏 | 题图压底主稿 | 侧栏）、订阅框与「本期要目」分类网格；文章页保持 Typora Newsprint 的阅读节奏（40em 栏宽、楷体引文、斑马表格、暖纸色板）。

全部模板与模板部件均为区块标记，可在站点编辑器（Site Editor）中直接编辑；色板、字体、字号、间距均由 theme.json 提供，在「样式」面板调整后前台与编辑器同步生效。

内置：KaTeX 公式、Mermaid 图表、代码高亮按需加载；一键复制本文 Markdown；/llms.txt 与 /llms-full.txt 机器可读索引；JSON-LD BlogPosting。主题自身资产零外部请求。

== Installation ==

1. 后台「外观 → 主题 → 上传主题」上传 zip 并安装。
2. 启用主题。若此前使用经典主题并建有菜单，「主导航」区块会自动沿用已分配到 primary 位置的经典菜单。
3. 「外观 → 编辑器」中可编辑报头双耳文案、订阅框、页脚等全部版面元素。
4. 若 /llms.txt 返回 404，请到「设置 → 固定链接」点一次「保存更改」刷新重写规则。

== Frequently Asked Questions ==

= 报头的「第 N 卷 · 第 M 期」和日期从哪来？ =

版面信息行是普通段落，主题输出时对任意段落做令牌替换（不要求特定类名，np-dynamic 只是历史遗留的标注），把 {volume}（当前年份 − 最早文章年份 + 1）、{issue}（已发布文章数）、{date}（今日日期）、{year}（当前年份）、{est}（最早文章年份）替换为实际值。你可以在站点编辑器中修改这些段落的文字，只要保留花括号令牌即可。

= 首页主稿为什么只显示部分段落？ =

首页「主稿」区域的「文章内容」区块带有 np-lead-body 类名，主题会取正文纯文字段落（跳过公式 / 图片 / 引用），最多 10 段且最多 2200 字，先到为准，做首字下沉双栏排版，并附「阅读全文」链接。两档上限可在 inc/block-hooks.php 的 paddysun_ns_lead_body() 里调整。

= 如何调整正文字号与行高？ =

站点编辑器 → 样式 → 排版 → 文本，调整字号与行高即可，前台与编辑器同步。若希望书卷式排版（段首缩进 2em + 两端对齐），在文章模板中选中「文章内容」区块，在「样式」里切换为「书卷式」。

= 隐私 =

本主题不收集任何用户数据；主题自身资产（字体/脚本/样式/图标）零外部请求——pattern 内的友链头像/徽章为站长内容外链，是否替换为本地占位图由站长按发布检查口径决定。

== Upgrade Notice ==

= 0.10.3 =
官方标准整改：vendor 库随包附未压缩原始文件；检索词转义、检索表单 action、键盘焦点样式等可移植性/无障碍修正。升级后建议清一次缓存。

== Changelog ==

= 0.10.12 =
* 公开源码补丁候选：报头长标题允许收缩与断行，长表头自然换行，手机页脚状态居中、备案分组及静态状态点优化。
* 修正公开作者/主题链接和七组 Met 馆藏配对；内置回退图片相对于主题 URI 解析，兼容子目录安装。按钮示例链接改为本站首页。
* 简报摘要按最多64个汉字/词单位及192个字符双重预算限制，避免英文站点语言下中文或中英混排摘要过长；只影响渲染，不修改存档正文与摘要。统一两种阅读入口字体。取消回退作品介绍外链，保留作品名与资源来源记录。
* 连续超长拉丁词仅在报头采用局部字号，保留完整站名和普通标题排印；公开预览图采用站长已验收的 1200×900 PNG。
* SEO/schema、Markdown 存档与 REST、llms 保留并回归；本轮仅自主分发，WordPress.org 不在范围。候选分发仍须完成许可、兼容性、独立视觉与发布检查。

= 0.10.11 =
* maintenance-notice 通用化为「提醒确认弹窗」，保留旧 slug。编辑器改用普通核心标题/段落/按钮区块，前台渲染后才包装 dialog 并按需加载脚本；后台/REST/Feed 不增强。补部件元数据、初始化防重、showModal 异常保护及旧 dialog 兼容；不自动覆盖数据库定制内容。

= 0.10.10 =
* 手机端（≤760px）隐藏完整报头中的站点副标题、日期与星期、刊训；不保留空位。桌面和平板继续显示，卷期、站点名称、检索与RSS入口不受影响。

= 0.10.9 =
* 页首「检索本刊」与「RSS 订阅」工具组固定在网格最后一列并右对齐；移除窄屏对该组的强制居中规则，其他报头内容保持原有对齐方式。

= 0.10.8 =
* 修复桌面端右栏长文撑高首页三栏网格的问题：整个三栏区域统一为820px，网格行设为minmax(0, 1fr)，各栏可收缩且裁切溢出；中栏「阅读全文」与右栏阅读入口不收缩。手机和平板保持原有自然高度。

= 0.10.7 =
* 页脚备案号与服务状态改为占位内容，三个链接均设为 #，不再包含真实备案编号和监测服务地址；发布构建直接沿用占位页脚。

= 0.10.6 =
* 桌面端长读栏总高度限制为 820px，包含题头、正文与「阅读全文」；正文收缩裁切，阅读入口在限高范围内保留。手机和平板不变。screenshot.png 替换为站长提供的 0.10.x首页.png 原图。

= 0.10.5 =
* 站长反馈二轮（0.10.5）：「近期文章」板块与「徽章墙」pattern 整体删除（相关死代码/样式/构建规则同步清理）；桌面端三栏高度硬限制（主稿正文钳制 820px，CSS 变量可调，仅 ≥1025px 生效）；失效作品替换排除小图（≤64px，links 页失联友链小图不再破坏版面）；折叠按钮改为悬浮在内容内部底部（图片上方内缘），展开态回归文档流；悬浮目录毛玻璃维持现状。

= 0.10.4 =
* 站长测试日反馈修复轮（13 项）：无题图卡片空占位题花移除、卡片行等高间距恒定；置顶文不再重复出现（主稿只取置顶、简报侧栏偏移补偿，WP 预置顶两篇问题实测修复）；右栏关闭题图显示；单篇文章页补特色题图区块（此前模板从未放题图）；失效政策扩展到视频（video 失效换作品图+提示，5188 实测）；长图纳入折叠、折叠按钮改「展开」半透明毛玻璃小签；友情链接模板/友链网格 pattern 整体移除；关于页人物版式删徽章节；宽表横向滚动条纸墨主题化；悬浮目录毛玻璃强化（48% 实度+blur16+saturate）；首页导航→三栏间距减半。links-test 页已删（post 90）；about-test 徽章不显示根因=SVG 无内在尺寸在 flex 收缩为 0（徽章节已移除）。

= 0.10.3 =
* E0 官方标准整改：vendor 三库随包附未压缩原始文件（官方 required「压缩文件须附原始文件」，哈希入台账）；检索词双重转义修正（含 & 的检索词不再显示 &amp;）；报头检索表单 action 重写为 home_url（子目录安装可移植）；检索输入框恢复键盘焦点描边（去除 outline:none 对全局 focus-visible 的压制）；links-grid 引言包 gettext；readme.txt 补 Upgrade Notice 小节。E0 审查报告 docs/audit-official-standards.md（56 条核对表，P1×0）。

= 0.10.2 =
* 审查修复轮（C/D 双审查 42 项发现裁决）：mermaid 清洗失败分支补派发事件（时序契约全分支成立）；维护公告会话去重按注释意图补实现（sessionStorage）；首页侧栏摘要段落级剔除行内图/代码；失效图清单过滤器移到校验前（注入来源不再绕过 scheme 白名单）+ parse_url false 防御；悬浮目录跳过非法百分号转义锚点；导出表格 thead 回退去重；save_post 修订版排除（MD 存档/目录形态）；目录注入 postId 守卫激活（过滤器 3 参）；复制 MD 守卫分支合并；注释与行为对齐 13 项（Feed 口径统一、preload 字体正名、README 过时表述、零外部请求口径收窄等）；漏洞台账 VR-001/004/005 推进为版本已识别。

= 0.10.1 =
* 发布整备 R1：失效图清单 URL 校验拒绝协议相对 URL（//host/…），scheme 须显式 http/https 或站点相对路径（附单测）；替换脚本显式排除首页（含静态页形态），守住首页 0 JS；折叠用户标记 data-np-fold-user 语义修正（展开置位/收起撤销）；mermaid 失败回退容器纳入折叠扫描并双侧注释事件时序契约（附单测）；functions.php 缩进还原。

= 0.10.0 =
* 首页文章栏（H1）：近期文章查询去固定 offset，新增 np-cards-follow 去重协议——offset 归零并排除上方实际已展示的文章（作用域仅前台首页渲染，不污染 REST/编辑器）；保留分类、作者、排序、手动 exclude 等原条件；置顶文不前插也不消失。空结果给诚实空态。
* 首页卡片（H2/H3/H4）：卡片边框/背景 hover 与 focus-within 反馈（无位移缩放）；无题图卡片服务端输出中性题花占位（零 JS，点击仍去文章）；卡片流新增「更多文章」出口（单分类筛选指向该分类归档，或指向设置→阅读的文章页；无可验证入口时不渲染死链）。
* 长内容折叠（U2）：超高代码块（>480px 折为 320px）与超高 Mermaid（>70vh 折为 45vh）默认限高预览，原生按钮展开/收起（aria-expanded/aria-controls）；Mermaid 渲染成功后测量、渲染前不隐藏；复制取全文，无 JS 全文可读，打印自动展开；超宽内容仍横向滚动。
* 展示统一（U1/U3/U4/U5/U6）：表头背景改透明、墨字、粗规线区隔；悬浮目录更通透（纸面混色 62%、blur 10px）并主题化滚动条（无 backdrop-filter 时恢复高实度纸底）；评论字段常驻标签「显示名称/邮箱/网站」，必填星标随 require_name_email 设置；新增 .np-inline-figure 插图容器协议（显式包裹才居中）；各处分类组件统一 accent 深红。
* 失效图片作品替换机制（U7 D-a）：assets/artwork-fallbacks.json 配对清单（图片+介绍URL+替代文字），正文图片加载失败按图随机选一组替换、页面内稳定、二次失败降级文字；首页保持 0 JS；正式素材录入后自动生效。
* 失效图片作品替换正式素材接入（U7 D-b）：随包归档 7 张 Met 开放获取藏画占位图（assets/img/fallback/，公有领域 CC0 + 站长自制告示章），正式清单 7 组「图片＋Met 馆藏页介绍链接＋替代文字」固定配对录入 assets/artwork-fallbacks.json；Met 馆藏页 objectID 经开放获取 API 逐件核实。首页仍保持 0 JS。
* 登录页视觉适配（U9）：login_enqueue_scripts 加载独立 login.css（纸色/墨色/直角规线/focus 语言），覆盖登录/找回/重置/注册表单，认证逻辑零变更。

= 0.9.3 =
* 修复 Mermaid SVG 清洗误杀：清洗层原用严格 image/svg+xml 解析，Mermaid 产出的 SVG 内嵌 foreignObject/&nbsp; 等非 XML 合法构造时 parsererror，正常流程图被误判失败回退（archives/32 实测）。改用 text/html 解析（容错且 DOMParser 不执行脚本），结构化清理规则不变（script 移除 / on* 与 javascript: 属性剥离）。
* 代码块注释颜色统一为深灰（站长裁决）：.hljs-comment/.hljs-quote 由 ink-ghost 浅灰改为 ink-muted，与字符串/文档串一致，Bash # 注释不再不可读。
* 版本号随 JS cache-buster 同步升级。

= 0.9.2 =
* 修复 Mermaid 白名单回归：第三轮把「源码关键词」与 parse() 返回的 diagramType 两套命名空间混用，flowchart TD/graph/sequenceDiagram/classDiagram/stateDiagram 等正常图表被误拦不渲染。现预检匹配源码关键词（含 -v2 变体）、终判对 diagramType 做 -vN 归一化，两者清单分离；允许集合不变（flowchart/sequence/class/state/er/mindmap/pie）。
* 版本号随 JS cache-buster 同步升级（同版本下修改脚本会让已缓存访客拿不到新代码）。

= 0.9.1 =
* 安全加固（站长九项裁决落地）：密码文章一律排除 REST Markdown、llms.txt、首页摘要与 SEO 输出；非公开内容 REST 统一 404 不区分登录态。
* 新增单篇「允许公开复制与导出」开关（原文 MD 存档框内，默认开）；关闭后 REST/llms-full 跳过该篇，前台复制按钮不加载。
* JSON-LD 输出加 JSON_HEX_* 全套标志，杜绝 script 容器闭合序列逃逸。
* Mermaid 升级 11.12.0 → 11.16.1（公告修复底线），securityLevel 固定 strict，图表类型白名单（gantt/xychart/radar/architecture 等高风险高消耗图种直接回退源码显示）。
* HTML→MD 转换器加输入 512KB 预算；导出单篇 256KB 上限，超限段落边界截断并标注；正文超限降级为摘要；libxml/postId 上下文恢复调用前值。
* REST Markdown 与 llms.txt 公共响应加 Cache-Control（max-age=300 + s-maxage=300 + stale-while-revalidate=60），purge 口径见站长手册。
* 新增「维护公告」模板 part + 条件弹窗脚本：part 未挂载时零 JS 加载，维护窗口操作见站长手册。
* 仓库历史已清理运行配置文件（备份 bundle 存仓库外），配置不再跟踪。

= 0.9.0 =
* SEO 输出让位 Slim SEO/Yoast：插件在场时主题 meta description 与 BlogPosting JSON-LD 整体退出，并提供 `paddysun_ns_seo_active` 过滤器。
* 完成 baby-wp-comment-filter 联合复验；其前台资源与报纸化评论表单共存，无需插件侧 CSS 改动。
* 增补阶段五插件配置、安全自查与矩阵记录。

= 0.8.4 =
* 头版三栏底对齐（站长裁决）：主稿/侧栏正文 flex 吃满栏高，超出高度逐行隐藏（多栏定高下整行转入溢出栏被裁，无半行截断）；「阅读全文 →」与「读这篇文章 →」锚到各自栏底，同一高度。窄屏单列机制自然失效不裁字。
* 主稿预算 12 段 → 10 段（站长裁决；2200 字上限不变）。

= 0.8.3 =
* 首页主稿限长改双条件预算（站长裁决）：纯文字段落 12 段 / 2200 字先到为准——与侧栏（8 段 / 1400 字）同机制对称；长文足以填满头版双栏、三栏高度更平衡，短文不超发。此前固定前 4 段，长文时主稿栏偏短。

= 0.8.2 =
* 图片懒加载兜底重构：旧逻辑「整段内容只要有一处 loading= 就全部跳过」导致混合内容页（如含 iframe 懒加载标记或媒体库图片的徽章墙）外站图片悉数变立即加载。现改为逐张判定——沿用核心首屏阈值（前 3 张保护 LCP），其后缺 loading 的逐张补 lazy；feed 输出保持旧行为不变（Feed 政策）。

= 0.8.1 =
* 站长复验整改（三项）：① pattern 图片区块编辑器报「无效内容」——外站图片区块改为编辑器规范序列化（img 仅 src/alt，尺寸由主题 CSS 锁定，懒加载由前台过滤层注入），links/about 页编辑器警告清零；② 页脚备案行改版——备案号靠左、服务状态（8px 绿灯呼吸动画）靠右同处一行，窄屏自动换行；③ 修复长评论把 flex 行挤爆导致首字母头像被压至 10px（头像不参与收缩，正文允许收窄换行）。

= 0.8.0 =
* 站点收编（阶段四）：新增「友情链接页」「关于页」两个自定义页面模板（theme.json customTemplates，opt-in 不影响既有页面）。
* patterns 三枚进 links 页：友链网格（直角卡片墙，含失联标记）、88×31 按钮墙（尺寸锁定 + pixelated + 供他站复制的 HTML 片段）、徽章墙（grid 等高小网格 / banner 宽幅统计卡两形态严格分开）。
* patterns 一枚进 about 页：人物版式（media-text 简介 + 时间线 + 三枚宽幅徽章 banner 落位）。
* 页脚备案行：ICP + 公安备案链接整行居中，全端展示（打印样式随页脚整体隐藏）。
* 版面增量样式：栏目页版式（np-links / np-about）、1px 缝隙卡片墙、徽章两形态、时间线规线。
* 站长后台手册：docs/stage4-owner-guide.md（速查单页，六场景）。

= 0.7.3 =
* 站长复验整改：报头工具条去掉 llms.txt 文字链接（在检索/RSS 图标旁突兀；页脚保留）；悬浮目录透明度 86%→78% + 磨砂 5→6px，加重半透明可感知度。

= 0.7.2 =
* 目录（站长裁决）：列表去除序号（正文区 ol decimal 此前透过特异性打到目录上）；目录盒/悬浮目录均新增「收起/拉出」原生开关（details/summary，零新 JS，键盘可达读屏可播报）；链接配色反转——未选中深灰（ink-faded）、当前章红色加粗（悬浮形态），hover 仍红。

= 0.7.1 =
* 悬浮目录（站长裁决）：下滚一屏（scrollY ≥ 视口高）后才浮现、回顶即隐（150ms 令牌过渡；armed 类渐进增强，无 JS 时目录保持常显）；背景改半透明纸底（86%）+ 轻磨砂 + 1px 淡规线，正文从目录底下滚过时透出。

= 0.7.0 =
* 动效令牌：全站交互反馈统一 150ms cubic-bezier(0.4,0,0.2,1)（theme.json custom.np，样式面板联动含编辑器）；回装既有交互（toast/检索/折叠/复制/宽表/工具条/标签）并新增检索弹层 3px 淡入入场；prefers-reduced-motion 全局归零继续覆盖。
* 按钮图标化：报头「检索」（放大镜）、报头/页脚 RSS（广播符号）、代码块「复制」（copy 图标）改用 Phosphor Icons（MIT）内联 SVG，currentColor 随宿主变色；均带等价中文 aria-label，图标 aria-hidden。
* 评论展示优化（吸收 geedea 调研方案）：一级评论条目分隔线 + hover 淡纸纹背景；子层级虚线分隔；作者评论署名后缀描边小徽「作者」（render_block 过滤器）；回复/编辑链接 hover 淡红晕底；表单 focus 红晕增强。
* 修复：0.5.0 起第 3 条以上一级评论被误折进「展开N条来信」——折叠现仅作用于回复串，一级评论永不折叠。

= 0.6.1 =
* Feed 输出：主题完全放弃控制（移除 rss_use_excerpt 强制全文过滤器），全文/摘要全由系统「设置 → 阅读」生成；订阅框文案同步调整。
* 清理本地阶段测试数据（测试文章/评论）；阶段三交接文档 docs/stage3-handoff.md。

= 0.6.0 =
* 站长人工测试反馈修复：首页恢复「本期要目」分类网格、卡片流后移为「近期文章」栏目；简报/卡片摘要加长至 64 字；随笔侧栏绑定随笔分类并限长（8 段/1400 字）；工具条恢复单行（检索弹层移入合法容器，站点编辑器「无效内容」警告消除）；表格表头不折行、移动端宽表自动收起测量修正；脚注角标显示序号（多处引用同号、回链跳首个引用位）；mermaid 渲染双保险（保存规则放宽 + 渲染层补类）。
* 新增文章级「目录形态」侧栏选项（悬浮默认/目录盒/无，位于「原文 MD」框下方）；单篇文章模板默认悬浮目录。
* 修复：摘要跳公式过滤器的 preg_replace 回调误用；llms.txt 空标题文章兜底。

= 0.4.0 =
* 首页：「本期要目」改为近期文章卡片流（题图懒加载、三栏、卡片高度随摘要、移动端摘要 5 行上限）；简报栏层级拉开、摘要加长并跳过未渲染公式段；移动端主稿（长读）置顶；随笔侧栏展示全量正文（剔除表格/代码/图/公式）。
* 文章页：目录三形态（头部 / 右侧悬浮滚动跟随 / 关闭，站点编辑器内切换）；表格列宽内容自适应 + 移动端宽表自动收起；代码块右上角一键复制；报头「检索」弹出输入层。
* 评论：本地 SVG 首字母头像（替代被墙的 gravatar，零外部请求）；表单报纸化；子回复 >2 折叠展开；层级视觉递减。
* Markdown 协同：[^id] 脚注与 core/footnotes 互转（粘贴即角标+文末配对清单；复制 MD / REST / llms-full 往返保留脚注形态）。
* AI 浏览：llms.txt 按分类分组附更新时间；JSON-LD 增 keywords/articleSection/wordCount/publisher。
* 修复：render_block_* 过滤器三参签名（检索页标题此前未生效）。

= 0.3.1 =
* 站长人工测试反馈修复：三线表（墨底反白表头）、目录去编号收紧、正文内版面区块的圆点与目录污染、链接悬停保持深红、图片默认居中、双规线分隔线加粗、代码高亮灰阶分级、复制按钮移至署名行（浅色「复制 MD」）、要目标题省略、REST 中文原样输出。
* 区块标记归一化扩展：有序列表补 ordered 属性、无语言 mermaid 代码块按首行关键字补类、平铺列表升级 list-item 形态（编辑器「无效内容」警告清零）。
* 动态令牌不再要求 np-dynamic 类，任意段落可用 {year}/{site} 等。

= 0.3.0 =
* 转换为完整块主题：templates/、parts/、patterns/ 全部区块化，支持站点编辑器。
* theme.json 升级为 v3：色板、自托管字体（fontFace）、字号/间距阶梯、元素与核心区块样式、样式变体。
* Customizer「排印」面板迁移为 theme.json 排版 + 「书卷式」区块样式。
* 对齐主题审核手册 required 项：不再移除非展示性 hooks；补充 readme.txt。

= 0.2.1 =
* 中文排印优化（clreq）；修复文章页排印缺失包装。

= 0.2.0 =
* Broadside 报纸版面全量移植；Newsprint 文章体验。

== Resources ==

字体（均为 SIL Open Font License 1.1，见 assets/fonts/LICENSE-OFL.txt）：
* Source Serif 4 — © Adobe Systems Incorporated, OFL 1.1, https://github.com/adobe-fonts/source-serif
* Libre Caslon Display — © Pablo Impallari, OFL 1.1, https://github.com/impallari/Libre-Caslon
* UnifrakturMaguntia — © j. 'mach' wust, Peter Wiegel, OFL 1.1, http://unifraktur.sourceforge.net/

图标（内联 SVG，随模板直出，零额外请求）：
* Phosphor Icons — Copyright (c) 2020-2024 Phosphor Icons, MIT License, https://github.com/phosphor-icons/core
  检索放大镜 / RSS 广播 / 复制来自站长提供的 regular SVG 集合；仅移除透明画布矩形并增加嵌入/可访问性属性，其余图形与来源一致。实际使用位置为 parts/header.html、parts/footer.html、inc/block-hooks.php。完整来源许可见 assets/vendor/licenses/Phosphor-Icons-LICENSE.txt；不凭目录推定具体上游发布标签。

第三方脚本库（随主题打包，零外部请求；压缩制品与未压缩文件同时随包）：
完整通知位于 assets/vendor/licenses/；逐文件SHA-256、组件版本、实际路径和许可范围见 assets/resource-licenses.json。Mermaid 外层59个包版本、parser内嵌12个版本及roughjs下层4个精确锁定版本通知分别保留；roughjs下层按完整原文去重为3份通知，构建工具不随主题分发。四个主题字体已与公开分发来源逐字节核对一致。这不豁免清单中明确列出的特定权利链和组合分发路线待核事项。KaTeX字体采用OFL-1.1，版权/RFN见 assets/vendor/licenses/KaTeX-FONTS-NOTICE.txt 和 assets/fonts/LICENSE-OFL.txt，不以脚本MIT覆盖字体许可。本次分发保留GPL-2.0-or-later声明并行使GPLv3选项，DOMPurify采用Apache-2.0分支并保留双许可全文；详DISTRIBUTION-LICENSE.md与LICENSE-GPL-3.0.txt。points-on-curve 0.2.0按上游完整包MIT许可分发，flatness适配来源 https://seant23.wordpress.com/2010/11/12/offset-bezier-curves/ 保留说明，不另行改写其许可。
* KaTeX 0.16.22 — © Khan Academy, MIT License, https://github.com/KaTeX/KaTeX
  制品 assets/vendor/katex/katex.min.js — sha256 e8d885505949f3a5f4abdd5dd0d53696bd1371ad26ffbf4f310dcd77c8cdae89
* Mermaid 11.16.1 — © Knut Sveidqvist, MIT License, https://github.com/mermaid-js/mermaid
  来源 cdn.jsdelivr.net/npm/mermaid@11.16.1/dist/mermaid.min.js（0.9.1 安全升级，GHSA 十公告修复底线）
  制品 assets/vendor/mermaid/mermaid.min.js — sha256 18327bef70d96fb505fe7287d9f6a7362ebf07ff6576ddfaffb1a06f3e1a2954
* highlight.js 11.11.1 — © Ivan Sagalaev, BSD-3-Clause License, https://github.com/highlightjs/highlight.js
  制品 assets/vendor/highlight/highlight.min.js — sha256 c4a399dd6f488bc97a3546e3476747b3e714c99c57b9473154c6fb8d259b9381

图片：
* screenshot.png — 站长提供并授权缩放的公开主题预览图，PNG 1200×900；站长已确认后台主题预览正常。采用已验收公开文件，不在安装时导入私人媒体。
* assets/img/about-placeholder.svg — 主题作者自制，GPLv2 or later
* assets/img/fallback/fallback-*.webp — 失效图片替换图：底图取自 The Metropolitan Museum of Art Open Access（CC0 公有领域）；2026-09-14 经官方 collection API 逐件复核 isPublicDomain=true。告示章由主题作者自制，GPLv2 or later。图片随包提供，前端不显示作品介绍链接；下列馆藏地址仅作为资源来源记录。
  fallback-qian-xuan.webp — Qian Xuan, Wang Xizhi watching geese, ca. 1295; DP273822.jpg; https://www.metmuseum.org/art/collection/search/40081
  fallback-chen-hongshou.webp — Chen Hongshou, Miscellaneous Studies, one leaf dated 1619; DP157285.jpg; https://www.metmuseum.org/art/collection/search/37395
  fallback-bada-shanren.webp — Bada Shanren (Zhu Da), Birds in a lotus pond, ca. 1690; DP205836_CRD.jpg; https://www.metmuseum.org/art/collection/search/49143
  fallback-ren-yi.webp — Ren Yi (Ren Bonian), Animals, Flowers and Birds, 19th century; DP161418.jpg; https://www.metmuseum.org/art/collection/search/36170
  fallback-raven.webp — Allen & Ginter, Raven, Birds of America series (N4), 1888; DP828738.jpg; https://www.metmuseum.org/art/collection/search/406647
  fallback-crow.webp — Allen & Ginter, Crow, Birds of America series (N4), 1888; DP828750.jpg; https://www.metmuseum.org/art/collection/search/406660
  fallback-eastern-shore.webp — The Eastern Shore, after Winslow Homer, published by Louis Prang & Co., 1896; DP875985.jpg; https://www.metmuseum.org/art/collection/search/348605

88×31 按钮生成算法（GIF 不随主题包分发——站长经媒体库自行上传管理，此处记录按钮生成脚本的借鉴来源与许可边界）：
* clouds — 深度移植自 Shadertoy「up in the cloud sea」© mdb (2021)，CC BY-NC-SA 3.0，https://www.shadertoy.com/view/Ndc3zl（算法结构与参数体系同构，跨媒介重写；保持非商业用途）
* fire — 深度移植自 OpenProcessing「Flame (fork)」© Kazoops，CC BY-NC-SA，https://openprocessing.org/sketch/2727322（粒子行为模型对应，渲染管线独立实现；保持非商业用途）
* dynamic / cyberpunk — 概念借鉴（流场粒子 / CRT 坏信号均为通用技法），实现完全独立，无署名义务
* 详细评级与出处：主题开发仓库 ass/paddy-88x31-button/REFERENCES.md
