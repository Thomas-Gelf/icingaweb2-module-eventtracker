<?php

namespace Icinga\Module\Eventtracker\Web\Form\Reporting;

use DateTimeImmutable;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\InlineForm;
use InvalidArgumentException;

class ReportEndForm extends InlineForm
{
    use TranslationHelper;

    protected $useCsrf = false;
    protected $useFormName = false;
    protected $method = 'GET';

    protected function assemble()
    {
        $this->addElement('date', 'end', [
            'class' => 'autosubmit',
            'value' => (new DateTimeImmutable())->format('Y-m-d'),
        ]);
    }

    public function getDate(): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->getValue('end') . ' 00:00:00');
        if (! $date) {
            throw new InvalidArgumentException('Invalid date: ' . $this->getValue('end'));
        }

        return $date->modify('+1 day');
    }
}
