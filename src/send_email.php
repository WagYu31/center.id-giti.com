<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function writeMailLog($status, $toEmail, $subject, $details = '') {
    $logDir = __DIR__ . '/../public/bukti';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/mail_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] [{$status}] To: {$toEmail} | Subject: {$subject}";
    if (!empty($details)) {
        $line .= " | Info: {$details}";
    }
    $line .= PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

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
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

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

function sendApprovalRequestEmail($toEmail, $approverName, $requesterName, $jobTitle, $jobDesc, $jobId) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'potenza.id.rapidplex.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'verification@grav-tech.com';
        $mail->Password   = 'OffOff@18'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom('verification@grav-tech.com', 'Web Bukti - Grav Tech Center');
        $mail->addAddress($toEmail, $approverName);

        $mail->isHTML(true);
        $mail->Subject = '[PERLU APPROVAL] ' . $jobTitle . ' - dari ' . $requesterName;
        
        $jobUrl = "https://center.id-giti.com/bukti/index.php?job_id=" . urlencode($jobId);
        $cleanDesc = nl2br(htmlspecialchars(strip_tags(mb_substr($jobDesc, 0, 400)))) . (mb_strlen($jobDesc) > 400 ? '...' : '');

        $emailTemplate = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8fafc; padding: 25px 15px;'>
            <div style='background-color: #ffffff; padding: 35px 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
                <div style='text-align: center; margin-bottom: 25px;'>
                    <span style='background: #eff6ff; color: #2563eb; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 6px 14px; border-radius: 30px; border: 1px solid #bfdbfe;'>
                        🔔 Permintaan Persetujuan
                    </span>
                    <h2 style='color: #0f172a; margin: 15px 0 6px 0; font-size: 22px; font-weight: 800;'>Persetujuan Hasil Meeting / Kerja</h2>
                    <p style='color: #64748b; font-size: 14px; margin: 0;'>Halo <b>{$approverName}</b>, ada pekerjaan yang membutuhkan validasi Anda.</p>
                </div>
                
                <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 25px;'>
                    <div style='margin-bottom: 12px;'>
                        <span style='color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Judul Pekerjaan</span>
                        <div style='color: #0f172a; font-size: 16px; font-weight: 700; margin-top: 3px;'>{$jobTitle}</div>
                    </div>
                    <div style='margin-bottom: 12px;'>
                        <span style='color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Diajukan Oleh</span>
                        <div style='color: #334155; font-size: 14px; font-weight: 600; margin-top: 3px;'>👤 {$requesterName}</div>
                    </div>
                    <div>
                        <span style='color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Ringkasan / Hasil Meeting</span>
                        <div style='color: #475569; font-size: 13px; line-height: 1.6; margin-top: 5px; background: #ffffff; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;'>
                            {$cleanDesc}
                        </div>
                    </div>
                </div>
                
                <div style='text-align: center; margin-bottom: 25px;'>
                    <a href='{$jobUrl}' style='display: inline-block; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; padding: 14px 32px; border-radius: 12px; box-shadow: 0 4px 14px rgba(2,132,199,0.35);'>
                        Tinjau & Berikan Keputusan &rarr;
                    </a>
                    <p style='color: #94a3b8; font-size: 12px; margin-top: 12px;'>Anda dapat langsung memilih <b>Setujui (Lanjut Kerjakan)</b> atau <b>Meeting Ulang</b> di sistem.</p>
                </div>
                
                <hr style='border: none; border-top: 1px solid #f1f5f9; margin: 25px 0;'>
                <p style='color: #94a3b8; font-size: 11px; text-align: center; margin: 0;'>&copy; " . date('Y') . " Grav Tech Center - Web Bukti. All rights reserved.</p>
            </div>
        </div>
        ";

        $mail->Body = $emailTemplate;
        $mail->send();
        writeMailLog('SUCCESS', $toEmail, $mail->Subject);
        return true;
    } catch (Exception $e) {
        writeMailLog('FAILED', $toEmail, $mail->Subject ?? $jobTitle, $mail->ErrorInfo ?: $e->getMessage());
        return false;
    }
}

