<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author      TheMarketer.com
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 *
 * @project     TheMarketer.com
 *
 * @website     https://themarketer.com/
 *
 * @docs        https://themarketer.com/resources/api
 **/

namespace Mktr\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Stores arrays as JSON and still reads the values that earlier versions wrote
 * in PHP's native serialization format. The legacy reader only understands arrays and scalars, so
 * a value that contains an object is rejected instead of being instantiated.
 */
class Codec
{
    public static function encode($value)
    {
        return json_encode($value);
    }

    /**
     * @param mixed $value
     *
     * @return mixed the decoded array or scalar, or null when $value cannot be read
     */
    public static function decode($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $first = $value[0];
        if ($first === '[' || $first === '{') {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $offset = 0;
        $decoded = self::readLegacy($value, $offset);

        return $offset === strlen($value) ? $decoded : null;
    }

    /**
     * Reads one value in PHP's native serialization format, limited to N, b, i, d, s and a.
     *
     * @param string $data
     * @param int $offset advanced past the value; set past the end on error
     *
     * @return mixed
     */
    private static function readLegacy($data, &$offset)
    {
        $length = strlen($data);
        $fail = function () use (&$offset, $length) {
            $offset = $length + 1;

            return null;
        };

        if ($offset + 1 >= $length) {
            return $fail();
        }

        $type = $data[$offset];

        if ($type === 'N' && $data[$offset + 1] === ';') {
            $offset += 2;

            return null;
        }

        if ($data[$offset + 1] !== ':') {
            return $fail();
        }

        switch ($type) {
            case 'b':
            case 'i':
            case 'd':
                $end = strpos($data, ';', $offset + 2);
                if ($end === false) {
                    return $fail();
                }
                $raw = substr($data, $offset + 2, $end - $offset - 2);
                $offset = $end + 1;
                if ($type === 'b') {
                    return $raw === '1';
                }

                return $type === 'i' ? (int) $raw : (float) $raw;
            case 's':
                $colon = strpos($data, ':', $offset + 2);
                if ($colon === false) {
                    return $fail();
                }
                $size = (int) substr($data, $offset + 2, $colon - $offset - 2);
                if (substr($data, $colon + 1, 1) !== '"' || substr($data, $colon + 2 + $size, 2) !== '";') {
                    return $fail();
                }
                $offset = $colon + 4 + $size;

                return substr($data, $colon + 2, $size);
            case 'a':
                $colon = strpos($data, ':', $offset + 2);
                if ($colon === false || substr($data, $colon + 1, 1) !== '{') {
                    return $fail();
                }
                $count = (int) substr($data, $offset + 2, $colon - $offset - 2);
                $offset = $colon + 2;
                $result = [];
                for ($n = 0; $n < $count; ++$n) {
                    $key = self::readLegacy($data, $offset);
                    if ($offset > $length || !(is_int($key) || is_string($key))) {
                        return $fail();
                    }
                    $result[$key] = self::readLegacy($data, $offset);
                    if ($offset > $length) {
                        return $fail();
                    }
                }
                if (substr($data, $offset, 1) !== '}') {
                    return $fail();
                }
                ++$offset;

                return $result;
            default:
                return $fail();
        }
    }
}
