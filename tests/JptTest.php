<?php

use Petalbranch\Jpt\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Petalbranch\Jpt\Jpt;
use Petalbranch\Jpt\JptPayload;
use Petalbranch\Jpt\Exception\TokenValidationException;

class JptTest extends TestCase
{
    private array $config = [
        'secret' => 'top-secret-key-1234567890123456',
        'iss' => 'test-issuer',
        'aud' => 'test-app',
        'allowed_issuers' => ['test-issuer'],
        'allowed_audiences' => ['test-app']
    ];


    private Jpt $jpt;
    private JptPayload $payload;

    protected function setUp(): void
    {
        parent::setUp();

        // 初始化 Jpt 实例
        $this->jpt = new Jpt([
            'secret' => 'test-secret-key-for-unit-testing-123',
            'iss' => 'test-issuer',
            'aud' => 'test-audience',
            'alg' => 'HS256',
        ]);

        // 构造复杂的嵌套数据结构用于测试
        $this->jpt->setCrownData([
            'user' => [
                'id' => 1001,
                'name' => 'Alice',
                'profile' => [
                    'bio' => 'Developer',
                    'tags' => ['admin', 'vip', 'beta'],
                    'stats' => [
                        'logins' => 50,
                        'posts' => 12
                    ]
                ],
                // 特殊键名：包含点号，用于测试多参数模式
                'config.local' => 'dev-mode',
                'config.prod' => 'prod-mode'
            ],
            'meta' => [
                'version' => '1.0'
            ]
        ]);

        $this->jpt->setPetalData([
            'contact' => [
                'email' => 'alice@example.com',
                'phone' => '13800138000',
                'address' => [
                    'city' => 'Beijing',
                    'zip' => '100000'
                ]
            ],
            'secret_token' => 'xyz-987'
        ]);

        // 生成并立即获取 Payload 对象 (模拟 validate 后的结果)
        // 注意：这里使用 toJptPayload() 是为了直接测试数据结构，
        // 实际场景中通常是通过 $jpt->validate($token) 获取

        $this->payload = $this->jpt->toJptPayload();
    }


    /**
     * 测试最基本的生成和验证流程
     * @throws Exception
     */
    public function testGenerateAndValidateSuccess()
    {
        $jpt = new Jpt($this->config);
        $data = ['user_id' => 1001, 'role' => 'admin'];

        $token = $jpt->setPetalData($data)->withCrown('ext', 'extra-info')->generate();

        $payload = $jpt->validate($token);

        $this->assertEquals('test-issuer', $payload->iss);
        $this->assertEquals($data, array_intersect_key($payload->petal, $data));
        $this->assertEquals('extra-info', $payload->getCrownData('ext', null));
    }

    /**
     * 测试签名被篡改的情况
     * @throws Exception
     */
    public function testInvalidSignature()
    {
        $jpt = new Jpt($this->config);
        $token = $jpt->generate();

        // 恶意篡改签名的最后一个字符
        $temperedToken = $token . 'f';

        $this->expectException(TokenValidationException::class);
        $this->expectExceptionCode(401005); // 对应你代码里的签名验证失败
        $jpt->validate($temperedToken);
    }

    /**
     * 测试过期处理
     * @throws Exception
     */
    public function testTokenExpiration()
    {
        $config = $this->config;
        $config['ttl'] = 0; // 立即过期

        $jpt = new Jpt($config);
        $token = $jpt->generate();

        sleep(1); // 确保时间推进

        $this->expectException(TokenValidationException::class);
        $this->expectExceptionCode(401012); // 令牌已过期
        $jpt->validate($token);
    }


    /**
     * 模拟攻击：篡改 Crown 明文，尝试骗过校验
     * @throws Exception
     */
    public function testTamperCrownData()
    {
        $jpt = new Jpt($this->config);
        $token = $jpt->generate();

        [$crownUrl, $petalUrl, $thorn] = explode('.', $token);

        // 1. 解码 Crown
        $crown = Utils::b64UrlDecode($crownUrl);
        // 2. 篡改关键数据，比如把过期时间延长 1 小时
        $crown['exp'] += 3600;
        // 3. 重新编码回去
        $tamperedCrownUrl = Utils::b64UrlEncode($crown);

        // 4. 重新拼接（注意：此时签名 thorn 一定会匹配失败，
        // 我们假设攻击者甚至尝试伪造了签名，但由于摘要 digest 存在，他无法通过校验）
        $tamperedToken = "$tamperedCrownUrl.$petalUrl.$thorn";

        $this->expectException(TokenValidationException::class);
        // 理论上会先触发签名失败，如果绕过签名，则应触发 401006 摘要不匹配
        $jpt->validate($tamperedToken);
    }