function sendApprovalResultEmail($toEmail, $recipientName, $approverName, $jobTitle, $statusResult, $notes, $jobId) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'potenza.id.rapidplex.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'verification@grav-tech.com';
        $mail->Password   = 'OffOff@18'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom('verification@grav-tech.com', 'Web Bukti - Grav Tech Center');
        $mail->addAddress($toEmail, $recipientName);

        $mail->isHTML(true);
        $jobUrl = "https://center.id-giti.com/bukti/index.php?job_id=" . urlencode($jobId);

        if ($statusResult === 'approved') {
            $mail->Subject = '[DISETUJUI] ' . $jobTitle . ' - Lanjut Kerjakan';
            $badgeColor = '#059669';
            $badgeBg = '#ecfdf5';
            $badgeBorder = '#a7f3d0';
            $badgeText = '✅ DISETUJUI: LANJUT KERJAKAN';
            $headline = 'Pekerjaan Disetujui Pimpinan!';
            $message = 'Pekerjaan / Hasil Meeting Anda telah <b>disetujui oleh ' . htmlspecialchars($approverName) . '</b>. Silakan langsung melanjutkan ke tahap pengerjaan.';
            $notesSection = '';
        } else {
            $mail->Subject = '[MEETING ULANG] ' . $jobTitle . ' - Perlu Pembahasan Ulang';
            $badgeColor = '#dc2626';
            $badgeBg = '#fef2f2';
            $badgeBorder = '#fecaca';
            $badgeText = '⚠️ PERLU MEETING ULANG';
            $headline = 'Perlu Pembahasan / Revisi Ulang';
            $message = 'Pekerjaan / Hasil Meeting Anda memerlukan <b>pembahasan ulang (Meeting Ulang)</b> bersama pimpinan (' . htmlspecialchars($approverName) . ').';
            $notesHtml = nl2br(htmlspecialchars($notes ?: 'Harap koordinasikan kembali jadwal meeting dan revisi poin-poin yang dibahas.'));
            $notesSection = "
            <div style='margin-top: 15px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 14px;'>
                <span style='color: #9f1239; font-size: 11px; font-weight: 700; text-transform: uppercase;'>Catatan / Instruksi Pimpinan:</span>
                <div style='color: #881337; font-size: 13px; margin-top: 5px; line-height: 1.5;'>{$notesHtml}</div>
            </div>";
        }

        $emailTemplate = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8fafc; padding: 25px 15px;'>
            <div style='background-color: #ffffff; padding: 35px 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
                <div style='text-align: center; margin-bottom: 25px;'>
                    <span style='background: {$badgeBg}; color: {$badgeColor}; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 6px 14px; border-radius: 30px; border: 1px solid {$badgeBorder};'>
                        {$badgeText}
                    </span>
                    <h2 style='color: #0f172a; margin: 15px 0 6px 0; font-size: 22px; font-weight: 800;'>{$headline}</h2>
                    <p style='color: #64748b; font-size: 14px; margin: 0;'>Halo <b>{$recipientName}</b>, status review pekerjaan Anda telah diperbarui.</p>
                </div>
                
                <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 25px;'>
                    <div style='margin-bottom: 10px;'>
                        <span style='color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Judul Pekerjaan</span>
                        <div style='color: #0f172a; font-size: 16px; font-weight: 700; margin-top: 3px;'>{$jobTitle}</div>
                    </div>
                    <div style='color: #475569; font-size: 14px; line-height: 1.6;'>
                        {$message}
                    </div>
                    {$notesSection}
                </div>
                
                <div style='text-align: center; margin-bottom: 25px;'>
                    <a href='{$jobUrl}' style='display: inline-block; background: #0f172a; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 700; padding: 12px 28px; border-radius: 10px;'>
                        Buka Pekerjaan di Web Bukti &rarr;
                    </a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #f1f5f9; margin: 25px 0;'>
                <p style='color: #94a3b8; font-size: 11px; text-align: center; margin: 0;'>&copy; " . date('Y') . " Grav Tech Center - Web Bukti. All rights reserved.</p>
            </div>
        </div>
        ";

        $mail->Body = $emailTemplate;
        $mail->send();
        writeMailLog('SUCCESS', $toEmail, $mail->Subject);
        return true;
    } catch (Exception $e) {
        writeMailLog('FAILED', $toEmail, $mail->Subject ?? $jobTitle, $mail->ErrorInfo ?: $e->getMessage());
        return false;
    }
}

