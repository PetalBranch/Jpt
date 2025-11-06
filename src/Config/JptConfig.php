<?php

namespace Petalbranch\Jpt\Config;

/**
 * Class JptConfig
 *
 * 用于管理 JPT Token 的基本配置信息。
 */
class JptConfig
{
    /** @var string 算法类型，如 HS512 / RS256 */
    protected string $alg = 'HS512';

    /** @var string|null 对称密钥或私钥 */
    protected ?string $secret = null;

    /** @var string|null 公钥（仅非对称算法） */
    protected ?string $publicKey = null;

    /** @var int 默认过期时间（秒） */
    protected int $ttl = 3600;

    /** @var array 默认公共字段 */
    protected array $defaultCrown = [
        'typ' => 'JPT',
        'iss' => 'localhost',
    ];

    /** @var array 默认私有字段 */
    protected array $defaultPetal = [];

    /** @var string 默认签名字段名 */
    protected string $thornField = 'sign';

    /**
     * JptConfig constructor.
     *
     * @param array $options 支持以下键:
     *                       - alg: string
     *                       - secret: ?string
     *                       - publicKey: ?string
     *                       - ttl: int
     *                       - defaultCrown: array
     *                       - defaultPetal: array
     *                       - thornField: string
     */
    public function __construct(array $options = [])
    {
        $allowedKeys = [
            'alg', 'secret', 'publicKey', 'ttl',
            'defaultCrown', 'defaultPetal', 'thornField'
        ];

        foreach ($options as $key => $value) {
            if (!in_array($key, $allowedKeys, true)) {
                continue;
            }

            // 特殊处理需要类型检查的字段
            switch ($key) {
                case 'ttl':
                    if (!is_int($value) || $value <= 0) {
                        throw new \InvalidArgumentException("TTL must be a positive integer.");
                    }
                    break;
                case 'alg':
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException("Algorithm must be a string.");
                    }
                    break;
                case 'defaultCrown':
                case 'defaultPetal':
                    if (!is_array($value)) {
                        throw new \InvalidArgumentException("$key must be an array.");
                    }
                    break;
            }

            $this->$key = $value;
        }
    }

    public function getAlg(): string
    {
        return $this->alg;
    }

    public function setAlg(string $alg): self
    {
        $this->alg = $alg;
        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): self
    {
        $this->secret = $secret;
        return $this;
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function setPublicKey(?string $publicKey): self
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl): self
    {
        if ($ttl <= 0) {
            throw new \InvalidArgumentException("TTL must be a positive integer.");
        }
        $this->ttl = $ttl;
        return $this;
    }

    public function getDefaultCrown(): array
    {
        return $this->defaultCrown;
    }

    public function getDefaultPetal(): array
    {
        return $this->defaultPetal;
    }

    public function getThornField(): string
    {
        return $this->thornField;
    }
}