    /**
     * 测试签发人不在白名单的情况
     * @throws Exception
     */
    public function testIssuerNotAllowed()
    {
        // 配置里只允许 'trusted-server'
        $config = $this->config;
        $config['allowed_issuers'] = ['trusted-server'];
        $jpt = new Jpt($config);

        // 生成一个由 'evil-server' 签发的 token
        $jptForEvil = new Jpt(array_merge($config, ['iss' => 'evil-server']));
        $token = $jptForEvil->generate();

        $this->expectException(TokenValidationException::class);
        $this->expectExceptionCode(401007); // 令牌签发人验证失败
        $jpt->validate($token);
    }

    /**
     * 测试通配符 * 是否能正常工作
     * @throws Exception
     */
    public function testWildcardAllowed()
    {
        $config = $this->config;
        $config['allowed_issuers'] = ['*']; // 允许所有
        $jpt = new Jpt($config);

        $jptAny = new Jpt(array_merge($config, ['iss' => 'any-issuer']));
        $token = $jptAny->generate();

        $payload = $jpt->validate($token);
        $this->assertEquals('any-issuer', $payload->iss);
    }

    /**
     * 测试配置标准化及链式设置
     */
    public function testOptionsNormalizationAndChaining()
    {
        $jpt = new Jpt();
        $jpt->setOptIss('new-iss')
            ->setOptTtl(7200)
            ->withPetal('user_id', 123);

        $this->assertEquals('new-iss', $jpt->getOption('iss'));
        $this->assertEquals(7200, $jpt->getOption('ttl'));

        // 验证 Petal 数据是否成功存入
        $this->assertEquals(123, $jpt->getPetalData('user_id', null));
    }

    /**
     * 测试禁止修改的 Crown 字段
     * @throws Exception
     */
    public function testForbidCrownProtection()
    {
        $jpt = new Jpt();
        // 尝试修改核心字段 iat
        $jpt->withCrown('iat', 123456789);
        $jpt->setOptAllowedIssuers(['*']); // 防止签发错误
        $jpt->setOptAllowedAudiences(['*']); // 防止受众错误

        // 即使设置了，在 generate 时也会被系统时间覆盖
        $token = $jpt->generate();
        $payload = $jpt->validate($token);

        $this->assertNotEquals(123456789, $payload->iat);
    }

    /**
     * 非法格式
     * @throws JsonException
     */
    #[DataProvider('invalidTokenProvider')]
    public function testInvalidTokenFormats($token = "")
    {
        $jpt = new Jpt($this->config);
        $this->expectException(TokenValidationException::class);
        $this->expectExceptionCode(401001); // 格式错误
        $jpt->validate($token);
    }

    public static function invalidTokenProvider(): array
    {
        return [
            ['just-a-string'],
            ['part1.part2'],
            ['part1.part2.part3.part4']
        ];
    }


    /**
     * 测试场景 1：基本功能与自动生成
     * 目标：验证在不手动调用 generate() 的情况下，toJptPayload() 是否会自动执行生成逻辑
     */
    public function testAutoGenerate()
    {
        $jpt = new Jpt($this->config);

        // 直接调用，不先调用 generate()
        $payloadObj = $jpt->toJptPayload();

        // 断言：返回的必须是 JptPayload 实例
        $this->assertInstanceOf(JptPayload::class, $payloadObj);

        // 断言：内部必须包含生成的 Token 字符串
        $this->assertNotEmpty($payloadObj->raw);
        $this->assertIsString($payloadObj->raw);

        // 断言：jti 必须存在
        $this->assertNotEmpty($payloadObj->jti);
        $this->assertStringStartsWith('jpt.', $payloadObj->jti);
    }

