<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Module\Eventtracker\Db\DbCleanup;
use Icinga\Module\Eventtracker\Db\DbCleanupFilter;
use Icinga\Module\Eventtracker\DbFactory;

/**
 * Cleanup commands for the Eventtracker database
 */
class DeleteCommand extends Command
{
    /**
     * Deletes current issues according to given rules
     *
     * At least a time constraint (--before) is required, other filter parameters
     * are optional. Alternatively, --force allows to wipe all issues in an unfiltered
     * way.
     *
     * USAGE
     *
     * icingacli eventtracker delete issues
     *    [--before <expression>|--keep-days <days>|--force]
     *    [--keep-severity <min-severity>]
     *    [--host_name <name>, [--host_name <name> ...]]
     *    [--object_name <name>, [--object_name <name> ...]]
     *    [--object_class <name>, [--object_class <name> ...]]
     *    [--simulate]
     *
     * OPTIONS
     *
     * --before <expression>  Expression can be a specific date (YYYY-MM-DD) or an
     *                        at-style expression like 'yesterday' or 'last monday'
     *
     * --keep-days <days>     Keep the given amount of days, delete everything older
     *                        Conflicts with '--before'
     *
     * --force                Delete issues, even if no time restriction has been
     *                        given (or keep-days was 0)
     *
     * --keep-severity <min-severity>  Does not delete issues with a severity equal
     *                        or greater than <min-severity>
     *
     * --host_name <name>     Deletes only issues for the given host. Can be supplied
     *                        multiple times
     *
     * --object_name <name>   Deletes only issues with the given object name. Can be
     *                        supplied multiple times
     *
     * --object_class <name>  Deletes only issues with the given object class. Can be
     *                        supplied multiple times
     *
     * --simulate             Does not delete, but gives the number of rows, that would
     *                        have been deleted
     *
     * --optimize             Run an OPTIMIZE TABLE after the cleanup
     */
    public function issuesAction()
    {
        $simulate = (bool) $this->params->shift('simulate');
        $cleanup = new DbCleanup(DbFactory::db(), 'issue', DbCleanupFilter::fromCliParams($this->params));
        if ($simulate) {
            printf('Dry run, %d issues would have been deleted', $cleanup->count());
        } else {
            printf('%d issues have been deleted', $cleanup->delete());
        }
    }

    /**
     * Deletes historic (closed) issues according to given rules
     *
     * At least a time constraint (--before) is required, other filter parameters
     * are optional. Alternatively, --force allows to wipe all historic issues in
     * an unfiltered way.
     *
     * USAGE
     *
     * icingacli eventtracker delete history
     *    [--before <expression>|--keep-days <days>|--force]
     *    [--keep-severity <min-severity>]
     *    [--host_name <name>, [--host_name <name> ...]]
     *    [--object_name <name>, [--object_name <name> ...]]
     *    [--object_class <name>, [--object_class <name> ...]]
     *    [--simulate]
     *
     * OPTIONS
     *
     * --before <expression>  Expression can be a specific date (YYYY-MM-DD) or an
     *                        at-style expression like 'yesterday' or 'last monday'
     *
     * --keep-days <days>     Keep the given amount of days, delete everything older
     *                        Conflicts with '--before'
     *
     * --force                Delete issues, even if no time restriction has been
     *                        given (or keep-days was 0)
     *
     * --keep-severity <min-severity>  Does not delete issues with a severity equal
     *                        or greater than <min-severity>
     *
     * --host_name <name>     Deletes only issues for the given host. Can be supplied
     *                        multiple times
     *
     * --object_name <name>   Deletes only issues with the given object name. Can be
     *                        supplied multiple times
     *
     * --object_class <name>  Deletes only issues with the given object class. Can be
     *                        supplied multiple times
     * --simulate             Does not delete, but gives the number of rows, that would
     *                        have been deleted
     * --optimize             Run an OPTIMIZE TABLE after the cleanup
     */
    public function historyAction()
    {
        $simulate = (bool) $this->params->shift('simulate');
        $cleanup = new DbCleanup(DbFactory::db(), 'issue_history', DbCleanupFilter::fromCliParams($this->params));
        if ($simulate) {
            printf("Dry run, %d issue history rows would have been deleted\n", $cleanup->count());
        } else {
            printf("%d issue history rows have been deleted\n", $cleanup->delete());
        }
    }
}
