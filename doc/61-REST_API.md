<a name="REST_API"></a>REST API
==================================

The Icinga Eventtracker provides a REST API for various tasks. Please note that
there are two different kinds of related Bearer Tokens. You can create "Inputs"
based on REST API Tokens, which allow sending Events pinned to exactly that Input,
bound to related (optionally configured) Rule/Transformation chains.

In addition to this, you can configure general-purpose tokens, with the possibility
to restrict their permissions. They can fetch, close or acknowledge issues - but
these keys are not allowed to submit Events.

REST API Requests - Overview
----------------------------

### Authentication

All REST API requests need to be authenticated, usually with a related Bearer Token.
Such a token can easily be created through our web UI. Please consider using different
tokens for different tasks and applications.

![REST API: Configure bearer token](screenshot/rest_api_close-configure_bearer_token.png)

### Accept Header

A REST API request is identified by its `Accept` header, which MUST be `application/json`:  

    Accept: application/json

Sample Requests
---------------

### Fetching issues

To fetch issues please use GET requests against `eventtracker/issues`:
```http
GET https://monitoring.example.com/icingaweb2/eventtracker/issues
Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
Accept: application/json
```

You'll get all open issues:

```json
[
    {
        "issue_uuid": "20a3379a-0356-48cd-8daf-e32a67f88cf0",
        "status": "open",
        "severity": "critical",
        "priority": "normal",
        "input_uuid": "65b3ff52-ed7b-4070-b3d5-850f1e29fc3e",
        "sender_id": 99999,
        "sender_event_id": "",
        "host_name": "some1-other.example.com",
        "object_class": "Job Errors",
        "object_name": "Some Job 1",
        "problem_identifier": null,
        "ts_expiration": null,
        "ts_first_event": 1729771313839,
        "ts_last_modified": 1729771313839,
        "cnt_events": 1,
        "owner": null,
        "ticket_ref": "#26325",
        "message": "The Job failed: ...",
        "attributes": {
            "team": "Operating",
            "wiki": "https://wiki.example.com/wiki/Some_Page"
        }
    },
    {
        "issue_uuid": "69741aa3-1983-4fb6-9e56-007e4bcda023",
        "status": "open",
        "severity": "critical",
        "priority": "normal",
        "input_uuid": "65b3ff52-ed7b-4070-b3d5-850f1e29fc3e",
        "sender_id": 99999,
        "sender_event_id": "",
        "host_name": "some-other.example.com",
        "object_class": "Job Errors",
        "object_name": "Some Job 1",
        "problem_identifier": null,
        "ts_expiration": null,
        "ts_first_event": 1712736765712,
        "ts_last_modified": 1712738003704,
        "cnt_events": 2,
        "owner": null,
        "ticket_ref": null,
        "message": "The Job failed: ... blabla",
        "attributes": {
            "team": "Operating",
            "wiki": "https://wiki.example.com/wiki/Other_Page"
        }
    }
]
```

This could become a lot of data, so first you might want to pick just some specific
properties. The URL parameter `properties` expects a comma-separated property list:

```http 
GET https://monitoring.example.com/eventtracker/issues?properties=issue_uuid,input_uuid,ticket_ref
Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
Accept: application/json
```

The result for the above request might look as follows:

```json
[
    {
        "issue_uuid": "20a3379a-0356-48cd-8daf-e32a67f88cf0",
        "input_uuid": "65b3ff52-ed7b-4070-b3d5-850f1e29fc3e",
        "ticket_ref": "#26325"
    },
    {
        "issue_uuid": "69741aa3-1983-4fb6-9e56-007e4bcda023",
        "input_uuid": "65b3ff52-ed7b-4070-b3d5-850f1e29fc3e",
        "ticket_ref": null
    }
]
```

When developing a tool with the intention to synchronize problems, it might come in
handy to filter by the related Input UUID:

```http
GET https://monitoring.example.com/eventtracker/issues?properties=issue_uuid,ticket_ref&input_uuid=65b3ff52-ed7b-4070-b3d5-850f1e29fc3e
Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
Accept: application/json
```

```json
[
    {
        "issue_uuid": "20a3379a-0356-48cd-8daf-e32a67f88cf0",
        "ticket_ref": "#26325"
    },
    {
        "issue_uuid": "69741aa3-1983-4fb6-9e56-007e4bcda023",
        "ticket_ref": null
    }
]
```

