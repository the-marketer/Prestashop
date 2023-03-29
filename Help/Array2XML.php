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
 * Usage:
 *  \App\Repositories\Unas\Array2XML::cXML("root_node_name",$array)->saveXML();
 */
class Array2XML
{
    const DEFAULT_DOM_VERSION = '1.0';
    const DEFAULT_ENCODING = 'UTF-8';
    const DEFAULT_STANDALONE = false;
    const DEFAULT_FORMAT_OUTPUT = true;

    const LABEL_ATTRIBUTES = '@attributes';
    const LABEL_CDATA = '@cdata';
    const LABEL_DOCTYPE = '@docType';
    const LABEL_VALUE = '@value';

    public static $noNull = false;

    protected static $xml;

    protected static $domVersion;

    protected static $encoding;

    protected static $standalone;

    protected static $formatOutput;

    protected static $labelAttributes;

    protected static $labelCData;

    protected static $labelDocType;

    protected static $labelValue;

    private static $last_xml;

    private static $errors;

    private static $CDataStatus = false;
    private static $CDataValues = [];

    private static $nodeAdd = false;

    public static function init(
        $version = null,
        $encoding = null,
        $standalone = null,
        $format_output = null,
        $labelAttributes = null,
        $labelCData = null,
        $labelDocType = null,
        $labelValue = null
    ): DOMDocument {
        self::setDomVersion($version);
        self::setEncoding($encoding);
        self::setStandalone($standalone);
        self::setFormatOutput($format_output);

        self::setLabelAttributes($labelAttributes);
        self::setLabelCData($labelCData);
        self::setLabelDocType($labelDocType);
        self::setLabelValue($labelValue);

        self::$xml = new DOMDocument(self::getDomVersion(), self::getEncoding());

        // self::$xml->xmlStandalone = self::isStandalone();

        self::$xml->formatOutput = self::isFormatOutput();
        self::$nodeAdd = true;

        return self::$xml;
    }

    public static function getDomVersion(): string
    {
        return self::$domVersion ?? self::DEFAULT_DOM_VERSION;
    }

    public static function getEncoding(): string
    {
        return self::$encoding ?? self::DEFAULT_ENCODING;
    }

    public static function isStandalone(): bool
    {
        return self::$standalone ?? self::DEFAULT_STANDALONE;
    }

    public static function isFormatOutput(): bool
    {
        return self::$formatOutput ?? self::DEFAULT_FORMAT_OUTPUT;
    }

    protected static function setDomVersion($domVersion = null)
    {
        self::$domVersion = $domVersion ?? self::DEFAULT_DOM_VERSION;
    }

    protected static function setEncoding($encoding = null)
    {
        self::$encoding = $encoding ?? self::DEFAULT_ENCODING;
    }

    protected static function setStandalone($standalone = null)
    {
        self::$standalone = $standalone ?? self::DEFAULT_STANDALONE;
    }

    protected static function setFormatOutput($formatOutput = null)
    {
        self::$formatOutput = $formatOutput ?? self::DEFAULT_FORMAT_OUTPUT;
    }

    public static function getLabelAttributes(): string
    {
        return self::$labelAttributes ?? self::LABEL_ATTRIBUTES;
    }

    public static function getLabelCData(): string
    {
        return self::$labelCData ?? self::LABEL_CDATA;
    }

    public static function getLabelDocType(): string
    {
        return self::$labelDocType ?? self::LABEL_DOCTYPE;
    }

    public static function getLabelValue(): string
    {
        return self::$labelValue ?? self::LABEL_VALUE;
    }

    protected static function setLabelAttributes($labelAttributes = null)
    {
        self::$labelAttributes = $labelAttributes ?? self::LABEL_ATTRIBUTES;
    }

    protected static function setLabelCData($labelCData = null)
    {
        self::$labelCData = $labelCData ?? self::LABEL_CDATA;
    }

    public static function setCDataValues($name)
    {
        self::$CDataStatus = true;
        if (is_array($name)) {
            self::$CDataValues = $name;
        } else {
            self::$CDataValues[] = $name;
        }
    }

    protected static function setLabelDocType($labelDocType = null)
    {
        self::$labelDocType = $labelDocType ?? self::LABEL_DOCTYPE;
    }

