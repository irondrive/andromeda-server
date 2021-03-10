<?php namespace Andromeda\Apps\Server; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\{Main, FailedAppLoadException};
require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{EmailRecipient, Emailer};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/exceptions/ErrorLogEntry.php"); use Andromeda\Core\Exceptions\ErrorLogEntry;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\{Database, DatabaseException, DatabaseConfigException};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

use Andromeda\Core\{UnknownActionException, UnknownConfigException, MailSendException};

/** Exception indicating that the specified mailer object does not exist */
class UnknownMailerException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_MAILER"; }

/** Client error indicating that the mailer config failed */
class MailSendFailException extends Exceptions\ClientErrorException { public $message = "MAIL_SEND_FAILURE"; }

/** Client error indicating that the database config failed */
class DatabaseFailException extends Exceptions\ClientErrorException { public $message = "INVALID_DATABASE"; }

/** Client error indicating authentication failed */
class AuthFailedException extends Exceptions\ClientDeniedException { public $message = "ACCESS_DENIED"; }

/** Exception indicating an invalid app name was given */
class InvalidAppException extends Exceptions\ClientErrorException { public $message = "INVALID_APPNAME"; }

/**
 * Primary app included with the framework.
 * 
 * Handles DB config, install, and getting/setting config.
 */
class ServerApp extends AppBase
{
    public static function getVersion() : string { return "2.0.0-alpha"; } 

    public static function getUsage() : array
    {
        return array(
            'random [--length int]',
            'usage|help', 
            'runtests',
            'install [--enable bool]',
            'installapps',
            'dbconf '.Database::GetInstallUsage(),
            ...Database::GetInstallUsages(),
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
            'deletemailer --mailid id',
            'geterrors '.ErrorLogEntry::GetLoadUsage()
        );
    }
  
    private ?ObjectDatabase $database;
    
    /** if true, the Accounts app is installed and should be used */
    private bool $useAuth;
    
    /** if true, the user has admin access (via the accounts app or if not installed, a privileged interface) */
    private bool $isAdmin;
    
    public function __construct(Main $api)
    {
        parent::__construct($api);
        $this->database = $api->GetDatabase();
        
        $this->useAuth = array_key_exists('accounts',$this->API->GetApps());
    }

    /**
     * {@inheritDoc}
     * @throws DatabaseConfigException if the database needs to be configured
     * @throws UnknownConfigException if config needs to be initialized
     * @throws UnknownActionException if the given action is not valid
     * @see AppBase::Run()
     */
    public function Run(Input $input)
    {
        // if the database is not installed, require configuring it
        if (!$this->database)
        {
            if (!in_array($input->GetAction(), array('dbconf','usage')))
                throw new DatabaseConfigException();
        }
        // if config is not available, require installing it
        else if (!$this->API->GetConfig() && !in_array($input->GetAction(), array('install','usage')))
            throw new UnknownConfigException(static::class);
        
        if (isset($this->isAdmin)) $oldadmin = $this->isAdmin;
        if (isset($this->authenticator)) $oldauth = $this->authenticator;
        
        // if the Accounts app is installed, use it for authentication, else check interface privilege
        if ($this->useAuth && $this->database)
        {
            require_once(ROOT."/apps/accounts/Authenticator.php");
            
            $this->authenticator = \Andromeda\Apps\Accounts\Authenticator::TryAuthenticate(
                $this->database, $input, $this->API->GetInterface());
            $this->isAdmin = $this->authenticator !== null && $this->authenticator->isAdmin();
        }
        else $this->isAdmin = $this->API->GetInterface()->isPrivileged();
                
        switch($input->GetAction())
        {
            case 'random':  return $this->Random($input);
            case 'runtests': return $this->RunTests($input);
            
            case 'dbconf':  return $this->ConfigDB($input);
            case 'install': return $this->Install($input);
            case 'installapps': return $this->InstallApps($input);
            
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
            
            case 'geterrors': return $this->GetErrors($input);
            
            case 'usage': case 'help':
                return $this->GetUsages($input);
            
            default: throw new UnknownActionException();
        }
        
        if (isset($oldadmin)) $this->isAdmin = $oldadmin; else unset($this->isAdmin);
        if (isset($oldauth)) $this->authenticator = $oldauth; else unset($this->authenticator);
    }

