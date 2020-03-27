<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class MssqlProcessesTable extends BaseTable
{
    protected $defaultAttributes = [
        'class' => 'common-table'
    ];

    public function prepareQuery()
    {
        return $this->db()
            ->select()
            ->from('sys.dm_exec_requests', $this->getRequiredDbColumns())
            ->order('session_id')
            ;
    }

    protected function initialize()
    {
        $cols = [
            'session_id',
            'request_id',
            'start_time',
            'status',
            'command',
            'sql_handle', // => 'sys.dm_exec_sql_text(sql_handle)',
            'statement_start_offset',
            'statement_end_offset',
            'plan_handle',
            'database_id',
            'user_id',
            'connection_id',
            'blocking_session_id',
            'wait_type',
            'wait_time',
            'last_wait_type',
            'wait_resource',
            'open_transaction_count',
            'open_resultset_count',
            'transaction_id',
            'context_info',
            'percent_complete',
            'estimated_completion_time',
            'cpu_time',
            'total_elapsed_time',
            'scheduler_id',
            'task_address',
            'reads',
            'writes',
            'logical_reads',
            'text_size',
            'language',
            'date_format',
            'date_first',
            'quoted_identifier',
            'arithabort',
            'ansi_null_dflt_on',
            'ansi_defaults',
            'ansi_warnings',
            'ansi_padding',
            'ansi_nulls',
            'concat_null_yields_null',
            'transaction_isolation_level',
            'lock_timeout',
            'deadlock_priority',
            'row_count',
            'prev_error',
            'nest_level',
            'granted_query_memory',
            'executing_managed_code',
            'group_id',
            'query_hash',
            'query_plan_hash',
            'statement_sql_handle',
            'statement_context_id',
            'dop',
            'parallel_worker_count',
            'external_script_request_id',
            'is_resumable',
            'page_resource',
            'page_server_reads',
        ];
        foreach ($cols as $key => $col) {
            if (is_int($key)) {
                $this->addAvailableColumn($this->createColumn($col));
            } else {
                $this->addAvailableColumn($this->createColumn($key, null, $col));
            }
        }
    }
}
