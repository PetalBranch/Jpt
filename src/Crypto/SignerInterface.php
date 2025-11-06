<?php

namespace Petalbranch\Jpt\Crypto;

interface SignerInterface
{
    /**
     * 获取签名算法标识，如：HS256、RS256
     */
    public function getAlgorithm(): string;

    /**
     * 对数据生成签名
     *
     * @param string $data 要签名的数据
     * @param string $key 密钥
     * @return string      签名（原始二进制或base64url编码）
     */
    public function sign(string $data, string $key): string;

    /**
     * 验证签名是否有效
     *
     * @param string $data 原始数据
     * @param string $signature 待验证签名
     * @param string $key 密钥
     * @return bool
     */
    public function verify(string $data, string $signature, string $key): bool;
}