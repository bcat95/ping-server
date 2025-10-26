
# Ping Server

A lightweight and robust PHP server endpoint for monitoring the status of websites, servers, and network ports. It provides detailed diagnostics, including an automatic retry mechanism for transient DNS failures, making it ideal for uptime monitoring services.

## Features

-   **Multi-type Checks**: Supports `website`, `port`, and `ping` checks.
-   **Detailed Diagnostics**: On failure, provides in-depth information including the timeout stage (`dns_lookup`, `tcp_connect`, `ssl_handshake`, etc.), cURL timing details, and a full verbose log.
-   **Automatic Retry for DNS**: Intelligently retries up to 2 additional times if a DNS resolution timeout occurs, reducing false alarms from temporary network issues.
-   **Flexible Website Checks**:
    -   Custom request methods (`GET`, `POST`, `PUT`, `PATCH`, `HEAD`).
    -   Custom headers and user agents.
    -   SSL certificate verification control.
    -   Redirect following.
    -   Response body and header validation.
-   **Easy Integration**: Simple `POST` request with a JSON response.

## Installation

### Prerequisites

-   PHP 7.4 or higher
-   Composer
-   A web server (Apache, Nginx, etc.)

### Steps

1.  **Clone the repository**
    ```bash
    git clone https://github.com/your-username/ping-server.git
    cd ping-server
    ```

2.  **Install dependencies**
    ```bash
    composer install
    ```

3.  **Configure your web server**
    Point your web server's document root to the `ping-server` directory. Ensure that the `ping-server/` directory (or the specific PHP file) is accessible via a URL.

## Usage

Send a `POST` request to the endpoint where you deployed the script (e.g., `https://YOUR_HOST/ping-server/`).

### Parameters

| Parameter      | Type   | Required | Description                                                              |
| -------------- | ------ | -------- | ------------------------------------------------------------------------ |
| `type`         | string | Yes      | The type of check: `website`, `port`, or `ping`.                          |
| `target`       | string | Yes      | The target URL (for `website`) or hostname/IP (for `port`, `ping`).       |
| `port`         | int    | Yes      | The port number. Use `0` for `website` and `ping` checks.                 |
| `settings`     | string | Yes      | A JSON-encoded string with detailed settings for the check.               |
| `user_agent`   | string | No       | A custom User-Agent string to use for the request.                        |
| `debug`        | int    | No       | Set to `1` to enable PHP error display for debugging.                    |

### `settings` Object (JSON)

The `settings` parameter must be a JSON-encoded string.

| Key                              | Type    | Default   | Description                                                                          |
| -------------------------------- | ------- | --------- | ------------------------------------------------------------------------------------ |
| `timeout_seconds`                | int     | `5`       | The timeout in seconds for the entire request.                                        |
| `verify_ssl_is_enabled`          | boolean | `true`    | Whether to verify the SSL certificate for `website` checks.                           |
| `follow_redirects`               | boolean | `true`    | Whether to follow HTTP redirects.                                                     |
| `request_method`                 | string  | `get`     | HTTP method for `website` checks (`get`, `post`, `put`, `patch`, `head`).             |
| `request_body`                   | string  | `null`    | The body for `POST`, `PUT`, `PATCH` requests.                                        |
| `request_headers`                | array   | `[]`      | An array of objects with `name` and `value` for custom request headers.              |
| `request_basic_auth_username`    | string  | `null`    | Username for Basic Authentication.                                                    |
| `request_basic_auth_password`    | string  | `null`    | Password for Basic Authentication.                                                    |
| `response_status_code`           | int/array | `200`   | Expected HTTP status code(s). A single int or an array of ints.                       |
| `response_body`                  | string  | `null`    | A string that must be present in the response body for the check to be successful.    |
| `response_headers`               | array   | `[]`      | An array of objects with `name` and `value` for required response headers.           |
| `ping_ipv`                       | string  | `ipv4`    | IP version for `ping` checks (`ipv4` or `ipv6`).                                      |
| `ping_method`                    | string  | `exec`    | Method for `ping` checks (`exec`, `fsockopen`).                                       |

---

## Examples

### 1. Basic Website Check

