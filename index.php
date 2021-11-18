<?php

/** 
 * The Andromeda directory can be installed anywhere, by
 * updating the include paths below.  It is recommended that
 * this file is used as the web entry point and that the Andromeda
 * and vendor folders are not in a web-accessible location.
 * 
 * vendor and Andromeda must exist in the same folder.
 */

$paths = array(
    __DIR__.'/Andromeda/index.php',
    '/usr/local/lib/andromeda-server/Andromeda/index.php',
    '/usr/lib/andromeda-server/Andromeda/index.php'
);

foreach ($paths as $path)
{
    if (file_exists($path))
    {
        require_once($path); die();
    }
}
 
die("Could not find Andromeda folder!");
