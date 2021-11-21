<?php require_once(__DIR__.'/init.php');

if (file_exists(ROOT.'/defs.php'))
    require_once(ROOT.'/defs.php');

/** 
 * An Andromeda API is a pure-PHP transactional REST-ish API.
 * Functionality is divided between the main framework core
 * and apps that implement the desired domain-specific functions.
 * Requests are given as an app name, action, and optional parameters.
 * 
 * The entire lifetime of the request happens under a single transaction.
 * Any exceptions encountered will roll back the entire request safely.
 * Multiple commands can be given to run in a single request/transaction.
 */

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/Output.php"); use Andromeda\Core\IOFormat\Output;

require_once(ROOT."/Core/IOFormat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/Core/Exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;

/** 
 * The basic procedure is to create the Main application, parse an array 
 * of commands from the interface, run the Main application for each 
 * command given, commit the transaction, and display output
 */

$interface = IOInterface::TryGet() or die('Unknown Interface');

$main = new Main($interface, new ErrorManager($interface)); 

$inputs = $interface->GetInputs($main->TryGetConfig());

$retvals = array_map(function(Input $input)use($main){
    return $main->Run($input); }, $inputs);

$output = Output::Success($retvals);

$main->commit();

if ($interface->UserOutput($output)) $main->commit();

$main->FinalizeOutput($output);

$interface->WriteOutput($output);