It's perfectly legal to combine multiple filter properties. Asterisks (`*`) can
be used for wildcard matches, brackets are possible, use pipe (`|`) symbols for
logical `OR`, ampersand (`&`) for logical `AND`:

```http
GET https://monitoring.example.com/eventtracker/issues?properties=issue_uuid,message,host_name
   &host_name=*example*&(message=*problem*|message=*bla*)
Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
Accept: application/json
```

Please check the Icinga Web documentation for more details about our URL filter
syntax. The result for the above request might look as follows:

```json
[
    {
        "issue_uuid": "69741aa3-1983-4fb6-9e56-007e4bcda023",
        "message": "The Job failed: ... blabla",
        "host_name": "some-other.example.com"
    },
    {
        "issue_uuid": "f99171d6-9c6c-480b-a14c-0fe4ddfa8126",
        "message": "There is a problem",
        "host_name": "app1.example.com"
    }
]
```

### Closing an Issue

To close an issue, please provide its UUID in an HTTP POST request. Example:

```http
POST https://monitoring.example.com/icingaweb2/eventtracker/issue/close?uuid=0f9ab9e0-600a-4e05-8e13-e48b20b1d37e&closedBy=Username
Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
Accept: application/json
```

As this has been implemented primarily for trouble ticket / service desk solutions,
and not all of them are able to track remote UUIDs and/or to ship them in a URL,
we also support closing issues by ticket ID:

```http
POST https://monitoring.example.com/icingaweb2/eventtracker/issue/close?ticket=43027&closedBy=Some%20Application
Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
Accept: application/json
```

Same as above, we also support closing issues via `sender_event_id`:

```http
POST https://icinga.example.com/icingaweb2/eventtracker/issue/close?sender_event_id=My%20Job%20Name&closedBy=me
Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
Accept: application/json
```

The `closedBy` parameter is required for all `close` variants.

#### Sample Responses

##### Success (200 Ok)

```json
{
  "success": true,
  "closedIssues": ["0f9ab9e0-600a-4e05-8e13-e48b20b1d37e"]
}
```

##### No related issue found (201 Unmodified)

```json
{
  "success": true,
  "error": "Found no issue for the given ticket/issue"
}
```

##### Other/internal error

```json
{
  "success": false,
  "error": "Unable to connect to socket:///run/icinga-eventtracker/eventtracker.sock"
}
```

### Acknowledge an Issue

To acknowledge an issue, please provide its UUID in an HTTP POST request. Example:

```http
POST https://monitoring.example.com/icingaweb2/eventtracker/issue/acknowledge?uuid=0f9ab9e0-600a-4e05-8e13-e48b20b1d37e&owner=John&ticker_ref=ITSM0231
Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
Accept: application/json
```

Acknowledging issues via `sender_event_id` is also supported:

```http
POST https://icinga.example.com/icingaweb2/eventtracker/issue/acknowledge?sender_event_id=My%20Job%20Name&owner=me
Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
Accept: application/json
```

The `owner` parameter is required for all `acknowledge` variants, the `ticket_ref` parameter is optional.

#### Sample Responses

##### Success (200 Ok)

```json
{
  "success": true,
  "issues": ["0f9ab9e0-600a-4e05-8e13-e48b20b1d37e"]
}
```

##### No related issue found (201 Unmodified)

```json
{
  "success": true,
  "error": "Found no issue for the given ticket/issue"
}
```

Creating Issues
---------------

Configuration for Event Senders is slightly different, as a related Event sender
must be configured:

![Configuration Dashboard: Inputs](screenshot/config_dashboard_inputs.png)

Same as above, the web form shows basic usage instructions and links to this documentation
section:

![REST API: Event Sender](screenshot/rest_api_event-sender.png)

### Issue / Event properties

You can send ANY property when sending Events via our REST API. Please note that
configured Rules / Transformations still apply, and might have a say on whether
your Event eventually becomes an Issue.

Properties are being validated AFTER your event passed the rule/filter/transformation
chain. An Event should have the following properties afterward

