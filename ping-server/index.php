<?php

/* Debugger if needed */
if(isset($_POST['debug'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

define('ROOT_PATH', realpath(__DIR__) . '/');

/* Autoload for vendor */
require_once ROOT_PATH . 'vendor/autoload.php';
require_once ROOT_PATH . 'CustomPing.php';

/* Potential error checks */
if(empty($_POST)) {
    die();
}

$required = [
    'type',
    'target',
    'port',
    'settings'
];

foreach($required as $required_field) {
    if(!isset($_POST[$required_field])) {
        die();
    }
}

/* Define some needed vars */
$_POST['settings'] = json_decode($_POST['settings']);

$error = null;

switch($_POST['type']) {

    /* Fsockopen */
    case 'port':

        $ping = new \Altum\Helpers\CustomPing($_POST['target']);
        $ping->setTimeout($_POST['settings']->timeout_seconds ?? 5);
        $ping->setPort($_POST['port']);
        $latency = $ping->ping('fsockopen');

        if($latency !== false) {

            $response_status_code = 0;
            $response_time = $latency;

            /*  :)  */
            $is_ok = 1;
        } else {

            $response_status_code = 0;
            $response_time = 0;

            /*  :)  */
            $is_ok = 0;

        }

        break;

    /* Ping check */
    case 'ping':

        $ping = new \Altum\Helpers\CustomPing($_POST['target']);
        $ping->setTimeout($_POST['settings']->timeout_seconds ?? 5);
        $ping->set_ipv($_POST['settings']->ping_ipv ?? 'ipv4');
        $latency = $ping->ping($_POST['ping_method']);

        if($latency !== false) {

            $response_status_code = 0;
            $response_time = $latency;

            /*  :)  */
            $is_ok = 1;
        } else {

            $response_status_code = 0;
            $response_time = 0;

            /*  :)  */
            $is_ok = 0;

        }

        break;

    /* Websites check */
    case 'website':

        /* Set timeout */
        \Unirest\Request::timeout($_POST['settings']->timeout_seconds ?? 5);

        /* Set follow redirects */
        \Unirest\Request::curlOpts([
            CURLOPT_FOLLOWLOCATION => $_POST['settings']->follow_redirects ?? true,
            CURLOPT_MAXREDIRS => 5,
        ]);

        try {

            /* Cache buster */
            if($_POST['settings']->cache_buster_is_enabled ?? false) {
                $query = parse_url($_POST['target'], PHP_URL_QUERY);

                $_POST['target'] .= ($query ? '&' : '?') . 'cache_buster=' . mb_substr(md5(time() . rand()), 0, 8);
            }

            /* Verify SSL */
            \Unirest\Request::verifyPeer($_POST['settings']->verify_ssl_is_enabled ?? true);

            /* Set auth */
            \Unirest\Request::auth($_POST['settings']->request_basic_auth_username ?? '', $_POST['settings']->request_basic_auth_password ?? '');

            /* Make the request to the website */
            $method = mb_strtolower($_POST['settings']->request_method ?? 'get');

            /* Prepare request headers */
            $request_headers = [];

            /* Set custom user agent */
            if(isset($_POST['user_agent']) && $_POST['user_agent']) {
                $request_headers['User-Agent'] = $_POST['user_agent'];
            }

            foreach($_POST['settings']->request_headers ?? [] as $request_header) {
                $request_headers[$request_header->name] = $request_header->value;
            }

            /* Bugfix on Unirest php library for Head requests */
            if($method == 'head') {
                \Unirest\Request::curlOpt(CURLOPT_NOBODY, true);
            }

            if(in_array($method, ['post', 'put', 'patch'])) {
                $response = \Unirest\Request::{$method}($_POST['target'], $request_headers, $_POST['settings']->request_body ?? '');
            } else {
                $response = \Unirest\Request::{$method}($_POST['target'], $request_headers);
            }

            /* Clear custom settings */
            \Unirest\Request::clearCurlOpts();

            /* Get info after the request */
            $info = \Unirest\Request::getInfo();

            /* Some needed variables */
            $response_status_code = $info['http_code'];
            $response_time = $info['total_time'] * 1000;

            /* Check the response to see how we interpret the results */
            $is_ok = 1;

            $_POST['settings']->response_status_code = $_POST['settings']->response_status_code ?? 200;
            if(
                (is_array($_POST['settings']->response_status_code) && !in_array($response_status_code, $_POST['settings']->response_status_code))
                || (!is_array($_POST['settings']->response_status_code) && $response_status_code != ($_POST['settings']->response_status_code ?? 200))
            ) {
                $is_ok = 0;
                $error = ['type' => 'response_status_code'];
            }

            if(($_POST['settings']->response_body ?? '') && mb_strpos($response->raw_body, ($_POST['settings']->response_body ?? '')) === false) {
                $is_ok = 0;
                $error = ['type' => 'response_body'];
                $response_body = $response->raw_body;
            }

            foreach($_POST['settings']->response_headers ?? [] as $response_header) {
                $response_header->name = mb_strtolower($response_header->name);

                if(!isset($response->headers[$response_header->name]) || (isset($response->headers[$response_header->name]) && $response->headers[$response_header->name] != $response_header->value)) {
                    $is_ok = 0;
                    $error = ['type' => 'response_header'];
                    break;
                }
            }

        } catch (\Exception $exception) {
            $response_status_code = 0;
            $response_time = 0;
            $error = [
                'type' => 'exception',
                'code' => curl_errno(\Unirest\Request::getCurlHandle()),
                'message' => curl_error(\Unirest\Request::getCurlHandle()),
            ];

            /*  :)  */
            $is_ok = 0;

        }

        break;
}

/* Prepare the answer */
$response = [
    'is_ok' => $is_ok,
    'response_time' => $response_time,
    'response_status_code' => $response_status_code,
    'response_body' => $response_body ?? null,
    'error' => $error,
];

echo json_encode($response);

die();
