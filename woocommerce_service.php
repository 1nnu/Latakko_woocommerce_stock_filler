<?php

require_once('./woocommerce_client.php');
define('WOOCOMMERCE_STOCK_FILE', 'woocommerce_stock_info.json');


class Stock_item
{
    function __construct($article_id, $quantity_available, $price, $EAN)
    {
        $this->article_id = $article_id;
        $this->quantity_available = $quantity_available;
        $this->price = $price;
        $this->EAN = $EAN;

    }
    public $article_id;
    public $quantity_available;
    public $price;
    public $EAN;

    public function __toString()
    {
        return "Article ID: {$this->article_id}, Quantity: {$this->quantity_available}, Price: {$this->price}, EAN: {$this->EAN}";
    }
}

function update_woocommerce_stock()
{
    global $woocommerce;
    $new_woocommerce_stock_data = create_products_check_list();
    $current_woocommerce_stock_data = retrieve_current_products_list();
    $products_to_remove = find_products_to_remove($new_woocommerce_stock_data, $current_woocommerce_stock_data);
    $products_to_add = find_products_to_add($new_woocommerce_stock_data, $current_woocommerce_stock_data);
    remove_products($products_to_remove, $woocommerce);
    add_products($current_woocommerce_stock_data, $products_to_add, $woocommerce);
    file_put_contents(WOOCOMMERCE_STOCK_FILE, json_encode($new_woocommerce_stock_data));
}

function update_product_categories()
{
    global $woocommerce;
    $missing_categories = [];
    $existing_categories = [];

    try {
        // Fetch existing WooCommerce categories
        $woocommerce_categories = $woocommerce->get('products/categories', ['per_page' => 100]);
    } catch (Exception $e) {
        error_log('Error fetching categories: ' . $e->getMessage());
        return;
    }

    foreach ($woocommerce_categories as $category) {
        $existing_categories[] = strtolower(trim($category->name));
    }

    // Fetch stock data
    $stock_data = json_decode(file_get_contents(LATAKKO_STOCK_FILE), true);
    if ($stock_data === null) {
        error_log('Error reading stock data from file.');
        return;
    }

    // Identify missing categories
    foreach ($stock_data as $item) {
        $main_group_name = strtolower(trim($item["MainGroupName"]));
        if (!in_array($main_group_name, $existing_categories) && !in_array($main_group_name, $missing_categories)) {
            $missing_categories[] = $main_group_name;
        }
    }

    // Create missing categories
    if (!empty($missing_categories)) {
        try {
            foreach ($missing_categories as $category_name) {
                $data = ['name' => $category_name];
                $woocommerce->post('products/categories', $data);
                error_log('Created category: ' . $category_name);
            }
        } catch (Exception $e) {
            error_log('Error creating categories: ' . $e->getMessage());
        }
    } else {
        error_log('No missing categories to create.');
    }

    return count($missing_categories);
}
/*
    Check and update all woocommerce attribute groups and terms
    NOTE: Redundant!
*/
function check_product_attributes()
{
    global $woocommerce;
    check_existance_of_attributes($woocommerce);
}

/*
    Check for existance and add attribute groups
*/
function check_existance_of_attributes($woocommerce)
{
    $woocommerce_attributes = $woocommerce->get('products/attributes');
    if (!$woocommerce_attributes) {
        $data = [
            'name' => 'Aspect Ratio',
            'slug' => 'aspect_ratio',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'Brand Name',
            'slug' => 'brand_name',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'Diameter',
            'slug' => 'diameter',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'Weight',
            'slug' => 'weight',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'Width',
            'slug' => 'width',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'EAN',
            'slug' => 'ean',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'Radial',
            'slug' => 'radial',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'Load Index',
            'slug' => 'load_index',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'Speed index',
            'slug' => 'speed_index',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'Fuel Efficiency',
            'slug' => 'fuel_effiency',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'Noise Class',
            'slug' => 'noice_class',
        ];
        $woocommerce->post('products/attributes', $data);
        $data = [
            'name' => 'Noise Value',
            'slug' => 'noice_value',
        ];
        $woocommerce->post('products/attributes', $data);
        return true;
    }
    return false;
}

/*
    Get all categories and their respective id's in a map
*/
function map_category_name_to_woocommerce_id($object_array)
{
    $result = array();
    foreach ($object_array as $category) {
        $result[$category->name] = $category->id;
    }
    return $result;
}

/*
    Get all attributes and their id's in a map
*/
function map_attribute_name_to_woocommerce_id($object_array)
{
    $result = array();
    foreach ($object_array as $attribute) {
        $result[$attribute->slug] = $attribute->id;
    }
    return $result;
}

/*
    Helper function to provide a product with attributes
*/
function create_product_attributes($product_info, $woocommerce_attributes_map)
{
    $product_info = array_change_key_case($product_info, CASE_LOWER);
    $product_attributes = array();
    $attribute_names = array_keys($woocommerce_attributes_map);
    foreach ($attribute_names as $attribute) {
        $attribute_name = substr(str_replace('_', "", $attribute), 2);
        $options = $product_info[$attribute_name];
        if ($options == NULL) {
            $options = '-';
        } else if (is_float($options)) {
            $options = number_format($options, 2, '.', '');
        } else if (is_int($options)) {
            $options = strval($options);
        }
        $attribute_details = [
            'id' => $woocommerce_attributes_map[$attribute],
            'name' => $attribute,
            'visible' => true,
            'options' => $options,
        ];
        array_push($product_attributes, $attribute_details);
    }
    return $product_attributes;
}

