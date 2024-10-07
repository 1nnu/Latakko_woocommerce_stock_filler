<?php
error_reporting(E_ALL); // DEBUG: set error reporting to all
require_once('./api_service.php');
require_once('./logging_service.php');
require_once('./image_service.php');
require_once('./woocommerce_service.php');
$config = require('./config.php');

try {
    global $config;
    function update_stock(array $config)
    {
        echo "Main started\n";

        if (!isset($config['api_user'], $config['api_key'])) {
            throw new Exception('API user or API key not set in config.');
        }

        // Use the API service to check the API key
        check_api_key($config['api_user'], $config['api_key']);

        try {
            echo "DEBUG: GETTING STOCK\n";
            get_latakko_stock_json();
            echo "DEBUG: STOCK RETRIEVED\n";
            echo "DEBUG: CHECKING FOR IMAGES\n";
            check_new_images();
            echo "DEBUG: IMAGES UPDATES\n";
            echo "DEBUG: UPDATING CATEGORIES\n";
            update_product_categories();
            echo "DEBUG: CATEGORIES UPDATED\n";
            echo "DEBUG: UPDATING ATTRIBUTES\n";
            check_product_attributes();
            echo "DEBUG: ATTRIBUTES UPDATED\n";
            echo "DEBUG: STARTING WOOCOMMERCE UPDATE\n";
            update_woocommerce_stock();
            echo "DEBUG: WOOCOMMERCE STOCK UPDATED\n";
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . "\n";
            exit(1);
        }


        echo "Main stopped\n";
    }

    update_stock($config);

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}