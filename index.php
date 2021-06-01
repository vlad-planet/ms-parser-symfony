<?php
use Symfony\Component\CssSelector\CssSelectorConverter;
use XPathSelector\Selector;
use XPathSelector\Exception\NodeNotFoundException;

chdir(__DIR__);

include "vendor/autoload.php";

header("Content-Type: text/plain;Charset=utf-8");

$settings = array(
    //Стартовая страница каталога
    "start_page" => "http://smartkros.ru/categories/ugg-australia",

    //Селектор каталога
    "catalog_selector" => ".menu-dropdown",
    "catalog_selector_type" => "css",
    //Максимальный уровень вложенности каталога
    "catalog_max_parse_level" => 2,

    //Селектор следующей страницы пагинации
    "next_page_selector" => ".pagenumberer-next",
    "next_page_selector_type" => "css",

    //Селектор ссылки на товар в каталоге
    "catalog_item_link_selector" => ".products-view-name-link",
    "catalog_item_link_selector_type" => "css",

    //Дополнительные фильтры ссылок каталога
    "catalog_link_filters" => array(
        "like" => array("/categories/"),
        "not_like" => array(),
        ),

    //Дополнительные фильтры ссылок товара
    "catalog_item_link_filters" => array(
        "like" => array("/products/"),
        "not_like" => array(),
        ),

    "item_fields_parser" => array(
        array(
            "name" => "Название",
            "selector" => ".page-title",
            "selector_type" => "css",
            "extact_multiple" => false,
            "extract_type" => "text", //html|text|value|attr
            "extract_attr_name" => null,
            "uniq_results" => false,
            "formatter" => null, // intval, floatval, trim
            "download" => false
            ),

        array(
            "name" => "Артикул",
            "selector" => ".details-sku .details-param-value",
            "selector_type" => "css",
            "extact_multiple" => false,
            "extract_type" => "text", //html|text|value|attr
            "extract_attr_name" => null,
            "uniq_results" => false,
            "formatter" => null, // intval, floatval, trim
            "download" => false
            ),

        array(
            "name" => "Размеры",
            "selector" => "[data-sizes-viewer]",
            "selector_type" => "css",
            "extact_multiple" => false,
            "extract_type" => "attr", //html|text|value|attr
            "extract_attr_name" => "data-sizes",
            "uniq_results" => false,
            "formatter" => function($val) {
                    $res = @json_decode($val, true);
                    if ($res) {
                        return array_map(function($v) { return $v["SizeName"]; }, $res);
                    }
                    return array();
                },
            "download" => false
            ),

        array(
            "name" => "Цена",
            "selector" => ".price-number",
            "selector_type" => "css",
            "extact_multiple" => false,
            "extract_type" => "text", //html|text|value|attr
            "extract_attr_name" => null,
            "uniq_results" => false,
            "formatter" => "intval", // intval, floatval, trim
            "download" => false
            ),

        array(
            "name" => "Фото",
            "selector" => ".details-carousel-item",
            "selector_type" => "css",
            "extact_multiple" => true,
            "extract_type" => "attr", //html|text|value|attr
            "extract_attr_name" => "data-parameters",
            "uniq_results" => false,
            "formatter" => function($val) {
                    $val = str_replace("'", '"', $val);
                    $res = @json_decode($val, true);
                    if ($res) {
                        return $res["originalPath"];
                    }
                    return null;
                },
            "download" => true
            ),

        )
    );

$start_page_url_data = parse_url($settings["start_page"]);

// $catalog_links_stack = array("debug.html");
$catalog_links_stack = array($settings["start_page"]);

$items_links = array();

$catalog_links_stack_passed = array();

while (count($catalog_links_stack)) {
    $next_catalog_link = array_pop($catalog_links_stack);
    $catalog_links_stack_passed[] = $next_catalog_link;

    echo "catalog: " . $next_catalog_link . PHP_EOL;

    $html = @file_get_contents($next_catalog_link);

    if (!empty($html)) {
        $xs = Selector::loadHTML($html);

        $catalog_xpath = getXpath($settings["catalog_selector"], $settings["catalog_selector_type"]);

        $catalogNode = $xs->findOneOrNull($catalog_xpath);

        if (!empty($catalogNode)) {
            $catalog_links = array();
            getCatalogLinks($catalogNode->getDOMNode(), 0, $catalog_links);

            ksort($catalog_links);

            $catalog_links = array_values($catalog_links);

            foreach ($catalog_links as $catalog_level => $cl) {
                if ($catalog_level + 1 <= $settings["catalog_max_parse_level"]) {
                    foreach ($cl as $c) {
                        $url = getCorrectUrl($c, $start_page_url_data["scheme"], $start_page_url_data["host"]);

                        if ($url && filterPass($url, $settings["catalog_link_filters"]) && !in_array($url, $catalog_links_stack) && !in_array($url, $catalog_links_stack_passed)) {
                            $catalog_links_stack[] = $url;
                        }
                    }
                }
            }
        }

        $next_page_xpath = getXpath($settings["next_page_selector"], $settings["next_page_selector_type"]);

        $nextPageNode = $xs->findOneOrNull($next_page_xpath);

        if (!empty($nextPageNode)) {
            $url = getCorrectUrl($nextPageNode->getDOMNode()->getAttribute("href"), $start_page_url_data["scheme"], $start_page_url_data["host"]);

            if ($url) {
                $catalog_links_stack[] = $url;
            }
        }

        $catalog_item_link_xpath = getXpath($settings["catalog_item_link_selector"], $settings["catalog_item_link_selector_type"]);

        $catalogItemsNodeList = $xs->findAll($catalog_item_link_xpath);

        $catalogItemsNodeList->each( function($catalogItemNode, $index) use ($settings, $start_page_url_data, &$items_links) {

            if ($catalogItemNode->getDOMNode()->nodeName == "a") {
                $url = getCorrectUrl($catalogItemNode->getDOMNode()->getAttribute("href"), $start_page_url_data["scheme"], $start_page_url_data["host"]);
                if ($url && filterPass($url, $settings["catalog_item_link_filters"])) {
                    $items_links[] = $url;
                }
            }
        });
    }
}

