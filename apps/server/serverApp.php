<?php namespace Andromeda\Apps\Server; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\{Main, FailedAppLoadException};
require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\{Config, InvalidAppException};
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{EmailRecipient, Emailer};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/exceptions/ErrorLog.php"); use Andromeda\Core\Exceptions\ErrorLog;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\{Database, DatabaseException, DatabaseConfigException};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/logging/RequestLog.php"); use Andromeda\Core\Logging\RequestLog;
require_once(ROOT."/core/logging/ActionLog.php"); use Andromeda\Core\Logging\ActionLog;
require_once(ROOT."/core/logging/BaseAppLog.php"); use Andromeda\Core\Logging\BaseAppLog;

use Andromeda\Core\{UnknownActionException, UnknownConfigException, MailSendException};

/** Exception indicating that the specified mailer object does not exist */
class UnknownMailerException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_MAILER"; }

/** Client error indicating that the mailer config failed */
class MailSendFailException extends Exceptions\ClientErrorException { public $message = "MAIL_SEND_FAILURE"; }

/** Client error indicating that the database config failed */
class DatabaseFailException extends Exceptions\ClientErrorException { public $message = "INVALID_DATABASE"; }

/** Client error indicating authentication failed */
class AuthFailedException extends Exceptions\ClientDeniedException { public $message = "ACCESS_DENIED"; }

/**
 * Server management/info app included with the framework.
 * 
 * Handles DB config, install, and getting/setting config/logs.
 */
class ServerApp extends AppBase
{
    public static function getVersion() : string { return "2.0.0-alpha"; } 
    
    public static function getLogClass() : ?string 
    { 
        if (static::useAuth())
             require_once(ROOT."/apps/server/FullAccessLog.php");
        else require_once(ROOT."/apps/server/AccessLog.php");
        
        return AccessLog::class;
    }
    
    /** Returns true if we should use the accounts app for auth */
    protected static function useAuth() : bool
    {
        return array_key_exists('accounts', Main::GetInstance()->GetApps());
    }

    public static function getUsage() : array
    {
        $retval = array(
            'random [--length int]',
            'usage', 
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
            'geterrors '.ErrorLog::GetLoadUsage(),
            'getrequests '.RequestLog::GetPropUsage().' '.RequestLog::GetLoadUsage().' [--expand bool] [--applogs bool]',
            'countrequests '.RequestLog::GetPropUsage().' '.RequestLog::GetCountUsage(),
            'getallactions '.ActionLog::GetPropUsage().' '.ActionLog::GetLoadUsage().' [--expand bool] [--applogs bool]',
            'countallactions '.ActionLog::GetPropUsage().' '.ActionLog::GetCountUsage()
        );
        
        $logGet = array(); $logCount = array(); $logApps = array();
        
        $apps = Main::GetInstance()->GetApps(); foreach ($apps as $appname=>$app)
        {
            if (($class = $app::getLogClass()) !== null)
            {
                $logApps[] = $appname;
                $logGet[] = "\t --appname $appname ".$class::GetPropUsage();
                $logCount[] = "\t --appname $appname ".$class::GetPropUsage();
            }
        }
        
        $retval[] = 'getactions --appname '.implode('|',$logApps).' '.BaseAppLog::GetBasePropUsage().' '.BaseAppLog::GetLoadUsage().' [--expand bool]'; array_push($retval, ...$logGet);
        $retval[] = 'countactions --appname '.implode('|',$logApps).' '.BaseAppLog::GetBasePropUsage().' '.BaseAppLog::GetCountUsage().' [--expand bool]'; array_push($retval, ...$logCount);
        
        return $retval;
    }
  
    private ?ObjectDatabase $database;

    /** if true, the user has admin access (via the accounts app or if not installed, a privileged interface) */
    private bool $isAdmin;
    
    public function __construct(Main $api)
    {
        parent::__construct($api);
        
        $this->database = $api->GetDatabase();
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
        
        $useAuth = static::useAuth(); $this->authenticator = null;
        
        // if the Accounts app is installed, use it for authentication, else check interface privilege
        if ($useAuth && $this->database)
        {
            require_once(ROOT."/apps/accounts/Authenticator.php");
            
            $this->authenticator = \Andromeda\Apps\Accounts\Authenticator::TryAuthenticate(
                $this->database, $input, $this->API->GetInterface());
            
            $this->isAdmin = $this->authenticator !== null && $this->authenticator->isAdmin();
        }
        else $this->isAdmin = $this->API->GetInterface()->isPrivileged();
        
        if ($useAuth)
             (static::getLogClass())::Create($this->database, $this->authenticator, $this->isAdmin);
        else (static::getLogClass())::Create($this->database, $this->isAdmin);
                
        switch ($input->GetAction())
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
            
            case 'getmailers':   return $this->GetMailers($input); 
            case 'createmailer': return $this->CreateMailer($input);
            case 'deletemailer': return $this->DeleteMailer($input);
            
            case 'geterrors':     return $this->GetErrors($input);
            case 'getrequests':   return $this->GetRequests($input);
            case 'countrequests': return $this->CountRequests($input);
            
            case 'getallactions':   return $this->GetAllActions($input);
            case 'countallactions': return $this->CountAllActions($input);
            
            case 'getactions':    return $this->GetActions($input);
            case 'countactions':  return $this->CountActions($input);
            
            case 'usage': return $this->GetUsages($input);
            
            default: throw new UnknownActionException();
        }
        
