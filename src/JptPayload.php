<?php


namespace Petalbranch\Jpt;

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
 * @param string $raw 原始 Token 字符串
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
     * @param string $raw 原始 Token 字符串
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
        public string  $raw,
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


    /**
     * 魔术方法，使对象可以像函数一样调用
     * 支持通过路径访问嵌套的数组数据，使用点号分隔或可变参数
     *
     * @param string $path 起始路径（根节点标识符，如 'c' 表示 crown，'p' 表示 petal）
     * @param string ...$segments 可选的额外路径参数，将与 path 一起组成完整的路径数组
     * @return mixed 返回找到的值，如果路径不存在或无效则返回 null
     */
    public function __invoke(string $path, string ...$segments): mixed
    {
        // 构建路径键名数组
        $keys = empty($segments) ? explode('.', $path) : [$path, ...$segments];

        if (empty($keys)) return null;

        // 获取根节点键名并映射到实际属性名
        $rootKey = array_shift($keys);
        $map = ['c' => 'crown', 'p' => 'petal'];

        $propertyName = $map[$rootKey] ?? $rootKey;

        // 检查属性是否存在
        if (!property_exists($this, $propertyName)) return null;

        $current = $this->$propertyName;

        // 如果当前值不是数组且还有剩余路径，则无法继续访问
        if (!is_array($this->$propertyName)) return empty($keys) ? $current : null;


        // 遍历剩余的路径键名，逐层访问嵌套数组
        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) return null;
            $current = $current[$key];
        }

        return $current;
    }


}