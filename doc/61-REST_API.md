<a name="REST_API"></a>REST API
==================================

The Icinga Eventtracker provides a REST API for various tasks.

REST API Requests - Overview
----------------------------

### Authentication

All REST API requests need to be authenticated, usually with a related Bearer Token.
Such a token can easily be created through our web UI. Please consider using different
tokens for different tasks and applications.

![REST API: Configure bearer token](screenshot/rest_api_close-configure_bearer_token.png)

The Bearer Token needs to be

### Accept Header

A REST API request is identified by its `Accept` header, which MUST be `application/json`:  

    Accept: application/json

Sample Requests
---------------

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
