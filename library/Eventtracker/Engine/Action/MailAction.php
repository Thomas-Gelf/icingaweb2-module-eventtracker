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
use Icinga\Module\Eventtracker\Web\Form\Action\MailFormExtension;
use React\EventLoop\LoopInterface;
use Zend_Mail;
use Zend_Mail_Transport_Sendmail;

class MailAction extends SimpleTaskConstructor implements Action
{
    use ActionProperties;
    use EventEmitterTrait;
    use SettingsProperty;

    /** @var LoopInterface */
    protected $loop;

    /** @var string */
    protected $from;

    /** @var string */
    protected $to;

    /** @var string */
    protected $subject;

    /** @var string */
    protected $body;

    protected function initialize()
    {
        $settings = $this->getSettings();
        $this->from = $settings->getRequired('from');
        $this->to = $settings->getRequired('to');
        $this->subject = $settings->get('subject');
        $this->body = $settings->get('body');
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

    public function run(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->start();
    }

    public function start()
    {
    }

    public function stop()
    {
    }

    public function pause()
    {
    }

    public function resume()
    {
    }

    public function process(Issue $issue): void
    {
        $mail = (new Zend_Mail('UTF-8'))
            ->setFrom($this->from)
            ->addTo($this->to);

        $mail->setSubject(ConfigHelper::fillPlaceholders($this->subject, $issue));
        $mail->setBodyText(ConfigHelper::fillPlaceholders($this->body, $issue));

        $mail->send(new Zend_Mail_Transport_Sendmail('-f ' . escapeshellarg($this->from)));
    }
}
