<?php

namespace Petalbranch\Jpt;

use Exception;
use InvalidArgumentException;
use Petalbranch\Jpt\Exception\TokenValidationException;
use Petalbranch\PetalCipher\PetalCipher;

class Jpt
{
    private array $options;
    private PetalCipher $pc;

    const SUPPORT_ALG = [
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

    private array $petal = [
        'digest' => null,
    ];


    /**
     * 构造函数，用于初始化JWT处理类
     *
     * @param array $options 配置选项数组，可包含以下键值：<br>
     *                       - iss: 签发人，默认为'nameless'<br>
     *                       - aud: 接收人，默认为'*'<br>
     *                       - ttl: 生命周期，默认为3600秒<br>
     *                       - alg: 加密算法，默认为'HS256'<br>
     *                       - secret: 密钥，默认为空字符串<br>
     *                       - leeway: 允许的误差时间，默认为0<br>
     *                       - issuers: 签发人白名单，默认为'nameless'<br>
     */
    public function __construct(array $options = [])
    {
        // 设置默认配置选项
        $default = [
            'iss' => 'nameless',        // 签发人
            'aud' => '*',               // 接收人
            'ttl' => 3600,              // 生命周期
            'alg' => 'HS256',           // 加密算法
            'secret' => '',             // 密钥
            'leeway' => 0,              // 允许的误差时间
            'issuers' => 'nameless',    // 签发人白名单
        ];

        // 合并默认配置与传入配置
        $this->options = array_merge($default, $options);

        // 初始化加密器实例
        $this->pc = new PetalCipher($this->options['secret']);
    }


    /**
     * 设置选项值
     *
     * @param string $key 选项键名
     * @param mixed $value 选项值
     * @return object 返回当前对象实例，支持链式调用
     */
    public function setOption(string $key, mixed $value): object
    {
        $this->options[$key] = $value;
        return $this;
    }


    /**
     * 设置选项数组
     *
     * @param array $options 要合并到当前选项的数组
     * @return object 返回当前对象实例，支持链式调用
     */
    public function setOptions(array $options): object
    {
        // 将传入的选项与现有选项进行合并
        $this->options = array_merge($this->options, $options);
        return $this;
    }


    /**
     * 获取指定键的选项值
     *
     * @param string $key 选项键名
     * @return mixed 返回指定键对应的选项值
     */
    public function getOption(string $key = null): mixed
    {
        if ($key === null) return $this->options;
        return $this->options[$key];
    }

    /**
     * 获取剩余过期时间
     *
     * @return int 剩余过期时间（秒），如果已过期则返回0
     * @throws InvalidArgumentException 当未设置过期时间或过期时间不是数字时抛出异常
     */
    public function getExpiration(): int
    {
        // 检查是否设置了过期时间
        if (!isset($this->crown['exp'])) {
            throw new InvalidArgumentException('未设置过期时间');
        }

        $exp = $this->crown['exp'];

        // 验证过期时间是否为数字
        if (!is_numeric($exp)) {
            throw new InvalidArgumentException('过期时间必须是数字');
        }

        // 计算剩余时间，确保不小于0
        $remainingTime = (int)$exp - time();

        return max($remainingTime, 0);
    }


    /**
     * 验证
     *
     * @param string $token
     * @return object
     * @throws TokenValidationException
     */
    public function validate(string $token): object
    {
        // 验证令牌格式：必须由三部分组成 (crown.petal.thorn)
        $token = explode('.', $token);
        if (count($token) != 3) throw new TokenValidationException("令牌格式错误", 401001);

        $crown = Utils::b64UrlDecode($token[0]);
        $petal = Utils::cipherUrlDecode($token[1], $this->pc);
        $thorn = $token[2];

        if (!isset($crown['alg'])) {
            throw new TokenValidationException("无效的 crown 数据", 401002);
        }

        if (!isset($petal['digest'])) {
            throw new TokenValidationException("无效的 petal 数据", 401003);
        }

        $alg = $crown['alg'];
        if (!isset(self::SUPPORT_ALG[$alg])) throw new TokenValidationException("不支持的加密算法", 401004);
        $alg = self::SUPPORT_ALG[$alg];

        // 校验签名是否正确：使用指定算法和密钥重新计算签名并与令牌中的签名比较
        $crown = array_filter($crown);
        $petal = array_filter($petal);
        $signature = hash_hmac($alg, json_encode(['crown' => $crown, 'petal' => $petal]), $this->options['secret']);
        if (!hash_equals($signature, $thorn)) {
            throw new TokenValidationException("令牌签名验证失败", 401005);
        }

        // 校验 crown 和 petal 的摘要是否匹配：确保 crown 内容未被篡改
        $crown_digest = md5(json_encode($crown));
        if ($crown_digest != $petal['digest']) {
            throw new TokenValidationException("令牌数据验证失败", 401006);
        }

        // iss 签发人验证：检查签发人是否在允许列表中
        if ($this->getOption('issuers') !== '*' && $this->options['issuers'] !== null) {
            if (!in_array($crown['iss'], explode(',', $this->options['issuers']))) {
                throw new TokenValidationException("令牌签发人验证失败", 401007);
            }
        }

        //  aud 接收人验证：检查接收人是否在允许列表中
        if ($this->getOption('aud') !== '*' && $this->options['aud'] !== null) {
            if (!in_array($crown['aud'], explode(',', $this->options['aud']))) {
                throw new TokenValidationException("令牌接收人验证失败", 401008);
            }
        }

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

        // 合并 crown 和 petal 数据到实例属性中，供后续访问使用
        $this->crown = array_merge($this->crown, $crown);
        $this->petal = array_merge($this->petal, $petal);

        return $this;
    }


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
        if (!isset(self::SUPPORT_ALG[$alg])) throw new Exception("不支持的加密算法");
        $alg = self::SUPPORT_ALG[$alg];

        // 更新密钥种子
        $this->pc->updateSeed($this->options['secret']);

        // 构建公开数据部分（crown）
        $crown = $this->crown;
        $crown['alg'] = $this->options['alg'];
        $crown['iss'] = $this->options['iss'];
        $crown['sub'] = $this->options['sub'];
        $crown['aud'] = $this->options['aud'];
        $crown['nbf'] = $this->options['nbf'] ?? $crown['iat']; // 默认生效时间为签发时间
        $crown['iat'] = time();
        $crown['exp'] = $crown['iat'] + $this->options['ttl'];
        $crown['jti'] = md5(uniqid());
        $crown['typ'] = "JPT";
        $crown = array_filter($crown);

        // 构建私有数据部分（petal），并加入摘要信息
        $petal = $this->petal;
        $petal['digest'] = md5(json_encode($crown));
        $petal = array_filter($petal);

        // 计算签名
        $thorn = ['crown' => $crown, 'petal' => $petal];
        $signature = hash_hmac($alg, json_encode($thorn), $this->options['secret']);

        // 编码各部分内容
        $crown_url = Utils::b64UrlEncode($crown);
        $petal_url = Utils::cipherUrlEncode($petal, $this->pc);


        // 拼接最终令牌
        return "$crown_url.$petal_url.$signature";
    }


