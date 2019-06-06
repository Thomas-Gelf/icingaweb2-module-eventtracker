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
