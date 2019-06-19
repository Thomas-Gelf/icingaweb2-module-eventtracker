<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Web\HtmlPurifier;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class IssueDetails extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $defaultAttributes = [
        'class' => 'issue-details',
    ];

    /** @var Issue */
    protected $issue;

    public function __construct(Issue $issue)
    {
        $this->issue = $issue;
    }

    protected function assemble()
    {
        $this->showMessage($this->issue);
    }

    protected function showMessage(Issue $issue)
    {
        $this->add(
            Html::tag('div', [
                'class' => 'output comment'
            ], [
                Html::tag('h2', 'MESSAGE'),
                Html::tag('pre', [
                    'style' => 'clear: both;'
                ], [
                    Html::tag('strong', $this->translate('Message') . ': '),
                    HtmlPurifier::process($issue->get('message')),
                ])
            ])
        );
    }
}
