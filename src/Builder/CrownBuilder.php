<?php

namespace Petalbranch\Jpt\Builder;

use Petalbranch\Jpt\Utils\Base64Url;

/**
 * Crown 构建器
 *
 * 负责构建 JPT 的公开数据部分。
 * 包含标准声明（如 alg、typ、iat、exp、sub）以及业务自定义字段。
 */
class CrownBuilder
{
    protected array $header = [];
    protected array $claims = [];

    public function __construct()
    {
        $this->header = ['typ' => 'JPT'];
    }

    /**
     * 设置算法（用于声明）
     */
    public function setAlgorithm(string $alg): static
    {
        $this->header['alg'] = $alg;
        return $this;
    }

    /**
     * 设置类型（默认 JPT，可自定义）
     */
    public function setType(string $type): static
    {
        $this->header['typ'] = $type;
        return $this;
    }

    /**
     * 添加标准声明
     * 例如：iss, sub, aud, exp, iat, nbf, jti 等
     */
    public function setClaim(string $name, mixed $value): static
    {
        $this->claims[$name] = $value;
        return $this;
    }

    /**
     * 添加多个业务字段
     */
    public function addClaims(array $claims): static
    {
        $this->claims = array_merge($this->claims, $claims);
        return $this;
    }

    /**
     * 获取完整数据结构（header + claims）
     */
    public function toArray(): array
    {
        return array_merge($this->header, $this->claims);
    }

    /**
     * 编码为 Base64Url
     */
    public function toBase64(): string
    {
        return Base64Url::encodeJson($this->toArray());
    }

    /**
     * 从 Base64Url 解码成对象
     */
    public static function fromBase64(string $encoded): static
    {
        $data = Base64Url::decodeJson($encoded) ?? [];
        $builder = new static();

        foreach ($data as $k => $v) {
            if (in_array($k, ['alg', 'typ'])) {
                $builder->header[$k] = $v;
            } else {
                $builder->claims[$k] = $v;
            }
        }

        return $builder;
    }

    /**
     * 获取单个字段
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->claims[$key] ?? $this->header[$key] ?? $default;
    }
}
