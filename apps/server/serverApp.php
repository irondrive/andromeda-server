<?php namespace Andromeda\Apps\Server; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{EmailRecipient, Emailer};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\{Database, DatabaseException, DatabaseConfigException};
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;

use Andromeda\Core\{UnknownActionException, UnknownConfigException, MailSendException};

class UnknownMailerException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_MAILER"; }
class MailSendFailException extends Exceptions\ClientErrorException     { public $message = "MAIL_SEND_FAILURE"; }
class AuthFailedException extends Exceptions\ClientDeniedException      { public $message = "ACCESS_DENIED"; }
class DatabaseFailException extends Exceptions\ClientErrorCopyException { public $message = "INVALID_DATABASE"; }

class ServerApp extends AppBase
{
    public static function getVersion() : array { return array(0,0,1); } 
    
    public static function getUsage() : array
    {
        return array(
            'random [--length int]',
            'usage|help', 
            'runtests',
            'install [--enable bool]',
            'initdb '.Database::GetInstallUsage(),
            'phpinfo',
            'serverinfo',
            'testmail [--mailid id] [--dest email]',
            'enableapp --appname alphanum',
            'disableapp --appname alphanum',
            'getconfig',
            'setconfig '.Config::GetSetConfigUsage(),
            'getmailers',
            'createmailer [--test email] '.Emailer::GetCreateUsage(),
            ...array_map(function($s){ return "\t $s"; },Emailer::GetCreateUsages()),
            'deletemailer --mailid id'
        );
    }
 
    private $authenticator = null;
    
    public function __construct(Main $api)
    {
        parent::__construct($api);
        
        $this->useAuth = file_exists(ROOT."/apps/accounts/Authenticator.php");
        if ($this->useAuth) { require_once(ROOT."/apps/accounts/Authenticator.php"); }
    }
    
    public function Run(Input $input)
    {
        if (!$this->API->GetDatabase())
        {
            if ($input->GetAction() !== 'initdb')
                throw new DatabaseConfigException();
        }
        else if (!$this->API->GetConfig() && $input->GetAction() !== 'install')
            throw new UnknownConfigException(static::class);
        
        if ($this->useAuth && $this->API->GetDatabase())
        {
            $this->authenticator = \Andromeda\Apps\Accounts\Authenticator::TryAuthenticate(
                $this->API->GetDatabase(), $input, $this->API->GetInterface());
            $this->isAdmin = $this->authenticator !== null && $this->authenticator->isAdmin();
        }
        else $this->isAdmin = $this->API->isLocalCLI();
                
        switch($input->GetAction())
        {
            case 'random':  return $this->Random($input);
            case 'runtests': return $this->RunTests($input);
            
            case 'initdb':  return $this->InitDB($input);
            case 'install': return $this->Install($input);
            
            case 'phpinfo':    return $this->PHPInfo($input);
            case 'serverinfo': return $this->ServerInfo($input);
            case 'testmail':   return $this->TestMail($input);
            
            case 'enableapp':  return $this->EnableApp($input);
            case 'disableapp': return $this->DisableApp($input);
            
            case 'getconfig':  return $this->GetConfig($input);
            case 'setconfig':  return $this->SetConfig($input);
            
            case 'getmailers': return $this->GetMailers($input); 
            case 'createmailer': return $this->CreateMailer($input);
            case 'deletemailer': return $this->DeleteMailer($input);
            
            case 'usage': case 'help':
                return $this->GetUsages($input);
            
            default: throw new UnknownActionException();
        }
    }
    
    protected function Random(Input $input)
    {
        $length = $input->TryGetParam("length", SafeParam::TYPE_INT);
        
        return Utilities::Random($length ?? 16);
    }
        
    protected function GetUsages(Input $input)
    {
        $output = array(); foreach ($this->API->GetApps() as $name=>$app)
        {
            array_push($output, ...array_map(function($line)use($name){ return "$name $line"; }, $app::getUsage())); 
        }
        return $output;
    }

    protected function RunTests(Input $input)
    {
        set_time_limit(0);
        
        if ($this->API->GetDebugState() >= Config::LOG_DEVELOPMENT)
        {
            return array_map(function($app)use($input){ return $app->Test($input); }, $this->API->GetApps());
        }
        else throw new UnknownActionException();
    }
    
    protected function InitDB(Input $input)
    {
        if ($this->API->GetDatabase()) throw new UnknownActionException();
        
        try { Database::Install($input); }
        catch (\PDOException | DatabaseException $e) { throw new DatabaseFailException($e); }
    }
    