| Property             | Description                                                                                                                                                                                             |
|----------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `host_name`          | Related hostname, optional                                                                                                                                                                              |
| `object_name`        | Related subject / object name, optional                                                                                                                                                                 |
| `object_class`       | Object class, useful for summaries, required                                                                                                                                                            |
| `severity`           | debug, informational, notice, warning, error, critical, alert, emergency                                                                                                                                |
| `priority`           | lowest, low, normal, high, highest                                                                                                                                                                      |
| `problem_identifier` | Allows to give problem-handling related hints, please see Problem Handling                                                                                                                              |
| `message`            | Details related to the problem (error message, plugin output)                                                                                                                                           |
| `event_timeout`      | In case an issue will be created, it will be auto-closed after this many seconds. Optional                                                                                                              |
| `sender_event_id`    | Optional unique identifier, defaults to a checksum based on host_name, object_name and object_class. Has correlation purposes,  Multiple events for the same id will be considered being the same issue |
| `attributes`         | Dictionary with custom properties. Should have string properties and string or integer values. Optional                                                                                                 |
| `acknowledge`        | Optional boolean value, allows the sender to acknowledge an issue with the same custom (or generated) sender_event_id                                                                                   |
| `clear`              | Optional boolean value, allows the sender to clear (close) an issue with the same custom (or generated) sender_event_id                                                                                 |

### Perl Example

Our ```contrib``` directory contains a small library, ```EventTracker.pm``` and
the following example code:

```perl
#!/usr/bin/env perl

# Where to find EventTracker.pm:
use lib '/usr/local/lib/perl';
use EventTracker;

my $base_url = 'https://icinga.example.com/icingaweb2';
my $token = '84caa56f-0e69-4ff2-874f-71e743bac89d';
my $event_tracker = EventTracker->new($base_url, $token);
my $job_name = 'Backup Job'

# Use optional SSL Client Certificate:
# $event_tracker->ssl_opts(
#     SSL_ca_file         => '/some/where/ca.pem',
#     SSL_cert_file       => '/some/where/client.example.com.pem',
#     SSL_key_file        => '/some/where/client.example.com.key'
# );

# Disable SSL Checks (don't do this):
# $event_tracker->ssl_opts(verify_hostname => 0, SSL_verify_mode => 0x00);

my $response = $event_tracker->send_event({
    'severity'     => 'warning',
    'host_name'    => 'some.example.com',
    'object_name'  => 'Some Job',
    'object_class' => 'Job Errors',
    'message'      => sprintf('Job failed: %s', $job_name),
    'problem_identifier' => $jobName,
    'attributes'   => {
        'team' => 'Operating',
        'wiki' => 'https://wiki.example.com/wiki/Some_Page',
    },
    'files' => [
        {
            'name' => 'screenshot.png',
            # data can be the raw data or (when problematic with JSON-encoding)
            # base64-encoded when prefixed as follows: base64,bl0a===
            'data' => 'File content',
        }
    ]
});

printf("Submitted: %s\n", $response);
```

Creating Host Lists and  Host List entries
---------------

Host List and Host List entry properties

| Property | Description                                         | 
|----------|-----------------------------------------------------| 
| listUuid | The Uuid of the corresponding host list             |
| label    | The name of the label that the host list should get |
| hostname | The name of the host in a host list                 |    



### Fetch all available Host Lists

this endpoint will fetch all available **host lists** that are saved in the database as json output as you can see in the
given example response.

```http
GET https://monitoring.example.com/icingaweb2/eventtracker/configuration/hostlists
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c
Accept: application/json
```

#### Example response:

```json
[
    {
        "label": "Hosts in Downtime",
        "uuid": "8ca271cc-3764-4859-a572-faa8c6a68970"
    },
    {
        "label": "Windows Hosts",
        "uuid": "78643a8a-6088-4532-8409-795aed0a3bd1"
    },
    {
        "label": "Linux Hosts",
        "uuid": "f1884b05-d7df-4576-b0ee-d83bab57c0de"
    }
]
```

### Fetch all hosts from a specific Host List

to fetch all hosts that belong to a specific **host list** you need to provide the `listUuid` as url parameter
This will return a json array of host lists as seen in the example response.

```http
GET https://monitoring.example.com/icingaweb2/eventtracker/configuration/hostlist
   ?listUuid=8ca271cc-3764-4859-a572-faa8c6a68970
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c
Accept: application/json
```

