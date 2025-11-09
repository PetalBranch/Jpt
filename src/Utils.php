<?php

namespace Petalbranch\Jpt;

use Petalbranch\PetalCipher\PetalCipher;

/**
 * 工具类
 */
class Utils
{

    /**
     * 对数组数据进行Base64 URL安全编码
     *
     * @param array $data 需要编码的数组数据
     * @return string 编码后的URL安全字符串
     */
    public static function b64UrlEncode(array $data): string
    {
        // 先将数组转换为JSON字符串，再进行Base64编码，最后替换字符确保URL安全
        return rtrim(strtr(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    }

    /**
     * 对Base64 URL安全编码的字符串进行解码
     *
     * @param string $data 需要解码的URL安全Base64字符串
     * @return array 解码后的数组数据
     */
    public static function b64UrlDecode(string $data): array
    {
        // 将URL安全字符替换回标准Base64字符
        $replaced = strtr($data, '-_', '+/');

        // 补充Base64编码所需的填充字符
        $padding = strlen($replaced) % 4;
        if ($padding > 0) $replaced .= str_repeat('=', 4 - $padding);

        // 进行Base64解码并转换为数组
        return json_decode(base64_decode($replaced), true);
    }

    /**
     * 对数据进行加密并URL安全编码
     *
     * @param array $data 需要加密的数据数组
     * @param PetalCipher $pc 加密器实例
     * @return string 加密后经过URL安全编码的字符串
     */
    public static function cipherUrlEncode(array $data, PetalCipher $pc): string
    {
        // 先将数组JSON编码，然后使用加密器加密，最后进行URL安全的base64编码替换
        return rtrim(strtr($pc->encrypt(json_encode($data, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    }

    /**
     * 对URL安全编码的加密数据进行解码和解密
     *
     * @param string $data 需要解密的URL安全编码字符串
     * @param PetalCipher $pc 解密器实例
     * @return array 解密后的原始数据
     */
    public static function cipherUrlDecode(string $data, PetalCipher $pc): array
    {
        // 将URL安全编码转换回标准base64编码
        $replaced = strtr($data, '-_', '+/');

        // 补齐base64编码所需的填充字符
        $padding = strlen($replaced) % 4;
        if ($padding > 0) $replaced .= str_repeat('=', 4 - $padding);

        // 解密数据并解析JSON格式
        return json_decode($pc->decrypt($replaced), true);
    }

}
