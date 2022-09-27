
* [Overview](#overview)
* [General Usage](#general-usage)
* [Installation](#installation)
* [License](#license)

# Overview

Andromeda is a self-hostable cloud file storage solution.  This repository contains the backend server.  It is a pure-PHP REST-ish transactional API divided into a reusable core framework and component "apps" which implement the actual API calls.

### Core Framework
The framework is independent of the apps built for Andromeda and can be used for other projects.  The core principally provides safe input/output handling and formatting, error handling and logging, and an object-oriented transactional database abstraction.  The related "core" app is used for server configuration, enabling/disabling apps, and other core-specific tasks.  

The framework can log accesses and errors to the database, or to log files if a data directory is configured.  It also allows setting up outgoing email configurations that may be used by apps.

### Primary Apps
In pursuit of being a cloud storage solution, Andromeda principally includes the "accounts" and "files" apps.  Accounts implements the account management and authentication/session-management tasks.  Files app provides the filesystem interface and related features.  The files app requires the accounts app.

As the framework itself is app-agnostic, the commands and documentation are generally written in an app-agnostic way (not specific to accounts or files).  See the [wiki](https://github.com/irondrive/andromeda-server/wiki) for more app-specific information.

# General Usage

Andromeda and *all* its API calls can be run either through an HTTP webserver, or via the command line interface (CLI).  The API is thus a bit of a REST-ish hybrid.  All calls run single "actions" and are run as transactions.  Any errors encountered will result in a rolling back of the entire request. 

Run the API from the CLI with no arguments (either `./andromeda-server` or `php index.php`) to view the general CLI usage.  The general usage is `./andromeda-server myapp myaction` where myapp and myaction are the app and action to run.  Use `./andromeda-server core usage` to view the list of all available API calls.  Action-specific parameters use the traditional `--name value` syntax and come at the end of the command.  Commands showing `[--name value]` with brackets indicates an optional parameter. Commands with a repeated `(action)` line show a subset of usage for the command based on a specific case of the main usage.  Note that app and action are implicit and do not require --app or --action.  Parameters can be specified as a flag with no value, in which case they are implicitly mapped to `true` for booleans and `null` for all other types.  

Commands mentioned in the readme or wiki will omit the `php index.php` or `./andromeda-server` and will only specify the `app action` to run, e.g. `core usage`.  The `core usage` output that documents all API calls is also tracked as USAGE.txt in the [server API docs](https://github.com/irondrive/andromeda-server-docs) repository.

The installer (see below) uses a different entry point (`php install.php` or `./andromeda-install`) with the same general usage format.  

### Common Exceptions

`SAFEPARAM_*` related exceptions indicate a problem with the input provided.  For example `SAFEPARAM_KEY_MISSING` indicates that a required parameter was not given.  `SAFEPARAM_INVALID_VALUE` indicates that the parameter did not pass input validation (e.g. giving a string for a numeric input).  `UNKNOWN_APP` and `UNKNOWN_ACTION` indicate that the requested app or action are invalid.

### Parameter Types

All input parameters are strictly validated against their expected types.  Most that you will see in `core usage` are self-explanatory (`bool`, `int`, etc.).  Less-obvious types include `raw` (no validation), `randstr` (an andromeda-generated random value), `name` (a label or human name), `text` (escapes HTML tags with `FILTER_SANITIZE_SPECIAL_CHARS`), and  `id` (a reference to an object by its ID).  Andromeda code is object-oriented and uses unique IDs to refer to database objects.  A parameter type that begins with ? (e.g. `?int`) indicates that the parameter can be null (e.g. `... --myparam null`).  This can have a different meaning than just omitting the parameter.

### Global CLI Flags
CLI-specific global flags must come *before* the app/action.
* `--outmode json`/`--outmode printr` use JSON or PHP printr() for output (default printr)
* `--debug enum` change the debug output level (default server errors only)
* `--dryrun` rollback the transaction at the end of the request
* `--dbconf path/myconf.php` use the provided database configuration file
* `--metrics enum` will show performance metrics, SQL queries executed, and other development stuff

### Environment Variables
To ease command line usage for commands that may involve repeated parameters (e.g. a session key), environment variables prefixed with `andromeda_` can be set that will always be included in a request.  For example, `export andromeda_mykey=myvalue` is equivalent to adding `--mykey=myvalue` to all future commands.

### Response Format
Every request will return an object with `ok` and `code`.  `ok` denotes whether the transaction was successful, and `code` returns the corresponding HTTP error code. If the request was successful (200), the `appdata` field will have the output from the app-action.  If there was an error, the `message` field will have a string describing the error.  For cleaner output, when using CLI the default `--outmode` is `plain`, meaning only the `appdata` or `message` field will be printed (if possible).

### HTTP Differences
Parameters can be placed in the URL query string, the POST body as `application/x-www-form-urlencoded` or similar (see PHP $_POST), or cookies.  The only restrictions are app and action must be URL variables, and any parameter starting with `auth_` cannot be in the URL.  Andromeda does not make use of the different HTTP methods, headers, or endpoints.  Only GET or POST are allowed.  The output format is always JSON.  The actual HTTP response code is only used if no JSON is output (e.g. downloading a file).  Example `/index.php?app=myapp&action=myaction&myparam=myval`.

### CLI Batching
Andromeda also allows making requests that run multiple actions as a single transaction.  If there is an error at any point, all actions are reverted ("all or nothing").  The returned `appdata` will be an array, each entry for the corresponding action.  Batches can be run directly from the command line, or from batch files.  To run a batch file, simply list each command on its own line in a plain text file, then run `./andromeda-server batch@ myfile.txt`.  To run a batch directly from the command line, each app/action must be its own quoted argument.  E.g. `./andromeda-server batch "core setconfig --debug none" "core getconfig"`.  

### HTTP Batching
Via HTTP, this is done using the `batch` input variable.  Each entry in the `batch` parameter holds the action to be run, while parameters outside `batch` will be run for every action.  Example `index.php?app=testutil&action=random&batch[0]&batch[1][length]=5` will output two random numbers, the second with a length of 5 (ex. `{"ok":true,"code":200,"appdata":["oyxvyz2z2d2yqus1","s7enc"]}`).

### Arrays and Objects
Parameters can also be given that are arrays or objects.  On the CLI, this is done using JSON.  E.g. `--myarray "[5,10,15]"` or `--myobj "{test:5}"`.  Via HTTP it would look like `?myarr[0]=test&myarr[1]=test` or `?myobj[key]=val`.

### Alternative Input
Certain parameters (password, etc.) are better when not direclty on the command line.  Using `!` at the end of a parameter name (e.g. `--myparam!`) will read the parameter value interactively from the console (or from STDIN, though the order is not specified).  This is a good way to input things like passwords.  Unfortunately PHP does not support silent input, so all input will be echoed to the console.  A parameter can also source its content from a file using `--myparam@ path`.

### File Inputs
Certain app actions require that they are passed a file stream as input.  With HTTP they should be a regular `multipart/form-data` file upload. See PHP's $_FILES.  With CLI they can be specified as a path with `--myfile% path` or they can be read directly from STDIN (one file only) with `--myfile-`.  With `%` the inputted file's name can optionally be changed as well, e.g. `--myfile% path newname`.  App actions that require file input will specify `%` or `-` in their usage text.

# Installation

For development, simply clone the repo and use `composer install` to download and install the required PHP dependencies.  By default this includes development-specific dependencies.  For production, download a release tarball with dependencies included, or use `composer install --no-dev`.  Installation is done with the `./andromeda-install` entry point.

### Basic Requirements
Andromeda requires PHP >= 7.4 (8.x is supported) and the JSON (7.x only), mbstring, PDO and Sodium PHP extensions.  Other extensions may be required by apps for additional functionality.  Supported databases are MySQL, PostgreSQL and SQLite. These require the corresponding PDO extensions (PDO-mysql, PDO-pgsql, PDO-sqlite).  PostgreSQL ALSO requires the PHP-pgsql extension.

Andromeda does not use any OS or webserver-specific functions and works on Windows, Linux, BSD, Apache, Nginx, etc.  *No* specific PHP or webserver configuration is required.  It is recommended for security to ensure that the web server cannot write to any of the PHP code folders.

It is strongly recommended (but not required) to make sure that only the main entry point (`index.php`) is web-accessible.  `Andromeda` and `vendor` should be installed elsewhere (e.g. `/usr/local/lib`).  The `index.php` and `andromeda-server` entry points will check `./`, `/usr/local/lib/andromeda-server/` and `/usr/lib/andromeda-server/` in that order for the `Andromeda` folder.  Hiding the subdirectories is not strictly required, but having them accessible [may create vulnerabilities](https://thephp.cc/articles/phpunit-a-security-risk).  In case the folders must exist in `/var/www`, .htaccess files are included to restrict access with Apache 2.4, but manual configuration is needed for nginx or other servers.  For development, the tools assume that the folders are still in the repository root.

#### Database Config
The `./andromeda-install core dbconf` command is used to create database configuration.  By default, it will return the contents of the file instead of writing it anywhere.  Using `--outfile` as a flag will instead store the configuration file (`DBConfig.php`) by in the `Andromeda/` folder.  An alternative output filename can be picked by specifying a path/name with `--outfile path`.  When Andromeda runs it checks its `./Andromeda/`, `~/.config/andromeda/`, `/usr/local/etc/andromeda/` and `/etc/andromeda/` in that order for `DBConfig.php`.  The name/path can be permanently overriden by adding `<?php define('DBCONF','path-to-config');` to `Andromeda/userInit.php`.

For example to create and use an SQLite database and save the config file in the default location, run `./andromeda-install core dbconf --driver sqlite --dbpath mydata.s3db --outfile`.  SQLite is only recommended for testing or tiny deployments as it does not support concurrent access.

### CLI Install Steps
Use the `./andromeda-install core usage` command to see options for all available commands.

1. Run `./andromeda-install core dbconf --outfile` to generate and write database configuration.
2. Run `./andromeda-install core install-all` to install the database tables for all apps that exist. It returns a list of all installed apps mapped their specific install output.  Apps can also be installed separately, e.g. `core install` or `accounts install`.  Apps can have database dependencies that may dictate installation order.  The `core install-all` command can take any parameter needed by an individual app. 
3. Run `./andromeda-server core scanapps --enable` to enable all apps that exist.  It returns a list of enabled apps.

Note the install commands are allowed by any user on any interface when required, so it is recommended to have public web access disabled during install.  It can also be permanently disabled for HTTP by adding `<?php define('ALLOW_HTTP_INSTALL',false)` to `Andromeda/userInit.php`.  Or you can just delete the install.php entry point if you will only be using CLI for install/upgrade.

Note that MySQL does not support transactions for queries that modify table structure.  If an install/upgrade fails midway, the database may be left in an inconsistent state.

#### Full SQLite Web Server Install Example with Proper Directories
This is just a reference and not meant to actually be run.

```

# server code:  /usr/local/lib/andromeda-server/
# index.php:    /var/www/html/andromeda/
# entry script: /usr/local/bin/
# db config:    /usr/local/etc/andromeda/
# sqlite db:    /var/lib/andromeda/

# install the server files
cd /usr/local/lib
git clone https://github.com/irondrive/andromeda-server
cd andromeda-server
composer install

# copy the entry points
cp andromeda-server /usr/local/bin/
cp index.php /var/www/html/andromeda/

# create directories
mkdir /var/lib/andromeda
mkdir /usr/local/etc/andromeda
chown -R www-data:www-data /var/lib/andromeda
chown -R www-data:www-data /usr/local/etc/andromeda
chmod -R 770 /var/lib/andromeda
chmod -R 770 /usr/local/etc/andromeda

## /usr/local/lib/andromeda-server and /var/www/html/andromeda
## should NOT be writeable by www-data!

# initialize the SQLite database
sudo -u www-data \
   ./andromeda-install core dbconf --driver sqlite \
   --dbpath /var/lib/andromeda/database.s3db \
   --outfile /usr/local/etc/andromeda/DBConfig.php
   
sudo -u www-data ./andromeda-install core install-all
sudo -u www-data ./andromeda-server core scanapps --enable

# set the core datadir (for logging)
sudo -u www-data andromeda-server core setconfig \
   --datadir /var/lib/andromeda
```

### Upgrading
When the code being run does not match the version stored in an app's database, running the app's upgrade command is required, e.g. `./andromeda-install core upgrade` or `./andromeda-install accounts upgrade`.  You can upgrade all apps at once with `core upgrade-all`.  It returns a list of all upgraded apps mapped their specific upgrade output.  Apps can have database dependencies that may dictate upgrade order.  The `core upgrade-all` command can take any parameter needed by an individual app. 

The same rules about public web access for install also apply to upgrade (see above).


# License

Andromeda including all source code, documentation, and APIs are copyrighted by the author.  Use of any code is licensed under the SSPL (Server Side Public License) Version 1.  This license also applies to the external API, and therefore any other software that substantially implements the server API, but not to external consumers of it (client software).  Use of any documentation (wiki, readme, etc.) is licensed under the Creative Commons Attribution-NonCommercial-ShareAlike 3.0 (CC BY-NC-SA 3.0) license.  Alternative commercial licenses for either can be obtained separately.  Contributors agree to the terms in CONTRIBUTING.md for all contributions.  All 3rdparty code (located in `vendor/` folders) retains its original licenses - see `composer licenses`.  All must be copyleft-permissive - no GPL or derivatives.
