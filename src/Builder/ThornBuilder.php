<?php

namespace Petalbranch\Jpt\Builder;

use Petalbranch\Jpt\Crypto\SignerInterface;
use Petalbranch\Jpt\Utils\Base64Url;

/**
 * Thorn 构建器（Zone3）
 *
 * 负责生成 JPT 的签名区，用于完整性校验。
 * 签名来源：base64url(crown) . "." . encrypted(petal)
 */
class ThornBuilder
{
    protected SignerInterface $signer;
    protected string $secret;

    public function __construct(SignerInterface $signer, string $secret)
    {
        $this->signer = $signer;
        $this->secret = $secret;
    }

    /**
     * 生成签名
     *
     * @param string $crownBase64 已编码的 zone1
     * @param string $petalEncrypted 已加密的 zone2
     * @return string base64url 编码签名（zone3）
     */
    public function sign(string $crownBase64, string $petalEncrypted): string
    {
        $data = "$crownBase64.$petalEncrypted";
        return $this->signer->sign($data, $this->secret);
    }

    /**
     * 验证签名
     *
     * @param string $crownBase64
     * @param string $petalEncrypted
     * @param string $thornSignature
     * @return bool
     */
    public function verify(string $crownBase64, string $petalEncrypted, string $thornSignature): bool
    {
        $data = "$crownBase64.$petalEncrypted";
        return $this->signer->verify($data, $thornSignature, $this->secret);
    }

    /**
     * 获取算法名
     */
    public function getAlgorithm(): string
    {
        return $this->signer->getAlgorithm();
    }
}
