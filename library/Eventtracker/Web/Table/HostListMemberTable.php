<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use Icinga\Module\Eventtracker\Web\WebAction;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class HostListMemberTable extends BaseTable
{
    /** @var WebAction */
    protected $action;
    protected $hasColumnEnabled = false;
    /** @var UuidInterface */
    protected $hostListUuid;

    public function __construct($db, UuidInterface $hostListUuid)
    {
        parent::__construct($db);
        $this->hostListUuid = $hostListUuid;
    }

    protected function initialize()
    {
        $dateFormatter = new LocalDateFormat();
        $timeFormatter = new LocalTimeFormat();
        $formatTime = function ($value) use ($dateFormatter, $timeFormatter) {
            if ($value === null) {
                return '-';
            }

            return $dateFormatter->getFullDay($value / 1000) . ' '
                . $timeFormatter->getShortTime($value);
        };
        $this->addAvailableColumns([
            $this->createColumn('hostname', $this->translate('Host'), ['hostname'])->setRenderer(function ($row) {
                return [
                    Link::create(Icon::create('cancel'), '#'),
                    ' ',
                    $row->hostname,
                ];
            }),
            // $this->createColumn('start_time', $this->translate('From'))
            //->setRenderer(function ($row) use ($formatTime) {
            //     return $formatTime($row->start_time);
            // }),
            // $this->createColumn('end_time', $this->translate('To'))->setRenderer(function ($row) use ($formatTime) {
            //     return $formatTime($row->end_time);
            // }),
        ]);
    }

    public function prepareQuery()
    {
        return $this->db()
            ->select()
            ->from(['hlm' => 'host_list_member'], $this->getRequiredDbColumns())
            ->where('hlm.list_uuid = ?', $this->hostListUuid->getBytes())
            ->order('hostname');
    }
}
