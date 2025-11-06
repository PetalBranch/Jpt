<?php

namespace Petalbranch\Jpt;

use Exception;
use Petalbranch\Jpt\Builder\CrownBuilder;
use Petalbranch\Jpt\Builder\PetalBuilder;
use Petalbranch\Jpt\Builder\ThornBuilder;
use Petalbranch\Jpt\Crypto\SignerInterface;
use Petalbranch\PetalCipher\PetalCipher;

class Jpt
{
    protected SignerInterface $signer;
    protected PetalCipher $cipher;
    protected string $secret;

    public function __construct(SignerInterface $signer, PetalCipher $cipher, string $secret)
    {
        $this->signer = $signer;
        $this->cipher = $cipher;
        $this->secret = $secret;
    }


    /**
     * 生成 JPT Token
     *
     * @param array $claims 公共数据
     * @param array $private 私有数据
     * @return string
     */
    public function encode(array $claims, array $private = []): string
    {
        // 构建 Crown
        $crown = (new CrownBuilder())
            ->setAlgorithm($this->signer->getAlgorithm())
            ->addClaims($claims);
        $crownBase64 = $crown->toBase64();

        // 构建 Petal
        $petal = (new PetalBuilder($this->cipher))
            ->setDigest($crownBase64, $this->secret)
            ->addFields($private);
        $petalEncrypted = $petal->toEncrypted();

        // 构建 Thorn 签名
        $thorn = new ThornBuilder($this->signer, $this->secret);
        $signature = $thorn->sign($crownBase64, $petalEncrypted);

        // 拼装完整 Token
        return "$crownBase64.$petalEncrypted.$signature";
    }


    /**
     * 解析 JPT Token
     *
     * @param string $token
     * @return array [crown, petal, thorn]
     * @throws Exception
     */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception("Invalid JPT format");
        }

        [$crownBase64, $petalEncrypted, $thornSignature] = $parts;

        $crown = CrownBuilder::fromBase64($crownBase64);
        $petal = PetalBuilder::fromEncrypted($this->cipher, $petalEncrypted);

        return [
            'crown' => $crown->toArray(),
            'petal' => $petal->toArray(),
            'thorn' => $thornSignature,
        ];
    }


    /**
     * 验证 Token
     *
     * @param string $token
     * @return bool
     */
    public function verify(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$crownBase64, $petalEncrypted, $thornSignature] = $parts;

        // 验证签名
        $thorn = new ThornBuilder($this->signer, $this->secret);
        if (!$thorn->verify($crownBase64, $petalEncrypted, $thornSignature)) {
            return false;
        }

        // 验证 Crown 与 Petal 绑定关系
        $petal = PetalBuilder::fromEncrypted($this->cipher, $petalEncrypted);
        if (!$petal->verifyDigest($crownBase64, $this->secret)) {
            return false;
        }

        // 验证过期时间
        $crown = CrownBuilder::fromBase64($crownBase64);
        $exp = $crown->get('exp');
        if ($exp && $exp < time()) {
            return false;
        }

        return true;
    }

}