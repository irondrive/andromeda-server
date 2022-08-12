<?php require_once(__DIR__.'/init.php');

if (file_exists(ROOT.'/userInit.php'))
    require_once(ROOT.'/userInit.php');

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

require_once(ROOT."/Core/ApiPackage.php"); use Andromeda\Core\ApiPackage;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/Output.php"); use Andromeda\Core\IOFormat\Output;

require_once(ROOT."/Core/IOFormat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/Core/Exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;

/** 
 * The basic procedure is to create the ApiPackage, parse an array 
 * of commands from the interface, run the AppRunner application for each 
 * command given, commit the transaction, and display output
 */

$interface = IOInterface::TryGet(); 
if (!$interface) die('INTERFACE_ERROR');

$errman = new ErrorManager($interface, true);
$apipack = new ApiPackage($interface, $errman);

$inputs = $interface->GetInputs($apipack->TryGetConfig());

$runner = $apipack->GetAppRunner();

$retvals = array_map(function(Input $input)use($runner){
    return $runner->Run($input); }, $inputs);

$output = Output::Success($retvals);

$runner->commit(true);
if ($interface->UserOutput($output)) 
    $runner->commit();

$apipack->SaveMetrics($output);
$interface->WriteOutput($output);
