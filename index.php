<?php define('Andromeda',true);

define("VERSION",array(0,0,1)); define("ROOT",__DIR__);

if (!version_compare(phpversion(),'7.1.0','>=')) { die("PHP must be 7.1.0 or greater (you have ".PHP_VERSION.")"); }

if (!function_exists('json_encode'))    die("PHP JSON Extension Required\n");
if (!function_exists('sodium_memzero')) die("PHP Sodium Extension Required\n");
if (!class_exists('PDO'))               die("PHP PDO Extension Required\n");

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

ignore_user_abort(); set_time_limit(0);

$main = null; $output = null;

try 
{    
    $error_manager = new ErrorManager();
    
    $interface = IOInterface::Get();
    
    $main = new Main($error_manager, $interface); 
    
    $input = $interface->GetInput();        
    
    $output = $main->Run($input); 
    
    $main->Commit();    
    
    if ($main->GetDebug()) $output->SetMetrics($main->GetMetrics());
    
    $interface->WriteOutput($output);
} 
catch (Exceptions\ClientException $e)
{
    $interface->WriteOutput($error_manager->HandleClientException($e));
}
catch (Exceptions\ServerException $e)
{
    $interface->WriteOutput($error_manager->HandleServerException($e));
}
catch (Throwable $e)
{
    $interface->WriteOutput($error_manager->HandleThrowable($e));
}

die();