function sendTagNotificationEmail($toEmail, $recipientName, $actorName, $jobTitle, $contextText, $jobId, $sourceType = 'job') {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'potenza.id.rapidplex.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'verification@grav-tech.com';
        $mail->Password   = 'OffOff@18'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom('verification@grav-tech.com', 'Web Bukti - Grav Tech Center');
        $mail->addAddress($toEmail, $recipientName);

        $mail->isHTML(true);
        $actionDesc = ($sourceType === 'comment') ? 'menandai Anda dalam komentar' : 'menandai Anda dalam pekerjaan';
        $mail->Subject = '[TAG] ' . $actorName . ' ' . $actionDesc . ': ' . $jobTitle;
        
        $jobUrl = "https://center.id-giti.com/bukti/index.php?job_id=" . urlencode($jobId);
        $cleanText = nl2br(htmlspecialchars(strip_tags(mb_substr($contextText, 0, 400)))) . (mb_strlen($contextText) > 400 ? '...' : '');

        $emailTemplate = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8fafc; padding: 25px 15px;'>
            <div style='background-color: #ffffff; padding: 35px 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
                <div style='text-align: center; margin-bottom: 25px;'>
                    <span style='background: #fef3c7; color: #b45309; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 6px 14px; border-radius: 30px; border: 1px solid #fde68a;'>
                        🏷️ Tanda Sebutan (@Tag)
                    </span>
                    <h2 style='color: #0f172a; margin: 15px 0 6px 0; font-size: 22px; font-weight: 800;'>Anda Ditandai</h2>
                    <p style='color: #64748b; font-size: 14px; margin: 0;'>Halo <b>{$recipientName}</b>, <b>{$actorName}</b> {$actionDesc}.</p>
                </div>
                
                <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 25px;'>
                    <div style='margin-bottom: 12px;'>
                        <span style='color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Judul Pekerjaan</span>
                        <div style='color: #0f172a; font-size: 16px; font-weight: 700; margin-top: 3px;'>{$jobTitle}</div>
                    </div>
                    <div>
                        <span style='color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Pesan / Isi Deskripsi</span>
                        <div style='color: #334155; font-size: 13px; line-height: 1.6; margin-top: 5px; background: #ffffff; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;'>
                            {$cleanText}
                        </div>
                    </div>
                </div>
                
                <div style='text-align: center; margin-bottom: 25px;'>
                    <a href='{$jobUrl}' style='display: inline-block; background: linear-gradient(135deg, #d97706 0%, #b45309 100%); color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; padding: 14px 32px; border-radius: 12px; box-shadow: 0 4px 14px rgba(217,119,6,0.35);'>
                        Buka Pekerjaan di Web Bukti &rarr;
                    </a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #f1f5f9; margin: 25px 0;'>
                <p style='color: #94a3b8; font-size: 11px; text-align: center; margin: 0;'>&copy; " . date('Y') . " Grav Tech Center - Web Bukti. All rights reserved.</p>
            </div>
        </div>
        ";

        $mail->Body = $emailTemplate;
        $mail->send();
        writeMailLog('SUCCESS', $toEmail, $mail->Subject);
        return true;
    } catch (Exception $e) {
        writeMailLog('FAILED', $toEmail, $mail->Subject ?? $jobTitle, $mail->ErrorInfo ?: $e->getMessage());
        return false;
    }
}

