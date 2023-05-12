<?php
/**
* theMarketer V1.0.3 module
* for Prestashop v1.7.X.
*
* @author themarketer.com
* @copyright  2022-2023 theMarketer.com
* @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
include '../../config/config.inc.php'; // first check if this link is ok
include '../../init.php'; // this link also
header('Content-type: text/xml');
$id_lang = Context::getContext()->language->id;
if (Configuration::get('THEMARKETER_REST_KEY') == $_GET['key']) {
    $cats = Category::getCategories($id_lang, true, false);
    $xml_schema = '<?xml version=\'1.0\' encoding=\'UTF-8\'?><categories>';
    foreach ($cats as $cat) {
        $category = new Category($cat['id_category']);
        $array_parent = $category->getParentsCategories();
        krsort($array_parent);
        $link = new Link();
        $link = $link->getCategoryLink($cat['id_category']);
        $c = '';
        foreach ($array_parent as $k => $ch) {
            if (isset($ch['name'])) {
                $c .= $ch['name'] . '|';
            } else {
            }
        }
        $hierarchy = mb_substr($c, 0, -1);
        $xml_schema .= '<category>
			<name><![CDATA[' . $cat['name'] . ']]></name>
			<url><![CDATA[' . $link . ']]></url>
			<id><![CDATA[' . $cat['id_category'] . ']]></id>
			<hierarchy><![CDATA[' . $hierarchy . ']]></hierarchy>
		  </category>';
    }
    $xml_schema .= '</categories>';
    echo $xml_schema;
} else {
}
