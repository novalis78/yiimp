<?php

define('BASE_URL', 'https://api.dex-trade.com/v1');

function dex_trade_api_query($endpoint, $params = [], $method = 'GET') {
    $url = BASE_URL . '/' . $endpoint;
    $headers = [];
    $ch = curl_init();

    require_once('/etc/yiimp/keys.php');
    if (!defined('EXCH_DEXTRADE_SECRET')) define('EXCH_DEXTRADE_SECRET', '');

    // optional secret key
    if (empty(EXCH_DEXTRADE_SECRET) && strpos($method, 'public') === FALSE) return FALSE;
    if (empty(EXCH_DEXTRADE_KEY) && strpos($method, 'public') === FALSE) return FALSE;

    $apikey = EXCH_DEXTRADE_KEY; // your API-key
    $apisecret = EXCH_DEXTRADE_SECRET; // your Secret-key


    if ($method === 'POST') {
        $params['request_id'] = time();
        ksort($params); // Sort parameters by key
        $query = http_build_query($params);
        $signature = hash_hmac('sha256', $query, $apikey);
        $headers = [
            'X-Auth-Token: ' . $apikey,
            'X-Auth-Sign: ' . $signature,
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    } else {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response);
}

// Example usage of the API

// Public API to get ticker info
function get_ticker_info($pair) {
    return dex_trade_api_query('public/ticker', ['pair' => $pair], 'GET');
}

// Private API to create an order
function create_order($pair, $volume, $rate, $type_trade, $trade_type) {
    $params = [
        'pair' => $pair,
        'volume' => $volume,
        'rate' => $rate,
        'type_trade' => $type_trade, // 0: limit, 1: market, etc.
        'type' => $trade_type // 0: buy, 1: sell
    ];
    return dex_trade_api_query('private/create-order', $params, 'POST');
}

// Example function calls
print_r(get_ticker_info('BTCUSD'));
print_r(create_order('BTCUSD', 1, 50000, 0, 0)); // Create a buy limit order

?>