    protected static function setLabelValue($labelValue = null)
    {
        self::$labelValue = $labelValue ?? self::LABEL_VALUE;
    }

    private static function getXMLRoot(): void
    {
        self::$xml ?? self::init();
    }

    public static function errors()
    {
        return self::$errors;
    }

    public static function Memory(): DOMDocument
    {
        return self::$last_xml ?? self::init();
    }

    public static function toObject($string = null)
    {
        return simplexml_load_string($string ?? self::Memory()->saveXML(), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
    }

    public function createXML($node_name, $arr = null, $docType = [])
    {
        return self::cXML($node_name, $arr, $docType);
    }

    private static function bool2str($v)
    {
        return $v === true ? 'true' : (($v === false) ? 'false' : $v);
    }

    private static function isValidTagName($tag): bool
    {
        $pattern = '/^[a-z_]+[a-z\d:\-._]*[^:]*$/i';

        return preg_match($pattern, $tag, $matches) && $matches[0] == $tag;
    }

    public static function cXML($node_name, $arr = null, $docType = [])
    {
        self::getXMLRoot();
        try {
            if ($docType) {
                self::$xml->appendChild(
                    (new DOMImplementation())
                        ->createDocumentType(
                            $docType['name'] ?? '',
                            $docType['publicId'] ?? '',
                            $docType['systemId'] ?? ''
                        )
                );
            }
            if ($arr == null && self::$nodeAdd) {
                self::$nodeAdd = false;
                $node_name = [$node_name => ''];
                // self::$xml->appendChild(self::$xml->createElement( $node_name ));
            }

            if ($arr == null) {
                foreach ($node_name as $key => $value) {
                    self::$xml->appendChild(self::convert($key, $value));
                }
            } else {
                self::$xml->appendChild(self::convert($node_name, $arr));
            }

            self::$last_xml = self::$xml;

            self::$xml = null;

            return self::$last_xml;
        } catch (Exception $e) {
            return self::$xml;
        }
    }

    /**
     * @throws Exception
     */
    private static function convert($node_name, $arr = [])
    {
        self::getXMLRoot();

        $node = self::$xml->createElement($node_name);

        if (self::$CDataStatus && !is_array($arr) && in_array($node_name, self::$CDataValues) && $arr !== null) {
            $arr = ['@cdata' => $arr];
        }

        if (is_array($arr)) {
            if (array_key_exists(self::getLabelAttributes(), $arr) && is_array($arr[self::getLabelAttributes()])) {
                foreach ($arr[self::getLabelAttributes()] as $key => $value) {
                    if (!self::isValidTagName($key)) {
                        $error = 'Illegal character in attribute name. attribute: ' . $key . ' in node: ' . $node_name;
                        self::$errors[] = $error;
                        throw new Exception($error);
                    }
                    $node->setAttribute($key, self::bool2str($value));
                }
                unset($arr[self::getLabelAttributes()]);
            }

            if (array_key_exists(self::getLabelValue(), $arr)) {
                $node->appendChild(self::$xml->createTextNode(self::bool2str($arr[self::getLabelValue()])));
                unset($arr[self::getLabelValue()]);

                return $node;
            } elseif (array_key_exists(self::getLabelCData(), $arr)) {
                $node->appendChild(self::$xml->createCDATASection(self::bool2str($arr[self::getLabelCData()])));
                unset($arr[self::getLabelCData()]);

                return $node;
            }

            foreach ($arr as $key => $value) {
                if (!self::isValidTagName($key)) {
                    $error = 'Illegal character in tag name. tag: ' . $key . ' in node: ' . $node_name;
                    self::$errors[] = $error;
                    throw new Exception($error);
                }
                if (is_array($value) && is_numeric(key($value))) {
                    foreach ($value as $v) {
                        if (self::$noNull && $v === null) {
                            continue;
                        }
                        $node->appendChild(self::convert($key, $v));
                    }
                } else {
                    if (self::$noNull && $value === null) {
                        continue;
                    }
                    $node->appendChild(self::convert($key, $value));
                }
                unset($arr[$key]);
            }
        }

        if (!is_array($arr)) {
            if (self::$noNull && $arr === null) {
                return $node;
            }
            if ($arr === null) {
                $arr = '';
            }
            $node->appendChild(self::$xml->createTextNode(self::bool2str($arr)));
        }

        return $node;
    }
}
