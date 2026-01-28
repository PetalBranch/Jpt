<?php


namespace Petalbranch\Jpt;

use InvalidArgumentException;

/**
 * JptPayload 类用于表示一个具有JPT结构的载荷对象。
 * 该类为只读，一旦创建实例后其属性值不可更改。
 *
 * @param string $iss 发行人
 * @param string|null $sub 主题
 * @param string $aud 受众
 * @param int $iat 签发时间
 * @param int $exp 过期时间
 * @param string $jti JPT标识符
 * @param string $alg 算法类型
 * @param int $nbf 生效时间
 * @param string $payload 载荷数据
 * @param array $crown Crown数据集
 * @param array $petal Petal数据集
 */
readonly class JptPayload
{
    public string $typ;

    /**
     * @param string $iss 发行人
     * @param string|null $sub 主题
     * @param string $aud 受众
     * @param int $iat 签发时间
     * @param int $exp 过期时间
     * @param string $jti JPT标识符
     * @param string $alg 算法类型
     * @param int $nbf 生效时间
     * @param string $payload 载荷数据
     * @param array $crown Crown数据集
     * @param array $petal Petal数据集
     */
    public function __construct(
        public string  $iss,
        public ?string $sub,
        public string  $aud,
        public int     $iat,
        public int     $exp,
        public string  $jti,
        public string  $alg,
        public int     $nbf,
        public string  $payload,
        public array   $crown,
        public array   $petal,
    )
    {
        $this->typ = "JPT";
    }

    /**
     * 获取 Crown 数据
     *
     * @param string|null $key 数据键名，如果为 null 则返回所有数据
     * @param mixed $default 默认值，当指定键名不存在时返回此值
     * @return mixed 返回指定键名对应的值或所有数据
     */
    public function getCrownData(?string $key, mixed $default): mixed
    {
        // 如果指定了键名，则返回对应的数据或默认值
        if ($key !== null) {
            return $this->crown[$key] ?? $default;
        }
        // 如果未指定键名，则返回所有数据
        return $this->crown;
    }

    /**
     * 获取 Petal 数据
     *
     * @param string|null $key 数据键名，如果为null则返回所有数据
     * @param mixed $default 默认值，当指定键名不存在时返回此值
     * @return mixed 返回指定键名对应的值或所有数据
     */
    public function getPetalData(?string $key, mixed $default): mixed
    {
        // 如果指定了键名，则返回对应的数据或默认值
        if ($key !== null) {
            return $this->petal[$key] ?? $default;
        }
        // 未指定键名时返回所有 Petal 数据
        return $this->petal;
    }


    /**
     * 获取过期时间
     *
     * @return int 返回剩余的过期时间（秒），如果已过期则返回0
     */
    public function getExpiration(): int
    {
        return max(0, $this->exp - time());
    }
}