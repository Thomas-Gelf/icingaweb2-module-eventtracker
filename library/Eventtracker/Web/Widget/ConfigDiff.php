<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use Diff;
use ipl\Html\ValidHtml;
use InvalidArgumentException;

class ConfigDiff implements ValidHtml
{
    protected $a;

    protected $b;

    protected $diff;

    protected $htmlRenderer = 'SideBySide';

    protected $knownHtmlRenderers = [
        'SideBySide',
        'Inline',
    ];

    protected $knownTextRenderers = [
        'Context',
        'Unified',
    ];

    protected $moduleDir;

    protected function __construct($a, $b)
    {
        $this->moduleDir = \dirname(\dirname(\dirname(\dirname(__DIR__))));
        require_once $this->moduleDir . '/vendor/php-diff/lib/Diff.php';

        if (empty($a)) {
            $this->a = [];
        } else {
            $this->a = explode("\n", (string) $a);
        }

        if (empty($b)) {
            $this->b = [];
        } else {
            $this->b = explode("\n", (string) $b);
        }

        $options = [
            'context' => 5,
            // 'ignoreWhitespace' => true,
            // 'ignoreCase' => true,
        ];
        $this->diff = new Diff($this->a, $this->b, $options);
    }

    public function render()
    {
        return $this->renderHtml();
    }

    /**
     * @return string
     */
    public function renderHtml()
    {
        return $this->diff->Render($this->getHtmlRenderer());
    }

    public function setHtmlRenderer($name)
    {
        if (in_array($name, $this->knownHtmlRenderers)) {
            $this->htmlRenderer = $name;
        } else {
            throw new InvalidArgumentException("There is no known '$name' renderer");
        }

        return $this;
    }

    protected function getHtmlRenderer()
    {
        $filename = sprintf(
            '%s/vendor/php-diff/lib/Diff/Renderer/Html/%s.php',
            $this->moduleDir,
            $this->htmlRenderer
        );
        require_once($filename);

        $class = 'Diff_Renderer_Html_' . $this->htmlRenderer;

        return new $class;
    }

    public function __toString()
    {
        return $this->renderHtml();
    }

    public static function create($a, $b)
    {
        return new static($a, $b);
    }
}
