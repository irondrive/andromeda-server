<?php define('Andromeda',true);

define("VERSION",array(0,0,1)); define("ROOT",__DIR__.'/');

if (!version_compare(phpversion(),'7.1.0','>=')) { die("PHP must be 7.1.0 or greater (you have ".PHP_VERSION.")"); }

if (!function_exists('json_encode')) die("PHP JSON Extension Required\n");

ignore_user_abort(); set_time_limit(0);

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;

$interface = IOInterface::TryGet();
if ($interface === null) die('Unknown Interface');

$error_manager = new ErrorManager($interface);

$main = new Main($error_manager, $interface); 

// TODO - all or nothing transaction approach right now, support piecemeal

$inputs = $interface->GetInputs(); $data = array();

foreach ($inputs as $input) array_push($data, $main->Run($input));

$output = Output::Success($data);

$metrics = $main->Commit(); 

if ($metrics !== null) $output->SetMetrics($metrics);

$interface->WriteOutput($output); die();
