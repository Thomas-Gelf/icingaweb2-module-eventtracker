<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\IcingaWeb2\Icon;
use Icinga\Module\Eventtracker\Web\Form;

class LinkLikeForm extends Form
{
    /** @var string */
    protected $linkLabel;

    /** @var string|null */
    protected $linkTitle;

    /** @var string|null */
    protected $linkIcon;

    /**
     * TestForm constructor.
     * @param string|$linkLabel
     * @param null $linkTitle
     * @param string|null $linkIcon
     */
    public function __construct($linkLabel, $linkTitle = null, $linkIcon = null)
    {
        $this->linkLabel = (string) $linkLabel;
        if ($linkTitle !== null) {
            $this->linkTitle = (string) $linkTitle;
        }
        if ($linkIcon !== null) {
            $this->linkIcon = (string) $linkIcon;
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
