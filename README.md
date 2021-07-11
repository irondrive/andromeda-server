# Overview

Andromeda is a self-hostable cloud file storage solution.  This repository contains the backend server API.  It is a pure-PHP REST-ish transactional API divided into a core framework and component "apps" which implement the actual domain-specific calls.

### Core Framework
The framework is independent of the apps built for Andromeda and can be used for other projects.  The core principally provides safe input/output handling and formatting, error handling and logging, and an object-oriented transactional database abstraction.  The related "server" app is used for server configuration, enabling/disabling apps, and other core-specific tasks.  

The framework can log accesses and errors to the database, or to log files if a data directory is configured.  It also allows setting up outgoing email configurations that may be used by apps.

### Primary Apps
In pursuit of being a cloud storage solution, Andromeda principally includes the "accounts" and "files" apps.  Accounts implements the account management and authentication/session-management tasks.  Files app provides the filesystem interface and related features.  The files app requires the accounts app.

The server app will use the accounts app if it is enabled. Otherwise it will assume that CLI is privileged and HTTP is not, since running Andromeda from the CLI entails having access to the database config file and thus full database access.

See the wiki for more app-specific information.

# General Usage

Andromeda and *all* its API calls can be run either through an HTTP webserver, or via the command line interface.  The API is thus a bit of a REST-ish hybrid.  All calls run single "actions" and are run as transactions.  Any errors encountered will result in a rolling back of the entire request. 

Run the API from the CLI with no arguments (either `./andromeda` or `php index.php`) to view the general CLI usage.  The general usage is `./andromeda myapp myaction` where myapp and myaction are the app and app-action to run.  Use `server usage` to view the list of all available API calls.  Action-specific parameters use the traditional `--name value` syntax and come at the end of the command.  Commands showing `[--name value]` with brackets indicates an optional parameter. Note that app and action are implicit and do not require --app or --action.  Parameters can be specified with no value, in which case they are implicitly mapped to `true`.  

The `server usage` output that documents all API calls is also tracked as USAGE.txt in the [server API docs](https://github.com/lightray22/andromeda-server-docs) repository.

### Common Exceptions

`SAFEPARAM_*` related exceptions indicate a problem with the input provided.  For example `SAFEPARAM_KEY_MISSING` indicates that a required parameter was not given.  `SAFEPARAM_INVALID_DATA` indicates that the parameter did not pass input validation (e.g. giving a string for a numeric input).  

### Parameter Types

All input parameters are strictly validated against their expected types.  Most that you will see in `server usage` are self-explanatory (`bool`, `int`, etc.).  Less-obvious types include `raw` (no validation), `randstr` (an andromeda-generated random value), `name` (a label or human name), `text` (escapes HTML tags with `FILTER_SANITIZE_SPECIAL_CHARS`), and  `id` (a reference to an object by its ID).  Andromeda is heavily object-oriented and uses unique IDs to refer to database objects.  

### Global CLI Flags
CLI-specific global flags must come *before* the app/action.
* `--json`/`--printr` use JSON or PHP printr() for output (default printr)
* `--debug` change the debug output level (default 1 - errors only)
* `--dryrun` rollback the transaction at the end of the request
* `--dbconf path/myconf.php` use the provided database configuration file

### Environment Variables
To ease command line usage for commands that may involve repeated parameters (e.g. a session key), environment variables prefixed with `andromeda_` can be set that will always be included in a request.  For example, `export andromeda_mykey=myvalue` is equivalent to adding `--mykey=myvalue` to all future commands.

### Response Format
Every request will return an object with `ok` and `code`.  `ok` denotes whether the transaction was successful, and `code` returns the corresponding HTTP error code. If the request was successful (200), the `appdata` field will have the output from the app-action.  If there was an error, the `message` field will have a string describing the error.  For simpler CLI usage, if `appdata` or `message` are just a string, only that string will be output to CLI (not the whole object).  

### HTTP Differences

App and action must be URL variables, but all other parameters can be freely placed in the URL, in the POST body, or in cookies.  Andromeda does not make use of the different HTTP methods or headers.  The output format is always JSON.  Example `/index.php?app=myapp&action=myaction&myparam=myval`.  

### CLI Batching
Andromeda also allows making requests that run multiple actions as a single transaction.  If there is an error at any point, all actions are reverted ("all or nothing").  To run a batch, simply list each command on its own line in a plain text file, then run `./andromeda batch myfile.txt`.  The returned `appdata` will be an array, each entry for the corresponding action.

### HTTP Batching

Via HTTP, this is done using the `batch` input variable.  Each entry in the `batch` parameter holds the action to be run, while parameters outside `batch` will be run for every action.  Example `index.php?app=server&action=random&batch[0]&batch[1][length]=5` will output two random numbers, the second with a length 5 `{"ok":true,"code":200,"appdata":["oyxvyz2z2d2yqus1","s7enc"]}`.

### Arrays and Objects
Parameters can also be given that are arrays or objects.  On the CLI, this is done using JSON.  E.g. `--myarray "[5,10,15]"` or `--myobj "{test:5}"`.  Via HTTP it would look like `?myarr[0]=test&myarr[1]=test` or `?myobj[key]=val`.

### File Inputs
From the CLI, files can be included in two ways.  The file's contents can be dumped into the variable itself via @ `--myparam@ myfile.txt` or the file's path can be sent to the app directly via % `--myparam% myfile.txt`.  @ will appear to the app as a regular parameter (and is a safer way of inputting things like passwords) while % is for app actions that specifically require files.  With %, the inputted file's name can optionally be modified as well `--myparam% myfile.txt newname.txt`.  


# Installation

For development, simply clone the repo and use `composer install` to download and install the required PHP dependencies.  For production, download a release tarball with dependencies included.

### Basic Requirements
Andromeda requires PHP >= 7.4 (8.x is supported) and the JSON, mbstring, PDO and Sodium PHP extensions.  Other extensions may be required by apps for additional functionality.  Supported databases are MySQL, PostgreSQL and SQLite.  Andromeda does not use any OS or webserver-specific functions and works on Windows and Linux, Apache and Nginx, etc.  *No* specific PHP or webserver configuration is required.

### Install Steps

Use the `server usage` command to see options for all available commands.

1. Run `server dbconf` to generate a database configuration file.
2. Run `server install` to install the core database tables.  This will enable all apps that are found in the apps folder, and return the list of them for step 3.
3. Install all apps that require it.  Hint: try `./andromeda server usage | grep install`.

Installing the accounts app optionally will also create an initial administrator account (see its `server usage` entry).  From here you will probably want to create and use a session with your new account.  See the accounts app wiki for more information.

#### Database Config
The `server dbconf` command will store the new configuration file (Config.php) by default in the core/database folder.  When Andromeda runs it checks `core/database`, `/usr/local/etc/andromeda` and `/etc/andromeda` in that order for the config file.  

For example to create and use an SQLite database - `php index.php server dbconf --driver sqlite --dbpath mydata.s3db`.  SQLite is only recommended for testing or tiny deployments.

### Upgrading
When the code being run does not match the version stored in the database, running `server upgrade` is required. This will automatically update all apps.  Apps can also have their `(myapp) upgrade` command run separately if supported.


# License

Andromeda including this readme, the wiki, and any documentation are copyrighted by the author.  Use of this repository and source code is licensed under the AGPLv3.  Commercial licenses can be obtained separately.