    /**
     * Generates a random value, usually for sanity checking
     * @throws UnknownActionException if not debugging or using CLI
     * @return string random value
     */
    protected function Random(Input $input) : string
    {
        if ($this->API->GetDebugLevel() < Config::LOG_DEVELOPMENT &&
            !$this->API->GetInterface()->isPrivileged())
            throw new UnknownActionException();
        
        $length = $input->GetOptParam("length", SafeParam::TYPE_INT);
    
        return Utilities::Random($length ?? 16);
    }
        
    /**
     * Collects usage strings from every installed app and returns them
     * @return string[] array of possible commands
     */
    protected function GetUsages(Input $input) : array
    {            
        $want = $input->GetOptParam('app',SafeParam::TYPE_ALPHANUM);
        
        $output = array(); foreach ($this->API->GetApps() as $name=>$app)
        {
            if ($want !== null && $want !== $name) continue;
            
            array_push($output, ...array_map(function($line)use($name){ return "$name $line"; }, $app::getUsage())); 
        }
        return $output;
    }

    /**
     * Runs unit tests on every installed app
     * @throws UnknownActionException if not debugging
     * @return array<string, mixed> app names mapped to their output
     */
    protected function RunTests(Input $input) : array
    {
        if ($this->API->GetDebugLevel() < Config::LOG_DEVELOPMENT) 
            throw new UnknownActionException();
        
        set_time_limit(0);
            
        return array_map(function($app)use($input){ return $app->Test($input); }, $this->API->GetApps());
    }
    
    /**
     * Creates a database config with the given input
     * @throws DatabaseFailException if the config is invalid
     */
    protected function ConfigDB(Input $input) : void
    {
        try { Database::Install($input); }
        catch (DatabaseException $e) { throw new DatabaseFailException($e); }
    }
    
    /**
     * Installs the server by importing its SQL template and creating config
     * @throws UnknownActionException if config already exists
     * @return array `{apps:[string]}` list of registered apps
     */
    public function Install(Input $input) : array
    {
        if ($this->API->GetConfig()) throw new UnknownActionException();
        
        $this->database->importTemplate(ROOT."/core");        
        
        $apps = array_filter(scandir(ROOT."/apps"),function($e){ return !in_array($e,array('.','..')); });
        
        $config = Config::Create($this->database);
        foreach ($apps as $app) $config->EnableApp($app);
        
        $enable = $input->GetOptParam('enable', SafeParam::TYPE_BOOL);        
        $config->setEnabled($enable ?? !$this->API->GetInterface()->isPrivileged());
        
        return array('apps'=>array_filter($apps,function($e){ return $e !== 'server'; }));
    }
    
    /**
     * Runs Install() on all registered apps
     * @throws AuthFailedException if not admin
     * @return array [string:mixed]
     */
    protected function InstallApps(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        return array_map(function(AppBase $app)use($input){ 
            try { return $app->Install($input); }
            catch (UnknownActionException $e){ return null; }
        }, $this->API->GetApps());
    }
    
    /**
     * Prints the phpinfo() page
     * @throws AuthFailedException if not admin-level access
     */
    protected function PHPInfo(Input $input) : void
    {
        if (!$this->isAdmin) throw new AuthFailedException();

        $this->API->GetInterface()->RegisterOutputHandler(function(){
            $this->API->GetInterface()->SetOutputMode(null); phpinfo(); });
    }
    
