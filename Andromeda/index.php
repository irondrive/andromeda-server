<?php declare(strict_types=1); require_once(__DIR__.'/init.php');

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

use Andromeda\Core\ApiPackage;
use Andromeda\Core\IOFormat\{Input, Output, IOInterface};
use Andromeda\Core\Exceptions\ErrorManager;


/** First create the global resources */

$interface = IOInterface::TryGet(); 
if (!$interface) die('INTERFACE_ERROR');

$errman = new ErrorManager($interface, true);

$inputs = $interface->LoadInputs(); // check early

$apipack = new ApiPackage($interface, $errman);

$runner = $apipack->GetAppRunner();
$metrics = $apipack->GetMetricsHandler();


/** Run the array of user commands */

$retvals = array_map(
    function(Input $input)use($runner){
        return $runner->Run($input); }, $inputs);


/** Save/commit changes, display output */

$output = Output::Success($retvals);

$runner->commit();

if ($interface->UserOutput($output)) 
    $runner->commit();

$metrics->SaveMetrics($apipack, $output);
$interface->FinalOutput($output);




