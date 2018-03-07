# Overview

The Andromeda Server Core is an app-based PHP framework that provides safe input/output handling
and formatting, error handling and logging, and an object-oriented transactional database abstraction
to "apps". The framework+apps are meant to serve as a backend JSON/RPC solution, i.e. to interface
with a javascript client.  Usage over CLI is also supported with no limitations.
The server core on its own does not expose any functionality - it is a framework for apps.
It is in early development and the API not yet considered stable. 


## Installing and Prerequisites

#### Requirements
* \>= PHP 7.1
* PHP PDO Extension
* PHP JSON Extension
* PHP Sodium Extension (only if importing the Crypto class)

Testing has been done on Windows, Linux, and FreeBSD.

Any database [supported by PDO](https://secure.php.net/manual/en/pdo.drivers.php)
can probably be used (with the appropriate driver) but development is targeted at MySQL.

#### Basic Install Steps

1. Clone the repository
2. Run ```git submodule init``` and ```git submodule update```
3. Import the given example database in ```andromeda.sql```
4. Copy ```core/database/Config.example.php``` to ```core/database/Config.php``` and update it



## Usage and Input/Output

Andromeda and any app can be used over CLI or through a normal web server.

Every request must include the "app" and "action" parameters, to indicate which app and function to run.  Requests can include additional parameters that are passed along to the app.  App parameters are always strongly typed.

### CLI Usage

##### Request
```
php index.php app action [--json] [--app_type_key value]
```

##### Response
```
Array
(
    [ok] => 1
    [code] => 200
    [appdata] => yay!
)
```

#### Environment Variables

To ease command line usage for commands that may involve repeated parameters (e.g. a session key),
environment variables can be set that will be show up and be treated as normal input parameters.

The key name of the environment variable must follow the `andromeda_type_key` format.
In Windows, environment variables can be set up graphically, or temporarily with the command `SET andromeda_type_key=value`
In Linux, you can use `export andromeda_type_key=value` or even add it to your bashrc to make it permanent.


### Web Usage

##### Request 
```
/index.php?app=test&action=test&type_key=value
```

##### Response
```
{"ok":true,"code":200,"appdata":"yay!"}
```

### Parameter Types
`bool int float alphanum text raw email object`

The strong typing facilitates automatic input filtering before passing to the app.

#### Arrays and Objects 

Arrays and Objects are always given in JSON format. 
Any parameter type can be *preceded* by a + to indicate an array.
Objects must use the ```type_key``` format as their keys.

#### Full Example

The following example specifies an object named "test2" and an integer array named "test1".
"test2" contains a text "test5" and an object "test3" which contains an int "test4".

```
php index.php tests testjson --app_+int_test1 [12,37,56] --app_object_test2 {\"text_test5\":\"yay!\",\"object_test3\":{\"int_test4\":300}}
```

I recommend using the ```php index.php tests dumpinput``` command to experiment.




 
## Creating a Simple App

Check out the Wiki (in progress) for full API documentation, or read the existing server and test apps for simple examples.

To create an app called `test`, create a folder `apps/test` and a file `apps/test/testApp.php`
In the PHP file, declare namespace `Andromeda\\Apps\\Test`, create a class `TestApp` that extends `AppBase` for which you should add a line to require and use.
At a minimum your class must implement the following method: ```public function Run(Input $input)```.

#### Minimal Example

```php
<?php namespace Andromeda\Apps\Test; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/ioformat/Input.php");use Andromeda\Core\IOFormat\Input;
use Andromeda\Core\UnknownActionException;

class TestApp extends AppBase
{
    public function Run(Input $input)
    {
        $action = $input->GetAction();        
        if ($action == 'test') return "hello world!"; 
        else throw new UnknownActionException();
    }
}
```

`/index.php?app=test&action=test` -> 
`{"ok":true,"code":200,"appdata":"hello world!"}`

#### Framework Overview

* Input handling is done for you.  Use *only* the `Input` class, never `$_POST` or `filter_var` or other PHP solutions.
* Output handling is done for you.  Simply return from `Run()` with the data you want to output.  Don't use `die()` or `echo` or anything else.
* Error handling is done for you.  Extend the given exception classes.  Don't use `die` or `echo()` or anything else.
* You have a database that gives an object abstraction.  To store and access data, create a class extending `StandardObject` representing it, and use the provided methods.

Your app will be constructed with ```$this->API``` which contains a reference to the `Main` singleton object.
This is your entry point for accessing the `Server` and `ObjectDatabase` objects.
The `Input` class manages filtering input variables and checking for existence, making getting parameters a breeze.

`AppBase` is extended to create apps, and `BaseObject` or `StandardObject` are extended to create database objects.
Several other classes such as `Crypto` and `Utilities` contain static methods you may find useful.
For error handling, you should create exception classes to extend the given `ServerException` and `ClientXXXException` classes.

The `ObjectDatabase` manages transactions and uses prepared statements for security and atomicity.  If your app generates an unhandled exception, the database will never be modified.
The `ErrorManager` automatically prints traces and logs errors to the database while still printing valid outputs to the client, making debugging easy.

Read the Wiki for a more detailed API!

## License

Andromeda Server Core including this readme, the wiki, and any documentation are copyrighted by the author.  All rights reserved.
Use of the Andromeda Server Core and source code is licensed under the AGPLv3.  