# ping-server

curl --location --request POST 'https://YOUR_HOST' \
--header 'Content-Type: application/x-www-form-urlencoded' \
--data-urlencode 'type=website' \
--data-urlencode 'target=https://google.com' \
--data-urlencode 'port=0' \
--data-urlencode 'settings={"timeout_seconds":5,"verify_ssl_is_enabled":true,"follow_redirects":true,"request_method":"GET"}' \
--data-urlencode 'user_agent=66uptime-curl-test' \
--data-urlencode 'debug=1'
