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
 * Class ThemarketerApiModuleFrontController.
 */
class ThemarketerApiModuleFrontController extends ModuleFrontController
{
    public const mime = [
        'xml' => 'application/xhtml+xml',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'csv' => 'text/csv',
    ];
    public const def_mime = 'xml';

    public static $pg = null;
    public static $pages = [
        'feed',
    ];
    public static $mime = null;
    public static $read = null;

    public function __construct()
    {
        parent::__construct();
        self::$pg = strtolower(Tools::getValue('p', false));
        self::$mime = Tools::getValue('mime-type', self::def_mime);
        self::$read = Tools::getValue('read', false);
    }

    public function initContent()
    {
        if (self::$pg !== false && in_array(self::$pg, self::$pages)) {
            self::{self::$pg}();
        } else {
            self::$mime = 'json';
            echo '{"error":"Invalid Page"}';
        }

        header('Content-type: ' . self::mime[self::$mime] . '; charset=UTF-8');
        header('HTTP/1.1 200 OK');
        http_response_code(201);
        header('Status: 200 All rosy');
        exit(0);
    }

    public static function feed()
    {
        if (Tools::getValue('key', false) == Configuration::get('THEMARKETER_REST_KEY')) {
            if (Tools::getIsset('read') && self::fileExists('feed.xml')) {
                echo self::readFile('feed.xml');
            } else {
                TheMarketer::getModel('Product');
                TheMarketer::getHelp('Array2XML');

                $pro = ModelProduct::getNewFeed(Tools::getValue('page', null));

                Array2XML::setCDataValues(['name', 'description', 'category', 'brand', 'size', 'color', 'hierarchy']);
                Array2XML::$noNull = true;
                try {
                    $file = Array2XML::cXML('products', ['product' => $pro])->saveXML();
                    self::writeFile('feed.xml', $file);
                    echo $file;
                } catch (DOMException $e) {
                    echo Array2XML::errors();
                }
            }
        }
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