$items_links = array_unique($items_links);

// file_put_contents(__DIR__ . "/found_links.csv", implode(PHP_EOL, $items_links));

foreach ($items_links as $item_id => $items_link) {
    $html = @file_get_contents($items_link);

    echo "item: " . $items_link . PHP_EOL;

    if ($html) {
        $xs = Selector::loadHTML($html);

        $extract_result = array();
        foreach ($settings["item_fields_parser"] as $field) {
            $extract_result[$field["name"]] = extractFieldData($item_id, $xs, $field);
        }
        addResultToCSV($extract_result);
    }
}

function addResultToCSV($csv_data) {

    static $fp;

    if (is_null($fp)) {
        $fp = fopen("result.csv", "a");
    }

    $csv_data = array_map(function($val) { return is_array($val) ? implode(";", $val) : $val; }, $csv_data);

    fputcsv($fp, $csv_data, ";");

}

function extractFieldData($item_id, $xs, $field) {
    $field_xpath = getXpath($field["selector"], $field["selector_type"]);

    $extractNodeList = array();

    if ($field["extact_multiple"]) {
        $xs->findAll($field_xpath)->each( function($extractNode, $index) use (&$extractNodeList) {
            $extractNodeList[] = $extractNode->getDOMNode();
        });
    } else {
        $extractNode = $xs->findOneOrNull($field_xpath);
        if (!empty($extractNode)) {
            $extractNodeList[] = $extractNode->getDOMNode();
        }
    }

    $extractResults = array();

    foreach ($extractNodeList as $extractNode) {
        if ($extractNode->nodeType != 1) continue;

        switch ($field["extract_type"]) {
            case "html":
                $extractResults[] = getInnerHtml($extractNode);
                break;

            case "text":
                $extractResults[] = getTextFromNode($extractNode);
                break;

            case "attr":
                $extractResults[] = $extractNode->getAttribute($field["extract_attr_name"]);
                break;
        }
    }

    if ($field["uniq_results"]) {
        $extractResults = array_unique($extractResults);
    }

    if (is_callable($field["formatter"])) {
        switch ($field["formatter"]) {
            case "intval":
            case "floatval":
                $extractResults = array_map(function($val) { return preg_replace("/\s+/", "", $val); }, $extractResults);
                break;
        }

        $extractResults = array_map($field["formatter"], $extractResults);
    }

    if ($field["download"]) {
        $folder_name = str_pad($item_id, 6, "0", STR_PAD_LEFT);

        $folder_path = __DIR__ . DIRECTORY_SEPARATOR . "downloads" . DIRECTORY_SEPARATOR . $folder_name;

        if (!is_dir($folder_path)) {
            mkdir($folder_path, 0777, true);
        }

        foreach ($extractResults as $file_index => $file_path) {
            $ext = @pathinfo($file_path, PATHINFO_EXTENSION);

            if ($ext) {
                $file = @file_get_contents($file_path);
                if ($file) {
                    file_put_contents($folder_path . DIRECTORY_SEPARATOR . $file_index . "." . $ext, $file);
                }
            }
        }
    }

    return $field["extact_multiple"] ? $extractResults : reset($extractResults);
}

function getInnerHtml( $node ) {
    $innerHTML= "";

    $children = $node->childNodes;
    foreach ($children as $child) {
        $innerHTML .= $child->ownerDocument->saveXML( $child );
    }

    return $innerHTML;
}

function getTextFromNode($Node, $Text = "") {
    if (@$Node->tagName == null)
        return $Text.$Node->textContent;

    $Node = $Node->firstChild;
    if ($Node != null)
        $Text = getTextFromNode($Node, $Text);

    while($Node->nextSibling != null) {
        $Text = getTextFromNode($Node->nextSibling, $Text);
        $Node = $Node->nextSibling;
    }
    return $Text;
}

