<?php

namespace Simp\Commerce\connection;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class Mail
{
    protected string $host;
    protected string $port;
    protected string $username;
    protected string $password;
    protected string $encryption;
    protected string $from;
    protected string $fromName;
    protected string $replyTo;
    protected string $replyToName;

    public function __construct()
    {
        $this->host = $_ENV['SMTP_HOST'];
        $this->port = $_ENV['SMTP_PORT'];
        $this->username = $_ENV['SMTP_USER'];
        $this->password = $_ENV['SMTP_PASS'];
        $this->encryption = $_ENV['SMTP_SECURE'];
        $this->from = $_ENV['FROM_EMAIL'];
        $this->fromName = $_ENV['FROM_NAME'];
        $this->replyTo = $_ENV['REPLY_TO_EMAIL'];
        $this->replyToName = $_ENV['REPLY_TO_NAME'];

        // Validate properties
        if (empty($this->host) || empty($this->port) || empty($this->username) || empty($this->password)) {
            throw new \InvalidArgumentException('Missing SMTP configuration.');
        }
    }

    /**
     * Sends an email using the configured mailer settings.
     *
     * @param string $to The recipient's email address.
     * @param string $subject The subject of the email.
     * @param string $htmlMessage The HTML content of the email.
     * @param string $plaintextMessage The plaintext alternative content of the email (optional).
     * @param string $toName The recipient's name (optional).
     * @param array $attachments An array of file paths to attach to the email (optional).
     * @param array $cc An array of email addresses to send carbon copies to (optional).
     * @param array $bcc An array of email addresses to send blind carbon copies to (optional).
     * @return bool Returns true if the email was sent successfully, otherwise returns false.
     */
    public function send(string $to, string $subject, string $htmlMessage, string $plaintextMessage= '', string $toName = '', array $attachments = [], array $cc = [], array $bcc = []): bool
    {
        try{
            //Create an instance; passing `true` enables exceptions
            $mail = new PHPMailer(true);

            //Server settings
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->username;
            $mail->Password   = $this->password;
            $mail->SMTPSecure = $this->encryption;
            $mail->Port       = $this->port;

            //Recipients
            $mail->setFrom($this->from, $this->fromName);
            $mail->addAddress($to, $toName);

            if (!empty($this->replyTo) && !empty( $this->replyToName)) {
                $mail->addReplyTo($this->replyTo, $this->replyToName);
            }

            foreach ($cc as $ccEmail) {
                $mail->addCC($ccEmail);
            }

            foreach ($bcc as $bccEmail) {
                $mail->addBCC($bccEmail);
            }

            foreach ($attachments as $attachment) {

                if (file_exists($attachment)) {
                    $filename = pathinfo($attachment, PATHINFO_BASENAME);
                    $mail->addAttachment($attachment, strtoupper($filename));
                }
            }

            //Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlMessage;
            $mail->AltBody = $plaintextMessage;

            return $mail->send();
        }catch (\Throwable $e) {
            return false;
        }
    }
}