<?php

namespace Petalbranch\Jpt\Builder;

use Petalbranch\Jpt\Utils\PetalCipherUrl;
use PetalBranch\PetalCipher\PetalCipher;

/**
 * Petal 构建器
 *
 * 负责构建 JPT 的私有数据区
 */
class PetalBuilder
{
    protected PetalCipher $cipher;
    protected array $payload = [];
    protected string $digestAlgo = 'sha256';
    protected ?string $digest = null;

    public function __construct(PetalCipher $cipher)
    {
        $this->cipher = $cipher;
    }

    /**
     * 添加私有字段
     *
     * 示例: server_tag、ip、env 等
     */
    public function setField(string $key, mixed $value): static
    {
        $this->payload[$key] = $value;
        return $this;
    }

    /**
     * 批量添加字段
     */
    public function addFields(array $fields): static
    {
        $this->payload = array_merge($this->payload, $fields);
        return $this;
    }

    /**
     * 获取 payload 数组
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    /**
     * 使用 PetalCipher 加密后输出
     */
    public function toEncrypted(): string
    {
        return PetalCipherUrl::encodeJson($this->payload, $this->cipher);
    }

    /**
     * 从加密数据解密成对象
     */
    public static function fromEncrypted(PetalCipher $cipher, string $encrypted): static
    {
        $data = PetalCipherUrl::decodeJson($encrypted, $cipher) ?? [];

        $builder = new static($cipher);
        $builder->payload = $data;

        return $builder;
    }

    /**
     * 设置 crown 的摘要（HMAC 或 hash）
     */
    public function setDigest(string $crownData, string $secret): static
    {
        $this->digest = hash_hmac($this->digestAlgo, $crownData, $secret);
        $this->payload['digest'] = $this->digest;
        return $this;
    }

    /**
     * 验证 crown 摘要是否一致（防篡改）
     */
    public function verifyDigest(string $crownData, string $secret): bool
    {
        $expected = hash_hmac($this->digestAlgo, $crownData, $secret);
        return hash_equals($expected, $this->digest ?? '');
    }
}