function getCorrectUrl($url, $base_scheme, $base_host) {

    $url_data = parse_url($url);

    if (!empty($url_data["host"]) && $url_data["host"] != $base_host) {
        return null;
    }

    $url_data = array_merge($url_data, array("scheme" => $base_scheme, "host" => $base_host));

    return unparse_url($url_data);
}

function unparse_url($parsed_url) {
    $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
    $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
    $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
    $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
    $pass     = ($user || $pass) ? "$pass@" : '';
    $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

    return "$scheme$user$pass$host$port$path$query$fragment";
}

function filterPass($link, $filter_params) {
    $result = true;

    foreach (array("not_like", "like") as $filter_type) {
        $filter_data = $filter_params[$filter_type];
        if (empty($filter_data)) continue;

        $result = false;

        foreach ($filter_data as $term) {
            switch ($filter_type) {
                case "like":
                    if (stripos($link, $term) !== false) {
                        $result = true;
                    }
                    break;

                case "not_like":
                    if (stripos($link, $term) !== false) {
                        return false;
                    }
                    break;
            }
        }
    }

    return $result;
}

function getXpath($selector, $selector_type) {
    if ($selector_type == "xpath") {
        return $selector;
    }

    $converter = new CssSelectorConverter();
    return $converter->toXPath($selector);
}


function getCatalogLinks($catalogNode, $level = 0, &$result) {

    if ($catalogNode->nodeType == 1) {

        $elems = &$catalogNode->childNodes;

        for($index = $elems->length - 1; $index >= 0; --$index) {

            $child = $elems->item($index);

            if ($child->nodeName == "a") {
                if (!isset($result[$level])) {
                    $result[$level] = array();
                }

                $result[$level][] = $child->getAttribute("href");
            }

            getCatalogLinks($child, $level + 1, $result);
        }
    }

}

// function treeHTML(element, level, result) {
//     if (element && element.children) {
//         [].slice.call(element.children).forEach(function(elem) {

//             if (elem.tagName == 'A') {
//                 if (typeof result[level] == 'undefined') {
//                     result[level] = [];
//                 }

//                 result[level].push(elem.href);
//             }

//             treeHTML(elem, level + 1, result);
//         });
//     }

//     return result;
// }

// var result = treeHTML(document.querySelector(".cats4 > ul")),
//     keys = [];

// for (k in result) {
//     if (result.hasOwnProperty(k)) {
//         keys.push(k);
//     }
// }

// keys.sort();

// len = keys.length;

// for (i = 0; i < len; i++) {
//     k = keys[i];
//     console.log(i, ':', result[k]);
// }

// $filename = "result.csv";
// $links = file("links.csv");

// foreach ($links as $link) {
//     $html = file_get_contents("http://www.calend.ru".$link);
//     $xs = Selector::loadHTML($html);

//     $bodyNode = $xs->findOneOrNull('//div[@itemprop="articleBody"]');

//     $result = array();
//     if(!empty($bodyNode)) {
//         try {
//             $day = $bodyNode->find("p[@class = 'data']/a")->extract();
//             $result["day"] = intval($day);
//             $result["month"] = getMonthNum($day);
//             $result["title"] = trim($xs->find('//h1[@itemprop="name"]')->extract());
//             $result["holiday_type"] = trim($xs->find('//span[@itemtype="http://data-vocabulary.org/Breadcrumb" and position() = 2]')->extract());

//             $elems = $bodyNode->findAll("//*");

//             $elems->each( function($script, $index) {
//                 if(in_array($script->getDOMNode()->nodeName, array("img", "script", "p", "div")) ||
//                     stripos($script->getDOMNode()->getAttribute("class"), "imagebig") !== false) {
//                     $script->getDOMNode()->parentNode->removeChild($script->getDOMNode());
//                 } elseif(in_array($script->getDOMNode()->nodeName, array("b", "a", "div", "table", "tr", "th", "td"))) {

//                     $fragment = $script->getDOMNode()->ownerDocument->createDocumentFragment();

//                     while ($script->getDOMNode()->childNodes->length > 0) {
//                         $fragment->appendChild($script->getDOMNode()->childNodes->item(0));
//                     }

//                     return $script->getDOMNode()->parentNode->replaceChild($fragment, $script->getDOMNode());
//                 }
//             });

//             $text = $bodyNode->innerHtml();
//             $text = preg_replace('/<!--(.|\s)*?-->/', '', $text);
//             $text = htmlentities($text, null, "UTF-8");
//             $text = str_replace("&nbsp;", " ", $text);
//             $text = preg_replace("/\s+/", " ", $text);
//             $text = preg_replace("/\(Фото:[^\)]+\)/", "", $text);
//             $result["text"] = html_entity_decode(trim($text));
//             $result["text"] = str_replace("<br>", "<br/>", $result["text"]);
//             $result["text"] = preg_replace("/(<br\/>){3,}/", "<br/><br/>", $result["text"]);
//         } catch (Exception $e) {
//         }

//         file_put_contents($filename, implode("\t", array_values($result))."\n", FILE_APPEND);
//     }
