# Discuz! X 3.5 AI 防水墙

作者：iYa.App

## 目标

`ai_firewall` 是一个面向 Discuz! X 3.5 UTF-8 站点的发帖内容审核插件。用户提交新主题或新回复时，插件调用管理员配置的 OpenAI Chat Completions 兼容接口进行审核：

- AI 明确返回 `pass`：帖子保持 Discuz 原有发布状态，插件不做额外操作。
- AI 返回 `review`：已发布帖子被移入 Discuz 原生人工审核队列。
- 接口超时、请求失败或响应不可解析：默认移入人工审核队列，可由管理员改为故障时放行。

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
4. 模型名称；默认使用 `gpt-5.6-luna`。
5. Thinking 模式开关；默认开启。
6. 自定义审核 Prompt。
7. 使用结构化输出开关；默认启用严格 JSON Schema，不兼容的服务可关闭。
8. 新主题、新回复独立开关。
9. 生效版块；未选择时代表全部版块。
10. 管理员和版主豁免开关。
11. 请求超时秒数。
12. 最大送审字符数。
13. 接口故障策略：转人工审核或放行。
14. AI 通过的新主题是否跳过日志。
15. 日志保留天数。
16. 接口连通性测试。

## 请求与响应协议

请求发送到：

```text
POST {baseUrl}/chat/completions
Authorization: Bearer {apiKey}
Content-Type: application/json
```

请求会根据 Thinking 开关显式携带 `reasoning_effort`：开启时为 `medium`，停用时为 `none`。如果自定义模型或兼容接口不支持该参数值，连接测试会按接口错误处理。

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

论坛入口在加载具体 `forum_post.php` 模块前执行插件 `post` Hook。Hook 不再同步调用 AI，只判断该次提交是否需要进入异步审查，并在 Discuz 报告发帖成功后记录队列。判断条件包括：

- HTTP `POST` 请求；
- `action=newthread` 且存在真实 `topicsubmit`；
- `action=reply` 且存在真实 `replysubmit`；
- 有效 `formhash`；
- 插件及对应内容类型已启用；
- 当前版块在生效范围内；
- 当前用户不属于已启用的管理员/版主豁免范围。

队列只保存 `uid`、`fid`、`tid`、`pid`、内容类型和任务状态，不保存标题或正文。发帖请求结束后会立即尝试处理队列，让多数帖子在用户侧无感完成审查；插件计划任务每 5 分钟处理一批，作为响应后处理、计划任务触发失败或站点无访问时的兜底。后台审核使用当前帖子内容，可覆盖帖子发布后、审查前的少量编辑。

AI 判定需要人工审核时，异步任务按 Discuz 原生待审语义撤下内容：

- 主题设置 `forum_thread.displayorder = -2`，首帖设置 `invisible = -2`，并写入 `tid` 审核记录。
- 回复设置 `forum_post.invisible = -2`，并写入 `pid` 审核记录。
- 任务同步扣减主题、回复、今日发帖和版块/主题最后发表数据，发送管理员审核通知。
- 帖子状态位 3 会被置为 `1`，避免管理员在原生审核页通过时重复发放发帖积分。
- Discuz 原本已要求审核的帖子会保持待审状态，AI 通过也不会自动放行。

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
- 异步队列使用数据库状态和唯一帖子索引避免同一帖子重复审查；任务运行期间使用 Discuz 进程锁避免多个 Worker 重复消耗接口配额。

## 审计日志

安装时创建 `pre_ai_firewall_log`，主要字段：

```text
id, request_id, uid, fid, tid, pid, content_type, decision, reason, categories,
confidence, http_status, latency_ms, content_hash, error_code, created_at
```

后台日志页面支持按决策、内容类型和时间查看，`pass` 决策显示为绿色，`review` 决策显示为红色；用户名可在新窗口快速打开用户信息页，帖子链接可跳转到对应主题或楼层。历史日志没有帖子编号时不显示帖子链接。页面提供清理过期日志及手动清空功能。日志清理采用按概率触发的轻量回收，避免每次发帖都执行删除。