function add_products($current_woocommerce_stock_data, $products_to_add, $woocommerce)
{
    $lastest_stock_file = json_decode(file_get_contents(LATAKKO_STOCK_FILE), true);
    $woocommerce_categories = map_category_name_to_woocommerce_id($woocommerce->get('products/categories?per_page=100'));
    $woocommerce_attributes = map_attribute_name_to_woocommerce_id($woocommerce->get('products/attributes'));
    $no_image_id = get_image_id_of_missing_image();
    $items_added_counter = 0;
    foreach ($products_to_add as $product) {
        $product_info = find_product_from_stock_file($product, $lastest_stock_file);
        $product_image_id = get_image_id_by_slug($product_info["ImageId"]);
        if (!$product_image_id) {
            $product_image_id = $no_image_id;
        }
        $product_attributes = create_product_attributes($product_info, $woocommerce_attributes);
        $product_data = [
            'name' => $product_info["ArticleText"],
            'regular_price' => number_format($product_info["Price"], 2, '.', ''),
            'sku' => strval($product_info["EAN"]),
            'description' => $product_info["ArticleText"],
            'manage_stock' => true,
            'stock_quantity' => $product_info["QuantityAvailable"],
            'categories' => [
                [
                    'id' => $woocommerce_categories[trim($product_info["MainGroupName"])],
                ],
            ],
            'images' => [
                [
                    'id' => $product_image_id,
                ],
            ]
        ];
        $product_data['attributes'] = $product_attributes;
	var_dump($product_data);
        try {
            $woocommerce->post('products', $product_data);
            echo "INFO: Product " . $product_data['name'] . " added successfuly\n";
            $product_record = new Stock_item($product_info["ArticleId"], $product_info["QuantityAvailable"], $product_info["Price"], $product_info["EAN"]);
            array_push($current_woocommerce_stock_data, $product_record);
        } catch (Exception $e) {
            echo $e->getMessage() . '\n';
        }
        $items_added_counter++;
        if (($items_added_counter % 100) == 0) {
            file_put_contents(WOOCOMMERCE_STOCK_FILE, json_encode($current_woocommerce_stock_data));
        }
    }
}

function find_product_from_stock_file($product, $stock_data)
{
    foreach ($stock_data as $stock_item) {
        if ($stock_item['ArticleId'] == $product->article_id) {
            return $stock_item;
        }
    }
}

function remove_products($products_to_remove, $woocommerce)
{
    $stock_data = json_decode(file_get_contents(LATAKKO_STOCK_FILE), true);
    foreach ($products_to_remove as $product) {
        $woocommerce_product = $woocommerce->get("products?sku=$product->EAN");
	$product_id = null;
	foreach ($woocommerce_product as $product){
		$product_id = $product->id;
	}
	if ($product_id == null){
		continue;
	}
        $woocommerce->delete('products/' . $product_id , ['force' => true]);
        echo "Product with id:" . strval($product_id) . " deleted\n";
    }
}

/*
    Loop through current woocommerce stock and new woocommerce stock,
    return objects which have different values or are missing from original
*/
function find_products_to_remove($new_woocommerce_stock_data, $current_woocommerce_stock_data)
{
    // Use array_diff to find items in current stock that are not in the new stock
    $products_to_remove = array_diff($current_woocommerce_stock_data, $new_woocommerce_stock_data);

    // Return the resulting array
    return array_values($products_to_remove); // Re-index array
}

function find_products_to_add($new_woocommerce_stock_data, $current_woocommerce_stock_data)
{
    // Use array_diff to find items in new stock that are not in the current stock
    $to_be_added_products = array_diff($new_woocommerce_stock_data, $current_woocommerce_stock_data);

    // Return the resulting array
    return array_values($to_be_added_products); // Re-index array
}

// Maps a associative array to a Stock_item object
function map_to_stock_item($stock_item)
{
    return new Stock_item($stock_item->article_id, $stock_item->quantity_available, $stock_item->price, $stock_item->EAN);
}
function create_products_check_list()
{
    $stock_data = json_decode(file_get_contents(LATAKKO_STOCK_FILE), false);
    $new_woocommerce_stock_data = array();
    foreach ($stock_data as $item) {
        $new_stock_item = new Stock_item($item->ArticleId, $item->QuantityAvailable, $item->Price, $item->EAN);
        array_push($new_woocommerce_stock_data, $new_stock_item);
    }
    return $new_woocommerce_stock_data;
}

function retrieve_current_products_list()
{
    if (!file_exists(WOOCOMMERCE_STOCK_FILE)) {
        file_put_contents(WOOCOMMERCE_STOCK_FILE, '');
    }
    $current_woocommerce_stock_data = json_decode(file_get_contents(WOOCOMMERCE_STOCK_FILE), false);
    if (json_last_error() != JSON_ERROR_NONE) {
        file_put_contents(WOOCOMMERCE_STOCK_FILE, '');
        $current_woocommerce_stock_data = NULL;
    }
    $current_woocommerce_stock_array = array();
    if ($current_woocommerce_stock_data != NULL) {
        foreach ($current_woocommerce_stock_data as $item) {
            array_push($current_woocommerce_stock_array, map_to_stock_item($item));
        }
    }
    return $current_woocommerce_stock_array;
}