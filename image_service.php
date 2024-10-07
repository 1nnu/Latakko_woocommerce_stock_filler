<?php

require_once("./api_service.php");

$config = require('./config.php');
$directory = $config['photos_dir'];
$store_url = $config['store_url'];
$wp_user = $config['wp_user'];
$wp_key = $config['wp_key'];
$no_image_url = $config['no_image_url'];
function check_new_images()
{
    echo "INFO: Starting check for missing images\n";
    global $directory;
    if (!is_dir($directory)) {
        mkdir($directory);
    }
    $existing_image_file_ids = array();
    foreach (glob($directory . '/*') as $file_name) {
        $image_id = (int) (basename($file_name, ".jpg"));
        array_push($existing_image_file_ids, $image_id);
    }
    $stock_data = json_decode(file_get_contents(LATAKKO_STOCK_FILE), false);
    $missing_stock_image_ids = array();
    foreach ($stock_data as $item) {
        if (!in_array($item->ImageId, $existing_image_file_ids) && !in_array($item->ImageId, $missing_stock_image_ids)) {
            if ($item->ImageId === null) {
                continue;
            }
            array_push($missing_stock_image_ids, $item->ImageId);
        }
    }
    download_photos_from_latakko_api($missing_stock_image_ids, $directory);
    check_no_image_exists($directory);
    echo "INFO: Images fetched\n";
    echo "INFO: Add no-image.png if missing\n";
    add_no_image_png_to_library($directory);
    echo "INFO: Starting wordpress library check\n";
    $required_images = filter_for_new_images((get_all_image_details_from_wordpress_library()), $existing_image_file_ids);
    upload_images_to_wordpress_library($directory, $required_images);
    echo "INFO: Ended wordpress library check\n";
    return;
}

function check_no_image_exists($directory){
    global $no_image_url;
    if (!glob($directory . '/no-image.*')){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $no_image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        if (!$result){
            throw new Exception("Couldn't fetch no-image.png");
        }
        file_put_contents($directory."no-image.png", $result);
    }
}

function get_image_id_of_missing_image()
{
    global $store_url, $wp_user, $wp_key;
    $headers = [
        'Authorization: Basic ' . base64_encode($wp_user . ':' . $wp_key),
    ];
    $ch = curl_init();
    $get_url = $store_url . "/wp-json/wp/v2/media?search=no-image";
    curl_setopt($ch, CURLOPT_URL, $get_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($result, true);
    if ($result) {
        foreach ($result as $res) {
            return $res["id"];
        }
    }
}

function add_no_image_png_to_library($directory)
{
    global $store_url, $wp_user, $wp_key;
    $headers = [
        'Authorization: Basic ' . base64_encode($wp_user . ':' . $wp_key),
    ];
    $ch = curl_init();
    $get_url = $store_url . "/wp-json/wp/v2/media?search=no-image";
    curl_setopt($ch, CURLOPT_URL, $get_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($result, true);
    if (!$result) {
        $file_path = glob($directory . "/no-image.*");
        $filename = basename($file_path[0]);
        $upload_url = $store_url . '/wp-json/wp/v2/media';
        $file_data = file_get_contents($file_path[0]);
        $headers = [
            'Authorization: Basic ' . base64_encode($wp_user . ':' . $wp_key),
            'Content-Disposition: attachment; filename=' . $filename,
            'Content-Type: image/png'
        ];

        $response = wp_rest_upload($upload_url, $file_data, $headers);

        if ($response) {
            echo "INFO: Uploaded image {$filename} to WordPress.\n";
        } else {
            echo "ERROR: Failed to upload image {$filename}.\n";
        }

    }
}


function get_image_id_by_slug($image_slug)
{
    echo "DEBUG: finding image for $image_slug\n";
    global $store_url, $wp_user, $wp_key;
    $headers = [
        'Authorization: Basic ' . base64_encode($wp_user . ':' . $wp_key),
    ];
    $ch = curl_init();
    $get_url = $store_url . "/wp-json/wp/v2/media?slug=$image_slug";
    curl_setopt($ch, CURLOPT_URL, $get_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($result, true);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code != 200 || empty($result)) {
        echo "ERROR: image with slug not found";
        return false;
    } else {
        foreach ($result as $res) {
            return $res["id"];
        }
    }
}

function filter_for_new_images($library_image_ids, $existing_image_file_ids)
{
    $required_images = array();
    $library_image_ids = array_map('intval', $library_image_ids);
    foreach ($existing_image_file_ids as $existing_image_id) {
        if (!in_array($existing_image_id, $library_image_ids)) {
            array_push($required_images, $existing_image_id);
        }
    }
    return $required_images;
}


function get_all_image_details_from_wordpress_library()
{
    global $store_url, $wp_user, $wp_key;
    $headers = [
        'Authorization: Basic ' . base64_encode($wp_user . ':' . $wp_key),
    ];
    return wp_rest_get_items_list($store_url, $headers);
}

function wp_rest_get_items_list($store_url, $headers)
{
    $ch = curl_init();
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $page_number = 1;
    $library_image_ids = array();
    while (true) {
        $get_url = $store_url . "/wp-json/wp/v2/media?page=$page_number&per_page=100";
        curl_setopt($ch, CURLOPT_URL, $get_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code === 400) {
            break;
        }
        $data = json_decode($result, true);
        if (empty($data)) {
            break;
        }
        foreach ($data as $library_image) {
            array_push($library_image_ids, $library_image['slug']);
        }
        $page_number += 1;
    }
    curl_close($ch);
    return $library_image_ids;
}

function upload_images_to_wordpress_library($directory, $images_to_upload)
{
    global $store_url, $wp_user, $wp_key;

    foreach ($images_to_upload as $image_id) {
        $file_path = glob($directory . "/$image_id.*");
        if (!empty($file_path)) {
            $filename = basename($file_path[0]); // Use the first match
        } else {
            echo "ERROR: Image not found for ID: $image_id.\n";
            continue;
        }

        $upload_url = $store_url . '/wp-json/wp/v2/media';
        $file_data = file_get_contents($file_path[0]);
        $headers = [
            'Authorization: Basic ' . base64_encode($wp_user . ':' . $wp_key),
            'Content-Disposition: attachment; filename=' . $filename,
            'Content-Type: image/jpeg'
        ];

        $response = wp_rest_upload($upload_url, $file_data, $headers);

        if ($response) {
            echo "INFO: Uploaded image {$filename} to WordPress.\n";
        } else {
            echo "ERROR: Failed to upload image {$filename}.\n";
        }
    }
}

// Helper function to make the REST API call
function wp_rest_upload($url, $file_data, $headers)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $file_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    echo $result;
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code === 201; // 201 Created is the success code for file uploads
}

function get_public_images_url()
{
    global $directory;
    return $directory;
}