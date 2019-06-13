Simple Monitoring State API
===========================

For a special use-case this module ships an Icinga monitoring state proxy. It's
solely purpose is to grant unfiltered access to the current monitoring state of
specific Hosts or Services. The main use-case for this was an integration with
the IPC Incentage Process Cockpit, serving requests from the
[Incentage](https://www.incentage.com/) Middleware Suite.

As no ACLs are applied, access to this feature is granted:

* only for SSL-Requests
* with validated Certificates (please configure your web server accordingly)
* with a white-listed CN (Common Name)

Please configure such a white-list in your modules api.ini file. This is usually
`/etc/icingaweb2/modules/eventtracker/api.ini`:

```ini
[ssl]
allow_cn = "apiclient1.example.com, apiclient2.example.com"
```

In case you want to use this feature in a different scenario please let us know.
It should be easy to tweak this module accordingly.

Requests
--------

Please send HTTPS `GET` requests to `/icingaweb2/eventtracker/icinga/status`. The
URL accepts one single parameter, named `object`. `object` can be either
`<host_name>` or `<host_name>!<service_name>`.

Responses
---------

Response Format is a very simple UTF8-encoded HTML. Valid responses have a `result`
root node with a couple of property nodes, namely:

* host
* service (optional)
* state
* in_downtime
* acknowledged
* output

Valid `state` values for Hosts are `up`, `down`, `unreachable` and `pending`. For Services please expect `ok`, `warning`, `critical`, `unknown` and `pending`. `in_downtime` and `acknowledged` can be either `yes` or `no`.

In case an error occurs, please check the HTTP status code. The body carries a single `error` tag containing a related error message.

### Successful Response - Example

#### Header

```
HTTP/1.1 200 OK
Date: Thu, 13 Jun 2019 08:48:58 GMT
Server: Apache
Content-Length: 171
Content-Type: text/html; charset=UTF-8
```

#### Body

```html
<result>
<host>host1.example.com</host>
<state>down</state>
<in_downtime>yes</in_downtime>
<acknowledged>no</acknowledged>
<output>CRITICAL - Plugin timed out</output></result>
```

### Error Response - Example

#### Header

```
HTTP/1.1 403 Forbidden
Date: Thu, 13 Jun 2019 09:13:05 GMT
Server: Apache
Content-Length: 78
Content-Type: text/html; charset=UTF-8
```

#### Body

```html
<error>SSL CN 'attacker.example.com' is not allowed to access this resource</error>
```

Full examples
-------------

### Host Example

```
> GET https://icinga.example.com/icingaweb2/eventtracker/icinga2/status?object=host1.example.com

< HTTP/1.1 200 OK
< Date: Thu, 13 Jun 2019 08:48:58 GMT
< Server: Apache
< Content-Length: 171
< Content-Type: text/html; charset=UTF-8
<
< <result>
< <host>host1.example.com</host>
< <state>down</state>
< <in_downtime>yes</in_downtime>
< <acknowledged>no</acknowledged>
< <output>CRITICAL - Plugin timed out</output></result>
```

### Example with an unknown Host name

```
> GET https://icinga.example.com/icingaweb2/eventtracker/icinga2/status?object=invalid.example.com

HTTP/1.1 404 Not Found
Date: Thu, 13 Jun 2019 08:49:52 GMT
Server: Apache
Content-Length: 43
Content-Type: text/html; charset=UTF-8

<error>No such object: invalid.example.com</error>

### Service Example

```
> GET https://icinga.example.com/icingaweb2/eventtracker/icinga2/status?object=host1.example.com!File%20Systems
```

Response Body:

```html
<result>
<host>host1.example.com</host>
<service>File Systems</service>
<state>critical</state>
<in_downtime>no</in_downtime>
<acknowledged>no</acknowledged>
<output>FS CRITICAL - free space: /var/log</output></result>
```


### SSL Certificate not in white-list

```
HTTP/1.1 403 Forbidden
Date: Thu, 13 Jun 2019 09:13:05 GMT
Server: Apache
Content-Length: 78
Content-Type: text/html; charset=UTF-8

<error>SSL CN 'attacker.example.com' is not allowed to access this resource</error>
```

### Something bad happens

We try hard to catch errors on server side. Please expect an error code 500 in case something goes badly wrong:

```
HTTP/1.1 500 Internal Server Error
Date: Thu, 13 Jun 2019 09:18:02 GMT
Server: Apache
Content-Length: 144
Connection: close
Content-Type: text/html; charset=UTF-8

<error>SQLSTATE[42S02]: Base table or view not found: 1146 Table 'icinga2.nowhere' doesn't exist, query was: SELECT invalid FROM nowhere</error>
```