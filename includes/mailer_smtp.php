<?php
/**
 * mailer_smtp.php
 * Gửi email qua SMTP (Gmail)
 * Dùng cho OTP xác nhận đơn hàng / xác thực email
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ================== LOAD PHPMailer ================== */
/*
 * Cách 1 (KHUYẾN NGHỊ): dùng Composer
 * composer require phpmailer/phpmailer
 * -> uncomment dòng dưới
 */
// require_once __DIR__ . '/vendor/autoload.php';

/*
 * Cách 2: dùng bản zip PHPMailer (phổ biến với XAMPP)
 * Thư mục ví dụ:
 * includes/phpmailer/src/PHPMailer.php
 * includes/phpmailer/src/SMTP.php
 * includes/phpmailer/src/Exception.php
 */
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

/* ================== SMTP CONFIG ================== */
/**
 * ❗ BẮT BUỘC:
 * - Gmail phải bật 2-Step Verification
 * - Tạo App Password (16 ký tự)
 */
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;            // TLS
const SMTP_SECURE = 'tls';

const SMTP_USER = 'sonmoc24@gmail.com';     // 🔴 ĐỔI
const SMTP_PASS = 'xuufeqzyubrzyhfx';   // 🔴 ĐỔI (App Password)

const MAIL_FROM = 'sonmoc24@gmail.com';     // 🔴 ĐỔI
const MAIL_FROM_NAME = 'CROCS Vietnam';            // Tên người gửi

/* ================== SEND FUNCTION ================== */
function send_mail_smtp(string $to, string $subject, string $html): bool
{
    $mail = new PHPMailer(true);

    try {
        // Server
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // UTF-8
        $mail->CharSet = 'UTF-8';

        // Sender
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

        // Recipient
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;

        // Fallback text
        $mail->AltBody = strip_tags($html);

        $mail->send();
        return true;

    } catch (Exception $e) {
        // để auth_core.php đọc được lỗi
        $GLOBALS['MAIL_LAST_ERROR'] = $mail->ErrorInfo;
        return false;
    }
}
