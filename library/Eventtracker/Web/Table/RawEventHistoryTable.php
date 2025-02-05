<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Json\JsonString;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Time;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class RawEventHistoryTable extends BaseTable
{
    use TranslationHelper;

    protected $searchColumns = [
        'i.host_name',
        'i.object_name',
        'i.object_class',
        'i.ticket_ref',
        'ah.message'
    ];

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('ts_received', $this->translate('Time'), [
                'ts_received' => 'rh.ts_received',
                'processing_result'   => 'rh.processing_result',
                'uuid'        => 'rh.event_uuid',
            ])->setRenderer(function ($row) {
                return $this->linkToObject($row->uuid, [
                    $row->processing_result,
                    ' ',
                    Time::agoFormatted($row->ts_received)
                ]);
            })->setDefaultSortDirection('DESC'),
            $this->createColumn('raw_input', $this->translate('Raw Event'), [
                'raw_input'           => 'rh.raw_input',
                'processing_result'   => 'rh.processing_result',
                'error_message'       => 'rh.error_message',
                'input_format'        => 'rh.input_format',
            ])->setRenderer(function ($row) {
                if ($row->processing_result === 'failed' || $row->error_message) {
                    $result[] = Html::tag('span', ['class' => 'error'], $row->error_message);
                    $result[] = Html::tag('br');
                }
                if ($row->input_format === 'json') {
                    $result[] = Html::tag(
                        'pre',
                        JsonString::encode(JsonString::decode($row->raw_input), JSON_PRETTY_PRINT)
                    );
                } else {
                    $result[] = $row->raw_input;
                }

                return $result;
            }),
        ]);
    }

    protected function linkToObject($uuid, $label)
    {
        return Link::create($label, 'eventtracker/history/event', [
            'uuid' => Uuid::fromBytes($uuid)->toString()
        ], [
            'title' => $label
        ]);
    }

    public function getDefaultColumnNames()
    {
        return [
            'ts_received',
            'raw_input',
        ];
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from(['rh' => 'raw_event'], []);
        return $query->columns($this->getRequiredDbColumns());
    }
}
