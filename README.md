# Json Petal Token (JPT)

![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue)
![License](https://img.shields.io/badge/license-Apache%202.0-green)
![Stable](https://img.shields.io/badge/stable-v1.2.3-orange)

## 📘 简介

**PetalBranch/jpt** (Json Petal Token) 是一个轻量级、安全且灵活的 PHP 令牌生成与验证库。

不同于传统的 JWT，JPT 引入了 **"Petal" (花瓣)** 概念，支持**部分数据加密**
。它采用独特的三段式结构，既保留了公开数据的透明性，又通过加密段确保存储在客户端的敏感数据（如用户手机号、邮箱等）不被泄露。

## ✨ 特性

- 🔒 **混合加密结构**：公开区 (Crown) 使用 Base64URL，私有区 (Petal)
  使用[petal-cipher](https://github.com/PetalBranch/petal-cipher)加密兼顾隐私。
- 🛡️ **安全签名**：支持 HMAC 系列算法 (HS256 / HS384 / HS512)，防止数据篡改。
- 🧩 **灵活配置**：轻松设置签发人 (iss)、受众 (aud)、过期时间 (exp) 及 生效时间 (nbf)。
- ⏱ **容错机制**：支持时间漂移容差 (leeway)，适应分布式系统的时间偏差。
- 📦 **强类型载荷**：验证后返回强类型 `JptPayload` 对象，开发体验更佳。
- ⚡ **便捷路径访问**：(v1.3.0+) 支持类似 CSS 选择器的点号语法 (`$payload('c.user.name')`) 快速读取深层嵌套数据。

## 📦 安装

推荐使用 Composer 进行安装：

```bash
composer require petalbranch/jpt
```

## 🚀 快速开始

1. 生成 Token 并获取元数据

    ```php
    <?php
    
    use Petalbranch\Jpt\Jpt;
   
    // 1. 初始化配置
    $jpt = new Jpt([
    'secret' => 'your-secure-secret-key-Must-Be-Complex', // 密钥
    'iss'    => 'auth.domain.com',                        // 签发人
    'aud'    => 'payment-service',                        // 受众
    'ttl'    => 3600,                                     // 有效期 (秒)
    'alg'    => 'HS256'                                   // 签名算法
    ]);
   
    // 2. 设置公开数据 (Crown) - 客户端可见
    $jpt->setCrownData([
    'uid'  => 10086,
    'role' => 'admin'
    'profile' => [
            'name' => 'PetalUser',
            'tags' => ['vip', 'beta']
        ]
    ]);
    // 链式调用添加单个数据
    $jpt->withCrown('nickname', 'PetalUser');
   
    // 3. 设置私有数据 (Petal) - 仅服务端可解密
    $jpt->setPetalData([
    'email' => 'user@example.com',
    'sex'   => 1
    ]);
    // 链式调用添加单个数据
    $jpt->withPetal('phone', '13800138000');
   
    // 4. 生成字符串
    $token = $jpt->generate();
   
    // 5. [新特性] 立即获取元数据
    // 适用于需要将 JTI 存入 Redis 做黑名单或单点登录的场景
    $payloadObj = $jpt->toJptPayload();
   
    $jti = $payloadObj->jti; // 获取系统生成的唯一标识 ID
    $exp = $payloadObj->exp; // 获取过期时间戳
   
    echo "Token: " . $token . "\n";
    echo "JTI: " . $jti . "\n";
    ```

2. 验证 Token

    ```php
    <?php

    use Petalbranch\Jpt\Jpt;
    use Petalbranch\Jpt\Exception\TokenValidationException;
    
    $tokenString = '...客户端传来的Token...';
    
    $jpt = new Jpt([
    'secret' => 'your-secure-secret-key-Must-Be-Complex', // 必须与生成时一致
    // 可选：设置验证白名单
    'allowed_issuers'   => ['auth.domain.com'],
    'allowed_audiences' => ['payment-service', '*']
    ]);
    
    try {
    // 验证并获取载荷对象 (Payload)
    $payload = $jpt->validate($tokenString);
    
        // --- 验证成功，获取数据 ---
    
        // 获取所有数据
        $allCrown = $payload->getCrownData(null, []);
        $allPetal = $payload->getPetalData(null, []);
    
        // 获取单个数据
        $uid   = $payload->getCrownData('uid', 0);
        $email = $payload->getPetalData('email', ''); // 解密后的数据
        
        // 获取标准声明
        $expireIn = $payload->getExpiration(); // 距离过期剩余秒数
        $issuer   = $payload->iss;
   
        // --- 方式 A: 传统方法 (兼容旧版) ---
        $uid   =  $payload->getCrownData('uid', 0);
        $email =  $payload->getPetalData('email', ''); 
    
        // --- 方式 B: v1.3.0+ 捷径访问 (推荐) ---
        // 读取嵌套数据：c 代表 Crown, p 代表 Petal
        $userName =  $payload('c.profile.name') ?? 'Guest'; 
        $userTag  =  $payload('c.profile.tags.0'); // 访问数组元素
        $phone    =  $payload('p.contact.phone');  // 读取加密数据 
       
        echo "用户ID: {$uid}, 邮箱: {$email}";
    
    } catch (TokenValidationException $e) {
    // --- 验证失败 ---
    // 401001: 格式错误
    // 401005: 签名错误
    // 401012: 令牌过期
    http_response_code(401);
    echo "认证失败: " . $e->getMessage() . " (Code: " . $e->getCode() . ")";
    }
    ```

## ⚙️ 配置选项

初始化 Jpt 类时支持以下配置参数：

| 参数键名              | 类型     | 默认值        | 说明                                     |
|-------------------|--------|------------|----------------------------------------|
| secret            | string | ''         | 必填。用于签名和加密的密钥，请确保复杂度。                  |
| iss               | string | 'nameless' | Token 的签发者标识。不建议使用 `*`。                |
| aud               | string | 'nameless' | Token 的接收方/受众标识。不建议使用 `*`。             |
| ttl               | int    | 3600       | Token 的生命周期，单位为秒。                      |
| alg               | string | 'HS256'    | 签名算法，支持 HS256, HS384, HS512。           |
| leeway            | int    | 0          | 时间容差(秒)，允许服务器时间存在微小偏差。                 |
| allowed_issuers   | array  | []         | 验证白名单。若包含 `*` 则允许任意签发人 (跳过验证)；否则需精确匹配。 |
| allowed_audiences | array  | []         | 验证白名单。若包含 `*` 则允许任意受众 (跳过验证)；否则需精确匹配。  |

## 📚 令牌结构

| **结构**    | **名称 (Name)**                | **编码/加密 (Encoding)** | **可见性 (Visibility)** | **用途 (Usage)**                                                    |
|-----------|------------------------------|----------------------|----------------------|-------------------------------------------------------------------|
| **Crown** | 花冠 (Header & Public Payload) | Base64URL            | 🔓 公开 (Public)       | 存放元数据（算法、类型）及**非敏感**业务数据（如 UserID、权限等级）。相当于 JWT 的 Header+Payload。 |
| **Petal** | 花瓣 (Private Payload)         | PetalCipher          | 🔒 私密 (Private)      | 存放**敏感**业务数据（如手机号、邮箱、内部ID）及完整性摘要。仅持有密钥的服务端可解密。                    |
| **Thorn** | 花刺 (Signature)               | HMAC                 | 🛡️ 签名 (Signature)   | 防止 Token 被篡改。对 Crown 和 Petal 的密文进行签名。                             |

结构示例：

```text
eyJpc3MiOiJ0ZXN0LWlzc3VlciIsImF1ZCI6InRlc3QtYXBwIiwibmJmIjoxNzY5NzUxNDQwL
CJpYXQiOjE3Njk3NTE0NDAsImV4cCI6MTc2OTc1NTA0MCwianRpIjoianB0LjdlZDM0ZmZlMW
E0MGZhZjY0N2ExZTY3MWQzMWY0OTE3IiwiYWxnIjoiSFMyNTYiLCJ0eXAiOiJKUFQifQ.4Ayk
nbQwWNCkJ2G8h8Lih9zin3GvB3OJn9-_z9ylBsGF-3-_n9LxBoOFn_CvzXzchQBl-SaJ-oDln
sGxn_RN-ol8nsOxnQzihs-Az3-ALe7.1ef1768264878c6e8cb0a3f9616d6ca3c1580e7fcf
7cf0b50f39764dc36eb170
```

## 🌟 JptPayload 捷径访问 (v1.3.0+)

Jpt 类提供了一种捷径访问方式，用于读取嵌套数据。

**基本语法**

```php
$payload('根节点.键名.子键名...')
```

- 根节点简写：
    - c 或 crown：访问公开数据区 (Crown)
    - p 或 petal：访问私密数据区 (Petal)
- 自动分割：使用 . 自动层层深入数组。
- 安全空值：如果路径不存在，返回 null (不会报错)，方便使用 ?? 设置默认值。

**场景对比**

| 场景     | 旧写法 (传统方法)                                                                       | 新写法 (捷径访问)                        |
|:-------|:---------------------------------------------------------------------------------|:----------------------------------|
| 读取深层数据 | `$payload->getCrownData('user')['profile']['name']` <br> *(需手动确保中间层级存在)*         | `$payload('c.user.profile.name')` |
| 读取加密数据 | `$data = $payload->getPetalData('contact');`<br>`$phone = $data['phone'] ?? '';` | `$payload('p.contact.phone')`     |
| 带默认值   | `($d = $payload->getCrownData('u')) ? ($d['id']??0) : 0`                         | `$payload('c.user.id') ?? 0`      |
| 动态键名   | 需拼接数组或复杂逻辑                                                                       | `$payload("c.user.$dynamicKey")`  |

**高级用法：多参数模式**

如果您的键名中本身包含点号 (.)，或者需要动态拼接路径，可以传入多个参数：

```php
// 假设 Crown 中有一个键名叫 "config.local" (包含点号)
// 错误写法： $ payload('c.config.local') -> 会被拆分为 config->local
// 正确写法：将含点号的键名作为独立参数
$value =  $ payload('c', 'config.local'); 

// 动态拼接
$key = 'name';
$value =  $ payload('c', 'user', 'profile',  $ key);
```


## 📖 API 参考手册

### 1. Jpt 类 (核心操作)

#### 基础配置 (Chainable)

支持链式调用，用于动态修改初始化时的配置。

- `setOption(string $key, mixed $value): self` - 设置通用配置项。
- `setOptIss(string $iss): self` - 设置签发人。
- `setOptAud(string $aud): self` - 设置受众。
- `setOptTtl(int $ttl): self` - 设置生命周期(秒)。
- `setOptLeeway(int $leeway): self` - 设置时间漂移容差。
- `setOptNbf(?int $nbf): self` - 设置生效时间 (传入 null 可移除)。

#### 数据装载 (Chainable)

用于设置 Token 内携带的业务数据。

- `setCrownData(array $data): self` - 批量设置公开数据 (Crown)。
- `withCrown(string $key, mixed $value): self` - 添加单项公开数据。
- `setPetalData(array $data): self` - 批量设置私密数据 (Petal)。
- `withPetal(string $key, mixed $value): self` - 添加单项私密数据。
- `withoutCrown(string $key): self` - 移除某项公开数据。
- `withoutPetal(string $key): self` - 移除某项私密数据。

#### 核心动作

- `generate(): string` - 生成最终的 Token 字符串。
- `toJptPayload(): JptPayload` - 获取当前数据的载荷对象 (包含生成的 jti, exp 等)。**常用于生成后立即获取元数据。**
- `validate(string $token): JptPayload` - 验证并解析 Token 字符串。

---

### 2. JptPayload 类 (结果对象)

`validate()` 和 `toJptPayload()` 的返回结果，所有属性均为 **Readonly**。

#### 公开属性

可以直接访问以下属性：

- `$payload->iss` (string) - 签发人
- `$payload->sub` (?string) - 主题
- `$payload->aud` (string) - 受众
- `$payload->jti` (string) - 唯一标识 ID
- `$payload->exp` (int) - 过期时间戳
- `$payload->iat` (int) - 签发时间戳
- `$payload->nbf` (int) - 生效时间戳
- `$payload->payload` (string) - 原始 Token 字符串

#### 辅助方法

- `getCrownData(?string $key, mixed $default): mixed` - 获取 Crown 数据。不传 key 返回整个数组。
- `getPetalData(?string $key, mixed $default): mixed` - 获取 Petal 数据 (已解密)。不传 key 返回整个数组。
- `getExpiration(): int` - 获取距离过期的剩余秒数 (已过期返回 0)。

## 📜 更新日志

### [1.3.0] - 2026-03-13

#### Added

- [Feature] 为 JptPayload 类新增 __invoke 魔术方法，支持通过路径字符串直接访问嵌套数据：
    - 点号分隔模式：支持 $payload('c.user.profile.name') 语法，自动解析深层数组结构。
    - 多参数模式：支持 $payload('c', 'user', 'config.local') 语法，允许键名中包含特殊字符（如点号）。
    - 智能根节点映射：自动识别简写前缀 c (Crown) 和 p (Petal)。
    - 安全容错：路径不存在或类型不匹配时返回 null，完美适配 PHP 空合并运算符 (??)。

[👀 历史更新](CHANGELOG.md)

## 📄 许可证

本项目遵循 [Apache License 2.0](./LICENSE.txt) 开源协议。
