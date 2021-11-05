package EventTracker;

use strict;
use warnings;
use LWP::UserAgent;

my $base_url;
my $token;
my $uri;
my $json;
my %ssl_opts;

sub new {
   my $class = shift;
   my $self = {};
   bless $self, $class;
   $json = $self->find_json->new;
   $base_url = shift || $self->usage_fail('$base_url is required');
   $token = shift || $self->usage_fail('$token is required');
   $uri = "$base_url/eventtracker/event";
   return $self;
}

sub ssl_opts {
   my $self = shift;
   %ssl_opts = @_;
}

sub usage_fail {
   my $self = shift;
   my $error = shift;
   die "$error, please call EventTracker->new('https://icinga.example.com/icingaweb2', 'tOkEn')"
}

sub shorten_message {
   my $self = shift;
   my $string = shift;
   my $max_length = shift || 2048;
   return $string if length($string) <= $max_length;

   return substr($string, 0, int($max_length / 2)) . "\n... shortened...\n" . substr($string, -1 * int($max_length / 2));
}


sub find_json {
    my $self = shift;
    return 'Cpanel::JSON::XS' if eval { require Cpanel::JSON::XS; 1; };
    return 'JSON::XS' if eval { require JSON::XS; 1; };
    return 'JSON::PP' if eval { require JSON::PP; 1 };
    die "Found no JSON library, please install (Cpanel::JSON::XS, JSON::XS or JSON::PP)";
}

sub prepare_rest_request {
    my $self = shift;
    my $uri = shift;
    my $token = shift;
    my $body = shift;
    my $req = HTTP::Request->new('POST', $uri);
    my $json = find_json->new;

    $req->header(
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
        'Authorization' => "Bearer $token",
        'User-Agent'    => 'PerlEventtrackerClient/0.1',
    );
    $req->content($json->encode($body));

    return $req;
}

sub send_event {
    my $self = shift;
    my $event = shift;
    $event->{'message'} = $self->shorten_message($event->{'message'});
    my $client = LWP::UserAgent->new;
    $client->ssl_opts(%ssl_opts);
    my $response = $client->request($self->prepare_rest_request($uri, $token, $event));
    die "Can't create Ticket: ", $response->status_line
       unless $response->is_success;

    my $result = $self->find_json->new->decode($response->content);
    if ($result->{'error'}) {
        die "Can't create Ticket: ", $result->{'error'}
    }

    return $result->{'success'};
}

1;
