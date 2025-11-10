# Json Petal Token (JPT)

## 📘 简介

**PetalBranch/jpt** `JPT(Json Petal Token)` 是一个用于身份验证和授权的 PHP 令牌生成库。

## ✨ 特性

- 🔒 支持多种 HMAC 签名算法（HS256 / HS384 / HS512）
- 🌸 三段式结构：`crown.petal.thorn`
- 🧩 可设置签发人 (iss)、接收方 (aud)、有效期 (exp)、生效时间 (nbf)
- ⏱ 支持时间容差 (leeway)
- ⚙️ 提供自定义 crown / petal 数据接口
- 💬 抛出标准化验证异常（TokenValidationException）

## 📦 安装

使用 Composer：

```bash
composer require petalbranch/jpt
```

手动引入（不推荐）：

```php
require_once 'src/JPT.php';
```

## 🚀 快速开始

### 🧾 生成 Token

```php
<?php
use PetalBranch\Jpt\Jpt;

$jpt = new Jpt([
    'secret'    => 'your-secret-key',
    'iss'       => 'domain.com',
    'aud'       => 'your-audience',
    'ttl'       => 3600,
]);
$jpt->setCrownData(['name' => 'PetalBranch', 'age' => 18]);
$jpt->setPetalData(['email' => 'branch@petalmail.com']);

// 添加数据
$jpt->withCrown('nickname'=>> 'PB')
    ->withPetal('phone'=>> '1234567890');

$token = $jpt->generate();

echo $token; // crown.petal.thorn
```

### 🔍 验证 Token

```php
<?php
use PetalBranch\Jpt\Jpt;
$token = ''; // 待验证的 Token

$jpt = new Jpt([
'secret' => 'your-secret-key',
'issuers' => 'domain.com'
]);

// 验证
try {
    $jpt->validate($token);
}catch (TokenValidationException $e){
    // 处理验证失败
    $msg = $e->getMessage();
    $code = $e->getCode();
    // ......
    // die($msg);
}

// 获取所有数据
$crownData =$jpt->getCrownData();
$petalData = $jpt->getPetalData();

// 获取单个数据
$name = $jpt->getCrownData('name','unknown');
$age = $jpt->getCrownData('age',0);
$email = $jpt->getPetalData('email','unknown');
```

## 📚 结构

`crown.petal.thorn`

| 区段        | 名称                                         | 编码方式        | 可见性          | 主要作用                              |
|-----------|--------------------------------------------|-------------|--------------|-----------------------------------|
| **Crown** | 公开数据区<br />Public Zone<br />Crown Segment  | Base64URL   | 客户端可见        | 存放公开的算法信息、Token类型、业务数据、声明信息、自定义内容 |
| **Petal** | 私有数据区<br />Private Zone<br />Petal Segment | PetalCipher | 仅服务端可解密      | 存放内部校验摘要、私密字段、策略信息等               |
| **Thorn** | 签名区<br />Signature Zone<br />Thorn Segment | HMAC        | 公共可验证（但依赖密钥） | 防篡改、认证来源                          |

示例：

```text
eyJpc3MiOiJTbXNNZyIsInN1YiI6IlNtc01nIiwiYXVkIjoiU21zTWciLCJpYXQiOjE3NjI3NzI1
NjcsImV4cCI6MTc2Mjc3NjE2NywianRpIjoiYTQ1YjY3ZGQxMDU4NDBhMTRlMTQ3ZmUyOGU1MDEz
ODciLCJpZCI6MSwidXNlcm5hbWUiOiJ0ZXN0Iiwibmlja25hbWUiOiLmtYvor5XnlKjmiLciLCJh
bGciOiJIUzI1NiIsInR5cCI6IkpQVCJ9.1g9rt4v23A6tB6-d5dTBEQkdEQhxYbWrNiTxhfOAhbY
bYQZVEvY0hfOANaCuNm9q.3fba15e95d346f12c289c7e5e88c008a73e18d1d38f8b476dbbca0
f2add9486a`
```

## 📄 许可证

- [Apache License 2.0](LICENSE.txt)