<?php

define("ROOT",__DIR__.'/');

require_once(ROOT.'/../vendor/autoload.php');

define('Andromeda',true);

define('andromeda_version','2.0.0-alpha');

if (!version_compare(phpversion(),'7.4.0','>='))
    die("PHP must be 7.4.0 or greater (you have ".PHP_VERSION.PHP_EOL);

if (!function_exists('mb_internal_encoding'))
    die("PHP mbstring Extension Required".PHP_EOL);
else mb_internal_encoding("UTF-8");