    protected function Install(Input $input)
    {
        if ($this->API->GetConfig()) throw new UnknownActionException();
        
        $database = $this->API->GetDatabase();
        $database->importFile(ROOT."/andromeda2.sql");        
        
        $apps = array_filter(scandir(ROOT."/apps"),function($e){ return !in_array($e,array('.','..')); });
        
        $config = Config::Create($database);
        foreach ($apps as $app) $config->enableApp($app);
        
        $enable = $input->TryGetParam('enable', SafeParam::TYPE_BOOL);        
        $config->setEnabled($enable ?? !$this->API->isLocalCLI());
        
        return array('apps'=>array_filter($apps,function($e){ return $e !== 'server'; }));
    }
    
    protected function PHPInfo(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $this->API->GetInterface()->SetOutmode(IOInterface::OUTPUT_NONE); phpinfo();
    }
    
    protected function ServerInfo(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        return array(
            'uname' => php_uname(),
            'os' => $_SERVER['OPERATING_SYSTEM'] ?? "",
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? "",
            'signature' => $_SERVER['SERVER_SIGNATURE'] ?? "",
            'name' => $_SERVER['SERVER_NAME'] ?? "",
            'addr' => $_SERVER['SERVER_ADDR'] ?? "",
            'port' => $_SERVER['SERVER_PORT'] ?? "",
            'file' => $_SERVER['SCRIPT_FILENAME'],
            'db' => $this->API->GetDatabase()->getInfo()
        );
    }
    
    protected function TestMail(Input $input) : array
    {
        if (!$this->isAdmin || !$this->authenticator) throw new AuthFailedException();
        
        if (!$this->authenticator)
            $dest = $input->GetParam('dest',SafeParam::TYPE_EMAIL);
        else $dest = $input->TryGetParam('dest',SafeParam::TYPE_EMAIL);
        
        if ($dest) $dests = array(new EmailRecipient($dest));
        else $dests = $this->authenticator->GetAccount()->GetMailTo();        
        
        $subject = "Andromeda Email Test";
        $body = "This is a test email from Andromeda";
        
        if (($mailer = $input->TryGetParam('mailid', SafeParam::TYPE_ID)) !== null)
        {
            $mailer = Emailer::TryLoadByID($this->API->GetDatabase(), $mailer);
            if ($mailer === null) throw new UnknownMailerException();
            else $mailer->Activate();
        }
        else $mailer = $this->API->GetConfig()->GetMailer();
        
        try { $mailer->SendMail($subject, $body, $dests); }
        catch (MailSendException $e) { throw new MailSendFailException($e->getDetails()); }
        
        return array();
    }
    
    protected function EnableApp(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $app = $input->GetParam('appname',SafeParam::TYPE_ALPHANUM);
        
        $this->API->GetConfig()->EnableApp($app);
        
        return $this->API->GetConfig()->GetApps();
    }
    
    protected function DisableApp(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $app = $input->GetParam('appname',SafeParam::TYPE_ALPHANUM);
        
        $this->API->GetConfig()->DisableApp($app);
        
        return $this->API->GetConfig()->GetApps();
    }
    
    protected function GetConfig(Input $input) : array
    {
        if (!$this->isAdmin) return $this->API->GetConfig()->GetClientObject();
        
        return array(
            'config' => $this->API->GetConfig()->GetClientObject(true),
            'database' => $this->API->GetDatabase()->getConfig()
        );
    }
    
    protected function SetConfig(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        return $this->API->GetConfig()->SetConfig($input)->GetClientObject(true);
    }
    
    protected function GetMailers(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        return array_map(function($m){ return $m->GetClientObject(); }, 
            Emailer::LoadAll($this->API->GetDatabase()));
    }
    
    protected function CreateMailer(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $emailer = Emailer::Create($this->API->GetDatabase(), $input);
        
        if (($dest = $input->TryGetParam('test',SafeParam::TYPE_EMAIL)) !== null)
        {
            $input->GetParams()->AddParam('mailid',$emailer->ID())->AddParam('dest',$dest);
            
            $this->TestMail($input);
        }

        return $emailer->GetClientObject();
    }
    
    protected function DeleteMailer(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $mailer = Emailer::TryLoadByID($this->API->GetDatabase(), $input->GetParam('mailid',SafeParam::TYPE_ID));
        if ($mailer === null) throw new UnknownMailerException();
        
        $mailer->Delete(); return array();
    }
}

