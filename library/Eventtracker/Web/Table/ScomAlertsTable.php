<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use Icinga\Module\Eventtracker\Scom\ScomQuery;

class ScomAlertsTable extends BaseTable
{
    protected $defaultAttributes = [
        'class' => 'common-table'
    ];

    protected $searchColumns = [
        'entity_name',
        'alert_name',
    ];

    public function prepareQuery()
    {
        /** @var \Zend_Db_Adapter_Pdo_Mssql $db */
        $db = $this->db();
        return ScomQuery::prepareBaseQuery($db)->columns(
            ScomQuery::getDefaultColumns()
        );
    }

    public function getDefaultColumnNames()
    {
        return parent::getDefaultColumnNames(); // TODO: Change the autogenerated stub
    }

    public function getDefaultSortColumns()
    {
        return [
            // 'alert_severity DESC',
            'entity_name',
        ];
    }

    protected function initialize()
    {

        foreach (ScomQuery::getDefaultColumns() as $alias => $expression) {
            $this->addAvailableColumn(
                $this->createColumn($alias, null, $expression)
            );
        }
    }
}
