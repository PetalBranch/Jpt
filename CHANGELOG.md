# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.2] - 2026-03-14

### Changed
- **[Docs]** 更新 README.md 中的稳定版本号为 `v1.3.2`。
- **[Refactor]** **破坏性变更说明**：明确文档指出 `$payload->payload` 属性已在 **v1.3.0** 中被**移除**。该属性仅在 v1.2.x 版本中可用，新版本必须使用 `$payload->raw` 获取原始 Token 字符串。
- **[Docs]** 完善 API 属性列表说明，添加醒目的**迁移警告**，指导用户从旧版属性切换到 `raw`。

### Fixed
- **[Docs]** 修复英文文档 (README_EN.md) 中的段落格式及表格对齐问题。
- **[Docs]** 全面检查并修正文档中的代码示例，确保所有示例均使用 `$payload->raw`，避免误导用户。

## [1.3.1] - 2026-03-13
### Changed
- [Documentation] 更新英文 README.md 说明文档，提升国际化阅读体验。
Added

- [Test] 在测试文件中添加完整的点号分隔访问模式（Dot-notated Access Pattern）单元测试用例： 
  - 覆盖深层嵌套数组的解析逻辑。
  - 验证特殊字符键名的兼容性。
  - 确保智能根节点映射（c/p 前缀）的准确性。
  - 强化安全容错机制（空路径、类型不匹配）的断言测试。


## [1.3.0] - 2026-03-13

### Added
- [Feature] 为 JptPayload 类新增 __invoke 魔术方法，支持通过路径字符串直接访问嵌套数据：
  - 点号分隔模式：支持 $payload('c.user.profile.name') 语法，自动解析深层数组结构。
  - 多参数模式：支持 $payload('c', 'user', 'config.local') 语法，允许键名中包含特殊字符（如点号）。
  - 智能根节点映射：自动识别简写前缀 c (Crown) 和 p (Petal)。
  - 安全容错：路径不存在或类型不匹配时返回 null，完美适配 PHP 空合并运算符 (??)。


## [1.2.3] - 2026-01-30

### Documentation
- **[Clarification]** 完善 PHPDoc 注释与 README 文档：
  - 明确指出在设置 `iss` 和 `aud` 时不应使用通配符 `*`。
  - 澄清验证白名单 (`allowed_issuers` / `allowed_audiences`) 中 `*` 的含义为“允许任意来源”。

## [1.2.2] - 2026-01-30

### Added
- 新增 `toJptPayload()` 方法：支持在生成 Token 后立即获取 JTI、EXP 等元数据对象，无需二次解析。
- 新增 `Jpt::getLastPayload()` 内部快照机制，确保对象状态与生成的 Token 字符串强一致。
- 新增 API 参考手册至 README 文档。

### Changed
- 优化 `generate()` 方法逻辑，在生成字符串的同时构建 `JptPayload` 缓存。
- 更新 README 快速开始部分，增加获取 Token 元数据的示例。

## [1.2.1] - 2026-01-28

### Added
- 新增 `JptTest` 单元测试套件，覆盖率包含：
    - 生成和验证流程
    - 签名篡改与 Crown 数据篡改检测
    - 过期时间 (exp) 与生效时间 (nbf) 处理
    - 签发人 (iss) 与受众 (aud) 白名单及通配符支持
    - 配置标准化及链式设置
    - 禁止修改的核心 Crown 字段保护
- 新增 `Utils` 类，封装 Base64 URL 编解码逻辑。

### Changed
- 优化 `JptPayload.php`，清理未使用的引用。

### Removed
- 移除 `JptPayload.php` 中未使用的 `InvalidArgumentException`。
- 移除 `composer.lock` 文件提交，避免库开发中的依赖锁定问题。

## [1.2.0] - 2026-01-28

### Added
- **[Major]** 引入 `JptPayload` 类：将解析后的数组转换为强类型只读对象，提升开发体验。
- 新增 `.gitignore` 规则，忽略锁文件。
- README 增加项目徽章、结构图示及详细配置表格。

### Changed
- **[Breaking]** 更新 PHP 版本要求为 `^8.3`。
- 重构 `Jpt.php` 核心类：
    - 标准化配置选项键名处理。
    - 增强数据 Set/Get 方法的健壮性。
- 优化 `Utils.php`：引入 `JsonException`，在 `json_decode` 失败时抛出明确异常。

### Removed
- 移除旧版 `JptTest.php` 测试用例。

## [1.1.0] - 2025-11-10

### Added
- 新增 `withCrown()` 和 `withPetal()` 方法，支持流畅的链式调用设置数据。
- 补充 Token 结构图示与编码细节说明文档。

### Changed
- 优化 `sub` (Subject) 字段设置逻辑，防止空值覆盖默认配置。
- 修正 `setOptIssuers` 方法的返回类型声明。
- 修复 `getCrownData` 和 `getPetalData` 参数间距格式问题。

## [1.0.1] - 2025-11-09

### Added
- 新增 `TokenValidationException` 异常类：提供具体的错误码 (如 401001, 401012) 以区分验证失败原因。
- 扩展 README 文档，增加特性列表和详细使用示例。

### Changed
- 更新 `validate` 方法：现在抛出带错误码的 `TokenValidationException` 而非通用 Exception。
- 增强数据验证逻辑：增加对 Crown 和 Petal 结构的完整性检查。
- 改进数据获取方法：`getCrownData` 和 `getPetalData` 现支持传入默认值。

## [1.0.0] - 2025-11-09

### Changed
- 调整依赖配置：移除 `ext-openssl` 显式依赖 (由 PHP 核心或 ext-json 覆盖)。
- 锁定平台版本：明确指定 PHP 版本要求为 `^8.0` 以确保兼容性。
- 初始化项目发布。