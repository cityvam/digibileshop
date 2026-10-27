<?php
header('Content-Type: text/plain; charset=utf-8');

echo "--- Gateway Diagnostics ---\n";

$host = 'sepehr.shaparak.ir';
echo "Checking DNS for $host...\n";

$ip = gethostbyname($host);
if ($ip === $host) {
    echo "ERROR: Could not resolve host $host. This is a DNS issue on your server.\n";
} else {
    echo "SUCCESS: Host $host resolved to $ip\n";
}

$ports = [8080, 8081, 443];
foreach ($ports as $port) {
    echo "Testing connection to $host on port $port...\n";
    $connection = @fsockopen($host, $port, $errno, $errstr, 5);
    if (is_resource($connection)) {
        echo "SUCCESS: Port $port is OPEN\n";
        fclose($connection);
    } else {
        echo "ERROR: Port $port is CLOSED or BLOCKED. ($errno: $errstr)\n";
    }
}

echo "\n--- CURL Test ---\n";
$ch = curl_init("https://$host:8081/V1/PeymentApi/GetToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$error = curl_error($ch);
$info = curl_getinfo($ch);

if ($error) {
    echo "CURL ERROR: $error\n";
} else {
    echo "CURL SUCCESS: Received HTTP code " . $info['http_code'] . "\n";
}
curl_close($ch);