#### Example response:
```json
[
    {
        "hostname": "app1.example.com"
    },
    {
        "hostname": "app2.example.com"
    },
    {
        "hostname": "app3.example.com"
    }
]
```

### Create a new Host List
to create a new **host list** you need to provide the name of the host list as `label` in the json body
```http 
POST https://monitoring.example.com/icingaweb2/eventtracker/configuration/hostlist
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c
Accept: application/json

{
    "label": "Hosts in Downtime"
}
```

#### Example response:

```json
{
    "success": "true",
    "message": "added hostlist Hosts in Downtime"
}
```

### Create a set of new Host Lists
to create multiple new **host lists** at once the technique is the same as when you want to create only one new hostlist.
instead of sending one object in the json-body you have to send multiple objects inside an array.
```http
POST https://monitoring.example.com/icingaweb2/eventtracker/configuration/hostlists
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c
Accept: application/json

[
    {
        "label": "Linux Hosts"
    }, 
    {
        "label": "Windows Hosts"
    }
]
```

#### Example response:

```json
{
    "success": "true",
    "message": "added  2 hostlists"
}
```

### Replace hosts on specific Host List

To replace all existing hosts in a specific **host list** you need to use the `PUT` method on the `/hostlist-memebrs` endpoint
The new hosts are sent as json body with a `hostname` key for every single hostname object.
Keep in mind that this method **deletes** all existing hosts that correspond to this hostlist and are not supplied in the json body.

```http
PUT https://monitoring.example.com/icingaweb2/eventtracker/configuration/hostlist-members
   ?listUuid=8ca271cc-3764-4859-a572-faa8c6a68970
Authorization: Bearer ddc72f88-0f8a-4038-ab76-e0e1b024345c'
Accept: application/json'

[
    {
        "hostname": "app1.exmample.com"
    },
    {   
        "hostname": "app2.example.com"
    },
    {
        "hostname": "app3.example.com"
    }
]
```

#### Example response:
```json
{
    "success": "true",
    "message": "updated  3 hosts"
}
```
### Delete host from Host List

To delete a specific host from a specific **host list** you use the `DELETE` request on the `/hostlist-member` endpoint and provide the `listUuid` from the corresponding hostlist and the `hostname`
of the host you want to delete as url parameters.

```http
DELETE https://monitoring.example.com/icingaweb2/eventtracker/configuration/hostlist-member
      ?listUuid=8ca271cc-3764-4859-a572-faa8c6a68970&hostname=Windows%20Hosts
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c
Accept: application/json
```

#### Example response:
```json
{
    "success": "true",
    "message": "deleted host list member Windows Hosts"
}
```

### Add new host to specific Host List

To add a new host to an existing **host list** use a `POST` request on the `/hostlist-member` endpoint.
The corresponding host list is addressed with the `listUuid` url parameter. To specify the new hosts name you have to provide a json object with the `label` key.

```http
POST https://monitoring.example.com/icingaweb2/eventtracker/configuration/hostlist-member
    ?listUuid=8ca271cc-3764-4859-a572-faa8c6a68970
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c
Accept: application/json

{
    "hostname": "app1.example.com"
}
```

#### Example response:
```json
{
    "success": "true",
    "message": "added host app1.example.com to hostlist Linux Hosts"
}
```

### Add multiple hosts to existing Host List

To add multiple hosts to an existing **host list** use the a `POST` request on the `/hostlist-members` endpoint and provide the `listUuid` as url parameter. Each new host needs to be a json object and has to be send in the json body.
The `hostname` key 

```http
POST https://monitoring.example.com/icingaweb2/eventtracker/configuration/hostlist-members
    ?listUuid=8ca271cc-3764-4859-a572-faa8c6a68970
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c
Accept: application/json

[
    {
        "hostname": "some-other.example.com"
    },
    {   
        "hostname": "app1.example.com"
    },
    {
        "hostname": "app2.example.com"
    }
]
```

#### Example response:

```json
{
    "success": "true",
    "message": "added  3 hosts"
}
```

## Problemhandling

### Fetch all Problemhandling Entries
```http
GET https://monitoring.example.com/icingaweb2/eventtracker/configuration/problemHandlings
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c
Accept: application/json
```

