<?php

namespace Icinga\Module\Eventtracker\Web\Form\Reporting;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\InlineForm;
use Icinga\Module\Eventtracker\Reporting\AggregationSubject;

class AggregationSubjectForm extends InlineForm
{
    use TranslationHelper;

    protected $method = 'GET';
    protected $useCsrf = false;
    protected $useFormName = false;

    protected function assemble()
    {
        $this->addElement('select', 'aggregation', [
            'options' => AggregationSubject::enum(),
            'value'   => AggregationSubject::HOST,
            'class' => 'autosubmit',
        ]);
    }
}
