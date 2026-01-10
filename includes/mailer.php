<?php
// includes/mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!class_exists(PHPMailer::class)) {
  $base = __DIR__ . '/PHPMailer/src/';
  require_once $base . 'Exception.php';
  require_once $base . 'PHPMailer.php';
  require_once $base . 'SMTP.php';
}

/**
 * Cấu hình SMTP (sửa đúng mail của bạn)
 * - Gmail bắt buộc: bật 2FA + App Password 16 ký tự
 */
function mail_smtp_config(): array {
  // ƯU TIÊN đọc từ file cau_hinh/mail.php nếu bạn có
  $cfgFile = __DIR__ . '/../cau_hinh/mail.php';
  if (file_exists($cfgFile)) {
    $cfg = require $cfgFile;
    if (is_array($cfg)) return $cfg;
  }

  // Fallback: sửa trực tiếp tại đây
  return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'sonmoc24@gmail.com',
    'password' => 'xuufeqzyubrzyhfx',
    'encryption' => 'tls', // tls | ssl
    'from_email' => 'sonmoc24@gmail.com',
    'from_name'  => 'CROCS Vietnam',
  ];
}

function send_email(string $to, string $subject, string $html, string $alt = ''): bool {
  $GLOBALS['MAIL_LAST_ERROR'] = null;

  $cfg = mail_smtp_config();

  try {
    $m = new PHPMailer(true);
    $m->CharSet = 'UTF-8';

    $m->isSMTP();
    $m->Host       = $cfg['host'];
    $m->SMTPAuth   = true;
    $m->Username   = $cfg['username'];
    $m->Password   = $cfg['password'];
    $m->Port       = (int)$cfg['port'];

    if (($cfg['encryption'] ?? '') === 'ssl') {
      $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
      $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // tls
    }

    $fromEmail = $cfg['from_email'] ?? $cfg['username'];
    $fromName  = $cfg['from_name'] ?? 'Shop';
    $m->setFrom($fromEmail, $fromName);

    $m->addAddress($to);

    $m->isHTML(true);
    $m->Subject = $subject;
    $m->Body    = $html;
    $m->AltBody = $alt ?: strip_tags($html);

    // Bật debug nếu muốn xem log SMTP (chỉ dùng khi test)
    // $m->SMTPDebug = 2;
    // $m->Debugoutput = 'error_log';

    $m->send();
    return true;

  } catch (Throwable $e) {
    $GLOBALS['MAIL_LAST_ERROR'] = $e->getMessage();
    return false;
  }
}
