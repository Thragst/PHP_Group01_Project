<?php
function mailConfig() {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/mail_config.php';
    }
    return $cfg;
}

function smtpRead($fp) {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function smtpCmd($fp, $cmd, $expect) {
    if ($cmd !== null) {
        fwrite($fp, $cmd . "\r\n");
    }
    $resp = smtpRead($fp);
    if (strpos($resp, (string)$expect) !== 0) {
        throw new Exception("SMTP error. Expected $expect, got: " . trim($resp));
    }
    return $resp;
}

function smtpSend($cfg, $to, $subject, $htmlBody, $textBody) {
    $ctx = stream_context_create([
        // Verify the SMTP server certificate (prevents man-in-the-middle on STARTTLS).
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
            'peer_name'         => $cfg['smtp_host'],
            'SNI_enabled'       => true,
            'disable_compression' => true,
        ]
    ]);
    $fp = @stream_socket_client('tcp://' . $cfg['smtp_host'] . ':' . $cfg['smtp_port'], $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new Exception("Could not connect to SMTP host: $errstr ($errno)");
    }
    stream_set_timeout($fp, 20);

    try {
        smtpCmd($fp, null, 220);
        smtpCmd($fp, 'EHLO localhost', 250);
        smtpCmd($fp, 'STARTTLS', 220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0))) {
            throw new Exception('Could not start TLS. Check that openssl is enabled and openssl.cafile/curl.cainfo points to a CA bundle (cacert.pem) in php.ini.');
        }
        smtpCmd($fp, 'EHLO localhost', 250);
        smtpCmd($fp, 'AUTH LOGIN', 334);
        smtpCmd($fp, base64_encode($cfg['smtp_user']), 334);
        smtpCmd($fp, base64_encode($cfg['smtp_pass']), 235);
        smtpCmd($fp, 'MAIL FROM:<' . $cfg['from_email'] . '>', 250);
        smtpCmd($fp, 'RCPT TO:<' . $to . '>', 250);
        smtpCmd($fp, 'DATA', 354);

        $boundary = 'b' . md5(uniqid('', true));
        $fromName = '=?UTF-8?B?' . base64_encode($cfg['from_name']) . '?=';
        $subj     = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $msg  = "Date: " . date('r') . "\r\n";
        $msg .= "From: $fromName <" . $cfg['from_email'] . ">\r\n";
        $msg .= "To: <$to>\r\n";
        $msg .= "Subject: $subj\r\n";
        $msg .= "Message-ID: <" . md5(uniqid('', true)) . "@sportmart.local>\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n";
        $msg .= "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($textBody)) . "\r\n";
        $msg .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $msg .= "--$boundary--\r\n";

        fwrite($fp, $msg . "\r\n.\r\n");
        smtpCmd($fp, null, 250);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    } catch (Exception $e) {
        @fclose($fp);
        throw $e;
    }
}

function sendMail($to, $subject, $htmlBody, $textBody) {
    $cfg = mailConfig();
    $logFile = __DIR__ . '/../storage/mail.log';

    $logLine = "[" . date('Y-m-d H:i:s') . "] To: $to | Subject: $subject\n$textBody\n" . str_repeat('-', 60) . "\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND);

    if (!empty($cfg['smtp_enabled'])) {
        try {
            return smtpSend($cfg, $to, $subject, $htmlBody, $textBody);
        } catch (Throwable $e) {
            @file_put_contents($logFile, "[MAIL ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    $headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $cfg['from_name'] . " <" . $cfg['from_email'] . ">\r\n";
    return @mail($to, $subject, $htmlBody, $headers);
}

function createActivationToken($pdo, $email) {
    $cfg   = mailConfig();
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    $pdo->prepare("DELETE FROM email_verifications WHERE email = ?")->execute([$email]);
    $pdo->prepare("INSERT INTO email_verifications (email, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))")
        ->execute([$email, $hash, (int)$cfg['token_hours']]);

    return $token;
}

function sendActivationEmail($email, $fullName, $token) {
    $cfg  = mailConfig();
    $link = rtrim($cfg['base_url'], '/') . '/pages/activate.php?token=' . urlencode($token);
    $name = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $app  = htmlspecialchars($cfg['app_name'], ENT_QUOTES, 'UTF-8');
    $hrs  = (int)$cfg['token_hours'];

    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e5e5e5;border-radius:8px">'
          . '<h2 style="margin-top:0">Welcome to ' . $app . '</h2>'
          . '<p>Hi ' . $name . ',</p>'
          . '<p>Thanks for signing up. Click the button below to activate your account.</p>'
          . '<p><a href="' . $link . '" style="display:inline-block;background:#0d6efd;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:bold">Activate account</a></p>'
          . '<p style="font-size:13px;color:#555">Or copy this link into your browser:<br><a href="' . $link . '">' . $link . '</a></p>'
          . '<p style="font-size:12px;color:#888">This link expires in ' . $hrs . ' hours. If you did not create an account, ignore this email.</p></div>';

    $text = "Hi $fullName,\n\nActivate your {$cfg['app_name']} account:\n$link\n\nThis link expires in $hrs hours. If you did not create an account, ignore this email.";

    return [sendMail($email, 'Activate your ' . $cfg['app_name'] . ' account', $html, $text), $link];
}