    /**
     * 测试场景 2：数据一致性 (Token vs Object)
     * 目标：验证 generate() 返回的字符串与 toJptPayload() 返回的对象是“同一份数据”
     */
    public function testConsistencyBetweenGenerateAndPayload()
    {
        $jpt = new Jpt(['secret' => $this->config['secret']]);

        // 1. 手动生成 Token
        $tokenString = $jpt->generate();

        // 2. 获取对象
        $payloadObj = $jpt->toJptPayload();

        // 核心断言：对象里的 payload 字段必须等于 generate 返回的字符串
        $this->assertEquals($tokenString, $payloadObj->raw);

        // 验证：解析 Token 字符串中的 Crown (Base64部分) 来对比 JTI
        // 这里手动拆解一下 Token 来验证，确保万无一失
        $parts = explode('.', $tokenString);
        $crownJson = base64_decode(strtr($parts[0], '-_', '+/'));
        $crownArr = json_decode($crownJson, true);

        // 断言：加密字符串里的 jti 必须等于 对象里的 jti
        $this->assertEquals($crownArr['jti'], $payloadObj->jti);

        // 断言：加密字符串里的 exp 必须等于 对象里的 exp
        $this->assertEquals($crownArr['exp'], $payloadObj->exp);
    }

    /**
     * 测试场景 3：配置项反映
     * 目标：验证配置 (TTL, ISS, SUB) 是否正确反映在 Payload 对象中
     */
    public function testConfigurationReflection()
    {
        $ttl = 7200;
        $iss = 'my-auth-server';
        $sub = 'user_888';

        $jpt = new Jpt([
            'secret' => $this->config['secret'],
            'ttl' => $ttl,
            'iss' => $iss,
            'sub' => $sub
        ]);

        $obj = $jpt->toJptPayload();

        // 验证 ISS 和 SUB
        $this->assertEquals($iss, $obj->iss);
        $this->assertEquals($sub, $obj->sub);

        // 验证时间计算 (exp = iat + ttl)
        // 允许 1-2 秒的执行误差，但理论上是一致的
        $this->assertEquals($obj->iat + $ttl, $obj->exp);

        // 验证剩余有效期方法
        $this->assertGreaterThan(0, $obj->getExpiration());
        $this->assertLessThanOrEqual($ttl, $obj->getExpiration());
    }

    /**
     * 测试场景 4：闭环验证 (Validate vs toJptPayload)
     * 目标：验证“刚生成的对象”和“解密出来的对象”数据是否完全一致
     */
    public function testLoopValidation()
    {
        // 允许 iss 验证
        $jpt = new Jpt([
            'secret' => $this->config['secret'],
            'allowed_issuers' => ['nameless'], // 默认 iss
            'allowed_audiences' => ['nameless'] // 默认 aud
        ]);

        // 添加自定义数据
        $jpt->withPetal('role', 'admin');
        $jpt->withCrown('custom_tag', 'api_v1');

        // 生成并获取对象
        $originalPayloadObj = $jpt->toJptPayload();
        $token = $originalPayloadObj->raw;

        // 立即解密验证
        $validatedPayloadObj = $jpt->validate($token);

        // 对比两个对象的核心属性
        $this->assertEquals($originalPayloadObj->jti, $validatedPayloadObj->jti);
        $this->assertEquals($originalPayloadObj->iat, $validatedPayloadObj->iat);
        $this->assertEquals($originalPayloadObj->exp, $validatedPayloadObj->exp);

        // 对比自定义数据
        $this->assertEquals(
            $originalPayloadObj->getPetalData('role', null),
            $validatedPayloadObj->getPetalData('role', null)
        );
        $this->assertEquals(
            $originalPayloadObj->getCrownData('custom_tag', null),
            $validatedPayloadObj->getCrownData('custom_tag', null)
        );
    }


    /**
     * 测试：点号分隔模式 - 基础层级访问
     */
    public function testInvokeDotNotationBasic(): void
    {
        $payload = $this->payload;
        // 访问 Crown
        $this->assertSame('Alice', $payload('c.user.name'));
        $this->assertSame(1001, $payload('c.user.id'));

        // 访问 Petal
        $this->assertSame('alice@example.com', $payload('p.contact.email'));
        $this->assertSame('xyz-987', $payload('p.secret_token'));
    }

