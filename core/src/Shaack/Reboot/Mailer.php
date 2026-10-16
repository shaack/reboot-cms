<?php
/**
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/reboot-cms
 * License: MIT, see file 'LICENSE'
 */

namespace Shaack\Reboot;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Shaack\Logger;

/**
 * Sends mail on behalf of the CMS and its AddOns, configured in `local/config.yml`:
 *
 *     mail:
 *       transport: smtp              # smtp, mail (PHP mail()) or log (write to the log only)
 *       host: mail.example.com       # smtp only
 *       port: 465                    # smtp only, 465 for ssl, 587 for tls
 *       encryption: ssl              # smtp only: ssl, tls or none
 *       username: noreply@example.com
 *       password: "secret"
 *       from: noreply@example.com    # required
 *       fromName: "Example Inc."
 *       replyTo: office@example.com  # optional default Reply-To
 *       verifyPeer: true             # smtp only, false accepts self signed certificates
 *
 * The credentials are environment, not content, which is why they live in
 * `local/` and not in `site/config.yml`. Get an instance through
 * `$reboot->getMailer()`, check `isConfigured()` if sending is optional and
 * call `send()`, which throws a RuntimeException when the mail could not be
 * handed over. The `log` transport sends nothing and is meant for development.
 */
class Mailer
{
    public const TRANSPORT_SMTP = 'smtp';
    public const TRANSPORT_MAIL = 'mail';
    public const TRANSPORT_LOG = 'log';

    private const TRANSPORTS = [self::TRANSPORT_SMTP, self::TRANSPORT_MAIL, self::TRANSPORT_LOG];
    private const TIMEOUT = 15;

    private array $config;

    /**
     * @param array $config the `mail` section of `local/config.yml`
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * True if the configuration is complete enough to send.
     */
    public function isConfigured(): bool
    {
        return $this->configurationError() === null;
    }

    /**
     * Why the configuration is incomplete, or null if it is fine.
     */
    public function configurationError(): ?string
    {
        $transport = $this->getTransport();
        if (!in_array($transport, self::TRANSPORTS, true)) {
            return "mail.transport must be one of " . implode(', ', self::TRANSPORTS);
        }
        if (!filter_var($this->getFrom(), FILTER_VALIDATE_EMAIL)) {
            return "mail.from must be a valid e-mail address";
        }
        if ($transport === self::TRANSPORT_SMTP && trim((string)($this->config['host'] ?? '')) === '') {
            return "mail.host is required for the smtp transport";
        }
        return null;
    }

    public function getTransport(): string
    {
        return strtolower(trim((string)($this->config['transport'] ?? '')));
    }

    public function getFrom(): string
    {
        return trim((string)($this->config['from'] ?? ''));
    }

    public function getFromName(): string
    {
        return trim((string)($this->config['fromName'] ?? ''));
    }

    /**
     * A one line summary for the admin, without the password.
     */
    public function describe(): string
    {
        $transport = $this->getTransport();
        if ($transport === self::TRANSPORT_SMTP) {
            $user = trim((string)($this->config['username'] ?? ''));
            return 'smtp://' . ($user !== '' ? $user . '@' : '') . ($this->config['host'] ?? '')
                . ':' . $this->getPort() . ' (' . $this->getEncryption() . '), from ' . $this->getFrom();
        }
        if ($transport === self::TRANSPORT_MAIL) {
            return 'PHP mail(), from ' . $this->getFrom();
        }
        if ($transport === self::TRANSPORT_LOG) {
            return 'log only, nothing is sent';
        }
        return 'not configured';
    }

    /**
     * Sends a text mail, optionally with an HTML alternative.
     *
     * Options: `toName`, `html` (the HTML version, the text stays the plain
     * alternative), `replyTo`, `replyToName`.
     *
     * @throws \RuntimeException if the configuration is incomplete or the mail was not accepted
     */
    public function send(string $to, string $subject, string $text, array $options = []): void
    {
        $error = $this->configurationError();
        if ($error !== null) {
            throw new \RuntimeException('Mail is not configured: ' . $error);
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid recipient address');
        }
        if ($this->getTransport() === self::TRANSPORT_LOG) {
            Logger::info("Mailer (log transport): to <$to>, subject '$subject'\n" . $text);
            return;
        }
        $mailer = $this->createPHPMailer();
        try {
            $mailer->setFrom($this->getFrom(), $this->getFromName());
            $mailer->addAddress($to, (string)($options['toName'] ?? ''));
            $replyTo = (string)($options['replyTo'] ?? ($this->config['replyTo'] ?? ''));
            if ($replyTo !== '') {
                $mailer->addReplyTo($replyTo, (string)($options['replyToName'] ?? ''));
            }
            $mailer->Subject = $subject;
            if (!empty($options['html'])) {
                $mailer->isHTML(true);
                $mailer->Body = (string)$options['html'];
                $mailer->AltBody = $text;
            } else {
                $mailer->isHTML(false);
                $mailer->Body = $text;
            }
            $mailer->send();
            Logger::info("Mailer: sent to <$to>, subject '$subject'");
        } catch (PHPMailerException $e) {
            Logger::error("Mailer: sending to <$to> failed: " . $mailer->ErrorInfo);
            throw new \RuntimeException('Sending the mail failed: ' . $mailer->ErrorInfo, 0, $e);
        }
    }

    // Internals

    private function createPHPMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->Timeout = self::TIMEOUT;
        if ($this->getTransport() === self::TRANSPORT_MAIL) {
            $mailer->isMail();
            return $mailer;
        }
        $mailer->isSMTP();
        $mailer->Host = trim((string)$this->config['host']);
        $mailer->Port = $this->getPort();
        $username = trim((string)($this->config['username'] ?? ''));
        $mailer->SMTPAuth = $username !== '';
        if ($mailer->SMTPAuth) {
            $mailer->Username = $username;
            $mailer->Password = (string)($this->config['password'] ?? '');
        }
        switch ($this->getEncryption()) {
            case 'ssl':
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                break;
            case 'tls':
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;
            default:
                $mailer->SMTPSecure = '';
                $mailer->SMTPAutoTLS = false;
        }
        if (($this->config['verifyPeer'] ?? true) === false) {
            $mailer->SMTPOptions = ['ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]];
        }
        return $mailer;
    }

    private function getPort(): int
    {
        $port = (int)($this->config['port'] ?? 0);
        if ($port > 0) {
            return $port;
        }
        return $this->getEncryption() === 'ssl' ? 465 : 587;
    }

    private function getEncryption(): string
    {
        $encryption = strtolower(trim((string)($this->config['encryption'] ?? 'ssl')));
        return in_array($encryption, ['ssl', 'tls', 'none'], true) ? $encryption : 'ssl';
    }
}
