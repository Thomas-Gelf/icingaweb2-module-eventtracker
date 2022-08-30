<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use Evenement\EventEmitterTrait;
use gipfl\Translation\StaticTranslator;
use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Engine\SimpleTaskConstructor;
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

    protected $paused = true;

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
        $this->resume();
    }

    public function stop()
    {
        $this->pause();
    }

    public function pause()
    {
        $this->paused = true;
    }

    public function resume()
    {
        $this->paused = false;
    }

    protected function mail()
    {
        if ($this->paused) {
            $this->logger->info('Not sending Mail, Action has been paused');
        }
        $mail = (new Zend_Mail('UTF-8'))
            ->setFrom($this->from)
            ->addTo($this->to);

        $mail->setSubject($this->subject);
        $mail->setBodyText($this->body);

        $mail->send(new Zend_Mail_Transport_Sendmail('-f ' . escapeshellarg($this->from)));
        $this->logger->debug('A mail has been sent to ' . $this->to);
    }
}
