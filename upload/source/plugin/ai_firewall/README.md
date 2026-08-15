# Discuz! X 3.5 AI 防火墙

## 目标

`ai_firewall` 是一个面向 Discuz! X 3.5 UTF-8 站点的发帖内容审核插件。用户提交新主题或新回复时，插件调用管理员配置的 OpenAI Chat Completions 兼容接口进行审核：

- AI 明确返回 `pass`：插件不额外拦截，继续执行 Discuz 原有发帖流程。
- AI 返回 `review`：帖子进入 Discuz 原生人工审核队列。
- 接口超时、请求失败或响应不可解析：默认进入人工审核队列，可由管理员改为故障时放行。

AI 通过不会绕过 Discuz 已有的敏感词、版块审核、用户组审核或时段审核规则。

## 第一版范围

- 仅支持 UTF-8，不提供 GBK 版本。
- 审核新主题和新回复，不处理帖子编辑。
- 支持普通论坛和群组论坛。
- 支持完整发帖页、快速发帖、AJAX 回复和移动版使用相同发帖入口的请求。
- 审核标题、文本正文和正文中的链接，不上传附件或图片到 AI 服务。
- 使用 Discuz 原生待审核状态、后台审核列表、计数和通知。
- 不修改 Discuz 核心文件，不永久改变版块审核设置。
- 管理员和版主默认豁免，后台可关闭豁免。
- 提供轻量审计日志，不保存完整帖子正文或 API Key。

## 后台配置

插件提供独立后台配置页面，包含：

1. 总开关。
2. OpenAI 兼容接口 Base URL。
3. API Key，使用密码输入框；留空保存时保留现有值。
4. 模型名称。
5. 自定义审核 Prompt。
6. Structured Output 开关；默认启用严格 JSON Schema，不兼容的服务可关闭。
7. 新主题、新回复独立开关。
8. 生效版块；未选择时代表全部版块。
9. 管理员和版主豁免开关。
10. 请求超时秒数。
11. 最大送审字符数。
12. 接口故障策略：转人工审核或放行。
13. AI 通过的新主题是否跳过日志。
14. 日志保留天数。
15. 接口连通性测试。

## 请求与响应协议

请求发送到：

```text
POST {baseUrl}/chat/completions
Authorization: Bearer {apiKey}
Content-Type: application/json
```

管理员 Prompt 作为 `system` 消息。插件额外附加不可覆盖的输出约束，要求模型先生成理由、分类和置信度，最后才生成判定，只返回以下 JSON 之一：

```json
{
  "reason": "内容正常",
  "categories": [],
  "confidence": 0.98,
  "decision": "pass"
}
```

```json
{
  "reason": "疑似广告引流",
  "categories": ["spam"],
  "confidence": 0.91,
  "decision": "review"
}
```

默认请求携带 `response_format.type=json_schema`，Schema 要求四个字段全部存在、禁止额外字段，并在属性声明和 Prompt 中将 `decision` 排在最后，引导模型先分析后判定。JSON 对象的键顺序不属于语义协议，本地解析器接受服务端重排字段，但仍严格校验字段集合、类型、范围和枚举值。标题、正文、版块和内容类型使用结构化 JSON 作为独立 `user` 消息发送，避免把用户正文拼入系统 Prompt。只有合法且明确返回 `decision: pass` 才视为通过；未知值、缺失字段、空响应、非 JSON 和截断响应均按故障策略处理。

## 发帖接入

论坛入口在加载具体 `forum_post.php` 模块前执行插件 `post` Hook。Hook 只处理：

- HTTP `POST` 请求；
- `action=newthread` 且存在真实 `topicsubmit`；
- `action=reply` 且存在真实 `replysubmit`；
- 有效 `formhash`；
- 插件及对应内容类型已启用；
- 当前版块在生效范围内；
- 当前用户不属于已启用的管理员/版主豁免范围。

AI 判定需要人工审核时，插件只修改本次请求内的运行时审核条件：

- 普通论坛主题触发 `modnewposts` 的主题审核分支。
- 普通论坛回复触发 `modnewposts` 的回复审核分支。
- 群组论坛调整本次请求的群组直发权限判定。

后续由 Discuz 的 `model_forum_thread` 和 `model_forum_post` 原生逻辑设置 `displayorder = -2` 或 `invisible = -2`、写入 `common_moderate`、更新计数并通知管理员。

## 安全与隐私