        if (isset($oldadmin)) $this->isAdmin = $oldadmin; else unset($this->isAdmin);
        if (isset($oldauth)) $this->authenticator = $oldauth; else unset($this->authenticator);
    }

    /**
     * Generates a random value, usually for dev sanity checking
     * @throws UnknownActionException if not debugging or using CLI
     * @return string random value
     */
    protected function Random(Input $input) : string
    {
        if ($this->API->GetDebugLevel() < Config::LOG_DEVELOPMENT &&
            !$this->API->GetInterface()->isPrivileged())
            throw new UnknownActionException();
        
        $length = $input->GetOptParam("length", SafeParam::TYPE_UINT);

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
     */
    public function Install(Input $input) : void
    {
        if ($this->API->GetConfig()) throw new UnknownActionException();
        
        $this->database->importTemplate(ROOT."/core");        
        
        $config = Config::Create($this->database);
        
        $enable = $input->GetOptParam('enable', SafeParam::TYPE_BOOL);        
        $config->setEnabled($enable ?? !$this->API->GetInterface()->isPrivileged());
    }
    
    /**
     * Runs Install() on all apps in the FS
     * @throws AuthFailedException if not admin
     * @return array [string:mixed]
     */
    protected function InstallApps(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $apps = array_filter(scandir(ROOT."/apps"),function($e){
            return !in_array($e,array('.','..')); });            
        
        foreach ($apps as $app) $this->API->GetConfig()->EnableApp($app);
    
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
     * @return array `{uname:string, server:[various], db:Database::getInfo()}`
     * @see Database::getInfo()
     */
    protected function ServerInfo(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $server = array_filter($_SERVER, function($key){ 
            return strpos($key, 'andromeda_') !== 0;; }, ARRAY_FILTER_USE_KEY);
        
        unset($server['argv']); unset($server['argc']);
        
        return array(
            'uname' => php_uname(),
            'server' => $server,
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
        
        try { $this->API->GetConfig()->EnableApp($app); }
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
        
        return array_map(function(ErrorLog $e){ return $e->GetClientObject(); },
            ErrorLog::LoadByInput($this->database, $input));
    }
    
    /**
     * Returns all request logs matching the given input
     * @param Input $input input to filter logs with
     * @throws AuthFailedException if not admin
     * @return array RequestLog
     * @see RequestLog::GetFullClientObject()
     */
    protected function GetRequests(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $expand = $input->GetOptParam('expand',SafeParam::TYPE_BOOL) ?? false;
        $applogs = $input->GetOptParam('applogs',SafeParam::TYPE_BOOL) ?? false;
        
        $logs = RequestLog::LoadByInput($this->database, $input);
        
        $retval = array(); foreach ($logs as $log)
        {
            $retval[] = $log->GetFullClientObject($expand,$applogs);
        }
        
        return $retval;
    }
    
    /**
     * Counts all request logs matching the given input
     * @param Input $input input to filter logs with
     * @throws AuthFailedException if not admin
     * @return int log entry count
     */
    protected function CountRequests(Input $input) : int
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        return RequestLog::CountByInput($this->database, $input);
    }
    
    /**
     * Returns all action logs matching the given input
     * @param Input $input input to filter logs with
     * @throws AuthFailedException if not admin
     * @return array ActionLog
     * @see ActionLog::GetFullClientObject()
     */
    protected function GetAllActions(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $expand = $input->GetOptParam('expand',SafeParam::TYPE_BOOL) ?? false;
        $applogs = $input->GetOptParam('applogs',SafeParam::TYPE_BOOL) ?? false;
        
        $logs = ActionLog::LoadByInput($this->database, $input);
        
        $retval = array(); foreach ($logs as $log)
        {
            $retval[] = $log->GetFullClientObject($expand,$applogs);
        }
        
        return $retval;
    }
    
    /**
     * Counts all action logs matching the given input
     * @param Input $input input to filter logs with
     * @throws AuthFailedException if not admin
     * @return int log entry count
     */
    protected function CountAllActions(Input $input) : int
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        return ActionLog::CountByInput($this->database, $input);
    }
    
    /**
     * Returns all app action logs matching the given input
     * @param Input $input input to filter logs with
     * @throws AuthFailedException if not admin
     * @throws InvalidAppException if the given app is invalid
     * @return array BaseAppLog
     * @see BaseAppLog::GetFullClientObject()
     */
    protected function GetActions(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $appname = $input->GetParam("appname",SafeParam::TYPE_ALPHANUM);
        
        $apps = $this->API->GetApps(); 
        
        if (!array_key_exists($appname, $apps) ||
            ($class = $apps[$appname]::getLogClass()) === null)
            throw new InvalidAppException();        
        
        $expand = $input->GetOptParam('expand',SafeParam::TYPE_BOOL) ?? false;
        
        $logs = $class::LoadByInput($this->database, $input);
        
        $retval = array(); foreach ($logs as $log)
        {
            $retval[] = $log->GetFullClientObject($expand);
        }
        
        return $retval;
    }
    
    /**
     * Counts all app action logs matching the given input
     * @param Input $input input to filter logs with
     * @throws AuthFailedException if not admin
     * @throws InvalidAppException if the given app is invalid
     * @return int log entry count
     */
    protected function CountActions(Input $input) : int
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $appname = $input->GetParam("appname",SafeParam::TYPE_ALPHANUM);
        
        $apps = $this->API->GetApps();
        
        if (!array_key_exists($appname, $apps) ||
            ($class = $apps[$appname]::getLogClass()) === null)
            throw new InvalidAppException();   
        
        return $class::CountByInput($this->database, $input);
    }
}

