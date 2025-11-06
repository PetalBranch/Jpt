<?php

namespace Petalbranch\Jpt\Utils;


use Petalbranch\PetalCipher\PetalCipher;

/**
 * PetalCipherUrl 类提供了基于PetalCipher的URL安全加密解密功能
 * 主要用于将数据加密后转换为URL友好的格式，以及将URL友好的加密数据解密回原始数据
 */
class PetalCipherUrl
{

    /**
     * 将字符串数据加密并转换为URL友好的格式
     *
     * @param string $data 需要加密的原始数据
     * @param PetalCipher $cipher 加密器实例
     * @return string 加密后经过URL安全转换的字符串
     */
    public static function encode(string $data, PetalCipher $cipher): string
    {
        return rtrim(strtr($cipher->encrypt($data), '+/', '-_'), '=');
    }


    /**
     * 将URL友好的加密数据解密为原始字符串
     *
     * @param string $data URL友好的加密数据
     * @param PetalCipher $cipher 解密器实例
     * @return string 解密后的原始数据
     */
    public static function decode(string $data, PetalCipher $cipher): string
    {
        // 将URL安全字符转换回base64字符
        $replaced = strtr($data, '-_', '+/');
        // 补充base64解码所需的填充字符
        $padding = strlen($replaced) % 4;
        if ($padding > 0) {
            $replaced .= str_repeat('=', 4 - $padding);
        }
        return $cipher->decrypt($replaced);
    }

    /**
     * 将数组数据JSON编码后加密并转换为URL友好的格式
     *
     * @param array $data 需要加密的数组数据
     * @param PetalCipher $cipher 加密器实例
     * @return string 加密后经过URL安全转换的字符串
     */
    public static function encodeJson(array $data, PetalCipher $cipher): string
    {
        return self::encode(json_encode($data, JSON_UNESCAPED_UNICODE), $cipher);
    }


    /**
     * 将URL友好的加密数据解密并JSON解码为数组
     *
     * @param string $data URL友好的加密数据
     * @param PetalCipher $cipher 解密器实例
     * @return array|null 解密并JSON解码后的数组，失败时返回null
     */
    public static function decodeJson(string $data, PetalCipher $cipher): ?array
    {
        $json = self::decode($data, $cipher);
        return json_decode($json, true);
    }
}