## 异步审查队列

安装时创建 `pre_ai_firewall_queue`，只保存帖子编号、用户、版块、内容类型、尝试次数、结果、错误码和领取状态：

```text
id, uid, fid, tid, pid, content_type, status, attempts, result, error_code,
claim_token, created_at, claimed_at, processed_at
```

任务失败会按队列重试机制重新领取；超过尝试次数后记录 `worker_timeout` 并结束。完成任务按日志保留天数清理。

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
│   ├── moderator.php
│   └── queue.php
├── table/
│   ├── table_ai_firewall_config.php
│   ├── table_ai_firewall_log.php
│   └── table_ai_firewall_queue.php
├── cron/
│   └── cron_ai_firewall.php
└── language/
    ├── lang_admincp.php
    └── lang_template.php
```

## 实施顺序

1. 创建可导入的插件 XML、安装/升级/卸载脚本和数据表。
2. 实现独立后台配置、API Key 安全编辑、日志页面和接口测试。
3. 实现兼容 API 客户端、URL 校验、超时、响应大小限制和严格 JSON 解析。
4. 实现发帖 Hook、版块/身份豁免、成功后入队和原生人工审核接入。
5. 完成日志最小化、过期清理和错误归一化。
6. 执行 PHP 语法检查、安装包结构检查和关键决策场景测试。

## 安装与启用

1. 确认站点使用 UTF-8，且 PHP 已启用 cURL 扩展。
2. 将本目录保留在 `source/plugin/ai_firewall/`，进入 Discuz 管理后台的插件管理页面。
3. 扫描并安装 `AI 防水墙`，安装脚本会创建配置表、审计日志表和异步审查队列表。
4. 在插件的“配置”页面填写 Base URL、API Key、模型和审核 Prompt。
5. 先使用“保存并测试连接”验证接口和模型输出格式，再打开“启用审核”。
6. 在“审核日志”页面查看 AI 决策；待审帖子继续使用 Discuz 原生内容审核页面处理。

已有旧版本安装升级到 `1.4.0` 时，请在插件管理页面执行升级并更新缓存。升级脚本会补齐 Thinking 开关等配置默认值，给旧日志表增加 `tid`、`pid` 和帖子索引，并创建异步审查队列表，不会删除已有配置或日志。已保存的模型名称会继续保留；新安装默认使用 `gpt-5.6-luna`。

## 性能说明

AI 审核在发帖成功、响应基本发送给用户后执行。`fastcgi_finish_request()` 可用时用户无需等待 AI；FastCGI 不可用时 PHP 仍可能在连接层等待后台处理结束，但审查结果已不影响发帖成功状态。

- 默认故障策略为转人工审核，接口超时后不会继续无限等待。
- 响应后处理每次最多处理 3 条，计划任务每次最多处理 20 条，避免任务长时间占用 PHP 进程。
- 插件计划任务受 Discuz 插件计划任务字段限制，实际每 5 分钟轮询一次。
- 从发帖成功到异步审查完成之间，内容会短暂公开可见；通常为接口耗时，兜底周期最长约 5 分钟。
- 对高风险或强实时拦截要求高的站点，应继续使用同步审核架构或结合 Discuz 原生先审后发设置。

## 验收清单

- AI 返回 `pass` 时，新主题和回复正常发布。
- AI 返回 `review` 时，已发布的新主题和回复分别移入 Discuz 原生审核队列，且计数、最后发表和管理员通知正确。
- Discuz 原本要求审核时，AI 返回 `pass` 也不会绕过审核。
- 401、500、超时、空响应、Markdown JSON 和畸形 JSON 均按配置的故障策略执行。
- 插件关闭、内容类型关闭、版块未选中或身份豁免时不调用接口。
- 普通论坛和群组论坛的待审状态正确。
- 日志不包含完整正文和 API Key。
- 安装、升级、关闭、卸载和缓存更新流程可用。
- 异步响应后处理失败时，5 分钟计划任务能继续处理队列。
- 插件新增 PHP 文件通过仓库可用 PHP 版本的语法检查。