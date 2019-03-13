<?php

namespace Icinga\Module\Eventtracker\Web;

use Icinga\Web\Helper\HtmlPurifier as IcingaHtmlPurifier;
use ipl\Html\HtmlString;
use ipl\Html\ValidHtml;

class HtmlPurifier extends IcingaHtmlPurifier
{
    protected function configure($config)
    {
        $config->set(
            'HTML.Allowed',
            'p,br,b,a[href|target]'
        );
    }

    public static function process($html, $config = null)
    {
        return new HtmlString(parent::process($html, $config));
    }
}
