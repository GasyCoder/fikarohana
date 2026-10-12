<?php

// Test-only loopback transport harness; no outbound request, TLS override or application route.
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (! $server) {
    exit(1);
}
echo stream_socket_get_name($server, false).PHP_EOL;
flush();
$socket = stream_socket_accept($server, 5);
if (! $socket) {
    fclose($server);
    exit(1);
}
stream_set_timeout($socket, 2);
while (($line = fgets($socket, 4096)) !== false && trim($line) !== '') {
    // Drain this one request's headers; no input is evaluated or echoed.
}
$mode = $argv[1];
$headers = "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nConnection: close\r\n";
if ($mode === 'chunked') {
    $headers .= "Transfer-Encoding: chunked\r\n";
} elseif ($mode === 'compressed') {
    $headers .= "Content-Encoding: gzip\r\n";
}
fwrite($socket, $headers."\r\n");
// Four MiB maximum; finite runtime. Small paced chunks prove abort occurs during acquisition.
$chunk = str_repeat('x', 4096);
for ($index = 0; $index < 1024; $index++) {
    $data = $mode === 'chunked' ? dechex(strlen($chunk))."\r\n".$chunk."\r\n" : $chunk;
    if (@fwrite($socket, $data) === false || feof($socket)) {
        break;
    }
    usleep(1000);
}
if ($mode === 'chunked') {
    @fwrite($socket, "0\r\n\r\n");
}
fclose($socket);
fclose($server);
