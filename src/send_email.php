<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendOTP($toEmail, $otpCode, $userName) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'potenza.id.rapidplex.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'verification@grav-tech.com';
        $mail->Password   = 'OffOff@18'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('verification@grav-tech.com', 'Grav Tech Security');
        $mail->addAddress($toEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = 'Kode Verifikasi Akun - Grav Tech Center';
        
        $emailTemplate = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f9f9f9; padding: 20px;'>
            <div style='background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); text-align: center;'>
                <h2 style='color: #111; margin-bottom: 10px;'>Verifikasi Akun</h2>
                <p style='color: #666; font-size: 14px; margin-bottom: 30px;'>Halo <b>{$userName}</b>, gunakan kode di bawah ini untuk menyelesaikan pendaftaran Anda.</p>
                
                <div style='background-color: #f0f2f5; padding: 20px; border-radius: 8px; display: inline-block; margin-bottom: 30px;'>
                    <h1 style='color: #006c5b; margin: 0; font-size: 32px; letter-spacing: 5px;'>{$otpCode}</h1>
                </div>
                
                <p style='color: #888; font-size: 12px; margin-bottom: 0;'>Kode ini berlaku selama 15 menit.<br>Jangan berikan kode ini kepada siapapun.</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                
                <p style='color: #aaa; font-size: 11px;'>&copy; " . date('Y') . " Grav Tech Center. All rights reserved.</p>
            </div>
        </div>
        ";

        $mail->Body = $emailTemplate;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>