<?php

namespace Icinga\Module\Eventtracker\Scom;

use Zend_Db_Adapter_Pdo_Mssql as Mssql;
use Zend_Db_Expr as DbExpr;

class ScomQuery
{
    public static function getDefaultColumns()
    {
        // Mappings:
        // host_name       = $obj->entity_name,
        // object_name     = \substr($obj->alert_name, 0, 128),
        // object_class    = Microsoft SQL Server, Microsoft.SystemCenter.FunctionalInstanceContainer,
        //                   Microsoft.Windows.Cluster.Component, Microsoft.Windows.Server.AD.Library.ServiceComponent,
        //                   Microsoft.Windows.Server.AD.ServiceComponent, System.Computer, System.Entity,
        //                   System.Group, ...
        // severity        = alert_severity
        // priority        = alert_priority
        // message         = description | '-'
        // sender_event_id = alert_id
        // sender_id       = 1 -> SCOM / new-scom

        return [
            'alert_id'        => 'CONVERT([varchar](64), alert.AlertId)',
            'is_monitor'      => 'alert.IsMonitorAlert',
            'time_raised'     => 'alert.TimeRaised',
            'rule_monitor_id' => 'CONVERT([varchar](64), COALESCE(rules.RuleId, monitor.MonitorId))',
            'ticket_id'       => 'alert.TicketId',
            'category'        => 'COALESCE(rules.RuleCategory, monitor.MonitorCategory)',
            'alert_severity'  => "CASE alert.Severity
    WHEN 2 THEN 'critical'
    WHEN 1 THEN 'warning'
    WHEN 0 THEN 'informational'
  END",
            'alert_priority' => "CASE alert.Priority
    WHEN 2 THEN 'high'
    WHEN 1 THEN 'normal'
    WHEN 0 THEN 'low'
  END",
            'resolution_state' => 'alert.ResolutionState',
            'resolution_state_name' => 'rs.ResolutionStateName',
            'entity_name'      => 'LOWER(topentity.Name)',
            'entity_base_type' => 'topentitybasetype.TypeName',
            'entity_type'      => 'topentitytype.TypeName',
            'object_type'      => 'entitytype.TypeName',
            'alert_name'       => 'COALESCE(prettyalert.AlertName, rules.RuleName,'
                . ' monitor.MonitorName, alert.AlertName)',
            'description'      => 'prettyalert.AlertDescription',
            'in_maintenance'   => 'MM.IsInMaintenanceMode',
        ];
    }

    public static function prepareBaseQuery(Mssql $db)
    {
        $query = $db->select()
            ->from(
                ['alert' => new DbExpr('OperationsManager.dbo.Alert')],
                []
            )->join(
                ['entity' => new DbExpr('OperationsManager.dbo.BaseManagedEntity')],
                'entity.BaseManagedEntityId = alert.BaseManagedEntityId',
                []
            )->join(
                ['rs' => new DbExpr('OperationsManager.dbo.ResolutionState')],
                'rs.ResolutionState = alert.ResolutionState',
                []
            )->joinLeft(
                ['prettyalert' => new DbExpr('OperationsManagerDW.Alert.vAlert')],
                'prettyalert.AlertGuid = alert.AlertId',
                []
            )->joinLeft(
                ['topentity' => new DbExpr('OperationsManager.dbo.BaseManagedEntity')],
                'topentity.BaseManagedEntityId = entity.TopLevelHostEntityId',
                []
            )->joinLeft(
                ['entitytype' => new DbExpr('OperationsManager.dbo.ManagedType')],
                'entitytype.ManagedTypeId = entity.BaseManagedTypeId',
                []
            )->joinLeft(
                ['topentitytype' => new DbExpr('OperationsManager.dbo.ManagedType')],
                'topentitytype.ManagedTypeId = topentity.BaseManagedTypeId',
                []
            )->joinLeft(
                ['topentitybasetype' => new DbExpr('OperationsManager.dbo.ManagedType')],
                'topentitybasetype.ManagedTypeId = topentitytype.BaseManagedTypeId',
                []
            )->joinLeft(
                ['rules' => new DbExpr('OperationsManager.dbo.Rules')],
                'rules.RuleId = alert.RuleId',
                []
            )->joinLeft(
                ['monitor' => new DbExpr('OperationsManager.dbo.Monitor')],
                'monitor.MonitorId = alert.RuleId',
                []
            )->joinLeft(
                ['mm' => new DbExpr('OperationsManager.dbo.MaintenanceMode')],
                'mm.BaseManagedEntityId = alert.BaseManagedEntityId',
                []
            );

        $query->where('alert.ResolutionState = 0')
            ->where('alert.Severity > 0');
            // ->order('alert.Severity DESC')
            // ->order('alert.TimeRaised DESC')

        return $query;
    }
}
