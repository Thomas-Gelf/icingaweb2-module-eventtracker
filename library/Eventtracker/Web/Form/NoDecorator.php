<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Contract\FormElement;
use ipl\Html\Contract\FormElementDecorator;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;

class NoDecorator extends BaseHtmlElement implements FormElementDecorator
{
    const CSS_CLASS_ELEMENT_HAS_ERRORS = 'gipfl-form-element-has-errors';

    const CSS_CLASS_ELEMENT_ERRORS = 'gipfl-form-element-errors';

    const CSS_CLASS_DESCRIPTION = 'gipfl-element-description';

    protected $tag = 'div';

    protected $dt;

    protected $dd;

    /** @var FormElement */
    protected $element;

    /** @var HtmlDocument */
    protected $elementDoc;

    /**
     * @param FormElement $element
     * @return static
     */
    public function decorate(FormElement $element)
    {
        $decorator = clone($this);
        $decorator->initialize($element);

        return $decorator;
    }

    protected function initialize(FormElement $element)
    {
        $this->element = $element;
        $this->elementDoc = new HtmlDocument();
        $this->elementDoc->add($element);
        // if (! $element instanceof HiddenElement) {
        $element->prependWrapper($this);
        /** @var Attributes $attrs */
        $attrs = $element->getAttributes();
        if ($width = $attrs['width']) {
            unset($attrs['width']);
            $attrs['width'] = '100%';
            $this->addAttributes([
                'style' => 'width: ' . $width->getValue() . ';'
            ]);
        }
    }

    protected function prepareLabel(): ?HtmlElement
    {
        $element = $this->element;
        $label = $element->getLabel();
        if ((string) $label === '') {
            return null;
        }

        // Set HTML element.id to element name unless defined
        if ($element->getAttributes()->has('id')) {
            $attributes = ['for' => $element->getAttributes()->get('id')->getValue()];
        } else {
            $attributes = null;
        }

        if ($element->isRequired()) {
            $label = [$label, Html::tag('span', ['aria-hidden' => 'true'], '*')];
        }

        return Html::tag('label', $attributes, $label);
    }

    protected function prepareDescription(): ?HtmlElement
    {
        if ($this->element) {
            $description = (string) $this->element->getDescription();
            if ($description !== '') {
                return Html::tag('p', ['class' => static::CSS_CLASS_DESCRIPTION], $description);
            }
        }

        return null;
    }

    protected function prepareErrors(): ?HtmlElement
    {
        $errors = [];
        foreach ($this->element->getMessages() as $message) {
            $errors[] = Html::tag('li', $message);
        }

        if (empty($errors)) {
            return null;
        }

        return Html::tag('ul', ['class' => static::CSS_CLASS_ELEMENT_ERRORS], $errors);
    }

    public function add($content)
    {
        // Our wrapper implementation automatically adds the wrapped element but
        // we already do so in assemble()
        if ($content !== $this->element) {
            parent::add($content);
        }

        return $this;
    }

    protected function assemble()
    {
        $child = new HtmlDocument();
        $child->add($this->element);
        $this->add([$child]);
    }

    public function getElementDocument()
    {
        return $this->elementDoc;
    }

    public function dt()
    {
        if ($this->dt === null) {
            $this->dt = Html::tag('dt', null, $this->prepareLabel());
        }

        return $this->dt;
    }

    /**
     * @return HtmlElement
     */
    public function dd()
    {
        if ($this->dd === null) {
            $this->dd = Html::tag('dd', null, [
                $this->getElementDocument(),
                $this->prepareErrors(),
                $this->prepareDescription()
            ]);
        }

        return $this->dd;
    }

    public function __destruct()
    {
        $this->wrapper = null;
    }
}
