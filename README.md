Event Tracker
=============

> **Please do not use this module**. It's an early prototype for a specific
> migration project, designed to replace a BMC Event Manager. Breaking changes
> will take place with no prior announcement.

Purpose
-------

This module allows Operators to track Events from various sources in a single
place. It provides Hooks allowing Third-Party modules to trigger custom actions,
with Ticket/Issue-Creation being the most obvious use-case.

There are also hooks for a back-channel, providing information regarding created
tickets and acknowledged or resolved problems to various Event senders.

Configuration
-------------

To get this up and running, please:

* create a MySQL/MariaDB database
* apply the provided schema file in `schema/mysql.sql`
* define a related DB resource in Icinga Web 2

Now you're ready to populate `/etc/icingaweb2/modules/eventtracker/config.ini`:

### Refer the configured DB resource

```ini
[db]
resource = "Event Tracker"
```

### Eventually override default filter settings:

Per default, this module shows all problems starting from a `warning` level.
Filters are customizable in the UI, you can optionally customize the
default setting as follows:

```ini
[default-filters]
severity = emergency, alert, critical, error
```

### Synchronize custom variables from the IDO database

This module replicates available Icinga Object names from the IDO database, and
optionally also fetches Custom Variables. In case you need such, please define
then:

```ini
[ido-sync]
vars = location, priority
```

### Force msend command logging

Forwarding msend-like parameters via HTTP might become tricky, that's why we
provide a script that behaves like `msend` in `contrib/msend-eventtracker`. In
case you need to wrap this in a custom script and face encoding issues, logging
every single command might help:

```ini
[msend]
force_log = yes
```

### Disable parts of the provided features

It might be desirable to disable some of the provided features. Currently this
is possible only for the part that deals with the `owner` of your issues:

```ini
[features]
disabled = owner
```

### SCOM integration

In case you want to periodically replicate issues from SCOM, please add a
dedicated section:

```ini
[scom]
db_resource = "MSSQL SCOM"
; simulation_file = /tmp/scomtest.json
poll_interval = 5
cmd_update = "/usr/bin/ssh icinga@scom.example.com 'c:\\Scripts\\UpdateScomAlertTicketIdV1.ps1' '{sender_event_id}' '{ticket_ref}' '{owner}'"
cmd_close = "/usr/bin/ssh icinga@scom.example.com 'c:\\Scripts\\ResetScomMonitorV3.ps1' '{sender_event_id}'"
```

Usually you want to fetch from the MSSQL database populated by SCOM, so please
provide a related `db_resource`.
