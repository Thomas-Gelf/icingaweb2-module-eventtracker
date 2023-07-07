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
