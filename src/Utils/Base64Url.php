<?php

namespace Petalbranch\Jpt\Utils;

/**
 * Base64URL 工具类
 *
 * 用于对数据进行 URL 安全的 Base64 编解码。
 * 与标准 Base64 不同：替换 +/ 为 -_，并去掉末尾的 "="。
 *
 * 常用于 JPT 的 Crown（公开区）和 Thorn（签名区）。
 */
class Base64Url
{
    /**
     * 编码为 Base64URL
     *
     * @param string $data 原始字符串
     * @return string Base64URL 编码后的字符串
     */
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * 解码 Base64URL
     *
     * @param string $data Base64URL 字符串
     * @return string 解码后的原始字符串
     */
    public static function decode(string $data): string
    {
        $replaced = strtr($data, '-_', '+/');
        $padding = strlen($replaced) % 4;
        if ($padding > 0) {
            $replaced .= str_repeat('=', 4 - $padding);
        }
        return base64_decode($replaced);
    }

    /**
     * 编码 JSON 数据（JPT 常用）
     *
     * @param array $data
     * @return string Base64URL 编码的 JSON 字符串
     */
    public static function encodeJson(array $data): string
    {
        return self::encode(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 解码 Base64URL JSON 数据
     *
     * @param string $data
     * @return array|null
     */
    public static function decodeJson(string $data): ?array
    {
        $json = self::decode($data);
        return json_decode($json, true);
    }
}
