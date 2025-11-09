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
}

// 获取数据
$crownData =$jpt->getCrownData();
$petalData = $jpt->getPetalData();

// 获取单个数据
$name = $jpt->getCrownData('name','unknown');
$age = $jpt->getCrownData('age',0);
$email = $jpt->getPetalData('email','unknown');
```



## 📄 许可证

- [Apache License 2.0](LICENSE.txt)