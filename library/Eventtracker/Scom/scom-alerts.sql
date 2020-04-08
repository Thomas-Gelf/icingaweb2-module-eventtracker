SELECT
  CONVERT([varchar](64), alert.AlertId) AS alert_id,
  alert.IsMonitorAlert AS is_monitor,
  alert.TimeRaised AS time_raised,
  CONVERT([varchar](64), COALESCE(rules.RuleId, monitor.MonitorId)) AS rule_monitor_id,
  alert.TicketId AS ticket_id,
  COALESCE(rules.RuleCategory, monitor.MonitorCategory) AS category,

  CASE alert.Severity
    WHEN 2 THEN 'critical'
    WHEN 1 THEN 'warning'
    WHEN 0 THEN 'informational'
    END AS alert_severity,
  CASE alert.Priority
    WHEN 2 THEN 'high'
    WHEN 1 THEN 'normal'
    WHEN 0 THEN 'low'
  END AS alert_priority,

  alert.ResolutionState AS resolution_state,
  rs.ResolutionStateName AS resolution_state_name,

  LOWER(topentity.Name) AS entity_name,
  topentitybasetype.TypeName AS entity_base_type,
  topentitytype.TypeName AS entity_type,
  entitytype.TypeName AS object_type,

  COALESCE(prettyalert.AlertName, rules.RuleName, monitor.MonitorName, alert.AlertName) as alert_name,
  prettyalert.AlertDescription as description,
  MM.IsInMaintenanceMode

FROM OperationsManager.dbo.Alert alert
JOIN OperationsManager.dbo.BaseManagedEntity entity ON entity.BaseManagedEntityId = alert.BaseManagedEntityId
JOIN OperationsManager.dbo.ResolutionState rs ON rs.ResolutionState = alert.ResolutionState
JOIN OperationsManagerDW.Alert.vAlert prettyalert ON prettyalert.AlertGuid = alert.AlertId

LEFT JOIN OperationsManager.dbo.BaseManagedEntity topentity ON topentity.BaseManagedEntityId = entity.TopLevelHostEntityId
LEFT JOIN OperationsManager.dbo.ManagedType entitytype ON entitytype.ManagedTypeId = entity.BaseManagedTypeId
LEFT JOIN OperationsManager.dbo.ManagedType topentitytype ON topentitytype.ManagedTypeId = topentity.BaseManagedTypeId
LEFT JOIN OperationsManager.dbo.ManagedType topentitybasetype ON topentitybasetype.ManagedTypeId = topentitytype.BaseManagedTypeId
LEFT JOIN OperationsManager.dbo.Rules rules ON rules.RuleId = alert.RuleId
LEFT JOIN OperationsManager.dbo.Monitor monitor ON monitor.MonitorId = alert.RuleId
LEFT JOIN OperationsManager.dbo.MaintenanceMode mm ON mm.BaseManagedEntityId = alert.BaseManagedEntityId

WHERE alert.ResolutionState = 0
  AND alert.Severity > 0
ORDER BY alert.Severity DESC, alert.TimeRaised DESC
