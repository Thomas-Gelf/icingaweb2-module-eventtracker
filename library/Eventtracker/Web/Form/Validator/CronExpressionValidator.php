<?php

namespace Icinga\Module\Eventtracker\Web\Form\Validator;

use Cron\CronExpression;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form\Validator\SimpleValidator;

class CronExpressionValidator extends SimpleValidator
{
    use TranslationHelper;

    public function isValid($value)
    {
        // Hint: IMHO null should not reach this method. Will be addressed separately
        if ($value === null) {
            return true;
        }
        $valid = CronExpression::isValidExpression($value);
        if (! $valid) {
            $this->addMessage($this->translate('This is not a valid cron expression'));
        }

        return $valid;
    }
}
