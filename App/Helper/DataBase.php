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
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @docs        https://themarketer.com/resources/api
 **/

namespace Mktr\Helper;

abstract class DataBase
{
    protected $cast = [];
    protected $columns = [];
    protected $attributes = [];
    protected $ref = [];
    protected $functions = [];
    protected $vars = [];
    protected $orderBy = null;
    protected $direction = 'ASC';
    protected $limit = 250;
    protected $hide = [];
    protected $dateFormat = 'Y-m-d H:i';
    protected $data = null;
    protected $list = null;

    public function __call($name, $arguments)
    {
        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $arguments);
        } else {
            if (_PS_MODE_DEV_) {
                throw new \Exception("Method {$name} does not exist.");
            }

            return null;
        }
    }

    public static function __callStatic($name, $arguments)
    {
        $i = new static();
        if (method_exists($i, $name)) {
            return call_user_func_array([$i, $name], $arguments);
        } else {
            if (_PS_MODE_DEV_) {
                throw new \Exception("Static method {$name} does not exist.");
            }

            return null;
        }
    }

    private function toArray($if = null)
    {
        $list = [];
        if ($this->attributes) {
            foreach ($this->attributes as $key => $value) {
                if (!in_array($key, $this->hide)) {
                    $value = $this->{$key};
                    if ($value !== null && array_key_exists($key, $this->cast) && in_array($this->cast[$key], ['date', 'datetime'])) {
                        $list[$key] = $value->format($this->dateFormat);
                    } else {
                        if ($if !== null && in_array($key, $if)) {
                            if (!empty($value)) {
                                $list[$key] = $value;
                            }
                        } else {
                            $list[$key] = $value;
                        }
                    }
                }
            }
        }

        return $list;
    }

    public function __get($key)
    {
        if (array_key_exists($key, $this->attributes) && $this->attributes[$key] !== null) {
            return $this->attributes[$key];
        } elseif (array_key_exists($key, $this->ref)) {
            if (in_array($this->ref[$key], $this->functions)) {
                $this->attributes[$key] = call_user_func_array([$this, $this->ref[$key]], []);
            } elseif (in_array($this->ref[$key], $this->vars)) {
                $v = $this->ref[$key];
                $this->attributes[$key] = $this->{$v};
            } else {
                if (array_key_exists($key, $this->cast)) {
                    $this->attributes[$key] = $this->cast($key, $this->data->{$this->ref[$key]});
                } elseif ($this->data !== null) {
                    $this->attributes[$key] = $this->data->{$this->ref[$key]};
                } else {
                    $this->attributes[$key] = null;
                }
            }
        } else {
            $this->attributes[$key] = null;
        }

        return $this->attributes[$key];
    }

    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    protected function cast($key, $value)
    {
        switch ($this->cast[$key]) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
                return (float) $value;
            case 'double':
                return (float) $this->toDigit($value);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
            case 'array':
                return unserialize($value);
            case 'json':
                return json_decode($value, true);
            case 'date':
            case 'datetime':
                return new \DateTime($value);
            case 'timestamp':
                return $value;
            default:
                return $value;
        }
    }

    protected function unCast($key, $value)
    {
        switch ($this->cast[$key]) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (int) $value;
            case 'object':
            case 'array':
                return serialize($value);
            case 'json':
                return json_encode($value, true);
            case 'date':
            case 'datetime':
                return $value->format('c');
            case 'timestamp':
                return $value;
            default:
                return $value;
        }
    }

    protected function toDigit($num = null, $digit = 2)
    {
        if ($num !== null) {
            $num = str_replace(',', '.', $num);
            $num = preg_replace('/\.(?=.*\.)/', '', $num);

            return number_format((float) $num, $digit, '.', '');
        }

        return null;
    }

    protected function toJson($data = null)
    {
        return Valid::toJson($data);
    }
}
