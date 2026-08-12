<?php
/* ============================================================
   smtp.php — a small SMTP client. No libraries, no Composer.
   Used when config.php sets 'mail_method' => 'smtp'.
   ============================================================ */
declare(strict_types=1);

/**
 * @param string $to
 * @param string $headers  full header block, CRLF separated (must include To/Subject)
 * @param string $body     message body
 * @param array  $c        smtp config
 * @param string $err      filled with the conversation log on failure
 */
function smtp_send(string $to, string $headers, string $body, array $c, string &$err = ''): bool {
    $host   = (string)($c['host'] ?? '');
    $port   = (int)($c['port'] ?? 587);
    $secure = strtolower((string)($c['secure'] ?? 'tls'));   // tls | ssl | none
    $user   = (string)($c['user'] ?? '');
    $pass   = (string)($c['pass'] ?? '');
    $from   = (string)($c['from'] ?? $user);
    $log    = [];

    if ($host === '') { $err = 'smtp host not set'; return false; }

    $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
    ]]);

    $timeout = (int)($c['timeout'] ?? 12);
    $fp = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $err = "connect failed: $errstr ($errno)"; return false; }
    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp, &$log): string {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        $log[] = '< ' . trim($out);
        return $out;
    };
    $write = function (string $cmd, bool $hide = false) use ($fp, &$log): void {
        $log[] = '> ' . ($hide ? '***' : trim($cmd));
        fwrite($fp, $cmd . "\r\n");
    };
    $code = function (string $r): int { return (int)substr(trim($r), 0, 3); };

    $r = $read();
    if ($code($r) !== 220) { $err = "greeting: $r\n" . implode("\n", $log); fclose($fp); return false; }

    $helo = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $helo = preg_replace('/[^A-Za-z0-9\.\-]/', '', explode(':', $helo)[0]) ?: 'localhost';

    $write('EHLO ' . $helo); $r = $read();
    if ($code($r) !== 250) { $write('HELO ' . $helo); $r = $read(); }

    if ($secure === 'tls') {
        $write('STARTTLS'); $r = $read();
        if ($code($r) !== 220) { $err = "starttls: $r\n" . implode("\n", $log); fclose($fp); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $err = "tls handshake failed\n" . implode("\n", $log); fclose($fp); return false;
        }
        $write('EHLO ' . $helo); $read();
    }

    if ($user !== '') {
        $write('AUTH LOGIN'); $r = $read();
        if ($code($r) !== 334) { $err = "auth not offered: $r\n" . implode("\n", $log); fclose($fp); return false; }
        $write(base64_encode($user)); $r = $read();
        if ($code($r) !== 334) { $err = "username rejected: $r\n" . implode("\n", $log); fclose($fp); return false; }
        $write(base64_encode($pass), true); $r = $read();
        if ($code($r) !== 235) { $err = "login failed: $r\n" . implode("\n", $log); fclose($fp); return false; }
    }

    $write('MAIL FROM:<' . $from . '>'); $r = $read();
    if ($code($r) !== 250) { $err = "mail from: $r\n" . implode("\n", $log); fclose($fp); return false; }

    $write('RCPT TO:<' . $to . '>'); $r = $read();
    if (!in_array($code($r), [250, 251], true)) { $err = "rcpt to: $r\n" . implode("\n", $log); fclose($fp); return false; }

    $write('DATA'); $r = $read();
    if ($code($r) !== 354) { $err = "data: $r\n" . implode("\n", $log); fclose($fp); return false; }

    // dot-stuffing, then end of data
    $data = $headers . "\r\n\r\n" . $body;
    $data = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $data)));
    fwrite($fp, $data . "\r\n.\r\n");
    $log[] = '> [message, ' . strlen($data) . ' bytes]';
    $r = $read();
    if ($code($r) !== 250) { $err = "message rejected: $r\n" . implode("\n", $log); fclose($fp); return false; }

    $write('QUIT'); @$read();
    fclose($fp);
    $err = implode("\n", $log);
    return true;
}
