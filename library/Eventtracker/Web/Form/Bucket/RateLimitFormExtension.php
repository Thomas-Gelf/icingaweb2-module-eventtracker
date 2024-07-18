<?php

namespace Icinga\Module\Eventtracker\Web\Form\Bucket;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use ipl\Html\Form;

class RateLimitFormExtension implements FormExtension
{
    use TranslationHelper;

    public function enhanceForm(Form $form)
    {
        $form->addElement('number', 'thresholdCount', [
            'label'       => $this->translate('Threshold (Count)'),
            'description' => $this->translate(
                'After how many events should an Issue been triggered?'
            ),
            'required'    => true,
        ]);

        $form->addElement('number', 'windowDuration', [
            'label'       => $this->translate('Window duration'),
            'description' => $this->translate(
                'How long (in seconds) should a single window last?'
            ),
            'required'    => true,
        ]);

        $form->addElement('select', 'attributeSource', [
            'label'       => $this->translate('Keep Attributes from'),
            'options' => [
                null => $this->translate('- please choose -'),
                'first_event' => $this->translate('First related event'),
                'last_event'  => $this->translate('Last related event'),
            ],
            'required'    => true,
        ]);
        $form->addElement('text', 'message', [
            'label'       => $this->translate('Message Pattern'),
            'value'       => '${message}',
            'description' => $this->translate('Example: {event_count} failed sudo login attempts: {message}'),
            'required'    => true,
        ]);
    }
}