    /**
     * 测试：点号分隔模式 - 深层嵌套访问
     */
    public function testInvokeDotNotationDeepNested(): void
    {
        $payload = $this->payload;
        $this->assertSame('Developer', $payload('c.user.profile.bio'));
        $this->assertSame('Beijing', $payload('p.contact.address.city'));
        $this->assertSame(50, $payload('c.user.profile.stats.logins'));
    }

    /**
     * 测试：点号分隔模式 - 数组索引访问
     */
    public function testInvokeDotNotationArrayIndex(): void
    {
        $payload = $this->payload;
        $this->assertSame('admin', $payload('c.user.profile.tags.0'));
        $this->assertSame('vip', $payload('c.user.profile.tags.1'));
        $this->assertSame('beta', $payload('c.user.profile.tags.2'));
    }

    /**
     * 测试：多参数模式 - 处理含特殊字符（点号）的键名
     * 这是多参数模式的核心用途
     */
    public function testInvokeMultiParamSpecialKeys(): void
    {
        $payload = $this->payload;

        // 场景：键名本身包含点号 "config.local"
        // 如果使用点号字符串 'c.user.config.local'，会被错误解析为 user->config->local
        // 使用多参数模式可以正确识别为 user -> "config.local"

        $this->assertSame('dev-mode', $payload('c', 'user', 'config.local'));
        $this->assertSame('prod-mode', $payload('c', 'user', 'config.prod'));

        // 混合模式：前部分是字符串，最后部分是特殊键 此处应该返回null
        $this->assertNull($payload('c.user', 'config.local'));
    }

    /**
     * 测试：根节点别名支持 (c/crown, p/petal)
     */
    public function testInvokeRootNodeAliases(): void
    {
        $payload = $this->payload;

        // 简写
        $this->assertSame('Alice', $payload('c.user.name'));
        $this->assertSame('alice@example.com', $payload('p.contact.email'));

        // 全称
        $this->assertSame('Alice', $payload('crown.user.name'));
        $this->assertSame('alice@example.com', $payload('petal.contact.email'));
    }

    /**
     * 测试：无效路径返回 null
     */
    public function testInvokeInvalidPathReturnsNull(): void
    {
        $payload = $this->payload;

        // 键不存在
        $this->assertNull($payload('c.user.nonexistent'));
        $this->assertNull($payload('p.contact.fake'));

        // 路径中断 (中间层级不是数组)
        // 'name' 是字符串，不能再访问 'length'
        $this->assertNull($payload('c.user.name.length'));

        // 根节点错误
        $this->assertNull($payload('x.user.name'));
        $this->assertNull($payload('invalid.key'));
    }

    /**
     * 测试：与空合并运算符 (??) 的配合
     */
    public function testInvokeWithNullCoalescingOperator(): void
    {
        $payload = $this->payload;

        $existing = $payload('c.user.name') ?? 'Guest';
        $missing = $payload('c.user.nickname') ?? 'Guest';
        $deepMissing = $payload('c.user.profile.avatar.url') ?? 'default.png';

        $this->assertSame('Alice', $existing);
        $this->assertSame('Guest', $missing);
        $this->assertSame('default.png', $deepMissing);
    }

    /**
     * 测试：动态变量拼接
     */
    public function testInvokeWithDynamicVariables(): void
    {
        $payload = $this->payload;

        $field = 'name';
        $index = 1;

        $this->assertSame('Alice', $payload("c.user.$field"));
        $this->assertSame('vip', $payload("c.user.profile.tags.$index"));

        // 动态根节点
        $type = 'contact';
        $key = 'phone';
        $this->assertSame('13800138000', $payload("p.$type.$key"));
    }

    /**
     * 测试：类型安全性 - 当中间节点不是数组时
     */
    public function testInvokeTypeSafety(): void
    {
        $payload = $this->payload;

        // 'c.user.id' 是整数 1001
        // 尝试继续访问 1001->something 应该返回 null 而不是报错
        $result = $payload('c.user.id.something');
        $this->assertNull($result);
    }


}