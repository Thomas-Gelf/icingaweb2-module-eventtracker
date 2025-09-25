<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\IconHelper;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Json\JsonString;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form\Feature\NextConfirmCancel;
use gipfl\Web\InlineForm;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Eventtracker\Data\PlainObjectRenderer;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\ModifierRegistry;
use Icinga\Module\Eventtracker\Modifier\ModifierRuleStore;
use Icinga\Module\Eventtracker\Web\Form\ChannelConfigForm;
use Icinga\Module\Eventtracker\Web\Form\InstanceInlineForm;
use Icinga\Module\Eventtracker\Web\WebActions;
use Icinga\Web\Form\Element\Button;
use ipl\Html\Html;
use ipl\Html\Table;
use ipl\Web\Widget\ActionLink;
use ipl\Web\Widget\ButtonLink;
use Psr\Http\Message\ServerRequestInterface;

class ChannelRulesTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'id' => 'channel-rules-table',
        'class' => [
            'common-table',
        ]
    ];

    protected ModifierChain $modifierChain;
    protected Url $url;
    protected ServerRequestInterface $request;
    protected ModifierRuleStore $modifierRuleStore;
    private ?object $sampleObject;

    public function getModifierChain(): ModifierChain
    {
        return $this->modifierChain;
    }
    private ChannelConfigForm $form;
    private bool $hasBeenModified = false;

    public function __construct(
        ModifierChain $modifierChain,
        Url $url,
        ServerRequestInterface $request,
        ModifierRuleStore $modifierRuleStore,
        ?object $sampleObject = null
    ) {
        $this->modifierChain = $modifierChain;
        $this->url = $url;
        $this->request = $request;
        $this->sampleObject = $sampleObject;
        $this->modifierRuleStore = $modifierRuleStore;
    }

    public function isHasBeenModified(): bool
    {
        return $this->hasBeenModified;
    }

    protected function assemble()
    {
        $row = -1;
        if ($object = $this->sampleObject) {
            $object = clone($object);
            $old = PlainObjectRenderer::render($object);
        } else {
            $old = null;
        }
        $checksum = $this->modifierChain->getShortChecksum();
        foreach ($this->modifierChain->getModifiers() as list($propertyName, $modifier)) {
            $row++;
            $show = Html::tag('div', [
                'class' => ['collapsible-diff']
            ]);
            if ($old) {
                try {
                    ModifierChain::applyModifier($modifier, $object, $propertyName);
                    $new = PlainObjectRenderer::render($object);
                    $show->add(new SideBySideDiff(new PhpDiff($old, $new)));
                    $old = $new;
                } catch (\InvalidArgumentException $e) {
                    $show->add(Hint::error($e->getMessage()));
                } catch (\Throwable $e) {
                    $show->add(Hint::error($e));
                }
            }
            $this->add($this::row([
                $this::td([
                    $this->sampleObject ? Link::create(
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
                    ) : $modifier->describe($propertyName),
                    $show
                ], [
                    'class' => ['collapsible-table-row', 'collapsed']
                ]),
                $this::td([
                    $this->editButton($row, $modifier, $checksum),
                    $this->deleteButton($row),
                    $this->moveUpButton($row),
                    $this->moveDownButton($row),
                ])
            ]));
        }
    }

    public function hasModifications(): bool
    {
        $this->ensureAssembled();
        return $this->hasBeenModified;
    }

    protected function deleteButton($key): InstanceInlineForm
    {
        $form = new InstanceInlineForm('DEL_' . $key);
        $confirm = new NextConfirmCancel(
            NextConfirmCancel::buttonNext($this->translate(IconHelper::instance()->iconCharacter('trash')), [
                'class' => 'icon-button'
            ]),
            $yes = NextConfirmCancel::buttonConfirm($this->translate('YES, really delete')),
            NextConfirmCancel::buttonCancel($this->translate('Cancel'), [
                'formnovalidate' => true
            ])
        );
        $form->handleRequest($this->request);
        $confirm->addToForm($form);
        if ($yes->hasBeenPressed()) {
            $this->modifierChain->removeModifier($key);
            $this->modifierRuleStore->setModifierRules($this->modifierChain);
            $this->hasBeenModified = true;
        }
        return $form;
    }

    protected function editButton($key, $modifier, $checksum): Link
    {
        $link =  Link::create(
            Icon::create('edit', ['class' => 'icon-button']),
            'eventtracker/configuration/channelrules',
            [
                'uuid' => $this->url->getParam('uuid'),
                'row' => $key,
                'action' => 'edit',
                'checksum' => $checksum
            ],
        );
        return $link;
    }

    protected function moveUpButton($key): InlineForm
    {
        $form = new InstanceInlineForm($key);
        $form->addAttributes(['class' => 'move-up-form']);
        $form->handleRequest($this->request);
        $form->addElement('submit', 'submit', [
            'label' => IconHelper::instance()->iconCharacter('up-big'),
            'class' => 'icon-button'
        ]);
        if ($form->hasBeenSubmitted()) {
            $this->modifierChain->moveUp($key);
            $this->modifierRuleStore->setModifierRules($this->modifierChain);
            $this->hasBeenModified = true;
        }
        return $form;
    }

    protected function moveDownButton($key): InlineForm
    {
        $form = new InstanceInlineForm($key);
        $form->addAttributes(['class' => 'move-down-form']);
        $form->handleRequest($this->request);
        $form->addElement('submit', 'submit', [
            'label' => IconHelper::instance()->iconCharacter('down-big'),
            'class' => 'icon-button'
        ]);
        if ($form->hasBeenSubmitted()) {
            $this->modifierChain->moveDown($key);
            $this->modifierRuleStore->setModifierRules($this->modifierChain);
            $this->hasBeenModified = true;
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