function sendProgressUpdateEmail($toEmail, $recipientName, $actorName, $jobTitle, $statusBefore, $statusAfter, $notes, $jobId) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'potenza.id.rapidplex.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'verification@grav-tech.com';
        $mail->Password   = 'OffOff@18'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom('verification@grav-tech.com', 'Web Bukti - Grav Tech Center');
        $mail->addAddress($toEmail, $recipientName);

        $mail->isHTML(true);

        $statusLabels = [
            'todo' => 'Belum Mulai',
            'in_progress' => 'On Progress (Lanjut Kerjakan)',
            'done' => 'Selesai',
            'pending_approval' => 'Menunggu Approval',
            'need_meeting' => 'Meeting Ulang'
        ];
        $statusLabel = $statusLabels[$statusAfter] ?? $statusAfter;

        $mail->Subject = '[UPDATE PROGRES] ' . $jobTitle . ' - oleh ' . $actorName;
        $jobUrl = "https://center.id-giti.com/bukti/index.php?job_id=" . urlencode($jobId);
        $cleanNotes = nl2br(htmlspecialchars(strip_tags(mb_substr($notes ?: 'Update progres pekerjaan dilaporkan.', 0, 400)))) . (mb_strlen($notes) > 400 ? '...' : '');

        $emailTemplate = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8fafc; padding: 25px 15px;'>
            <div style='background-color: #ffffff; padding: 35px 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
                <div style='text-align: center; margin-bottom: 25px;'>
                    <span style='background: #eff6ff; color: #2563eb; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 6px 14px; border-radius: 30px; border: 1px solid #bfdbfe;'>
                        ⚡ Update Progres
                    </span>
                    <h2 style='color: #0f172a; margin: 15px 0 6px 0; font-size: 22px; font-weight: 800;'>Pembaruan Progres Kerja</h2>
                    <p style='color: #64748b; font-size: 14px; margin: 0;'>Halo <b>{$recipientName}</b>, ada catatan progres baru dari <b>{$actorName}</b>.</p>
                </div>
                
                <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 25px;'>
                    <div style='margin-bottom: 12px;'>
                        <span style='color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Judul Pekerjaan</span>
                        <div style='color: #0f172a; font-size: 16px; font-weight: 700; margin-top: 3px;'>{$jobTitle}</div>
                    </div>
                    <div style='margin-bottom: 12px;'>
                        <span style='color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Status Baru</span>
                        <div style='color: #0f172a; font-size: 14px; font-weight: 700; margin-top: 3px;'>📌 {$statusLabel}</div>
                    </div>
                    <div>
                        <span style='color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Catatan Progres</span>
                        <div style='color: #334155; font-size: 13px; line-height: 1.6; margin-top: 5px; background: #ffffff; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;'>
                            {$cleanNotes}
                        </div>
                    </div>
                </div>
                
                <div style='text-align: center; margin-bottom: 25px;'>
                    <a href='{$jobUrl}' style='display: inline-block; background: #0f172a; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 700; padding: 12px 28px; border-radius: 10px;'>
                        Lihat Progres & Lampiran di Web Bukti &rarr;
                    </a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #f1f5f9; margin: 25px 0;'>
                <p style='color: #94a3b8; font-size: 11px; text-align: center; margin: 0;'>&copy; " . date('Y') . " Grav Tech Center - Web Bukti. All rights reserved.</p>
            </div>
        </div>
        ";

        $mail->Body = $emailTemplate;
        $mail->send();
        writeMailLog('SUCCESS', $toEmail, $mail->Subject);
        return true;
    } catch (Exception $e) {
        writeMailLog('FAILED', $toEmail, $mail->Subject ?? $jobTitle, $mail->ErrorInfo ?: $e->getMessage());
        return false;
    }
}
?>