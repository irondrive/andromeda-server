## Bug Reporting

Any code 500 server-error response should be considered a bug and reported.  This excludes errors caught when interfacing with a badly-behaving storage backends.  By default, basic backtraces (no personal info) are logged to the database and can be viewed with the `core geterrors` command.  Bug reports made here must include the relevant error log entry if possible.

See the [error logging how-to](https://github.com/irondrive/andromeda-server/wiki/Core-and-CoreApp#error-logging) for more details.

## Generated Documentation
* [Repository](https://github.com/irondrive/andromeda-server-docs)
* [Doctum Output](https://irondrive.github.io/andromeda-server-docs/doctum_build)

The API documentation is generated using the `tools/mkdocs` script.

## Versioning Scheme
Andromeda follows the standard major.minor.patch semantic versioning scheme.

External clients need only match the major version exactly, while minor versions bring optional new features.  Non-admin external clients cannot see patch versions.  For the internal core<->app APIs, the minor version must also match exactly as minor releases _may_ change APIs.

|                | Purpose | External APIs | Internal APIs | Database Upgrade |
| ------------- | ------------- | ------------- | ------------- | ------------- |
| Patch Versions | Bugfixes | Unchanged | Unchanged | Not Required |
| Minor Versions | Small Features | Compatible | Incompatible | Required |
| Major Versions | Large Features | Incompatible | Incompatible | Required |

## Core Singletons - TODO OUTDATED
`index.php` implements the basic Andromeda procedure.  It creates the `IOInterface`, `ErrorManager`, and `Main` objects, fetches input objects from the interface, runs them using `Main` and maps them to output, then commits Main and sends the output to the interface.

The `Main` class is the primary API for apps and contains references for all the global core objects.  The `Main` class takes instantiates and takes care of the database, framework config, loading apps, and multiplexing Run requests to the appropriate app.  It also has the primary commit/rollback interface.

The `IOInterface` takes care of abstracting the differences between HTTP and CLI and is used for input and output handling.  The `ErrorManager` class takes care of logging exceptions to the database and handling uncaught errors and exceptions. It also provides a `LogDebug()` interface for anyone in the code to log custom debug information. 

Both Main and ErrorManager can be fetched anywhere in the code using the static `GetInstance`.  Andromeda does not support an autoloader and class files must be manually included with require_once().  

## Database API - TODO OUTDATED

`Main` also constructs the global `ObjectDatabase`.  This is the object-oriented PDO-based database abstraction.  The basic database structure includes the `Database` class which sets up the PDO connection from config, tracks stats, and abstracts some of the differences between the different drivers.  The `ObjectDatabase` class inherits from this and provides the object-based interfaces that work between `BaseObject` and the lower level database.  All database objects inherit from `BaseObject`.  Objects represent their database fields/columns using classes in the `FieldTypes` database which all inherit from the basic `FieldTypes\Scalar`.  

When developing higher level code, your objects will use the facilities provided by `BaseObject` and `FieldTypes`.  You should not have to touch the ObjectDatabase or make any manual queries.  Every BaseObject at a minimum needs to fill out `GetFieldTemplate` which maps column names to `FieldTypes` instances, which is how the object structure is declared.  BaseObject provides a number of static functions for loading or deleting objects from the database.  New objects are created using the `BaseCreate` function, which should be wrapped by a custom `Create` function.  An instantiated object separates its fields into scalars, objects, and objectrefs (see below), all of which have functions for getting/setting their values.  

When the ObjectDatabase is told to commit, it will automatically save every modified object, building the appropriate query that updates every modified column.  Your code does not have to worry about actual SQL queries or saving anything.

The `scalar` field functions use FieldTypes including `Scalar` (just a plain value), `Counter` (a field that uses + to update the DB column rather than setting its absolute value) and `JSON` (a field that gets auto JSON encoded and decoded and can be set to an array).  

The `object` field functions deal with fields that link to other objects.  This uses the `ObjectRef` (constant type) or `ObjectPoly` (polymorphic type) FieldTypes.  Internally the linked objects ID is stored in the column. 

The `objectrefs` field functions deal with fields that link to arrays of other objects.  These use the `ObjectRefs` or `ObjectJoin` types.  These require that the objects in the array "point back" at this object using their own `ObjectRef`, so this is really just a convenience field.  E.g. if A has an array of B objects it would allow doing `A->GetObjectRefs('b')` rather than `B::LoadByA('a')`.  The value stored in the actual DB column is just the reference count.  `ObjectJoin` is more complicated as it implements "many-to-many" relationships, which requires a 3rd "link" table to link the objects together (e.g. this is used for accounts/groups).

`StandardObject` is available and provides some convenience functions, such as always storing the `date_created` for an object, organizing columns into some standard prefixes, and providing counter "limits" that check maximum values of counter fields.  This is optional.   

## Creating an App - TODO OUTDATED

When writing custom server side code, you'll need to create a new app.  An app named 'test' should exist as `TestApp` in `Apps/Test/TestApp.php`.  Apps inherit from `BaseApp`.  Apps are all constructed with $API pointing to `Main` for convenience.  The basic functions your app will need to provide are `getUsage()` which returns the CLI usage strings, `getName()` which returns the app's name, and `Run()` which maps `Input` to a returned value (does the actual work).  

Apps also need to have a `metadata.json` that contains a JSON object with `api-version` (the API major.minor version this app is compatible with), `version` the version of the app, and `requires`, an array of dependency apps.  If your app wants to use the framework's action logging capabilities, `getLogClass()` should return the name of your class that inherits from `BaseAppLog`.  

Your app also can implement its own commit() and rollback() routines by overriding the given stub functions.  In `Run()`, your app will need to switch based on the `$input->GetAction()` and then run the appropriate routine.  

If your app uses database tables, it should inherit from `InstalledApp`.  This class takes care of the install and upgrade systems of the app.  The app's version must be stored in the database in a class that inherits from `BaseConfig`. Any other database tables your app requires should be represented with a `BaseObject` or `StandardObject`.  

If your app needs exceptions, they should inherit from `ServerException` or `ClientException`.  `Crypto.php` provides a wrapper around libsodium and `Utilities.php` provides other useful tools. Remember that all input is done via the `Input` object and all output is done by returning a value in `Run()`.  Your app should never use `$_GET` or `printr` or `echo` or anything similar as this breaks CLI/HTTP abstraction.  If your app needs to do its own binary/non-JSON output, it can use the interface's `SetOutputMode()` and `RegisterOutputHandler()` - see CoreApp.php `PHPInfo()` for an example. 

## Tools and Scripts

Tools and scripts are located in the `tools/` folder and must be run from the repository root.

### Database Export

Development is currently done primarily with mysql and phpmyadmin.  The database is then exported into the database templates that are committed with Andromeda.  This is accomplished with the `tools/dbexport` script.  This requires `pgloader` to be installed.  The [`mysql2sqlite`](https://github.com/dumblob/mysql2sqlite) script will be downloaded automatically from github.  It assumes that MySQL uses the `root` user and Postgres uses the `postgres` user, both on localhost, and that the MySQL database name is `andromeda`.

The tool works by first using `tools/pgtransfer` which uses `pgloader` to transfer the database to postgres.  Then the templates are created for core and each app using `mysqldump`, `pg_dump`, and finally `mysql2sqlite`.  

### API Docs Generation

Use the `tools/mkdocs` script.  This will output in the docs folder, which should itself be a checkout of the [API server docs](https://github.com/irondrive/andromeda-server-docs) repository.  The resulting documentation can then be committed.  This is normally only run as part of the release process, or when major changes are made.

### Release Export

Use the `tools/mkrelease tag` script. The first argument is the git tag to checkout.  This script will clone the API from github, checkout the given tag, remove everything development related, generate docs, and output release-ready tarballs.  

## PHP Unit Testing

PHPUnit is used for unit testing.  Tests are located in any `_tests/phpunit` folders.  Unit tests are only used for targeted cases and do not currently have high coverage.  Most functionality is tested externally with the integration tests.  The `tools/unittests` script will run PHPUnit.

## Integration Testing

A python test framework is used for integration testing.  The `tools/pytests` script will run the test suite.  The test suite will run as a consumer of the Andromeda API and exhaustively check every command for expected input/output.  It checks both the AJAX and CLI interfaces and can run with all database types.  Integration tests are located in `tests/integration` in core and apps.

The test framework uses a `pytest-config.json` file that must be located in the `tools/` directory.  This config file specifies which optional components to test based on what you have available in your test environment.  `cli` determines whether to test CLI.  `ajax` gives the URL for testing via HTTP.  `sqlite`, `mysql` and `pgsql` respectively set up which databases to test with.  The objects you set them to use parameters identical to that of the `core dbconf` command, except that pgsql does NOT support persistent connections.  

A minimal integration test would use `{"cli":true,"sqlite":"mytest.s3db"}`.  This will run the test suite using CLI with a local SQLite database.

## Static Analysis

The `tools/analyze` script will run PHPStan for static code analysis.
