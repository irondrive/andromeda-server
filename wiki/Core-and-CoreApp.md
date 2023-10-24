The core app is for framework-specific administration tasks.

The `core getconfig` command gets config while `core setconfig` changes it.  Unprivileged users are allowed to view a subset of config, including which apps are installed and their major/minor versions.

The core app will use the accounts app for authentication if it is installed and enabled. Otherwise it will grant admin access to CLI only and not HTTP.

## Install/Upgrade - TODO OUTDATED

Use the `core dbconf` to generate database config and `core install` to install the database tables.  

Use `core upgrade` to run upgrade scripts. See the main README for details.

Both `core install` and `core upgrade` will also install/upgrade any app that requires it.  This can be prevented with the `--noapps` flag.  Some apps may require specific arguments for install/upgrade - these can be passed directly to the core command also.

## App Management

Functions are provided to enable and disable individual apps. The core app itself cannot be disabled, but its code folder can be removed entirely if desired. To enable/disable an app, use `core enableapp` and `core disableapp`. To list all apps that exist on disk, use `core scanapps`. To enable all apps that exist at once, use `core scanapps --enable`.

## Data Directory

Some Andromeda functions (logging) require a server data directory to be configured. This directory must be readable and writable by the server and can be set or unset with the `core setconfig --datadir ?text` command.  

A good example location on Linux would be `/var/log/andromeda`.  Make sure this directory is not accessible over the web!

## Error Logging

Andromeda provides comprehensive debugging and error logging.  Use `core setconfig --debug type` to change the debug level permanently.  The CLI `--debug int` flag also allows changing it for just that request.  The default `errors`(1) shows a basic backtrace when an error occurs.  `development`(2) additionally shows a fuller backtrace, queries, and loaded object IDs. `sensitive`(3) shows input parameters, queries with their actual values, and function arguments.  Debug output is placed in the `debug` field of the response object.  

For 500-code server errors only, debug can also be logged to the database or to a file.  To enable/disable the database log, use `core setconfig --debug_dblog bool`.  To enable/disable the file log (`errors.log`), use `core setconfig --debug_filelog bool`.  A data directory must be configured for the file log.  

Use the `core geterrors` and `core counterrors` commands to view error log entries. The various flags enable filtering output. Debug output over HTTP must be explicitly enabled no matter the configured debug level.  Use `core setconfig --debug_http bool` to change it.  The defaults are debug level 1 (basic errors only), log to DB enabled, file log disabled, HTTP output disabled.

## Access Logging

Andromeda provides comprehensive access logging.  The logs provide info including time of request, IP address, user agent, response code, action performed, and specific input parameters.  Client errors are logged in the access log, server errors are logged in the error log. Apps can add to the access log with app-specific information (e.g. account or file IDs) and decide which input parameters are safe to log.  The access log can either be output to the database (`core setconfig --requestlog_db bool`) or to a file (`core setconfig --requestlog_file bool`) in (`access.log`). The defaults are both the database and file-based access logs disabled.

Select input parameters are only logged if enabled. Use `core requestlog_details` to configure it.  Details `none` never logs input parameters.  Details `basic` enables basic logging of critical parameters (e.g. the username for a session creation).  Details `full` allows apps to log more detailed but less critical information (e.g. the name of a created group). The default details level if enabled is basic.

Each access log entry is stored in three separate objects.  There is the request, which holds one or more actions (could be a batch request), each of which is tied to an app-specific action log (if the app supports it).  The `core getrequests` and `core countrequests` commands output request entries, with the action and app-action object arrays as subfields.  As such they are filterable only be request-specific fields (time, address, etc.).  The `core getallactions` and `core countallactions` commands output action entries, with the request and app-action objects are subfields. They are filterable by request or action-specific fields (time, address, app, action, etc.).  Finally the `core getactions` and `core countactions` commands output app-action entries, with the request and action objects as subfields. They are filterable by request, action, or app-specific fields (time, address, app, action, account, etc.).  As they are app-specific, the available filters will vary by app selected. 

## Emailer Config

The Andromeda core provides configuration and facilities for outgoing email using PHPMailer.  The framework itself does not use the outgoing email, but apps may (file sharing, password resets, etc).  Multiple emailers can be configured, and they will be used randomly for load balancing.  The `core getmailers` command shows the currently configured emailers and their IDs.  The `core deletemailer` command can remove them by ID.

The `core createmailer` command is used to configure an emailer.  If using SMTP, an individual emailer can also be mapped to multiple hosts.  In that case, each will be tried before failing.  The command syntax looks confusing but is saying that to configure SMTP with a single host, use the `--host` and optionally  `--port --proto` switches, while to configure with multiple hosts, use `--hosts` with a JSON array containing objects with each of those keys.  For example to configure SMTP to try both test.com and test2.com, you would use `--hosts "[{'host':'test.com'},{'host':'test2.com'}]"`.

The `core testmail` command is used to test configured emailers by sending a test email (optionally with a specific emailer ID and destination address). Email can also be disabled globally using the `core setconfig --email bool` command (default is enabled).  

## Other Config

The HTTP interface can be disabled entirely, for maintenance or other tasks.  See `core setconfig --enabled bool`.  The server can also be set into read-only mode, see `core setconfig --read_only bool`.  Read-only will only allow actions that do not write to the database.

## Other Functions
* `core phpinfo` displays the `phpinfo()` page
* `core serverinfo` shows PHP `$_SERVER`, `uname()` and database info
