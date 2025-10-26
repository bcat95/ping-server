<?php

/*
curl --location --request POST 'https://ping.cochaykhong.com/ping-server/' \
--header 'Content-Type: application/x-www-form-urlencoded' \
--data-urlencode 'type=website' \
--data-urlencode 'target=https://example.com/' \
--data-urlencode 'port=0' \
--data-urlencode 'settings={"timeout_seconds":5,"verify_ssl_is_enabled":true,"follow_redirects":true,"request_method":"GET"}' \
--data-urlencode 'user_agent=66uptime-curl-test'
*/

/* Công cụ debug nếu cần */
if (isset($_POST['debug'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

define('ROOT_PATH', realpath(__DIR__) . '/');

/* Tự động tải các thư viện từ vendor */
require_once ROOT_PATH . 'vendor/autoload.php';
require_once ROOT_PATH . 'CustomPing.php';

/* Kiểm tra lỗi tiềm năng */
if (empty($_POST)) {
    die();
}

$required = [
    'type',
    'target',
    'port',
    'settings'
];

foreach ($required as $required_field) {
    if (!isset($_POST[$required_field])) {
        die();
    }
}

/* Định nghĩa một số biến cần thiết */
$_POST['settings'] = json_decode($_POST['settings']);

$error = null;

switch ($_POST['type']) {

    /* Sử dụng Fsockopen */
    case 'port':

        $ping = new \Altum\Helpers\CustomPing($_POST['target']);
        $ping->setTimeout($_POST['settings']->timeout_seconds ?? 5);
        $ping->setPort($_POST['port']);
        $latency = $ping->ping('fsockopen');

        if ($latency !== false) {
            $response_status_code = 0;
            $response_time = $latency;
            $is_ok = 1;
        } else {
            $response_status_code = 0;
            $response_time = 0;
            $is_ok = 0;
        }

        break;

    /* Kiểm tra Ping */
    case 'ping':

        $ping = new \Altum\Helpers\CustomPing($_POST['target']);
        $ping->setTimeout($_POST['settings']->timeout_seconds ?? 5);
        $ping->set_ipv($_POST['settings']->ping_ipv ?? 'ipv4');
        $latency = $ping->ping($_POST['ping_method']);

        if ($latency !== false) {
            $response_status_code = 0;
            $response_time = $latency;
            $is_ok = 1;
        } else {
            $response_status_code = 0;
            $response_time = 0;
            $is_ok = 0;
        }

        break;

    /* Kiểm tra website */
    case 'website':

        // --- BẮT ĐẦU: KHỞI TẠO CHẠM ĐOÁN ---
        $verbose_log_resource = fopen('php://temp', 'w+');
        $max_retries = 3; // 1 lần thử ban đầu + 2 lần thử lại

        /* Thiết lập timeout */
        \Unirest\Request::timeout($_POST['settings']->timeout_seconds ?? 5);

        /* Thiết lập theo dõi chuyển hướng và BẬT chế độ verbose */
        \Unirest\Request::curlOpts([
            CURLOPT_FOLLOWLOCATION => $_POST['settings']->follow_redirects ?? true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_VERBOSE        => true,
            CURLOPT_STDERR         => $verbose_log_resource,
        ]);
        // --- KẾT THÚC: KHỞI TẠO CHẠM ĐOÁN ---

        $is_ok = 0;
        $response_status_code = 0;
        $response_time = 0;
        $response_body = null;
        $error = null;

        // --- BẮT ĐẦU: VÒNG LẶP THỬ LẠI ---
        $attempts_made = 0; // Biến theo dõi số lần thử
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            try {
                // Tăng biến theo dõi số lần thử
                $attempts_made = $attempt;

                // Reset log verbose sau mỗi lần thử
                rewind($verbose_log_resource);
                ftruncate($verbose_log_resource, 0);

                /* Cache buster */
                $target = $_POST['target']; // Dùng biến cục bộ để tránh chỉnh sửa bản gốc
                if ($_POST['settings']->cache_buster_is_enabled ?? false) {
                    $query = parse_url($target, PHP_URL_QUERY);
                    $target .= ($query ? '&' : '?') . 'cache_buster=' . mb_substr(md5(time() . rand()), 0, 8);
                }

                /* Xác minh SSL */
                \Unirest\Request::verifyPeer($_POST['settings']->verify_ssl_is_enabled ?? true);

                /* Thiết lập xác thực */
                \Unirest\Request::auth($_POST['settings']->request_basic_auth_username ?? '', $_POST['settings']->request_basic_auth_password ?? '');

                /* Thực hiện yêu cầu đến website */
                $method = mb_strtolower($_POST['settings']->request_method ?? 'get');

                /* Chuẩn bị headers cho yêu cầu */
                $request_headers = [];

                /* Thiết lập User Agent tùy chỉnh */
                if (isset($_POST['user_agent']) && $_POST['user_agent']) {
                    $request_headers['User-Agent'] = $_POST['user_agent'];
                }

                foreach ($_POST['settings']->request_headers ?? [] as $request_header) {
                    $request_headers[$request_header->name] = $request_header->value;
                }

                /* Fix lỗi trong thư viện Unirest PHP cho các yêu cầu HEAD */
                if ($method == 'head') {
                    \Unirest\Request::curlOpt(CURLOPT_NOBODY, true);
                }

                if (in_array($method, ['post', 'put', 'patch'])) {
                    $response = \Unirest\Request::{$method}($target, $request_headers, $_POST['settings']->request_body ?? '');
                } else {
                    $response = \Unirest\Request::{$method}($target, $request_headers);
                }

                /* Xóa các thiết lập tùy chỉnh */
                \Unirest\Request::clearCurlOpts();

                /* Lấy thông tin sau yêu cầu */
                $info = \Unirest\Request::getInfo();

                /* Một số biến cần thiết */
                $response_status_code = $info['http_code'];
                $response_time = $info['total_time'] * 1000;

                /* Kiểm tra phản hồi để xem kết quả */
                $is_ok = 1;

                $_POST['settings']->response_status_code = $_POST['settings']->response_status_code ?? 200;
                if (
                    (is_array($_POST['settings']->response_status_code) && !in_array($response_status_code, $_POST['settings']->response_status_code))
                    || (!is_array($_POST['settings']->response_status_code) && $response_status_code != ($_POST['settings']->response_status_code ?? 200))
                ) {
                    $is_ok = 0;
                    $error = ['type' => 'response_status_code'];
                }

                if (($_POST['settings']->response_body ?? '') && mb_strpos($response->raw_body, ($_POST['settings']->response_body ?? '')) === false) {
                    $is_ok = 0;
                    $error = ['type' => 'response_body'];
                    $response_body = $response->raw_body;
                }

                foreach ($_POST['settings']->response_headers ?? [] as $response_header) {
                    $response_header->name = mb_strtolower($response_header->name);

                    if (!isset($response->headers[$response_header->name]) || (isset($response->headers[$response_header->name]) && $response->headers[$response_header->name] != $response_header->value)) {
                        $is_ok = 0;
                        $error = ['type' => 'response_header'];
                        break;
                    }
                }

                // Nếu yêu cầu thành công (kể cả response code không như mong muốn), thoát khỏi vòng lặp
                break;
            } catch (\Exception $exception) {
                $curl_handle = \Unirest\Request::getCurlHandle();
                $curl_error_code = curl_errno($curl_handle);
                $curl_error_message = curl_error($curl_handle);

                // Kiểm tra nếu timeout DNS và còn thử lại được
                $is_dns_timeout = ($curl_error_code == CURLE_OPERATION_TIMEDOUT && strpos($curl_error_message, 'Resolving timed out') !== false);

                if ($is_dns_timeout && $attempt < $max_retries) {
                    // Là timeout DNS, nhưng có thể thử lại được. Tiếp tục thử lần sau.
                    continue;
                }

                // Không phải timeout DNS hoặc đây là lần thử cuối cùng. Chuẩn bị kết quả lỗi cuối cùng.
                $response_status_code = 0;
                $response_time = 0;

                $curl_info = curl_getinfo($curl_handle);
                rewind($verbose_log_resource);
                $verbose_log = stream_get_contents($verbose_log_resource);
                fclose($verbose_log_resource);

                // Phân tích để xác định giai đoạn gây timeout
                $timeout_stage = 'unknown';
                if ($curl_error_code == CURLE_OPERATION_TIMEDOUT) {
                    // Ưu tiên kiểm tra thông báo lỗi để xác định DNS timeout
                    if (strpos($curl_error_message, 'Resolving timed out') !== false) {
                        $timeout_stage = 'dns_lookup';
                    } else {
                        // Dựa vào phân tích thời gian cho các loại timeout khác
                        $total_time = $curl_info['total_time'];
                        $threshold = $total_time * 0.9;
                        if ($curl_info['namelookup_time'] >= $threshold) {
                            $timeout_stage = 'dns_lookup';
                        } elseif ($curl_info['connect_time'] >= $threshold) {
                            $timeout_stage = 'tcp_connect';
                        } elseif ($curl_info['pretransfer_time'] >= $threshold) {
                            $timeout_stage = 'ssl_handshake';
                        } elseif ($curl_info['starttransfer_time'] >= $threshold) {
                            $timeout_stage = 'server_response_ttfb';
                        } else {
                            $timeout_stage = 'data_transfer';
                        }
                    }
                }

                $error = [
                    'type'          => 'exception',
                    'code'          => $curl_error_code,
                    'message'       => $curl_error_message,
                    'attempts'      => $attempt,
                    'diagnostics'  => [
                        'timeout_stage' => $timeout_stage,
                        'curl_info'     => [
                            'namelookup_time_ms'   => round($curl_info['namelookup_time'] * 1000, 2),
                            'connect_time_ms'      => round($curl_info['connect_time'] * 1000, 2),
                            'pretransfer_time_ms'  => round($curl_info['pretransfer_time'] * 1000, 2),
                            'starttransfer_time_ms' => round($curl_info['starttransfer_time'] * 1000, 2),
                            'total_time_ms'        => round($curl_info['total_time'] * 1000, 2),
                            'primary_ip'           => $curl_info['primary_ip'],
                            'local_ip'             => $curl_info['local_ip'],
                            'http_code'            => $curl_info['http_code'],
                        ],
                        'verbose_log'   => $verbose_log,
                    ]
                ];
                $is_ok = 0;
                break; // Thoát khỏi vòng lặp khi gặp lỗi cuối cùng
            }
        }
        // --- KẾT THÚC: VÒNG LẶP THỬ LẠI ---

        break;
}

/* Chuẩn bị phản hồi */
$response = [
    'is_ok' => $is_ok,
    'response_time' => $response_time,
    'response_status_code' => $response_status_code,
    'response_body' => $response_body ?? null,
    'error' => $error,
    'attempts_made' => $attempts_made,
];

echo json_encode($response);

die();
