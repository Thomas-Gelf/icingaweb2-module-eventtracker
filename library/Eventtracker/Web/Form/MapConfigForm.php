<?php

namespace Icinga\Module\Eventtracker\Web\Form;

class MapConfigForm extends UuidObjectForm
{
    protected string $table = 'map';
    protected ?array $mainProperties = ['label', 'mappings'];

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
