<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Engine\Input;

class MapConfigForm extends UuidObjectForm
{
    protected $table = 'map';
    protected $mainProperties = ['label', 'mappings'];

    protected function assemble()
    {
        $this->addElement('text', 'label', [
            'label'   => $this->translate('Label'),
        ]);
        $this->addElement('textarea', 'mappings', [
            'label'   => $this->translate('Mappings'),
        ]);

        $this->addButtons();
    }
}
