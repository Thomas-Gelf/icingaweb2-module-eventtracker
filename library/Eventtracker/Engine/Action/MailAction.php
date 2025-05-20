<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use Evenement\EventEmitterTrait;
use gipfl\Translation\StaticTranslator;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Engine\SimpleTaskConstructor;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Web\Form\Action\MailFormExtension;
use React\Promise\PromiseInterface;
use Throwable;
use Zend_Mail;
use Zend_Mail_Transport_Smtp as SmtpTransport;

use function React\Promise\reject;
use function React\Promise\resolve;

class MailAction extends SimpleTaskConstructor implements Action
{
    use ActionProperties;
    use DummyTaskActions;
    use EventEmitterTrait;
    use SettingsProperty;

    protected ?string $from = null;
    protected ?string $to = null;
    protected ?string $subject = null;
    protected ?string $body = null;
    protected bool $stripTags = false;
    protected bool $paused = true;

    public function applySettings(Settings $settings)
    {
        $this->from = $settings->getRequired('from');
        $this->to = $settings->getRequired('to');
        $this->subject = $settings->get('subject');
        $this->body = $settings->get('body');
        $this->stripTags = $settings->get('strip_tags', 'n') === 'y';

        $this->setSettings($settings);
    }

    public static function getFormExtension(): FormExtension
    {
        return new MailFormExtension();
    }

    public static function getLabel()
    {
        return StaticTranslator::get()->translate('Mail');
    }

    public static function getDescription()
    {
        return StaticTranslator::get()->translate(
            'Send a mail'
        );
    }

    public function process(Issue $issue): PromiseInterface
    {
        try {
            $this->mail($issue);
        } catch (Throwable $e) {
            return reject($e);
        }

        return resolve("Mail has been sent to {$this->to}");
    }

    /**
     * This is very basic, we trust our admins
     */
    protected static function splitNameAndMail($mail): array
    {
        if (preg_match('/^(.+)<([^>]+)>$/', $mail, $match)) {
            return [trim($match[1]), $match[2]];
        } else {
            return [$mail, $mail]; // Zend_Mail skips the name if '', null or === $email
        }
    }

    protected function mail(Issue $issue): void
    {
        if ($this->paused) {
            $this->logger->info('Not sending Mail, Action has been paused');

            return;
        }

        [$fromName, $fromMail] = self::splitNameAndMail($this->from);
        [$toName, $toMail] = self::splitNameAndMail($this->to);

        $mail = (new Zend_Mail('UTF-8'))
            ->setFrom($fromMail, $fromName)
            ->addTo($toMail, $toName);

        $mail->setSubject(ConfigHelper::fillPlaceholders($this->subject, $issue));
        $mail->setBodyText(ConfigHelper::fillPlaceholders($this->body, $issue));

        $mail->send(new SmtpTransport());
        $this->logger->debug('A mail has been sent to ' . $this->to);
    }
}
