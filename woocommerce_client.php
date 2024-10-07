<?php

$config = require('./config.php');

require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;

$woocommerce = new Client(
    $config['store_url'], // Your store URL
    $config['woocommerce_consumer_key'], // Your consumer key
    $config['woocommerce_secret_key'], // Your consumer secret
    [
        'wp_api' => true, // Enable the WP REST API integration
        'version' => 'wc/v3', // WooCommerce WP REST API version
    ]
);