- Base URL 只允许 `http` 或 `https`。
- 默认阻止回环、本机、链路本地和私有网段地址，降低 SSRF 风险。
- 外部请求要求 PHP cURL 扩展；缺少扩展时按接口故障策略处理，不回退到可能跟随重定向的流式请求。
- 限制连接/总超时和响应体大小。
- API Key 不写入日志，后台页面不回显完整 Key。
- 日志仅记录用户、版块、内容类型、决策、原因、分类、置信度、HTTP 状态、耗时和内容 SHA-256。
- 不记录完整标题和正文。
- AI 原始错误不直接展示给发帖用户。
- 后台输出统一经过 HTML 转义。
- 相同用户并发提交相同内容时使用独立短期进程锁，避免重复调用 AI；撞锁请求进入人工审核。

## 审计日志

安装时创建 `pre_ai_firewall_log`，主要字段：

```text
id, request_id, uid, fid, tid, pid, content_type, decision, reason, categories,
confidence, http_status, latency_ms, content_hash, error_code, created_at
```

后台日志页面支持按决策、内容类型和时间查看，并可快速跳转到对应主题或楼层；历史日志没有帖子编号时不显示链接。页面提供清理过期日志及手动清空功能。日志清理采用按概率触发的轻量回收，避免每次发帖都执行删除。

## 文件结构

```text
upload/source/plugin/ai_firewall/
├── ai_firewall.class.php
├── config.inc.php
├── logs.inc.php
├── install.php
├── uninstall.php
├── upgrade.php
├── discuz_plugin_ai_firewall_SC_UTF8.xml
├── lib/
│   ├── client.php
│   ├── config.php
│   ├── logger.php
│   └── moderator.php
├── table/
│   ├── table_ai_firewall_config.php
│   └── table_ai_firewall_log.php
└── language/
    ├── lang_admincp.php
    └── lang_template.php
```

## 实施顺序

1. 创建可导入的插件 XML、安装/升级/卸载脚本和数据表。
2. 实现独立后台配置、API Key 安全编辑、日志页面和接口测试。
3. 实现兼容 API 客户端、URL 校验、超时、响应大小限制和严格 JSON 解析。
4. 实现发帖 Hook、版块/身份豁免及原生人工审核接入。
5. 完成日志最小化、过期清理和错误归一化。
6. 执行 PHP 语法检查、安装包结构检查和关键决策场景测试。

## 安装与启用

1. 确认站点使用 UTF-8，且 PHP 已启用 cURL 扩展。
2. 将本目录保留在 `source/plugin/ai_firewall/`，进入 Discuz 管理后台的插件管理页面。
3. 扫描并安装 `AI 防火墙`，安装脚本会创建配置表和审计日志表。
4. 在插件的“配置”页面填写 Base URL、API Key、模型和审核 Prompt。
5. 先使用“保存并测试连接”验证接口和模型输出格式，再打开“启用审核”。
6. 在“审核日志”页面查看 AI 决策；待审帖子继续使用 Discuz 原生内容审核页面处理。

已有旧版本安装升级到 `1.2.1` 时，请在插件管理页面执行升级并更新缓存。升级脚本会补齐配置默认值，并给旧日志表增加 `tid`、`pid` 和帖子索引，不会删除已有日志；历史日志没有帖子编号，因此不会显示帖子链接。

## 性能说明

AI 审核与发帖请求同步执行，用户必须等接口返回后才能完成提交。一次慢请求只直接延迟当前发帖，但等待期间会占用一个 PHP Web 工作进程和数据库连接；高并发发帖时，如果慢请求占满工作进程，站点的其他页面也可能排队变慢。

- 建议优先使用稳定、低延迟的小模型，将超时设置为 `3` 到 `8` 秒。
- 默认故障策略为转人工审核，接口超时后不会继续无限等待。
- 相同用户并发提交相同内容时只发起一次 AI 请求，重复请求直接进入人工审核。
- 大型或高并发站点若希望完全隔离 AI 延迟，需要改为“先进入审核队列，再由异步任务审核并发布”的架构；这会改变当前“AI 通过立即显示”的交互。

## 验收清单

- AI 返回 `pass` 时，新主题和回复正常发布。
- AI 返回 `review` 时，新主题和回复分别进入 Discuz 原生审核队列。
- Discuz 原本要求审核时，AI 返回 `pass` 也不会绕过审核。
- 401、500、超时、空响应、Markdown JSON 和畸形 JSON 均按配置的故障策略执行。
- 插件关闭、内容类型关闭、版块未选中或身份豁免时不调用接口。
- 普通论坛和群组论坛的待审状态正确。
- 日志不包含完整正文和 API Key。
- 安装、升级、关闭、卸载和缓存更新流程可用。
- 插件新增 PHP 文件通过仓库可用 PHP 版本的语法检查。
