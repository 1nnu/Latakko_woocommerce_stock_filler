<?php

require("./logging_service.php");

define("LATAKKO_FILE", "latakko.txt");
define("LATAKKO_STOCK_FILE", "stock.json");
define("TOKEN_ENDPOINT", "https://api.latakko.eu/Token");
define("IMAGE_ENDPOINT", "https://api.latakko.eu/api/ArticleImages/");
define("STOCK_ENDPOINT", "https://api.latakko.eu/api/Articles?OnlyStockItems=false&OnlyLocalStockItems=true&IncludeCarTyres=true&IncludeMotorcycleTyres=true&IncludeTruckTyres=true&IncludeEarthmoverTyres=true&IncludeAlloyRims=false&IncludeSteelRims=false&IncludeAccessories=false&IncludeOils=false&IncludeBatteries=false");

/*
    Check for existance of latakko api access file,
    if not expired, then continue, else request new access
*/
function check_api_key($api_user, $api_key){
    if (file_exists(LATAKKO_FILE)) {
        $contents = file_get_contents(LATAKKO_FILE);
        $values = json_decode($contents, true);

        // Check for valid JSON decode
        if (json_last_error() !== JSON_ERROR_NONE) {
            request_new_api_access($api_user, $api_key);
            return;
        }

        // Parse expiration date from the file
        $expiration_date = $values['.expires'] ?? null;

        if (!$expiration_date) {
            request_new_api_access($api_user, $api_key); // No expiration date found, request new API access
            return;
        }

        // Create DateTime objects for comparison
        try {
            $expirationDateTime = new DateTime($expiration_date);
            $currentDateTime = new DateTime();  // Current date and time
        } catch (Exception $e) {
            request_new_api_access($api_user, $api_key); // Invalid date format, request new API access
            return;
        }

        // Check if the API key expires tomorrow
        $tomorrowDateTime = (clone $currentDateTime)->modify('+1 day');

        if ($expirationDateTime > $currentDateTime && $expirationDateTime <= $tomorrowDateTime) {
            // API key expires tomorrow, request new API access
            request_new_api_access($api_user, $api_key);
        }
    } else {
        // File doesn't exist, request new API access
        request_new_api_access($api_user, $api_key);
    }
}

function request_new_api_access($api_user, $api_key)
{
    $ch = curl_init();
    $curl_opts = array(
        CURLOPT_URL => TOKEN_ENDPOINT,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "grant_type=password&username=$api_user&password=$api_key",
        CURLOPT_RETURNTRANSFER => true,
    );
    curl_setopt_array($ch, $curl_opts);
    $result = curl_exec($ch);

    if (!$result) { // Log error if request failed
        log_event("Error occured with generating api access");
    }
    curl_close($ch);
    file_put_contents(LATAKKO_FILE, $result);
    return;
}

function get_latakko_stock_json()
{
    $ch = curl_init();
    $credentials = get_latakko_credentials();
    var_dump($credentials["access_token"]);
    $curl_opts = array(
        CURLOPT_URL => STOCK_ENDPOINT,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => array('Authorization: bearer ' . $credentials["access_token"]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 120,
    );
    curl_setopt_array($ch, $curl_opts);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (!$result && $http_code != 500) { // Log error if request failed
        log_event("Error occured while fetching stock info");
        throw new Exception("Couldn't get stock");
    }

    file_put_contents(LATAKKO_STOCK_FILE, $result);
    curl_close($ch);
}

function download_photos_from_latakko_api(array $image_ids, $directory)
{
    $ch = curl_init();
    $credentials = get_latakko_credentials();
    foreach ($image_ids as $image_id) {
        $curl_opts = array(
            CURLOPT_URL => IMAGE_ENDPOINT . strval($image_id),
            CURLOPT_HTTPHEADER => array('Authorization: bearer ' . $credentials["access_token"]),
            CURLOPT_RETURNTRANSFER => true,
        );
        curl_setopt_array($ch, $curl_opts);

        $result = curl_exec($ch);


        if (!$result) {
            log_event("Error occured while fetching image");
            throw new Exception("Image fetch error");
        }
        file_put_contents($directory . '/' . strval($image_id) . ".jpg", $result);
    }
    curl_close($ch);
    $fi = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
    printf("There were %d Files\n", iterator_count($fi)); // DEBUG: Know how many files are present in uploads dir
}

function get_latakko_credentials()
{
    $latakko_file = file_get_contents(LATAKKO_FILE);
    return json_decode($latakko_file, true);
}