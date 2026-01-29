<?php

use Petalbranch\Jpt\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Petalbranch\Jpt\Jpt;
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
            ['part1.part2.part3.part4'],
            ['..'], // 三段但为空
        ];
    }




}