<?php

namespace Icinga\Module\Eventtracker\Web;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Validator\PhpSessionBasedCsrfTokenValidator;
use ipl\Html\Form as iplForm;
use ipl\Html\FormDecorator\DdDtDecorator;
use ipl\Html\FormElement\HiddenElement;
use ipl\Html\Html;
use RuntimeException;

class Form extends iplForm
{
    use TranslationHelper;

    protected $formNameElementName = '__FORM_NAME';

    public function ensureAssembled()
    {
        if ($this->hasBeenAssembled === false) {
            if ($this->getRequest() === null) {
                throw new RuntimeException('Cannot assemble a WebForm without a Request');
            }
            // TODO? $this->addElementLoader(__NAMESPACE__ . '\\Element');
            parent::ensureAssembled();
            $this->prepareWebForm();
        }

        return $this;
    }

    protected function styleWithDirector()
    {
        Html::tag('div', [
            'style' => 'display: inline',
            'class' => 'icinga-module module-director'
        ])->wrap($this);

        return $this;
    }

    protected function prepareWebForm()
    {
        $this->addFormNameElement();
        if ($this->getMethod() === 'POST') {
            $this->addCsrfElement();
            $this->setupStyling();
        }
    }

    protected function getUniqueFormName()
    {
        return \get_class($this);
    }

    protected function addFormNameElement()
    {
        $this->addElement('hidden', $this->formNameElementName, [
            'value' => $this->getUniqueFormName()
        ]);
    }

    protected function setupStyling()
    {
        $this->setDefaultElementDecorator(new DdDtDecorator());
    }

    protected function addCsrfElement()
    {
        $element = new HiddenElement('__CSRF__');
        $element->setValidators([
            new PhpSessionBasedCsrfTokenValidator()
        ]);
        $this->addElement($element);
        if ($this->hasBeenSent()) {
            if (! $element->isValid()) {
                $element->setValue($this->generateCsrfValue());
            }
        } else {
            $element->setValue($this->generateCsrfValue());
        }
    }

    public function getSentValue($name, $default = null)
    {
        $request = $this->getRequest();
        if ($request === null) {
            throw new RuntimeException(
                "It's impossible to access SENT values with no request"
            );
        }
        if ($request->getMethod() === 'POST') {
            $params = $request->getParsedBody();
        } elseif ($this->getMethod() === 'GET') {
            \parse_str($request->getUri()->getQuery(), $params);
        } else {
            $params = [];
        }

        if (\array_key_exists($name, $params)) {
            return $params[$name];
        } else {
            return $default;
        }
    }

    public function hasBeenSent()
    {
        if (parent::hasBeenSent()) {
            return $this->getSentValue($this->formNameElementName)
                === $this->getUniqueFormName();
        } else {
            return false;
        }
    }

    protected function generateCsrfValue()
    {
        $seed = \mt_rand();
        $token = hash('sha256', \session_id() . $seed);

        return sprintf('%s|%s', $seed, $token);
    }

    // TODO: The decorator should take care, shouldn't it?
    public function onError()
    {
        foreach ($this->getMessages() as $message) {
            $this->prepend(Html::tag('p', ['class' => 'error'], $message));
        }
        foreach ($this->getElements() as $element) {
            foreach ($element->getMessages() as $message) {
                $this->prepend(Html::tag('p', ['class' => 'error'], $message));
            }
        }
    }
}