    /**
     * Gets miscellaneous server identity information
     * @throws AuthFailedException if not admin-level access
     * @return array `{uname:string, os:string, software:string, signature:string, name:string, addr:string, port:string, file:string, db:Database::getInfo()}`
     * @see Database::getInfo()
     */
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
            'db' => $this->database->getInfo()
        );
    }
    
    /**
     * Sends a test email via a given mailer
     * @throws AuthFailedException if not an admin via the accounts app
     * @throws UnknownMailerException if the given mailer is invalid
     * @throws MailSendFailException if sending the email fails
     */
    protected function TestMail(Input $input) : void
    {
        if (!$this->isAdmin || !$this->authenticator) throw new AuthFailedException();
        
        if (!$this->authenticator)
            $dest = $input->GetParam('dest',SafeParam::TYPE_EMAIL);
        else $dest = $input->GetOptParam('dest',SafeParam::TYPE_EMAIL);
    
        if ($dest) $dests = array(new EmailRecipient($dest));
        else $dests = $this->authenticator->GetAccount()->GetContactEmails();
        
        $subject = "Andromeda Email Test";
        $body = "This is a test email from Andromeda";
        
        if (($mailer = $input->GetOptParam('mailid', SafeParam::TYPE_RANDSTR)) !== null)
        {
            $mailer = Emailer::TryLoadByID($this->database, $mailer);
            if ($mailer === null) throw new UnknownMailerException();
            else $mailer->Activate();
        }
        else $mailer = $this->API->GetConfig()->GetMailer();
        
        try { $mailer->SendMail($subject, $body, false, $dests, false); }
        catch (MailSendException $e) { throw MailSendFailException::Copy($e); }
    }
    
    /**
     * Registers (enables) an app
     * @throws AuthFailedException if not an admin
     * @return string[] array of enabled apps
     */
    protected function EnableApp(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $app = $input->GetParam('appname',SafeParam::TYPE_ALPHANUM);
        
        try { $this->API->LoadApp($app)->GetConfig()->EnableApp($app); }
        catch (FailedAppLoadException $e){ throw new InvalidAppException(); }

        return $this->API->GetConfig()->GetApps();
    }
    
    /**
     * Unregisters (disables) an app
     * @throws AuthFailedException if not an admin
     * @return string[] array of enabled apps
     */
    protected function DisableApp(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $app = $input->GetParam('appname',SafeParam::TYPE_ALPHANUM);
        
        $this->API->GetConfig()->DisableApp($app);
        
        return $this->API->GetConfig()->GetApps();
    }
    
    /**
     * Loads server config
     * @return array if admin, `{config:Config, database:Database}` \
         if not admin, `Config`
     * @see Config::GetClientObject() 
     * @see Database::GetClientObject()
     */
    protected function GetConfig(Input $input) : array
    {
        if (!$this->isAdmin) return $this->API->GetConfig()->GetClientObject();
        
        return array(
            'config' => $this->API->GetConfig()->GetClientObject(true),
            'database' => $this->database->GetClientObject()
        );
    }

    /**
     * Sets server config
     * @throws AuthFailedException if not an admin
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function SetConfig(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        return $this->API->GetConfig()->SetConfig($input)->GetClientObject(true);
    }
    
    /**
     * Returns a list of the configured mailers
     * @throws AuthFailedException if not an admin
     * @return array [id:Emailer]
     * @see Emailer::GetClientObject()
     */
    protected function GetMailers(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        return array_map(function($m){ return $m->GetClientObject(); }, 
            Emailer::LoadAll($this->database));
    }
    
    /**
     * Creates a new emailer config
     * @throws AuthFailedException if not an admin
     * @return array Emailer
     * @see Emailer::GetClientObject()
     */
    protected function CreateMailer(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $emailer = Emailer::Create($this->database, $input);
        
        if (($dest = $input->GetOptParam('test',SafeParam::TYPE_EMAIL)) !== null)
        {
            $input->GetParams()->AddParam('mailid',$emailer->ID())->AddParam('dest',$dest);
            
            $this->TestMail($input);
        }

        return $emailer->GetClientObject();
    }
    
    /**
     * Deletes a configured emailer
     * @throws AuthFailedException if not an admin 
     * @throws UnknownMailerException if given an invalid emailer
     */
    protected function DeleteMailer(Input $input) : void
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $mailid = $input->GetParam('mailid',SafeParam::TYPE_RANDSTR);
        $mailer = Emailer::TryLoadByID($this->database, $mailid);
        if ($mailer === null) throw new UnknownMailerException();
        
        $mailer->Delete();
    }
    
    /**
     * Returns the server error log, possibly filtered
     * @throws AuthFailedException if not an admin 
     */
    protected function GetErrors(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        return array_map(function(ErrorLogEntry $e){ return $e->GetClientObject(); },
            ErrorLogEntry::LoadByInput($this->database, $input));
    }
}