#### Example response:
```json
[
    {
        "label": "test",
        "uuid": "fc606e6d-503b-42ee-8af8-6d040ab24833",
        "instruction_url": "www.google.de"
    }
]
```
    

## Get History Entries

Host List and Host List entry properties

| Property        | Description                                                      | required |
|-----------------|------------------------------------------------------------------|:--------:|
| columns         | A comma seperated list of columns to show in the response        |   true   |
| fromTimestampMs | The timestamp to show all history entries after the given value  |  false   |
| toTimestampMs   | The timestamp to show all history entries before the given value |  false   |    

### Fetch all issue history entries

**HINT**: this is usually not a good idea, as you'll run into memory limits when
fetching millions of rows. Please always pass timestamp constraints, to be on the
safe side.

```http
GET https://monitoring.example.com/icingaweb2/eventtracker/history/issues
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c4
Accept: application/json
```
#### Example response:
```json
{ "objects": [{
    "issue_uuid": "5cd0840f-24af-4008-94e6-005b500b4973",
    "severity": "warning",
    "priority": "normal",
    "close_reason": "manual",
    "closed_by": "icinga",
    "input_uuid": "05d1aa91-fd68-41a7-b004-49f8334c652c",
    "sender_id": 99999,
    "sender_event_id": "",
    "sender_event_checksum": "0x3ca17bc6e2e79de0b8be247feeb4116329fa1077",
    "host_name": "test_api",
    "object_class": "Job Errors",
    "object_name": "downtime_timses",
    "problem_identifier": "1233",
    "ts_expiration": null,
    "ts_first_event": 1738761781203,
    "ts_last_modified": 1738763806009,
    "cnt_events": 2,
    "owner": null,
    "ticket_ref": null,
    "message": "test message for testing",
    "attributes": "[]"
}]}
```
### Fetch all issue history entries and show only two columns

If you only want specific columns in the output response, you can restrict the columns to show with the url parameter `columns`

```http
GET https://monitoring.example.com/icingaweb2/eventtracker/history/issues?columns=closed_by,issue_uuid
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c4
Accept: application/json
```

### Fetch all issue history entries in a given range

You can set a range to filter the output by specifying a `fromTimestampMs` and a `toTimestampMs` as url parameters.
You can use `fromTimestampMs` and `toTimeStampMs` separately

```http
GET https://monitoring.example.com/icingaweb2/eventtracker/history/issues?columns=closed_by,issue_uuid
   &fromTimeStampMs=1737284626&toTimeStampMs=1739966654
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c4
Accept: application/json
```

### Fetch all action history entries

```http
GET https://monitoring.example.com/icingaweb2/eventtracker/history/actions
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c4
Accept: application/json
```

## Reporting

It is possible to fetch aggregated data for reporting purposes.

### Fetch Report: History Summary

```http
GET https://monitoring.example.com/icingaweb2/eventtracker/reporting/history-summary?start=2025-11-10&end=2025-11-12&aggregation=daily
Authorization: Bearer 108600bf-4f77-4bdc-9a06-4cd04902537c4
Accept: application/json
```

#### Example response:
```json
{
    "objects": {
        "2025-11-10": {
            "cnt_total": 2548,
            "cnt_with_owner": 0,
            "cnt_with_ticket_ref": 0,
            "cnt_owner_no_ticket_ref": 0
        },
        "2025-11-11": {
            "cnt_total": 1219,
            "cnt_with_owner": 0,
            "cnt_with_ticket_ref": 0,
            "cnt_owner_no_ticket_ref": 0
        },
        "2025-11-12": {
            "cnt_total": 383,
            "cnt_with_owner": 0,
            "cnt_with_ticket_ref": 0,
            "cnt_owner_no_ticket_ref": 0
        }
    }
}
```

The following parameters are optional:

| Parameter   | Description                                                                     |
|-------------|---------------------------------------------------------------------------------|
| aggregation | Aggregation type (hourly, daily, weekly, weekday, monthly                       |
| start       | Day on which your report should start (YYYY-mm-dd, defaults to "now - 1 month") |
| end         | Day on which your report should end (YYYY-mm-dd, defaults to "now")             |
