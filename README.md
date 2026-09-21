![Paddy Newsprint 品牌 Logo](https://cos.paddysun.top/brd/paddy-newsprint-logo-orange.png)

# Paddy Newsprint

![版本 1.0.1](https://img.shields.io/badge/version-1.0.1-6b1f1f)
![WordPress 7.1+](https://img.shields.io/badge/WordPress-7.1%2B-21759b?logo=wordpress&logoColor=white)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![许可证 GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

报纸风格的中文 WordPress 块主题：完整头版、双耳报头、三栏内容与适合长文的阅读排印。主题自身的字体、图标、图片回退和脚本本地加载；这份 README 的品牌图片与 Shields.io 徽章是公开文档外部资源，不会随主题注入站点。

## 下载与安装

- [GitHub 仓库](https://github.com/PaddySun/paddy-newsprint)
- 国内下载（1.0.1）：`https://cos.paddysun.top/paddy-newsprint/rel/paddy-newsprint-1.0.1.zip`（**待上传**，上传后需重新下载核验字节数与 SHA-256）
- 安装包文件名：`paddy-newsprint-1.0.1.zip`；包内目录：`paddysun-newsprint/`。
- SHA-256：`43cf0e3fcae8373ff42d34cc7ca0871f574ad38facd67ed953fb5e6f546cf3a0`（5,331,765 字节，233 个主题文件）。安装、升级与 HTTP 回归结果见本仓库 `RELEASE-1.0.1.md`。不要将 GitHub 的仓库源码 ZIP 当作可直接安装的主题包。
- 上一版 1.0.0 仍可下载：`https://cos.paddysun.top/paddy-newsprint/rel/paddy-newsprint-1.0.0.zip`（待上传）。

在 WordPress 后台选择「外观 → 主题 → 上传主题」，安装并启用主题。升级前备份自己的站点；数据库中已定制的模板不会因主题文件更新自动覆盖。

## 主要功能

- **报纸版面**：工具条、完整／紧凑报头、双耳、简报、长读、侧栏及本期要目。
- **长文阅读**：本地衬线字体、引用、脚注、表格、目录、代码与图表展开／折叠。
- **公式与图表**：按内容加载 KaTeX、Mermaid、highlight.js，失败时保留可读回退。
- **媒体回退**：失效图片／视频替换为本地作品图，保留作品名、alt 和提示，不新增“作品介绍”外链。
- **Markdown**：原文存档、复制和 REST 单篇导出，保留权限、nonce、导出开关与可见性边界。
- **机器可读内容**：`/llms.txt` 索引与 `/llms-full.txt` 全量全文，可在「外观 → llms.txt 设置」中开关、选内容来源（自动生成／部分手动／全部手动）并控制全量的篇数与摘要口径；另有 SEO/schema 输出与既有 SEO 插件让位机制。
- **广告位**（外观 → 广告位）：首页横幅、文章页侧栏（桌面端专属，随悬浮目录）与「读者来信」上方三处槽位；占位图按桌面/平板/手机端分别配置（媒体库选图、分端开关与独立跳转链接）；后台填入广告代码后按受信管理员内容输出，加载失败可配置换回占位图或整块不输出。出厂为占位形态，主题不内置任何广告代码。
- **默认邮件样式**：出站邮件自动套用朴素表格布局并附纯文本降级；检测到其他邮件模板插件时整体让位。
- **国际化**：全部用户可见 PHP 文本可经 gettext 翻译（文本域 `paddysun-newsprint`），随包附 `.pot` 模板与英文翻译包；英文站点（en_US）前台与后台设置页开箱即英文，中文站点输出不变。
- **阅读动线**：文章页悬浮目录贴近正文右缘、默认收起。

SEO/schema、Markdown 存档与 REST、llms 是保留的产品功能，不需要另装强制插件。本轮为自主分发，不是 WordPress.org 上架版本或官方审核通过声明。

## 1.0.1 更新

- **国际化达标（WordPress.org 主题评审 i18n 要求）**：全部用户可见 PHP 文本挂入 `paddysun-newsprint` 文本域；随包附 `languages/paddysun-newsprint.pot` 与英文翻译包（en_US.po/.mo），语言包走 WordPress 核心标准路径加载（主题内零自定义加载代码）。英文站点前台与「外观 → llms.txt 设置」「外观 → 广告位」两个后台设置页开箱即英文。
- **修复 llms.txt 分类归组**：改按分类 slug 判定「未分类」。原按显示名比对，分类被改名或多语言站点上失效（英文默认分类名会直接成为 llms.txt 组标题）。这是本轮唯一的行为变更。
- 修复广告位设置页媒体框标题未包翻译函数的问题；按钮墙 alt、文章头日期格式等硬编码文本全部改为可翻译。
- 截图同尺寸压缩（1200×900，712KB → 219KB）；style.css 头部补 `Domain Path: /languages`。
- HTML 模板静态文本按评审「temporary exception」口径保留；theme.json 编辑器名称等元数据保持中文（产品定位，详见 RELEASE-1.0.1.md 的范围说明）。

## 1.0.0 更新

- 修复首页文章卡片正文缺失：正文为单个 HTML 块（手工 HTML 粘贴形态）的文章，首页侧栏摘录回退到经典段落提取，沿用侧栏既有预算与剔除规则；主稿、简报、单篇页输出不变。
- 新增「外观 → 广告位」：三处槽位 + 分端自定义占位（开关/选图/独立链接）+ 加载失败清理语义（换回占位图或整块不输出）全局配置；广告代码为受信管理员内容（仅 `manage_options` 可写、不做标记清洗、不过 kses），脚本注入方式由站点自行决定，主题不代注入。
- 新增朴素默认邮件样式；探测到其他邮件模板/重写类插件时整体让位；零设置项。
- 悬浮目录贴近正文右缘，**默认收起**（点「拉出」展开）；文章页侧栏广告位与目录同右缘对齐。
- **行为变更**：首页零 JS 基线仅在广告位为占位态时成立——在首页槽位填入广告代码后，该代码会引入自己的脚本（清理脚本随之加载），是否接受由站长自定。

0.10.13 及更早的更新记录见 `RELEASE-0.10.13.md` 等历史文件。

## 兼容性与验证边界

WordPress **7.1+**，PHP **7.4+**。候选包已在隔离的合成 WordPress 环境完成验证：PHP 7.4.33 与 8.3.30 上各自的全新安装与「从 1.0.0 升级」四套环境，安装/升级后主题文件与候选包 233 个文件逐一一致、无多余文件，升级保留原有内容、自定义模板、文章元数据与主题修改记录；HTTP 回归 558 项断言全部通过（含英文语言包在 en_US 站加载生效的新用例）。48 项静态公开检查全部通过，隐私扫描零命中，全源码无任何真实广告投放标识。WordPress.org 评审同款自动化工具 Theme Check（20260901）全量 9,642 项测试通过，0 个 REQUIRED 级与 0 个 WARNING 级结果。中文站点输出与 1.0.0 逐字节一致（仅 llms.txt 分类归组修复一处有意变更）。这不表示所有插件组合均已测试。

仓库中的导航、备案、服务状态及按钮示例是占位内容，安装后请按站点情况编辑。主题不导入个人数据库或媒体。

## 许可证与第三方来源

主题原声明保持 **GPL-2.0-or-later**。1.0.0 沿用同一许可路线：对 GPL 覆盖部分行使 **GPLv3 选项**；不是 GPLv3-only。DOMPurify 采用其 **Apache-2.0 分支**，原双许可全文仍保留。本轮变更未引入新的第三方资源。

- [分发许可路线](paddysun-newsprint/DISTRIBUTION-LICENSE.md)
- [GPLv3 全文](paddysun-newsprint/LICENSE-GPL-3.0.txt)
- [组件及文件哈希清单](paddysun-newsprint/assets/resource-licenses.json)
- [第三方通知目录](paddysun-newsprint/assets/vendor/licenses/)

`points-on-curve` 0.2.0 按上游完整包的 **MIT** 许可分发，完整许可已保留。其 `flatness` 函数注明适配自 [Offset Bézier Curves](https://seant23.wordpress.com/2010/11/12/offset-bezier-curves/)；在此保留来源说明，不将该网页描述成另一份已取得的许可，也不修改上游 MIT 声明。

## 作者

![Paddy 个人 88×31](https://cos.paddysun.top/88x31/paddy-88x31-clouds.webp)

[Paddy](https://www.paddysun.top/about-us) · [主题展示页](https://www.paddysun.top/paddy-newsprint)
