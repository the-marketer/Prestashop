<?php
/**
* theMarketer V1.0.0 module
* for Prestashop v1.7.X.
*
* @author themarketer.com
* @copyright  2022-2023 theMarketer.com
* @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
include '../../config/config.inc.php';
include '../../init.php';

if (!defined('MKTR_DIR')) {
    define('MKTR_DIR', dirname(__FILE__) . '/');
}
include MKTR_DIR . 'themarketer.php';
include MKTR_DIR . 'Model/Product.php';
$pid = Tools::getValue('product_id');
$atrr = Tools::getValue('comb_id');

ModelProduct::getProductByID($pid);
$variant = ModelProduct::getVariant($atrr);

echo json_encode([
    'product_id' => ModelProduct::getId(),
    'variation' => $variant,
]);
