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
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
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

class FileSystem
{
    private static $path;
    private static $lastPath;
    private static $status = [];
    private static $useRoot = false;

    private static $init;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }

        return self::$init;
    }

    /** @noinspection PhpUnused */
    public static function setWorkDirectory($name = 'Storage')
    {
        if ($name != 'base' && !self::$useRoot) {
            if ($name === 'Storage' || $name === 'Storage/') {
                self::$path = MKTR_APP . \Mktr\Model\Config::getStoragePath();
            } else {
                self::$path = MKTR_APP . $name . '/';
            }
            if (!is_dir(self::$path)) {
                @mkdir(self::$path, 0755, true);
            }
        } else {
            self::$path = MKTR_ROOT;
        }

        return self::init();
    }

    /** @noinspection PhpUnused */
    public static function writeFile($fName, $content, $mode = 'w+')
    {
        $fName = self::getName($fName);

        $file = fopen(self::$lastPath, $mode);
        if ($file === false) {
            throw new \Exception('Failed to open file.');
        }

        fwrite($file, $content);
        fclose($file);

        self::$status[] = [
            'path' => self::getPath(),
            'fileName' => $fName,
            'fullPath' => self::getPath() . $fName,
            'status' => true,
        ];

        return self::init();
    }

    /** @noinspection PhpUnused */
    public static function rFile($fName, $mode = 'rb')
    {
        $fName = self::getName($fName);

        if (self::fileExists($fName) && filesize(self::$lastPath) > 0) {
            $file = fopen(self::$lastPath, $mode);
            if ($file === false) {
                throw new \Exception('Failed to open file.');
            }

            $contents = fread($file, filesize(self::$lastPath));

            fclose($file);
        } else {
            $contents = '';
        }

        return $contents;
    }

    /** @noinspection PhpUnused */
    public static function readFile($fName, $mode = 'rb')
    {
        $fName = self::getName($fName);

        $contents = '';

        if (self::fileExists($fName) && filesize(self::$lastPath) > 0) {
            $file = fopen(self::$lastPath, $mode);
            if ($file === false) {
                throw new \Exception('Failed to open file.');
            }

            $contents = fread($file, filesize(self::$lastPath));

            fclose($file);
        }

        return $contents;
    }

    /** @noinspection PhpUnused */
    public static function fileExists($fName)
    {
        return file_exists(self::getPath() . $fName);
    }

    /** @noinspection PhpUnused */
    public static function deleteFile($fName)
    {
        $fName = self::getName($fName);

        if (self::fileExists($fName)) {
            if (!unlink(self::$lastPath)) {
                throw new \Exception('Failed to delete file.');
            }
        }

        return true;
    }

    public static function getPath()
    {
        if (self::$path == null) {
            self::setWorkDirectory();
        }

        return self::$path;
    }

    public static function resetPath()
    {
        self::$path = null;
    }

    /** @noinspection PhpUnused */
    public static function getLastPath()
    {
        return self::$lastPath;
    }

    public static function getStatus()
    {
        return self::$status;
    }

    /**
     * @param $fName
     * @return string
     * @throws \Exception
     */
    public static function getName($fName): string
    {
        $fName = basename($fName);

        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $fName)) {
            throw new \Exception('Invalid filename.');
        }

        if (empty($fName)) {
            throw new \Exception('Filename cannot be empty.');
        }

        self::$lastPath = self::getPath() . $fName;

        $realBase = realpath(self::getPath());
        $realUserPath = realpath(dirname(self::$lastPath));

        if ($realUserPath === false || $realBase === false || strpos($realUserPath, $realBase) !== 0) {
            throw new \Exception('Invalid file path.');
        }
        return $fName;
    }
}
