<?php declare(strict_types=1); 

if (!version_compare(phpversion(),'7.4.0','>='))
    die("PHP must be 7.4.0 or greater (you have ".PHP_VERSION.PHP_EOL);

define('Andromeda',true); // entry-points
define('andromeda_version','1.0.0-alpha');

define('ROOT',__DIR__.'/');

// requires zend.assertions=1 in php.ini
ini_set('assert.active','1');
ini_set('assert.exception','1');

require_once(ROOT.'/../vendor/autoload.php');

// the "almost PSR-4 compliant" autoloader?
// PHP's default seems to require lowercase files
spl_autoload_register(function(string $class)
{
    if (strpos($class,"Andromeda\\") !== 0) return;
    $class = substr($class, 10); // strlen("Andromeda\\")
    
    // usually a file is a single class
    $path = ROOT.str_replace("\\","/",$class).'.php';
    if (file_exists($path)) include_once($path);
    else
    {    // a file can also be a namespace
        if (($lpos = strrpos($class, "\\")) !== false)
            $class = substr($class, 0,  $lpos);
        $path = ROOT.str_replace("\\","/",$class).'.php';
        if (file_exists($path)) include_once($path);
    }
});

if (is_file(ROOT.'/userInit.php'))
    require_once(ROOT.'/userInit.php');

/** The following can be used in userInit.php */
/** DO NOT edit this file! It may be overwritten! */

/** Use to permanently set the database config path */
//define('DBCONF','path-to-config');

/** Use to permanently disable install/upgrade via HTTP */
//define('ALLOW_HTTP_INSTALL',false);
