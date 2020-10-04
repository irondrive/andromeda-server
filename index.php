<?php define('Andromeda',true); 

define("ROOT",__DIR__.'/');

if (!version_compare(phpversion(),'7.1.0','>=')) { die("PHP must be 7.1.0 or greater (you have ".PHP_VERSION.")\n"); }

if (!function_exists('json_encode')) die("PHP JSON Extension Required\n");

ignore_user_abort();

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;

$interface = IOInterface::TryGet();
if ($interface === null) die('Unknown Interface');

$error_manager = new ErrorManager($interface);

$main = new Main($error_manager, $interface); 

$inputs = $interface->GetInputs($main->GetConfig());

$data = array_map(function($input)use($main){ 
    return $main->Run($input); }, $inputs);

$output = Output::Success($data);
$metrics = $main->commit(); 

if ($metrics !== null) $output->SetMetrics($metrics);

$interface->WriteOutput($output); die();
