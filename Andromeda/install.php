<?php declare(strict_types=1); require_once(__DIR__.'/init.php');

/** A more minimal index.php that runs InstallRunner */

use Andromeda\Core\InstallRunner;
use Andromeda\Core\IOFormat\{Input, Output, IOInterface};
use Andromeda\Core\Exceptions\ErrorManager;


/** First create the global resources */

$interface = IOInterface::TryGet();
if (!$interface) die('INTERFACE_ERROR');

$errman = new ErrorManager($interface, true);

$inputs = $interface->GetInputs(); // check early

$runner = new InstallRunner($interface, $errman);


/** Run the array of user commands */

$retvals = array_map(
    function(Input $input)use($runner){
        return $runner->Run($input); }, $inputs);


/** Save/commit changes, display output */

$output = Output::Success($retvals);

$runner->commit();

if ($interface->UserOutput($output))
    $runner->commit();

$interface->FinalOutput($output);
