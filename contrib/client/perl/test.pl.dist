#!/usr/bin/env perl

# Where to fine EventTracker.pm:
use lib '/usr/local/lib/perl';
use EventTracker;

my $base_url = 'https://icinga.example.com/icingaweb2';
my $token = '84caa56f-0e69-4ff2-874f-71e743bac89d';
my $event_tracker = EventTracker->new($base_url, $token);

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
    'message'      => 'The Job failed: ...',
    'attributes'   => {
        'team' => 'Operating',
        'wiki' => 'https://wiki.example.com/wiki/Some_Page',
    }
});

printf("Submitted: %s\n", $response);
