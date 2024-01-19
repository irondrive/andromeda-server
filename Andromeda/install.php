<?php declare(strict_types=1); require_once(__DIR__.'/init.php');

/** A more minimal index.php that runs InstallRunner */

use Andromeda\Core\InstallRunner;
use Andromeda\Core\IOFormat\{Input, Output, IOInterface};
use Andromeda\Core\Errors\ErrorManager;


$interface = IOInterface::TryGet();
if ($interface === null) die('INTERFACE_ERROR');

$errman = new ErrorManager($interface, true);

$input = $interface->GetInput(); // check early

$runner = new InstallRunner($interface, $errman);

$runner->Run($input);
