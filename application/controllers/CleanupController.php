<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\Web\Widget\Hint;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Eventtracker\Web\Form\DbCleanupFilterForm;
use Icinga\Web\Notification;

class CleanupController extends Controller
{
    use AsyncControllerHelper;
    use IssuesFilterHelper;

    public function init()
    {
        parent::init();
        $this->assertPermission('eventtracker/admin');
        if ($this->Config()->get('ui', 'allows_cleanup') !== 'true') {
            throw new NotFoundError('Not found');
        }
    }

    public function indexAction()
    {
        $this->addSingleTab('DB Cleanup');
        $this->addTitle('Event Tracker Database Cleanup');
        $this->content()->add(Hint::warning($this->translate(
            'Remove open issues from the Eventtracker Database. Please note, that this is irreversible.'
            . ' No related actions or triggers will be fired, and there will be no related historic'
            . ' reference.'
        )));
        $form = new DbCleanupFilterForm();
        $form->on($form::ON_SUCCESS, function (DbCleanupFilterForm $form) {
            if ($form->wantsSimulation()) {
                $count = $this->syncRpcCall(
                    'cleanup.simulateDelete' . ucfirst($form->getTable()),
                    ['filter' => $form->getFilter()]
                );
                Notification::success(sprintf(
                    $this->translate('%d %s would have been deleted'),
                    $count,
                    $form->getTable()
                ));
            } else {
                $this->syncRpcCall(
                    'cleanup.delete' . ucfirst($form->getTable()),
                    ['filter' => $form->getFilter()]
                );
                Notification::success(sprintf(
                    $this->translate('%s cleanup has been started'),
                    $form->getTable()
                ));
                $this->redirectNow($this->url());
            }
        });
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
    }
}
