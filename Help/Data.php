<?php
/**
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @docs        https://themarketer.com/resources/api
 */

/**
 * @property int|mixed|null $reviewStore
 * @property int|mixed|null $update_feed
 * @property int|mixed|null $update_review
 */
class Data
{
    private static $init = null;

    private static $data;

    public function __construct()
    {
        $data = self::readFile('data.json');
        if ($data !== '') {
            self::$data = unserialize($data);
        } else {
            self::$data = [];
        }
    }

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }

        return self::$init;
    }

    public function __get($name)
    {
        if (!isset(self::$data[$name])) {
            if ($name == 'update_feed' || $name == 'update_review') {
                self::$data[$name] = 0;
            } else {
                self::$data[$name] = null;
            }
        }

        return self::$data[$name];
    }

    public function __set($name, $value)
    {
        self::$data[$name] = $value;
    }

    public static function getData()
    {
        return self::$data;
    }

    public static function addTo($name, $value, $key = null)
    {
        if ($key === null) {
            self::$data[$name][] = $value;
        } else {
            self::$data[$name][$key] = $value;
        }
    }

    public static function del($name)
    {
        unset(self::$data[$name]);
    }

    public static function save()
    {
        self::writeFile('data.json', serialize(self::$data));
    }

    public static function writeFile($fName, $content, $mode = 'w+')
    {
        $file = fopen(MKTR_DIR . $fName, $mode);
        fwrite($file, $content);
        fclose($file);
    }

    public static function readFile($fName, $mode = 'rb')
    {
        $contents = false;
        $lastPath = MKTR_DIR . $fName;

        if (self::fileExists($fName)) {
            $file = fopen($lastPath, $mode);

            $contents = fread($file, filesize($lastPath));

            fclose($file);
        }

        return $contents;
    }

    public static function fileExists($fName)
    {
        return file_exists(MKTR_DIR . $fName);
    }
}
