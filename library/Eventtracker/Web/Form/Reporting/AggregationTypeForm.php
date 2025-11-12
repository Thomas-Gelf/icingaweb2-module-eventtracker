<?php

namespace Icinga\Module\Eventtracker\Web\Form\Reporting;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\InlineForm;
use Icinga\Module\Eventtracker\Reporting\AggregationPeriod;

class AggregationTypeForm extends InlineForm
{
    use TranslationHelper;

    protected $method = 'GET';
    protected $useCsrf = false;
    protected $useFormName = false;

    protected function assemble()
    {
        $this->addElement('select', 'aggregation', [
            'options' => AggregationPeriod::enum(),
            'value'   => AggregationPeriod::MONTHLY,
            'class' => 'autosubmit',
        ]);
    }
}
