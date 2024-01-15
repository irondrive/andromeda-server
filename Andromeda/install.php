<?php declare(strict_types=1); require_once(__DIR__.'/init.php');

/** A more minimal index.php that runs InstallRunner */

use Andromeda\Core\InstallRunner;
use Andromeda\Core\IOFormat\{Input, Output, IOInterface};
use Andromeda\Core\Errors\ErrorManager;


/** First create the global resources */

$interface = IOInterface::TryGet();
if ($interface === null) die('INTERFACE_ERROR');

$errman = new ErrorManager($interface, true);

$input = $interface->GetInput(); // check early

$runner = new InstallRunner($interface, $errman);


/** Run the user command, save/commit changes, display output */

$retval = $runner->Run($input);
$runner->commit();

$output = Output::Success($retval);

if ($interface->UserOutput($output))
    $runner->commit();

$interface->FinalOutput($output);
