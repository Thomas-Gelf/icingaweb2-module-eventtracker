<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Json\JsonString;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form\Feature\NextConfirmCancel;
use Icinga\Module\Eventtracker\Data\PlainObjectRenderer;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Syslog\SyslogParser;
use Icinga\Module\Eventtracker\Web\Form\InstanceInlineForm;
use ipl\Html\Html;
use ipl\Html\Table;
use Psr\Http\Message\ServerRequestInterface;

class ChannelRulesTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class' => [
            'common-table',
            'table-row-selectable',
        ]
    ];

    /**
     * @var ModifierChain
     */
    protected $modifierChain;
    /**
     * @var Url
     */
    protected $url;
    /**
     * @var ServerRequestInterface
     */
    protected $request;

    public function __construct(ModifierChain $modifierChain, Url $url, ServerRequestInterface $request)
    {
        $this->modifierChain = $modifierChain;
        $this->url = $url;
        $this->request = $request;
    }

    protected function assemble()
    {
        $row = -1;
        if ($object = $this->getSampleObject()) {
            $old = PlainObjectRenderer::render($object);
            $this->add($this::tr($this::td([
                [
                    Html::tag('h3', "Original Event"),
                    Html::tag('pre', [
                        'class' => 'plain-object'
                    ], PlainObjectRenderer::render($object)),
                ]
            ], ['colspan' => 3])));
        } else {
            $old = null;
        }
        foreach ($this->modifierChain->getModifiers() as list($propertyName, $modifier)) {
            $row++;
            if ($old === null) {
                $show = null;
            } else {
                ModifierChain::applyModifier($modifier, $object, $propertyName);
                $new = PlainObjectRenderer::render($object);
                $show = Html::tag('div', new SideBySideDiff(new PhpDiff($old, $new)));
                $old = $new;
            }
            $this->add($this::row([
                $this::td([
                    Link::create(
                        [
                            Icon::create('right-dir'),
                            $modifier->describe($propertyName)
                        ],
                        '#'/*$this->url->setParams([
                            'modifier' => $row,
                            'checksum' => ModifierUtils::getShortConfigChecksum($propertyName, $modifier),
                        ] + $this->url->getParams()->toArray(false))*/,
                        null,
                        ['class' => 'control-collapsible']
                    ),
                    $show
                ], [
                    'class' => ['collapsible-table-row', 'collapsed']
                ]),
                $this::td([
                    $this->disableButton('X' . $row),
                    $this->deleteButton('X' . $row),
                    Icon::create('angle-down'),
                    Icon::create('angle-up'),
                ], [
                    'style' => 'text-align: right; width: 14em'
                ])
            ]));
        }
    }

    protected function getSampleObject()
    {
        return SyslogParser::parseLine(
            'Jan 11 13:12:54 goj oem_syslog[2837832]: timestamp=2025-01-11T12:12:54.560Z'
            . ' hostname=kri.example.com component=kri.example.com id=2837644 state=nok severity=2 oem_clear=false'
            . ' oem_host_name=kri.example.com oem_incident_ack_by_owner=no url=https://ip.gelf.net oem_incident_id=4996'
            . ' oem_incident_status=new'
            . ' oem_issue_type=incident oem_target_name=kri.example.com oem_target_type=host msg=Alert; Value=7;'
            . ' String <Returncode:> with values <> 0 found in /var/log/dbms/load_dbclone_for_oracle_mssql.log!'
            . ' OEMIncidentID: 4996'
        );
        return SyslogParser::parseLine(
            'Jan 11 13:12:54 goj oem_syslog[2837832]: timestamp=2025-01-11T12:12:54.560Z'
            . ' hostname=kri.example.com component=kri.example.com id=2837644 state=nok severity=2 oem_clear=false'
            . ' oem_host_name=kri.example.com oem_incident_ack_by_owner=no oem_incident_id=4996 oem_incident_status=new'
            . ' oem_issue_type=incident oem_target_name=kri.example.com oem_target_type=host msg=Alert; Value=7;'
            . ' String <Returncode:> with values <> 0 found in /var/log/dbms/dbclone_for_oracle_mssql.log!'
            . ' OEMIncidentID: 4996'
        );
        return JsonString::decode(
            '{' . "\n"
            . '    "host_name": "goj",' . "\n"
            . '    "object_name": "oem_syslog",' . "\n"
            . '    "object_class": "user",' . "\n"
            . '    "severity": "critical",' . "\n"
            . '    "priority": null,' . "\n"
            . '    "message": "timestamp=2025-02-02T13:24:30.276Z hostname=atb.example.com'
            . ' component=DBMS03_SITE1.EXAMPLE id=125690 state=nok severity=1 oem_clear=false'
            . ' oem_host_name=atb.example.com oem_incident_ack_by_owner=no oem_incident_id=144826'
            . ' oem_incident_status=new oem_issue_type=incident oem_target_name=dbms03_site1.example'
            . ' oem_target_type=oracle_pdb msg=The pluggable database DBMS03_SITE1.EXAMPLE is down.'
            . ' OEMIncidentID: 144826",' . "\n"
            . '    "attributes": {' . "\n"
            . '        "syslog_sender_pid": 125795' . "\n"
            . '    }' . "\n"
            . '}' . "\n"
        );
        return null;
    }

    protected function deleteButton($key)
    {
        $form = new InstanceInlineForm($key);
        $confirm = new NextConfirmCancel(
            NextConfirmCancel::buttonNext($this->translate('Delete')),
            $yes = NextConfirmCancel::buttonConfirm($this->translate('YES, really delete')),
            NextConfirmCancel::buttonCancel($this->translate('Cancel'), [
                'formnovalidate' => true
            ])
        );
        $form->handleRequest($this->request);
        $confirm->addToForm($form);
        if ($yes->hasBeenPressed()) {
            var_dump("KILL $key");
        }

        return $form;
    }

    protected function disableButton($key)
    {
        $form = new InstanceInlineForm($key);
        $confirm = new NextConfirmCancel(
            NextConfirmCancel::buttonNext($this->translate('Disable')),
            $yes = NextConfirmCancel::buttonConfirm($this->translate('YES, disable now')),
            NextConfirmCancel::buttonCancel($this->translate('Cancel'), [
                'formnovalidate' => true
            ])
        );
        $form->handleRequest($this->request);
        $confirm->addToForm($form);
        if ($yes->hasBeenPressed()) {
            var_dump("KILL $key");
        }

        return $form;
    }

    protected function enableButton($key)
    {
        $form = new InstanceInlineForm($key);
        $confirm = new NextConfirmCancel(
            NextConfirmCancel::buttonNext($this->translate('Enable')),
            $yes = NextConfirmCancel::buttonConfirm($this->translate('YES, enable now')),
            NextConfirmCancel::buttonCancel($this->translate('Cancel'), [
                'formnovalidate' => true
            ])
        );
        $form->handleRequest($this->request);
        $confirm->addToForm($form);
        if ($yes->hasBeenPressed()) {
            var_dump("KILL $key");
        }

        return $form;
    }
}
