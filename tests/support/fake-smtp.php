<?php
/**
 * A minimal SMTP server for the Mailer test. Accepts one connection on a free
 * port, writes the port to the first argument and everything the client sends
 * to the second, answers like a friendly server and exits after QUIT.
 *
 *     php fake-smtp.php <port file> <log file>
 */
[$script, $portFile, $logFile] = $argv;
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "fake-smtp: $errstr\n");
    exit(1);
}
$port = (int)substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
file_put_contents($logFile, '');
file_put_contents($portFile, (string)$port);

$client = @stream_socket_accept($server, 10);
if (!$client) {
    exit(1);
}
$log = function (string $line) use ($logFile) {
    file_put_contents($logFile, $line . "\n", FILE_APPEND);
};
$reply = function (string $line) use ($client) {
    fwrite($client, $line . "\r\n");
};
$reply('220 fake-smtp ESMTP');
$inData = false;
while (($line = fgets($client)) !== false) {
    $line = rtrim($line, "\r\n");
    $log($line);
    if ($inData) {
        if ($line === '.') {
            $inData = false;
            $reply('250 2.0.0 queued');
        }
        continue;
    }
    $upper = strtoupper($line);
    if (str_starts_with($upper, 'EHLO')) {
        $reply('250-fake-smtp');
        $reply('250-AUTH LOGIN PLAIN');
        $reply('250 OK');
    } elseif (str_starts_with($upper, 'HELO')) {
        $reply('250 fake-smtp');
    } elseif ($upper === 'AUTH LOGIN') {
        $reply('334 VXNlcm5hbWU6');
        $log(rtrim((string)fgets($client), "\r\n")); // user name
        $reply('334 UGFzc3dvcmQ6');
        $log(rtrim((string)fgets($client), "\r\n")); // password
        $reply('235 2.7.0 authenticated');
    } elseif (str_starts_with($upper, 'AUTH PLAIN')) {
        $reply('235 2.7.0 authenticated');
    } elseif (str_starts_with($upper, 'MAIL FROM') || str_starts_with($upper, 'RCPT TO')) {
        $reply('250 2.1.0 OK');
    } elseif ($upper === 'DATA') {
        $inData = true;
        $reply('354 go ahead');
    } elseif ($upper === 'QUIT') {
        $reply('221 2.0.0 bye');
        break;
    } else {
        $reply('250 OK');
    }
}
fclose($client);
fclose($server);
