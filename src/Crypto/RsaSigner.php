<?php

namespace Petalbranch\Jpt\Crypto;

use Petalbranch\Jpt\Utils\Base64Url;
use InvalidArgumentException;
use RuntimeException;

/**
 * RSA 签名器
 *
 * 支持算法：RS256 / RS384 / RS512
 * 适合需要公私钥分离验证的场景。
 */
class RsaSigner implements SignerInterface
{
    protected string $algorithm;
    protected string $privateKey;
    protected ?string $publicKey;

    /**
     * 构造函数，初始化 RSA 签名器实例
     *
     * @param string $algorithm 算法名称，如 "RS256"、"RS384" 或 "RS512"
     * @param string $privateKey PEM 格式的私钥字符串，用于签名数据
     * @param string|null $publicKey PEM 格式的公钥字符串，用于验证签名（可选）
     */
    public function __construct(string $algorithm, string $privateKey, ?string $publicKey = null)
    {
        $this->algorithm = strtoupper($algorithm);
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
    }

    /**
     * 获取当前使用的签名算法标识符
     *
     * @return string 当前设置的算法名称（大写形式）
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * 将标准算法名称映射到 OpenSSL 常量
     *
     * @return string 对应的 OpenSSL 算法常量
     * @throws InvalidArgumentException 如果不支持该算法时抛出异常
     */
    protected function mapAlgorithm(): string
    {
        return match ($this->algorithm) {
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
            default => throw new InvalidArgumentException("Unsupported RSA algorithm: $this->algorithm")
        };
    }

    /**
     * 使用私钥对给定的数据进行签名，并返回 URL 安全的 Base64 编码结果
     *
     * @param string $data 需要签名的原始数据
     * @param string $key 可选的私钥密码，默认为空字符串
     * @return string 经过 Base64Url 编码后的签名结果
     * @throws RuntimeException 若私钥无效或签名失败则抛出运行时异常
     */
    public function sign(string $data, string $key = ''): string
    {
        // 传入$key作为私钥密码（空字符串不影响未加密的私钥）
        $privateKey = openssl_pkey_get_private($this->privateKey, $key);
        if (!$privateKey) {
            throw new RuntimeException("Invalid private key");
        }

        $algo = $this->mapAlgorithm();
        $success = openssl_sign($data, $signature, $privateKey, $algo);

        if (!$success) {
            throw new RuntimeException("RSA signing failed");
        }

        return Base64Url::encode($signature);
    }

    /**
     * 使用公钥验证数据与签名是否匹配
     *
     * @param string $data 被签名的原始数据
     * @param string $signature 已经 Base64Url 编码过的签名值
     * @param string $key 可选的备用公钥，若构造时未提供将优先使用此参数
     * @return bool 验证通过返回 true，否则返回 false
     * @throws RuntimeException 公钥缺失、格式错误或 OpenSSL 内部错误时抛出异常
     */
    public function verify(string $data, string $signature, string $key = ''): bool
    {
        $publicKey = $this->publicKey ?? $key;
        if (!$publicKey) {
            throw new RuntimeException("Public key required for verification");
        }

        $publicKeyRes = openssl_pkey_get_public($publicKey);
        if (!$publicKeyRes) {
            throw new RuntimeException("Invalid public key");
        }

        // 解码并准备验证所需参数
        $decoded = Base64Url::decode($signature);
        $algo = $this->mapAlgorithm();

        $result = openssl_verify($data, $decoded, $publicKeyRes, $algo);

        if ($result === -1) {
            throw new RuntimeException("OpenSSL verification error: " . openssl_error_string());
        }

        return $result === 1;
    }
}
