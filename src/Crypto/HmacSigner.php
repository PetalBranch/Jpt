<?php

namespace Petalbranch\Jpt\Crypto;

use Petalbranch\Jpt\Utils\Base64Url;

/**
 * HMAC 签名器（默认实现）
 *
 * 支持算法：HS256 / HS384 / HS512
 */
class HmacSigner implements SignerInterface
{
    protected string $algorithm;

    /**
     * 构造函数
     *
     * @param string $algorithm 算法名称，例如 "HS256"
     */
    public function __construct(string $algorithm = 'HS256')
    {
        $this->algorithm = strtoupper($algorithm);
    }

    /**
     * 获取算法名称
     *
     * @return string 算法名称
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * 将JWT算法映射到PHP哈希算法
     *
     * @return string 对应的PHP哈希算法名称
     * @throws \InvalidArgumentException 当算法不支持时抛出异常
     */
    protected function mapAlgorithm(): string
    {
        return match ($this->algorithm) {
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512',
            default => throw new \InvalidArgumentException("Unsupported algorithm: {$this->algorithm}")
        };
    }

    /**
     * 对数据进行HMAC签名
     *
     * @param string $data 待签名的数据
     * @param string $key 签名密钥
     * @return string Base64Url编码的签名结果
     */
    public function sign(string $data, string $key): string
    {
        $algo = $this->mapAlgorithm();
        $raw = hash_hmac($algo, $data, $key, true);
        return Base64Url::encode($raw);
    }

    /**
     * 验证数据签名是否正确
     *
     * @param string $data 待验证的数据
     * @param string $signature 待验证的签名
     * @param string $key 验证密钥
     * @return bool 签名是否有效
     */
    public function verify(string $data, string $signature, string $key): bool
    {
        $expected = $this->sign($data, $key);
        return hash_equals($expected, $signature);
    }
}

