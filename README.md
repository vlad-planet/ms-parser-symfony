# parser
 sources
 
 
 
$settings = array(
    // Стартовая страница каталога
    "start_page" => "http://smartkros.ru/categories/ugg-australia",
    
    // Селектор каталога
    "catalog_selector" => ".menu-dropdown",
    "catalog_selector_type" => "css",
    // Максимальный уровень вложенности каталога
    "catalog_max_parse_level" => 2,

    // Селектор следующей страницы пагинации
    "next_page_selector" => ".pagenumberer-next",
    "next_page_selector_type" => "css",

    // Селектор ссылки на товар в каталоге
    "catalog_item_link_selector" => ".products-view-name-link",
    "catalog_item_link_selector_type" => "css",

    // Дополнительные фильтры ссылок каталога
    "catalog_link_filters" => array(
        "like" => array("/categories/"),
        "not_like" => array(),
        ),

    // Дополнительные фильтры ссылок товара
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