    /**
     * 设置 iss 选项值
     *
     * @param string $iss issuer 标识符
     * @return object 返回当前对象实例，支持链式调用
     */
    public function setOptIss(string $iss): object
    {
        $this->options['iss'] = $iss;
        return $this;
    }


    /**
     * 设置选项中的子项值
     *
     * @param string $sub 子项值
     * @return object 返回当前对象实例，支持链式调用
     */
    public function setOptSub(string $sub): object
    {
        $this->options['sub'] = $sub;
        return $this;
    }


    /**
     * 设置令牌的不可用时间（Not Before）
     *
     * @param int $nbf 不可用时间的时间戳
     * @return object 返回当前对象以支持链式调用
     */
    public function SetOptNbf(int $nbf): object
    {
        $this->options['nbf'] = $nbf;
        return $this;
    }


    /**
     * 设置受众选项
     *
     * @param array $aud 受众数组
     * @return object 返回当前对象实例，支持链式调用
     */
    public function setOptAud(array $aud): object
    {
        $this->options['aud'] = trim(implode(',', $aud), ',');
        return $this;
    }

    /**
     * 设置可选的发行方列表
     *
     * @param array $issuers 发行方数组
     * @return $this 返回当前对象实例，支持链式调用
     */
    public function setOptIssuers(array $issuers)
    {
        // 将发行方数组转换为逗号分隔的字符串，并去除首尾逗号
        $this->options['issuers'] = trim(implode(',', $issuers), ',');
        return $this;
    }


    /**
     * 设置 Crown 数据
     *
     * 将传入的数据与现有的 crown 数据进行合并更新
     *
     * @param array $data 需要合并到 crown 数据中的数组
     * @return object 返回当前对象实例，支持链式调用
     */
    public function setCrownData(array $data): object
    {
        // 将传入的数据与现有公开数据合并
        $this->crown = array_merge($this->crown, $data);
        return $this;
    }


    /**
     * 设置 Petal 数据
     *
     * @param array $data 要合并到 Petal 数据中的数组
     * @return object 返回当前对象实例，支持链式调用
     */
    public function setPetalData(array $data): object
    {
        $this->petal = array_merge($this->petal, $data);
        return $this;
    }



    /**
     * 获取 Crown 数据
     *
     * @param string|null $key 数据键名，如果为 null 则返回所有数据
     * @param mixed $default 默认值，当指定键名不存在时返回此值
     * @return mixed 返回指定键名对应的值或所有数据
     */
    public function getCrownData(string $key = null,mixed $default = null): mixed
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
    public function getPetalData(string $key = null,mixed $default = null): mixed
    {
        // 如果指定了键名，则返回对应的数据或默认值
        if ($key !== null) {
            return $this->petal[$key] ?? $default;
        }
        // 未指定键名时返回所有 Petal 数据
        return $this->petal;
    }



}