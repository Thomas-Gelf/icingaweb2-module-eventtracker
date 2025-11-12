<?php

namespace Icinga\Module\Eventtracker\Web\Form\Reporting;

use DateTimeImmutable;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\InlineForm;
use InvalidArgumentException;

class ReportStartForm extends InlineForm
{
    use TranslationHelper;

    protected $useCsrf = false;
    protected $useFormName = false;
    protected $method = 'GET';

    protected function assemble()
    {
        $this->addElement('date', 'start', [
            'class' => 'autosubmit',
            'value' => (new DateTimeImmutable('now-1 month'))->format('Y-m-d'),
        ]);
    }

    public function getDate(): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->getValue('start') . ' 00:00:00');
        if (! $date) {
            throw new InvalidArgumentException('Invalid date: ' . $this->getValue('start'));
        }

        return $date;
    }
}
