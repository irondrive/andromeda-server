<?php define('Andromeda',true); define('andromeda_version','2.0.0-alpha');

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

define("ROOT",__DIR__.'/');

require_once(ROOT."/vendor/autoload.php");

if (!version_compare(phpversion(),'7.4.0','>='))
    die("PHP must be 7.4.0 or greater (you have ".PHP_VERSION.")\n");

if (!function_exists('mb_internal_encoding')) 
    die("PHP mbstring Extension Required\n");
else mb_internal_encoding("UTF-8");

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;

/** 
 * The basic procedure is to create the Main application, parse an array 
 * of commands from the interface, run the Main application for each 
 * command given, commit the transaction, and display output
 */

$interface = IOInterface::TryGet() or die('Unknown Interface');

$main = new Main($interface); 

$inputs = $interface->GetInputs($main->GetConfig());

$retvals = array_map(function(Input $input)use($main){
    return $main->Run($input); }, $inputs);

$output = Output::Success($retvals);

$main->commit();

if ($interface->UserOutput($output)) $main->commit();

$main->FinalizeOutput($output);

$interface->WriteOutput($output);
