# Json Petal Token (JPT)

![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue)
![License](https://img.shields.io/badge/license-Apache%202.0-green)
![Stable](https://img.shields.io/badge/stable-v1.3.2-orange)

## 🌏 语言/Language

- [简体中文](README.md)
- 👉 English

## 📘 Introduction

**PetalBranch/jpt (Json Petal Token)** is a lightweight, secure, and flexible PHP library for token generation and
validation.

Unlike traditional JWTs, JPT introduces the concept of **"Petals"**, supporting partial data encryption. It adopts a
unique three-part structure that maintains transparency for public data while ensuring sensitive information stored on
the client side (such as phone numbers or emails) remains encrypted and secure.

## ✨ Features

- 🔒 **Hybrid Encryption Structure**:
    - Public section (**Crown**) uses Base64URL encoding.
    - Private section (**Petal**) uses [petal-cipher](https://github.com/PetalBranch/petal-cipher) for encryption,
      balancing privacy and usability.

- 🛡️ **Secure Signing**: Supports HMAC algorithms (HS256 / HS384 / HS512) to prevent tampering.

- 🧩 **Flexible Configuration**: Easily configure issuer (`iss`), audience (`aud`), expiration time (`exp`), and
  not-before time (`nbf`).

- ⏱ **Fault Tolerance**: Supports time drift tolerance (`leeway`) to accommodate slight time differences in distributed
  systems.

- 📦 **Strongly Typed Payload**: Returns a strongly typed `JptPayload` object after validation for a better developer
  experience.

- ⚡ **Convenient Path Access** (v1.3.0+): Supports dot notation similar to CSS selectors (e.g.,
  `$payload('c.user.name')`) for quick access to deeply nested data.

## 📦 Installation

It is recommended to install via Composer:

```bash
composer require petalbranch/jpt
```

## 🚀 Quick Start

### Generate a Token and Retrieve Metadata

```php
<?php

use Petalbranch\Jpt\Jpt;

// 1. Initialize configuration
$jpt = new Jpt([
    'secret' => 'your-secure-secret-key-Must-Be-Complex', // Secret key
    'iss'    => 'auth.domain.com',                        // Issuer
    'aud'    => 'payment-service',                        // Audience
    'ttl'    => 3600,                                     // Time-to-live (seconds)
    'alg'    => 'HS256'                                   // Signature algorithm
]);

// Alternatively, you can initialize like this:
// $options = [
//     'secret' => 'your-secure-secret-key-Must-Be-Complex',
//     'iss'    => 'auth.domain.com',
//     'aud'    => 'payment-service',
//     'ttl'    => 3600,
//     'alg'    => 'HS256'
// ];
// $jpt = new Jpt($options);

// 2. Set public data (Crown) - visible to clients
$jpt->setCrownData([
    'uid'  => 10086,
    'role' => 'admin',
    'profile' => [
        'name' => 'PetalUser',
        'tags' => ['vip', 'beta']
    ]
]);

// Chainable method to add a single item
$jpt->withCrown('nickname', 'PetalUser');

// 3. Set private data (Petal) - decryptable only by the server
$jpt->setPetalData([
    'email' => 'user@example.com',
    'sex'   => 1
]);

// Chainable method to add a single item
$jpt->withPetal('contact', ['phone' => '13800138000']);

// 4. Generate the token string
$token = $jpt->generate();

// 5. Immediately retrieve metadata
// Useful for scenarios like storing JTI in Redis for blacklist or single-sign-on
// Logic:
// 1. If generate() was never called, toJptPayload() will internally trigger generation.
// 2. If generate() was already called, toJptPayload() returns the last generated result (snapshot).
// 3. To regenerate based on new data, explicitly call generate() before toJptPayload().
$payloadObj = $jpt->toJptPayload();

$jti = $payloadObj->jti; // Get the system-generated unique ID
$exp = $payloadObj->exp; // Get the expiration timestamp

echo "Token: " . $token . "\n";
echo "JTI: " . $jti . "\n";
```

### Validate a Token

```php
<?php

use Petalbranch\Jpt\Jpt;
use Petalbranch\Jpt\Exception\TokenValidationException;

$tokenString = '...Token received from client...';

$jpt = new Jpt([
    'secret' => 'your-secure-secret-key-Must-Be-Complex', // Must match the one used during generation
    // Optional: Set allowlists for validation
    'allowed_issuers'   => ['auth.domain.com'],
    'allowed_audiences' => ['payment-service', '*']
]);

try {
    // Validate and retrieve the payload object
    $payload = $jpt->validate($tokenString);

    // --- Validation Successful: Retrieve Data ---

    // Get all data
    $allCrown = $payload->getCrownData(null, []);
    $allPetal = $payload->getPetalData(null, []);

    // Get single items
    $uid   = $payload->getCrownData('uid', 0);
    $email = $payload->getPetalData('email', ''); // Decrypted data

    // Get standard claims
    $expireIn = $payload->getExpiration(); // Seconds remaining until expiration
    $issuer   = $payload->iss;
    $audience = $payload('aud'); // v1.3.0+ supports dot notation for direct access

    // --- Method A: Traditional Approach ---
    $uid   = $payload->getCrownData('uid', 0);
    $email = $payload->getPetalData('email', ''); 
    $age   = $payload->getPetalData('age') ?? 0;

    // --- Method B: v1.3.0+ Shortcut Access (Recommended) ---
    // Read nested data: 'c' stands for Crown, 'p' stands for Petal
    $userName = $payload('c.profile.name') ?? 'Guest'; 
    $userTag  = $payload('c.profile.tags.0'); // Access array element
    $phone    = $payload('p.contact.phone');  // Read encrypted data

    echo "User ID: {$uid}, Email: {$email}";

} catch (TokenValidationException $e) {
    // --- Validation Failed ---
    // 401001: Format error
    // 401005: Signature error
    // 401012: Token expired
    http_response_code(401);
    echo "Authentication Failed: " . $e->getMessage() . " (Code: " . $e->getCode() . ")";
}
```

## ⚙️ Configuration Options

The `Jpt` class supports the following configuration parameters during initialization:

| Parameter Key       | Type   | Default Value | Description                                                                                                                  |
|:--------------------|:-------|:--------------|:-----------------------------------------------------------------------------------------------------------------------------|
| `secret`            | string | `''`          | **Required**. The key used for signing and encryption. Ensure high complexity.                                               |
| `iss`               | string | `'nameless'`  | Identifier for the token issuer. Using `*` is not recommended.                                                               |
| `aud`               | string | `'nameless'`  | Identifier for the token audience/recipient. Using `*` is not recommended.                                                   |
| `ttl`               | int    | `3600`        | Token lifespan in seconds.                                                                                                   |
| `alg`               | string | `'HS256'`     | Signature algorithm. Supports `HS256`, `HS384`, `HS512`.                                                                     |
| `leeway`            | int    | `0`           | Time tolerance (seconds) to allow for minor server time discrepancies.                                                       |
| `allowed_issuers`   | array  | `[]`          | Allowlist for issuers. If it contains `*`, any issuer is allowed (skips validation); otherwise, exact match is required.     |
| `allowed_audiences` | array  | `[]`          | Allowlist for audiences. If it contains `*`, any audience is allowed (skips validation); otherwise, exact match is required. |

## 📚 Token Structure

| Structure | Name                    | Encoding/Encryption | Visibility    | Usage                                                                                                                                          |
|:----------|:------------------------|:--------------------|:--------------|:-----------------------------------------------------------------------------------------------------------------------------------------------|
| **Crown** | Header & Public Payload | Base64URL           | 🔓 Public     | Stores metadata (algorithm, type) and **non-sensitive** business data (e.g., UserID, role). Equivalent to JWT Header + Payload.                |
| **Petal** | Private Payload         | PetalCipher         | 🔒 Private    | Stores **sensitive** business data (e.g., phone, email, internal IDs) and integrity checksums. Decryptable only by the server holding the key. |
| **Thorn** | Signature               | HMAC                | 🛡️ Signature | Prevents token tampering. Signs both the Crown and the encrypted Petal.                                                                        |

**Structure Example:**
`eyJpc3MiOiJ0ZXN0LWlzc3VlciIsImF1ZCI6InRlc3QtYXBwIiwibmJmIjoxNzY5NzUxNDQwLCJpYXQiOjE3Njk3NTE0NDAsImV4cCI6MTc2OTc1NTA0MCwianRpIjoianB0LjdlZDM0ZmZlMWU0MGZhZjY0N2ExZTY3MWQzMWY0OTE3IiwiYWxnIjoiSFMyNTYiLCJ0eXAiOiJKUFQifQ.4Aykn...`

## 🌟 JptPayload Shortcut Access (v1.3.0+)

The `Jpt` class provides a shortcut syntax for reading nested data.

### Basic Syntax

`$payload('root.key.subkey...')`

**Root Node Shortcuts:**

- `c` or `crown`: Access the public data section (Crown).
- `p` or `petal`: Access the private data section (Petal).

**Features:**

- **Auto-splitting**: Uses `.` to drill down into arrays automatically.
- **Safe Null Handling**: Returns `null` if the path doesn't exist (no errors), making it easy to use with `??` for
  default values.

### Scenario Comparison

| Scenario                       | Old Syntax (Traditional)                                                                               | New Syntax (Shortcut)             |
|:-------------------------------|:-------------------------------------------------------------------------------------------------------|:----------------------------------|
| **Reading Deeply Nested Data** | `$payload->getCrownData('user')['profile']['name']` *(Requires manual checks for intermediate levels)* | `$payload('c.user.profile.name')` |
| **Reading Encrypted Data**     | `$data = $payload->getPetalData('contact'); $phone = $data['phone'] ?? '';`                            | `$payload('p.contact.phone')`     |
| **With Default Value**         | `($d = $payload->getCrownData('u')) ? ($d['id']??0) : 0`                                               | `$payload('c.user.id') ?? 0`      |
| **Dynamic Keys**               | Requires array concatenation or complex logic                                                          | `$payload("c.user.$dynamicKey")`  |

### Advanced Usage: Multi-parameter Mode

If your key names contain dots (`.`) or you need to dynamically construct paths, you can pass multiple arguments:

```php
// Suppose there is a key named "config.local" in Crown (contains a dot)
// Incorrect: $payload('c.config.local') -> Would be split into config->local
// Correct: Pass the key containing the dot as a separate argument
$value = $payload('c', 'config.local'); 

// Dynamic concatenation
$key = 'name';
$value = $payload('c', 'user', 'profile', $key);
```

## 📖 API Reference Manual

### 1. Jpt Class (Core Operations)

**Basic Configuration (Chainable)**
Supports chainable calls to dynamically modify initialization settings.

- `setOption(string $key, mixed $value): self` - Set a generic configuration option.
- `setOptIss(string $iss): self` - Set the issuer.
- `setOptAud(string $aud): self` - Set the audience.
- `setOptTtl(int $ttl): self` - Set the time-to-live (seconds).
- `setOptLeeway(int $leeway): self` - Set the time drift tolerance.
- `setOptNbf(?int $nbf): self` - Set the not-before time (pass `null` to remove).

**Data Loading (Chainable)**
Used to set business data carried within the token.

- `setCrownData(array $data): self` - Batch set public data (Crown).
- `withCrown(string $key, mixed $value): self` - Add a single public data item.
- `setPetalData(array $data): self` - Batch set private data (Petal).
- `withPetal(string $key, mixed $value): self` - Add a single private data item.
- `withoutCrown(string $key): self` - Remove a specific public data item.
- `withoutPetal(string $key): self` - Remove a specific private data item.

**Core Actions**

- `generate(): string` - Generates the final token string.
- `toJptPayload(): JptPayload` - Retrieves the payload object for the current data (includes generated `jti`, `exp`,
  etc.). Often used to get metadata immediately after generation.
- `validate(string $token): JptPayload` - Validates and parses the token string.

### 2. JptPayload Class (Result Object)

The return result of `validate()` and `toJptPayload()`. All attributes are Readonly.

**Public Attributes**
Directly accessible properties:

- `$payload->iss` (string) - Issuer
- `$payload->sub` (?string) - Subject
- `$payload->aud` (string) - Audience
- `$payload->jti` (string) - Unique ID
- `$payload->exp` (int) - Expiration timestamp
- `$payload->iat` (int) - Issued-at timestamp
- `$payload->nbf` (int) - Not-before timestamp
- `$payload->raw` (string) - **The original raw Token string** (v1.3.0+).

> ⚠️ **Critical Note (Breaking Change)**:
> The legacy property `$payload->payload` was **completely removed** in **v1.3.0**.
> - It was only available in `v1.2.x`.
> - Accessing `$payload->payload` in `v1.3.0` or later will result in an error.
> - **You must use `$payload->raw` instead.**

**Helper Methods**

- `getCrownData(?string $key, mixed $default): mixed` - Get Crown data. Returns the whole array if no key is provided.
- `getPetalData(?string $key, mixed $default): mixed` - Get Petal data (decrypted). Returns the whole array if no key is
  provided.
- `getExpiration(): int` - Get seconds remaining until expiration (returns 0 if expired).

## 📜 Changelog

### [1.3.2] - 2026-03-14

#### Changed

- **[Docs]** Updated the stable version badge in README files to `v1.3.2`.
- **[Refactor]** **Breaking Change Documentation**: Clarified that the `$payload->payload` property was **removed** in *
  *v1.3.0**. This property was only available in v1.2.x; users must now use `$payload->raw` to access the original token
  string.
- **[Docs]** Enhanced API property descriptions with prominent **migration warnings** to guide users from the legacy
  property to `raw`.

#### Fixed

- **[Docs]** Fixed paragraph formatting and table alignment issues in the English documentation (README_EN.md).
- **[Docs]** Audited and corrected all code examples to exclusively use `$payload->raw`, preventing user confusion.

> ⚠️ **Critical Migration Note (Breaking Change)**:
> The property `$payload->payload` was **removed** in **v1.3.0**.
> - ❌ **Deprecated/Removed (v1.3.0+)**: `$token = $payload->payload;` (Will throw an error)
> - ✅ **Correct Usage**: `$token = $payload->raw;`
> 
> Please ensure your code is updated to use the `raw` property.

[👀 View Historical Updates](CHANGELOG.md)

## 📄 License

This project is licensed under the [Apache License 2.0](./LICENSE.txt).