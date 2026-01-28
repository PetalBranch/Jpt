<?php

namespace Petalbranch\Jpt;

use Exception;
use InvalidArgumentException;
use JsonException;
use Petalbranch\Jpt\Exception\TokenValidationException;
use Petalbranch\PetalCipher\PetalCipher;

class Jpt
{
    /** @var array 配置选项数组 */
    private array $options;

    /** @var array|string[] 配置选项白名单 */
    private array $allowed_options = [
        'iss' => true,
        'aud' => true,
        'ttl' => true,
        'alg' => true,
        'secret' => true,
        'leeway' => true,
        'allowed_issuers' => true,
        'allowed_audiences' => true
    ];

    private PetalCipher $pc;

    /** @var array|string[] 定义支持的算法类型及其对应的哈希函数 */
    private array $allowed_alg = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512'
    ];

    private array $crown = [
        'iss' => null,
        'sub' => null,
        'aud' => null,
        'nbf' => null, // 生效时间 默认为签发时间，可以通过setNbf修改
        'iat' => null, // 签发时间 仅通过Generate生成
        'exp' => null,
        'jti' => null,
    ];

    /** @var array 禁止修改 crown 列表 */
    private array $forbid_crown = [
        'iss' => true, 'sub' => true, 'aud' => true,
        'nbf' => true, 'iat' => true, 'exp' => true,
        'jti' => true
    ];

    private array $petal = [
        'digest' => null,
    ];


    /**
     * 构造函数，用于初始化JPT处理类
     *
     * @param array $options 配置选项数组，可包含以下键值：<br>
     *                       - iss 签发人，默认为 'nameless'<br>
     *                       - aud 受众，默认为 'nameless'<br>
     *                       - ttl 生命周期（秒），默认为 '3600'<br>
     *                       - alg 加密算法，默认为'HS256'，可选（HS256、HS384、HS512）<br>
     *                       - secret 密钥，默认为空字符串<br>
     *                       - leeway 允许的误差时间<br>
     *                       - allowed_issuers 允许的发行者列表<br>
     *                       - allowed_audiences 受众白名单<br>
     */
    public function __construct(array $options = [])
    {
        // 设置默认配置选项
        $default = [
            'iss' => 'nameless',                // 签发人
            'aud' => 'nameless',                // 受众
            'ttl' => 3600,                      // 生命周期
            'alg' => 'HS256',                   // 加密算法
            'secret' => '',                     // 密钥
            'leeway' => 0,                      // 允许的误差时间
            'allowed_issuers' => [],            // 签发人白名单
            'allowed_audiences' => [],          // 受众白名单
        ];

        // 标准化键名并过滤
        $options = $this->normalizeOptions($options);

        // 合并默认配置与传入配置
        $this->options = array_merge($default, $options);

        // 初始化加密器实例
        $this->pc = new PetalCipher($this->options['secret']);
    }



    /**********
     * # Getter
     */


    /**
     * 获取指定键名的选项值
     *
     * @param string $key 选项的键名
     * @return mixed 返回与给定键名相关联的值，如果键不存在则返回 null
     */
    public function getOption(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }

    /**
     * 获取选项数据
     *
     * @return array 返回所有选项数据
     */
    public function getOptions(): array
    {
        return $this->options;
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



    /**********
     * # Setter（覆盖）
     */


    /**
     * 设置允许的选项键值对
     *
     * @param string $key 要设置的选项键名
     * @param mixed $value 要设置的选项值
     * @return Jpt 如果键名存在于允许的选项列表中，则设置成功后返回当前对象实例，支持链式调用；否则直接返回当前对象实例
     */
    public function setOption(string $key, mixed $value): self
    {
        if (!array_key_exists($key, $this->allowed_options)) return $this;
        $this->options[$key] = $value;

        // 更新密钥种子
        if ($key == 'secret') $this->pc->updateSeed($this->options['secret']);

        return $this;
    }


    /**
     * 设置或更新选项
     *
     * @param array $options 要设置或更新的选项数组，可包含以下键值：<br>
     *                        - iss 签发人，默认为 'nameless'<br>
     *                        - aud 接收人，默认为 '*'<br>
     *                        - ttl 生命周期（秒），默认为 '3600'<br>
     *                        - alg 加密算法，默认为'HS256'，可选（HS256、HS384、HS512）<br>
     *                        - secret 密钥，默认为空字符串<br>
     *                        - leeway 允许的误差时间<br>
     *                        - allowed_issuers 允许的发行者列表<br>
     *                        - allowed_audiences 受众白名单<br>
     * @return Jpt 返回当前对象实例，支持链式调用
     */
    public function setOptions(array $options): self
    {
        // 标准化键名并过滤
        $options = $this->normalizeOptions($options);

        // 将传入的选项与现有选项进行合并
        $this->options = array_merge($this->options, $options);
        // 更新密钥种子
        $this->pc->updateSeed($this->options['secret']);
        return $this;
    }


    /**
     * 设置选项中的 iss 值<br>
     * 发行人：标识‌谁签发了‌JPT<br>
     * 示例：https://auth.example.com
     *
     * @param string $iss 要设置的 iss 值
     * @return Jpt 返回当前对象实例，支持链式调用
     */
    public function setOptIss(string $iss): self
    {
        $this->options['iss'] = $iss;
        return $this;
    }


    /**
     * 设置选项中的 sub 值<br>
     * 主题：标识JPT代表谁<br>
     * 示例：user123
     *
     * @param string $sub subject 主题; 标识该 JPT 所面向的用户或实体 `示例：uid_123456`
     * @return Jpt 返回当前对象实例，支持链式调用
     */
    public function setOptSub(string $sub): self
    {
        $this->options['sub'] = $sub;
        return $this;
    }


    /**
     * 设置选项中的 'aud' 值<br>
     * 受众：标识令牌的接收方<br>
     * 示例：payment-service
     *
     * @param string $aud 要设置的 'aud' 值
     * @return Jpt 返回当前对象实例，支持链式调用
     */
    public function setOptAud(string $aud): self
    {
        $this->options['aud'] = $aud;
        return $this;
    }


    /**
     * 设置选项的生存时间（TTL）
     *
     * @param int $ttl 生存时间，单位为秒。如果设置为小于或等于0，则不修改当前TTL
     * @return Jpt 返回当前对象实例，以支持链式调用
     */
    public function setOptTtl(int $ttl): self
    {
        if ($ttl <= 0) return $this;
        $this->options['ttl'] = $ttl;
        return $this;
    }


    /**
     * 设置选项中的宽容度（leeway）
     *
     * @param int $leeway 宽容度值，用于调整时间验证的宽容范围
     * @return Jpt 返回当前对象实例，支持链式调用
     */
    public function setOptLeeway(int $leeway): self
    {
        $this->options['leeway'] = $leeway;
        return $this;
    }


    /**
     * 设置 JPT 的 nbf (not before) 声明
     *
     * @param int|null $nbf 时间戳，表示 JPT 在此时间之前不可用。如果为 null，则移除 nbf 选项。
     * @return Jpt 返回当前对象实例，支持链式调用
     */
    public function SetOptNbf(?int $nbf): self
    {
        if (is_null($nbf)) {
            unset($this->options['nbf']);
            return $this;
        }

        if ($nbf < time()) return $this;
        $this->options['nbf'] = $nbf;
        return $this;
    }


    /**
     * 设置允许的发行者
     *
     * @param array $allowed_issuers 允许的发行者列表
     * @return Jpt 返回当前对象实例，以支持链式调用
     */
    public function setOptAllowedIssuers(array $allowed_issuers): self
    {
        $this->options['allowed_issuers'] = $allowed_issuers;
        return $this;
    }


    /**
     * 设置允许的受众
     *
     * @param array $allowed_audiences 允许的受众列表
     * @return Jpt 返回当前对象实例，用于链式调用
     */
    public function setOptAllowedAudiences(array $allowed_audiences): self
    {
        $this->options['allowed_audiences'] = $allowed_audiences;
        return $this;
    }


    /**
     * 设置 Crown 数据
     *
     * @param array $crown_data 需要设置的 Crown 数据数组
     * @return Jpt 返回当前对象实例，允许链式调用
     */
    public function setCrownData(array $crown_data): self
    {
        // 仅保留允许自定义的 crown
        $crown_data = array_diff_key($crown_data, $this->forbid_crown);
        // 合并保留默认结构的 null 值
        $this->crown = array_merge($this->crown, $crown_data);
        return $this;
    }


    /**
     * 设置 Petal 数据
     *
     * @param array $petal_data 要设置的Petal数据数组
     * @return Jpt 返回当前对象实例，支持链式调用
     */
    public function setPetalData(array $petal_data): self
    {
        unset($petal_data['digest']); // 强制移除 digest
        $this->petal = $petal_data;
        return $this;
    }





    /**********
     * # 链式添加
     */


    /**
     * 为 Crown 添加键值对
     *
     * @param string $key 要添加的键名
     * @param mixed $value 键对应的值
     * @return Jpt 返回当前对象实例，允许链式调用
     */
    public function withCrown(string $key, mixed $value): self
    {
        if (array_key_exists($key, $this->forbid_crown)) return $this;
        $this->crown[$key] = $value;
        return $this;
    }


    /**
     * 为 Petal 添加键值对
     *
     * @param string $key 要添加的键名
     * @param mixed $value 键对应的值
     * @return Jpt 返回当前对象实例，允许链式调用
     */
    public function withPetal(string $key, mixed $value): self
    {
        if ($key == 'digest') return $this;
        $this->petal[$key] = $value;
        return $this;
    }


    /**********
     * # 链式移除
     */


    /**
     * 从 crown 中移除指定键名的数据
     *
     * @param string $key 要移除的键名
     * @return Jpt 返回当前对象实例
     */
    public function withoutCrown(string $key): self
    {
        // 禁止移除核心保留字段，防止破坏结构
        if (array_key_exists($key, $this->forbid_crown)) return $this;
        unset($this->crown[$key]);
        return $this;
    }


    /**
     * 从 Petal 数据中移除指定键名的数据
     *
     * @param string $key 要移除的数据的键名
     * @return Jpt 返回当前对象实例，以支持链式调用
     */
    public function withoutPetal(string $key): self
    {
        unset($this->petal[$key]);
        return $this;
    }






    /**********
     * # 令牌生成
     */


    /**
     * 生成令牌字符串
     *
     * @return string 返回生成的令牌字符串，格式为 crown.petal.thorn
     * @throws Exception 当指定的加密算法不被支持时抛出异常
     */
    public function generate(): string
    {
        // 获取并验证加密算法
        $alg = $this->options['alg'];
        if (!isset($this->allowed_alg[$alg])) throw new Exception("不支持的加密算法");
        $alg = $this->allowed_alg[$alg];

        // 构建公开数据部分（crown）
        $crown = $this->crown;
        $crown['alg'] = $this->options['alg'];
        $crown['iss'] = $this->options['iss'];
        if (!empty($this->options['sub'])) $crown['sub'] = $this->options['sub'];
        $crown['aud'] = $this->options['aud'];
        $crown['iat'] = time();
        $crown['nbf'] = $this->options['nbf'] ?? $crown['iat']; // 默认生效时间为签发时间
        $crown['exp'] = $crown['iat'] + $this->options['ttl'];

        try {
            $crown['jti'] = "jpt." . bin2hex(random_bytes(16));
        } catch (Exception) {
            $crown['jti'] = "jpt." . md5(uniqid('jpt.', true)); // 降级方案
        }

        $crown['typ'] = "JPT";
        // 过滤 null
        $crown = array_filter($crown, fn($v) => !is_null($v));

        // 构建私有数据部分（petal），并加入摘要信息
        $petal = $this->petal;
        $petal['digest'] = hash('sha256', json_encode($crown, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $petal = array_filter($petal, fn($v) => !is_null($v));

        // 编码和签名
        $crown_url = Utils::b64UrlEncode($crown);
        $petal_url = Utils::cipherUrlEncode($petal, $this->pc);
        $signature = hash_hmac($alg, "$crown_url.$petal_url", $this->options['secret']);

        // 拼接令牌
        return "$crown_url.$petal_url.$signature";
    }




    /**********
     * # 令牌验证
     */


    /**
     * 验证令牌的有效性
     *
     * @param string $token 待验证的令牌字符串，格式为 crown.petal.thorn
     * @return JptPayload 返回一个包含令牌解析后数据的对象 JptPayload
     * @throws TokenValidationException 当令牌验证失败时抛出此异常，包括但不限于令牌格式错误、无效的数据、不支持的加密算法、签名验证失败等
     * @throws JsonException
     */
    public function validate(string $token): JptPayload
    {
        // 验证令牌格式：必须由三部分组成 (crown.petal.thorn)
        $tokens = explode('.', $token);
        if (count($tokens) != 3) throw new TokenValidationException("令牌格式错误", 401001);

        // 解构数组，增加可读性
        [$crownUrl, $petalUrl, $thorn] = $tokens;

        try {
            $crown = Utils::b64UrlDecode($crownUrl);
        } catch (JsonException $e) {
            throw new TokenValidationException("无法解析数据", 401013, $e);
        }

        if (!isset($crown['alg'])) throw new TokenValidationException("无效的 crown 数据", 401002);

        $alg = $crown['alg'];
        if (!isset($this->allowed_alg[$alg])) throw new TokenValidationException("不支持的加密算法", 401004);
        $alg = $this->allowed_alg[$alg];

        // 优先校验签名
        $calculatedSignature = hash_hmac($alg, "$crownUrl.$petalUrl", $this->options['secret']);
        if (!hash_equals($calculatedSignature, $thorn)) throw new TokenValidationException("令牌签名验证失败", 401005);


        // 解密 Petal
        try {
            $petal = Utils::cipherUrlDecode($petalUrl, $this->pc);
        } catch (JsonException $e) {
            throw new TokenValidationException("Petal 数据解析失败", 401013, $e);
        }
        if (!isset($petal['digest'])) throw new TokenValidationException("无效的 petal 数据", 401003);

        // 校验摘要
        $crown_recalculated_digest = hash('sha256', json_encode($crown, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (!hash_equals($petal['digest'], $crown_recalculated_digest)) {
            throw new TokenValidationException("令牌数据验证失败(摘要不匹配)", 401006);
        }

        // iss 签发人验证：检查签发人是否在允许列表中
        if (!in_array("*", $this->getOption('allowed_issuers'))) {
            if (!in_array($crown['iss'], $this->getOption('allowed_issuers'))) {
                throw new TokenValidationException("令牌签发人验证失败", 401007);
            }
        }

        //  aud 接收人验证：检查接收人是否在允许列表中
        if (!in_array("*", $this->getOption('allowed_audiences'))) {
            if (!in_array($crown['aud'], $this->getOption('allowed_audiences'))) {
                throw new TokenValidationException("令牌接收人验证失败", 401008);
            }
        }

        // 时间验证
        $current_time = time();

        // nbf 生效时间验证：当前时间需大于等于生效时间减去容差时间
        if (isset($crown['nbf']) && is_numeric($crown['nbf'])) {
            if ($current_time + $this->options['leeway'] < $crown['nbf']) {
                throw new TokenValidationException("令牌尚未生效", 401010);
            }
        }

        // exp 过期时间验证：当前时间需小于过期时间加上容差时间
        if (isset($crown['exp']) && is_numeric($crown['exp'])) {
            if ($current_time >= $crown['exp'] + $this->options['leeway']) {
                throw new TokenValidationException("令牌已过期", 401012);
            }
        }

        // 构建JptPayload
        return new JptPayload(
            iss: $crown['iss'],
            sub: $crown['sub'] ?? null,
            aud: $crown['aud'],
            iat: (int)$crown['iat'] ?? 0,
            exp: (int)$crown['exp'] ?? 0,
            jti: $crown['jti'] ?? '',
            alg: $crown['alg'],
            nbf: (int)($crown['nbf'] ?? ($crown['iat'] ?? 0)),
            payload: $token,
            crown: $crown,
            petal: $petal
        );
    }




    /**********
     * # 工具/辅助方法
     */


    /**
     * 标准化选项数组，包括转换键名大小写、过滤白名单键、校验并设置默认ttl值、校验算法alg以及将allowed_issuers和allowed_audiences标准化为数组
     *
     * @param array $options 需要被标准化的选项数组
     * @return array 经过标准化处理后的选项数组
     */
    private function normalizeOptions(array $options): array
    {
        $options = array_change_key_case($options);
        $options = array_intersect_key($options, $this->allowed_options);

        // 校验并标准化 ttl
        $options['ttl'] = isset($options['ttl']) && is_numeric($options['ttl']) && $options['ttl'] >= 0
            ? (int)$options['ttl'] : 3600;


        // 校验 alg
        if (isset($options['alg'])) {
            $alg = $options['alg'];
            if (!array_key_exists($alg, $this->allowed_alg)) $options['alg'] = array_key_first($this->allowed_alg);
        }

        // 标准化 allowed_issuers / allowed_audiences 为数组
        foreach (['allowed_issuers', 'allowed_audiences'] as $key) {
            if (isset($options[$key]) && !is_array($options[$key])) {
                $options[$key] = [$options[$key]];
            }
        }
        return $options;
    }

}