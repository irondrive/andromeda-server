<?php declare(strict_types=1); require_once(__DIR__.'/init.php');

/** 
 * An Andromeda API is a pure-PHP transactional REST-ish API.
 * Functionality is divided between the main framework core
 * and apps that implement the desired domain-specific functions.
 * Requests are given as an app name, action, and optional parameters.
 * 
 * The entire lifetime of the request happens under a single transaction.
 * Any exceptions encountered will roll back the entire request safely.
 */

use Andromeda\Core\AppRunner;
use Andromeda\Core\IOFormat\IOInterface;
use Andromeda\Core\Errors\ErrorManager;


$interface = IOInterface::TryGet(); 
if ($interface === null) die('INTERFACE_ERROR');

$errman = new ErrorManager($interface, true);

$input = $interface->GetInput(); // check early

(new AppRunner($interface, $errman))->Run($input);
