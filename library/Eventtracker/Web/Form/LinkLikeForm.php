<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\IcingaWeb2\Icon;
use Icinga\Module\Eventtracker\Web\Form;

class LinkLikeForm extends Form
{
    protected string $linkLabel;
    protected ?string $linkTitle = null;
    protected ?string $linkIcon = null;

    /**
     * @param ?string $linkTitle
     * @param ?string $linkIcon
     */
    public function __construct(string $linkLabel, ?string $linkTitle = null, ?string $linkIcon = null)
    {
        $this->linkLabel = $linkLabel;
        if ($linkTitle !== null) {
            $this->linkTitle = $linkTitle;
        }
        if ($linkIcon !== null) {
            $this->linkIcon = $linkIcon;
        }
        $this->setMethod('POST');
        $this->addAttributes(['class' => 'inline']);
        $this->styleWithDirector();
    }

    protected function assemble()
    {
        // TODO: class icon-button, if no label but icon -> set font!
        $this->addElement('submit', 'submit', [
            'class' => 'link-button',
            'title' => $this->linkTitle,
            'label' => $this->linkLabel
        ]);
        if ($this->linkIcon) {
            $this->add(Icon::create($this->linkIcon, [
                'class' => 'link-color'
            ]));
        }
    }
}
