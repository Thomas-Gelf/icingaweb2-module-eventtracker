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

    GET https://icinga.example.com/icingaweb2/eventtracker/issues
    Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
    Accept: application/json

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

    GET [..]/eventtracker/issues?properties=issue_uuid,input_uuid,ticket_ref
    Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
    Accept: application/json

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

    GET [..]/eventtracker/issues?properties=issue_uuid,ticket_ref&input_uuid=65b3ff52-ed7b-4070-b3d5-850f1e29fc3e
    Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
    Accept: application/json

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

    GET [..]/eventtracker/issues?properties=issue_uuid,message,host_name
       &host_name=*example*&(message=*problem*|message=*bla*)
    Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
    Accept: application/json

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

    POST https://icinga.example.com/icingaweb2/eventtracker/issue/close?uuid=0f9ab9e0-600a-4e05-8e13-e48b20b1d37e
    Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
    Accept: application/json

As this has been implemented primarily for trouble ticket / service desk solutions,
and not all of them are able to track remote UUIDs and/or to ship them in a URL,
we also support closing issues by ticket ID:

    POST https://icinga.example.com/icingaweb2/eventtracker/issue/close?ticket=43027
    Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
    Accept: application/json

Same as above, we also support closing issues via `sender_event_id`:

    POST https://icinga.example.com/icingaweb2/eventtracker/issue/close?sender_event_id=My%20Job%20Name
    Authorization: Bearer e756ca41-875f-4f92-991c-706dc07af192
    Accept: application/json


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
chain. An Event should have the following properties afterwa

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