This is the example you provided. It checks if `https://google.com` is accessible and returns a `200` status code within 5 seconds.

```bash
curl --location --request POST 'https://YOUR_HOST/ping-server/' \
--header 'Content-Type: application/x-www-form-urlencoded' \
--data-urlencode 'type=website' \
--data-urlencode 'target=https://google.com' \
--data-urlencode 'port=0' \
--data-urlencode 'settings={"timeout_seconds":5,"verify_ssl_is_enabled":true,"follow_redirects":true,"request_method":"GET"}' \
--data-urlencode 'user_agent=66uptime-curl-test'
```

### 2. Port Check

Checks if port `443` is open on `github.com`.

```bash
curl --location --request POST 'https://YOUR_HOST/ping-server/' \
--header 'Content-Type: application/x-www-form-urlencoded' \
--data-urlencode 'type=port' \
--data-urlencode 'target=github.com' \
--data-urlencode 'port=443' \
--data-urlencode 'settings={"timeout_seconds":3}'
```

### 3. Advanced Website Check (POST with Custom Header)

Checks an API endpoint by sending a `POST` request with a JSON body and a custom `Authorization` header. It expects a `201` status code.

```bash
curl --location --request POST 'https://YOUR_HOST/ping-server/' \
--header 'Content-Type: application/x-www-form-urlencoded' \
--data-urlencode 'type=website' \
--data-urlencode 'target=https://api.example.com/v1/resource' \
--data-urlencode 'port=0' \
--data-urlencode 'settings={"timeout_seconds":10,"request_method":"POST","request_headers":[{"name":"Authorization","value":"Bearer YOUR_TOKEN"},{"name":"Content-Type","value":"application/json"}],"response_status_code":201}' \
--data-urlencode 'request_body={"key":"value"}'
```

---

## Response Format

The server always responds with a JSON object.

### Successful Response

```json
{
    "is_ok": 1,
    "response_time": 145.52,
    "response_status_code": 200,
    "response_body": null,
    "error": null,
    "attempts_made": 1
}
```

### Failed Response (with Diagnostics)

This example shows a failure due to an SSL handshake timeout after 1 attempt.

```json
{
    "is_ok": 0,
    "response_time": 0,
    "response_status_code": 0,
    "response_body": null,
    "error": {
        "type": "exception",
        "code": 35,
        "message": "OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to example.com:443",
        "attempts": 1,
        "diagnostics": {
            "timeout_stage": "ssl_handshake",
            "curl_info": {
                "namelookup_time_ms": 25.12,
                "connect_time_ms": 120.45,
                "pretransfer_time_ms": 5001.23,
                "starttransfer_time_ms": 0,
                "total_time_ms": 5001.25,
                "primary_ip": "93.184.216.34",
                "local_ip": "192.168.1.10",
                "http_code": 0
            },
            "verbose_log": "*   Trying 93.184.216.34:443...\n*   Connected to example.com...\n* ALPN, offering h2...\n* SSL connection using TLSv1.3 ...\n* OpenSSL SSL_connect: SSL_ERROR_SYSCALL...\n* Closing connection 0\n"
        }
    },
    "attempts_made": 1
}
```

### Diagnostics Breakdown

-   `attempts_made`: The total number of attempts made (1 to 3).
-   `error.type`: The category of error (`exception`).
-   `error.code`: The cURL error code (e.g., `28` for timeout, `35` for SSL connect error).
-   `error.message`: The human-readable error message.
-   `error.diagnostics.timeout_stage`: The specific stage where the failure occurred. Possible values:
    -   `dns_lookup`: Failed to resolve the domain name.
    -   `tcp_connect`: Failed to establish a TCP connection.
    -   `ssl_handshake`: Failed during the SSL/TLS handshake.
    -   `server_response_ttfb`: Connected, but the server took too long to send the first byte (Time To First Byte).
    -   `data_transfer`: Timeout occurred while receiving data.
-   `error.diagnostics.curl_info`: Detailed timing breakdown from cURL in milliseconds.
-   `error.diagnostics.verbose_log`: The complete, low-level log of the entire request, invaluable for deep debugging.
