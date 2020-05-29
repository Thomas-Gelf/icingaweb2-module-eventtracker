<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlString;

class NameValuePreFormatted extends BaseHtmlElement
{
    protected $tag = 'pre';

    protected $defaultAttributes = ['class' => 'name-value-pre'];

    protected $pairs = [];

    public function addNameValueRow($name, $value)
    {
        return $this->pairs[] = [$name, $value];
    }

    public function addNameValuePairs($pairs)
    {
        foreach ($pairs as $name => $value) {
            $this->addNameValueRow($name, $value);
        }

        return $this;
    }

    protected function assemble()
    {
        $maxKeyLength = 0;
        foreach ($this->pairs as $pair) {
            $maxKeyLength = \max($maxKeyLength, \strlen($pair[0]));
        }
        $keyLength = $maxKeyLength + 1;
        $preSpaces = \str_repeat(' ', $keyLength);

        $first = true;
        foreach ($this->pairs as $pair) {
            if ($first) {
                $first = false;
            } else {
                $this->add("\n");
            }
            $len = \mb_strlen(\strip_tags(Html::wantHtml($pair[0])->render()));
            // Newlines should not harm the layout:
            $value = new HtmlString(\preg_replace(
                '/\n/',
                "\n $preSpaces", // the extra space is for the colon
                Html::wantHtml($pair[1])->render()
            ));
            $this->add([
                Html::tag('strong', $pair[0]),
                ':',
                \str_repeat(' ', $keyLength - $len),
                $value,
            ]);
        }
    }
}
