<?php namespace Andromeda\Apps\Server; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\{Main, FailedAppLoadException};
require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\{AppBase, UpgradableApp};
require_once(ROOT."/core/Config.php"); use Andromeda\Core\{Config, DBVersion, InvalidAppException, MissingMetadataException};
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{EmailRecipient, Emailer};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/exceptions/ErrorLog.php"); use Andromeda\Core\Exceptions\ErrorLog;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\{Database, DatabaseException, DatabaseConfigException};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\{IOInterface, OutputHandler};
require_once(ROOT."/core/logging/RequestLog.php"); use Andromeda\Core\Logging\RequestLog;
require_once(ROOT."/core/logging/ActionLog.php"); use Andromeda\Core\Logging\ActionLog;
require_once(ROOT."/core/logging/BaseAppLog.php"); use Andromeda\Core\Logging\BaseAppLog;

use Andromeda\Core\{UnknownActionException, InstallRequiredException, MailSendException};

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
class ServerApp extends UpgradableApp
{    
    public static function getName() : string { return 'server'; }
    
    protected static function getLogClass() : ?string { return AccessLog::class; }
    
    public static function getUsage() : array
    {
        $retval = array_merge(parent::getUsage(),array(
            'random [--length int]',
            'usage [--appname alphanum]',
            'dbconf '.Database::GetInstallUsage(),
            ...array_map(function($u){ return "(dbconf) $u"; }, Database::GetInstallUsages()),
            'install [--enable bool]',
            'listapps',
            'phpinfo',
            'serverinfo',
            'testmail [--mailid id] [--dest email]',
            'enableapp --appname alphanum',
            'disableapp --appname alphanum',
            'getconfig',
            'setconfig '.Config::GetSetConfigUsage(),
            'getmailers',
            'createmailer [--test email] '.Emailer::GetCreateUsage(),
            ...array_map(function($u){ return "(createmailer) $u"; },Emailer::GetCreateUsages()),
            'deletemailer --mailid id',
            'geterrors '.ErrorLog::GetPropUsage().' '.ErrorLog::GetLoadUsage(),
            'counterrors '.ErrorLog::GetPropUsage().' '.ErrorLog::GetCountUsage(),
            'getrequests '.RequestLog::GetPropUsage().' '.RequestLog::GetLoadUsage().' [--expand bool] [--applogs bool]',
            'countrequests '.RequestLog::GetPropUsage().' '.RequestLog::GetCountUsage(),
            'getallactions '.ActionLog::GetPropUsage().' '.ActionLog::GetLoadUsage().' [--expand bool] [--applogs bool]',
            'countallactions '.ActionLog::GetPropUsage().' '.ActionLog::GetCountUsage()
        ));
        
        $logGet = array(); $logCount = array(); $logApps = array();
        foreach (Main::GetInstance()->GetApps() as $appname=>$app)
        {
            if (($class = $app::getLogClass()) !== null)
            {
                $logApps[] = $appname;
                $logGet[] = "(getactions) --appname $appname ".$class::GetPropUsage();
                $logCount[] = "(countactions) --appname $appname ".$class::GetPropUsage();
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
     * @throws InstallRequiredException if config needs to be initialized
     * @throws UnknownActionException if the given action is not valid
     * @see AppBase::Run()
     */
    public function Run(Input $input)
    {
        if ($input->GetAction() !== 'usage')
        {
            // if the database is not installed, require configuring it
            if (!$this->database)
            {
                if ($input->GetAction() !== 'dbconf')
                    throw new DatabaseConfigException();
            }
            // if config is not available, require installing it
            else if (!$this->API->GetConfig() && $input->GetAction() !== 'install')
                    throw new InstallRequiredException('server');
        }
        
        if ($this->API->GetConfig() && ($retval = $this->CheckUpgrade($input))) return $retval;

        $useAuth = array_key_exists('accounts', Main::GetInstance()->GetApps());
        
        // if the Accounts app is installed, use it for authentication, else check interface privilege
        if ($useAuth && $this->database)
        {
            require_once(ROOT."/apps/accounts/Authenticator.php");
            require_once(ROOT."/apps/server/FullAccessLog.php");
            
            $authenticator = \Andromeda\Apps\Accounts\Authenticator::TryAuthenticate(
                $this->database, $input, $this->API->GetInterface());
            
            $isAdmin = $authenticator !== null && $authenticator->isRealAdmin();
        }
        else // not using the accounts app
        {
            require_once(ROOT."/apps/server/AccessLog.php");
            
            $authenticator = null; $isAdmin = $this->API->GetInterface()->isPrivileged();
        }
        
        $accesslog = !$this->database ? null : 
            AccessLog::Create($this->database, $authenticator, $isAdmin); 
        
        $input->SetLogger($accesslog);
                
        switch ($input->GetAction())
        {
            case 'usage':   return $this->GetUsages($input);
            case 'random':  return $this->Random($input);
            
            case 'dbconf':  return $this->ConfigDB($input);
            case 'install': return $this->Install($input);
            case 'listapps': return $this->ListApps($input, $isAdmin);
            
            case 'phpinfo':    return $this->PHPInfo($input, $isAdmin);
            case 'serverinfo': return $this->ServerInfo($input, $isAdmin);
            
            case 'testmail':   return $this->TestMail($input, $isAdmin, $authenticator, $accesslog);
            
            case 'enableapp':  return $this->EnableApp($input, $isAdmin, $accesslog);
            case 'disableapp': return $this->DisableApp($input, $isAdmin, $accesslog);
            
            case 'getconfig':  return $this->GetConfig($input, $isAdmin);
            case 'setconfig':  return $this->SetConfig($input, $isAdmin, $accesslog);
            
            case 'getmailers':   return $this->GetMailers($input, $isAdmin); 
            case 'createmailer': return $this->CreateMailer($input, $isAdmin, $authenticator, $accesslog);
            case 'deletemailer': return $this->DeleteMailer($input, $isAdmin, $accesslog);
            
            case 'geterrors':     return $this->GetErrors($input, $isAdmin);
            case 'counterrors':   return $this->CountErrors($input, $isAdmin);
            
            case 'getrequests':   return $this->GetRequests($input, $isAdmin);
            case 'countrequests': return $this->CountRequests($input, $isAdmin);
            
            case 'getallactions':   return $this->GetAllActions($input, $isAdmin);
            case 'countallactions': return $this->CountAllActions($input, $isAdmin);
            
            case 'getactions':    return $this->GetActions($input, $isAdmin);
            case 'countactions':  return $this->CountActions($input, $isAdmin);            
            
            default: throw new UnknownActionException();
        }
    }

    /**
     * Generates a random value, usually for dev sanity checking
     * @throws UnknownActionException if not debugging or using CLI
     * @return string random value
     */
    protected function Random(Input $input) : string
    {
        if ($this->API->GetDebugLevel() < Config::ERRLOG_DEVELOPMENT &&
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
        $want = $input->GetOptParam('appname',SafeParam::TYPE_ALPHANUM);
        
        $output = array(); foreach ($this->API->GetApps() as $name=>$app)
        {
            if ($want !== null && $want !== $name) continue;
            
            array_push($output, ...array_map(function($line)use($name){ return "$name $line"; }, $app::getUsage())); 
        }
        return $output;
    }
    
    /**
     * Creates a database config with the given input
     * @throws DatabaseFailException if the config is invalid
     */
    protected function ConfigDB(Input $input) : void
    {
        $this->API->GetInterface()->DisallowBatch();
        
        try { Database::Install($input); }
        catch (DatabaseException $e) { throw new DatabaseFailException($e); }
    }
    
    /**
     * Installs the server by importing its SQL template and creating config
     * 
     * Also enables all installable apps that exist (retval)
     * @throws UnknownActionException if config already exists
     * @return array<string> list of apps that were enabled
     */
    public function Install(Input $input) : array
    {
        if ($this->API->GetConfig()) throw new UnknownActionException();
        
        $this->API->GetInterface()->DisallowBatch();
        
        $this->database->importTemplate(ROOT."/core");
        
        $config = Config::Create($this->database);
        
        $enable = $input->GetOptParam('enable', SafeParam::TYPE_BOOL);
        
        $config->setEnabled($enable ?? !$this->API->GetInterface()->isPrivileged());
        
        $apps = Config::ListApps(); foreach ($apps as $app) $config->EnableApp($app); return $apps;
    }
    
    public static function getVersion() : string { return andromeda_version; }

    protected function getDBVersion() : DBVersion { return $this->API->GetConfig(); }
    
    protected static function getUpgradeScripts() : array
    {
        return require_once(ROOT."/core/_upgrade/scripts.php");
    }
    
    public function Upgrade() : void
    {
        parent::Upgrade();
        
        // upgrade all installed apps also
        foreach ($this->API->GetApps() as $name=>$app)
        {            
            if ($app instanceof UpgradableApp && 
                $name !== self::getName()) $app->Upgrade();
        }
    }
    
    /** @see Config::ListApps() */
    public function ListApps(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AuthFailedException(); return Config::ListApps();
    }
    
    /**
     * Prints the phpinfo() page
     * @throws AuthFailedException if not admin-level access
     */
    protected function PHPInfo(Input $input, bool $isAdmin) : void
    {
        $this->API->GetInterface()->SetOutputMode(IOInterface::OUTPUT_PLAIN);
        
        if (!$isAdmin) throw new AuthFailedException();
        
        ob_start(); phpinfo(); $retval = ob_get_contents(); ob_end_clean();
        
        $this->API->GetInterface()->RegisterOutputHandler(new OutputHandler(
            function()use($retval){ return strlen($retval); }, 
            function(Output $output)use($retval){ echo $retval; }));
    }
    
    /**
     * Gets miscellaneous server identity information
     * @throws AuthFailedException if not admin-level access
     * @return array `{uname:string, server:[various], db:Database::getInfo()}`
     * @see Database::getInfo()
     */
    protected function ServerInfo(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AuthFailedException();
        
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
    protected function TestMail(Input $input, bool $isAdmin, $authenticator, ?AccessLog $accesslog) : void
    {
        if (!$isAdmin || ($authenticator && !$authenticator->isRealAdmin())) throw new AuthFailedException();
        
        if (!$authenticator)
            $dest = $input->GetParam('dest',SafeParam::TYPE_EMAIL);
        else $dest = $input->GetOptParam('dest',SafeParam::TYPE_EMAIL);
    
        if ($dest) $dests = array(new EmailRecipient($dest));
        else $dests = $authenticator->GetAccount()->GetContactEmails();
        
        $subject = "Andromeda Email Test";
        $body = "This is a test email from Andromeda";
        
        if (($mailer = $input->GetOptParam('mailid', SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER)) !== null)
        {
            $mailer = Emailer::TryLoadByID($this->database, $mailer);
            if ($mailer === null) throw new UnknownMailerException();
            else $mailer->Activate();
        }
        else $mailer = $this->API->GetConfig()->GetMailer();       
        
        if ($accesslog) $accesslog->LogDetails('mailer',$mailer->ID());
        
        try { $mailer->SendMail($subject, $body, false, $dests, false); }
        catch (MailSendException $e) { throw MailSendFailException::Copy($e); }
    }
    
    /**
     * Registers (enables) an app
     * @throws AuthFailedException if not an admin
     * @return string[] array of enabled apps
     */
    protected function EnableApp(Input $input, bool $isAdmin, ?AccessLog $accesslog) : array
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        $app = $input->GetParam('appname',SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ALWAYS);
        
        try { $this->API->GetConfig()->EnableApp($app); }
        catch (FailedAppLoadException | MissingMetadataException $e){ 
            throw new InvalidAppException(); }

        return $this->API->GetConfig()->GetApps();
    }
    
    /**
     * Unregisters (disables) an app
     * @throws AuthFailedException if not an admin
     * @return string[] array of enabled apps
     */
    protected function DisableApp(Input $input, bool $isAdmin, ?AccessLog $accesslog) : array
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        $app = $input->GetParam('appname',SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ALWAYS);
        
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
    protected function GetConfig(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) return $this->API->GetConfig()->GetClientObject();
        
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
    protected function SetConfig(Input $input, bool $isAdmin, ?AccessLog $accesslog) : array
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        return $this->API->GetConfig()->SetConfig($input)->GetClientObject(true);
    }
    
    /**
     * Returns a list of the configured mailers
     * @throws AuthFailedException if not an admin
     * @return array [id:Emailer]
     * @see Emailer::GetClientObject()
     */
    protected function GetMailers(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        return array_map(function($m){ return $m->GetClientObject(); }, 
            Emailer::LoadAll($this->database));
    }
    
    /**
     * Creates a new emailer config
     * @throws AuthFailedException if not an admin
     * @return array Emailer
     * @see Emailer::GetClientObject()
     */
    protected function CreateMailer(Input $input, bool $isAdmin, $authenticator, ?AccessLog $accesslog) : array
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        $emailer = Emailer::Create($this->database, $input);
        
        if (($dest = $input->GetOptParam('test',SafeParam::TYPE_EMAIL)) !== null)
        {
            $input->AddParam('mailid',$emailer->ID())->AddParam('dest',$dest);
            
            $this->TestMail($input, $isAdmin, $authenticator, $accesslog);
        }

        if ($accesslog) $accesslog->LogDetails('mailer',$emailer->ID()); 
        
        return $emailer->GetClientObject();
    }
    
    /**
     * Deletes a configured emailer
     * @throws AuthFailedException if not an admin 
     * @throws UnknownMailerException if given an invalid emailer
     */
    protected function DeleteMailer(Input $input, bool $isAdmin, ?AccessLog $accesslog) : void
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        $mailid = $input->GetParam('mailid',SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $mailer = Emailer::TryLoadByID($this->database, $mailid);
        if ($mailer === null) throw new UnknownMailerException();
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('mailer', $mailer->GetClientObject());
        
        $mailer->Delete();
    }
    
    /**
     * Returns the server error log, possibly filtered
     * @throws AuthFailedException if not an admin 
     */
    protected function GetErrors(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        return array_map(function(ErrorLog $e){ return $e->GetClientObject(); },
            ErrorLog::LoadByInput($this->database, $input));
    }
    
    /**
     * Counts server error log entries, possibly filtered
     * @throws AuthFailedException if not an admin
     * @return int error log entry count
     */
    protected function CountErrors(Input $input, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        return ErrorLog::CountByInput($this->database, $input);
    }
    
    /**
     * Returns all request logs matching the given input
     * @throws AuthFailedException if not admin
     * @return array RequestLog
     * @see RequestLog::GetFullClientObject()
     */
    protected function GetRequests(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AuthFailedException();
        
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
     * @throws AuthFailedException if not admin
     * @return int log entry count
     */
    protected function CountRequests(Input $input, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        return RequestLog::CountByInput($this->database, $input);
    }
    
    /**
     * Returns all action logs matching the given input
     * @throws AuthFailedException if not admin
     * @return array ActionLog
     * @see ActionLog::GetFullClientObject()
     */
    protected function GetAllActions(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AuthFailedException();
        
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
     * @throws AuthFailedException if not admin
     * @return int log entry count
     */
    protected function CountAllActions(Input $input, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        return ActionLog::CountByInput($this->database, $input);
    }
    
    /**
     * Returns all app action logs matching the given input
     * @throws AuthFailedException if not admin
     * @throws InvalidAppException if the given app is invalid
     * @return array BaseAppLog
     * @see BaseAppLog::GetFullClientObject()
     */
    protected function GetActions(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AuthFailedException();
        
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
     * @throws AuthFailedException if not admin
     * @throws InvalidAppException if the given app is invalid
     * @return int log entry count
     */
    protected function CountActions(Input $input, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new AuthFailedException();
        
        $appname = $input->GetParam("appname",SafeParam::TYPE_ALPHANUM);
        
        $apps = $this->API->GetApps();
        
        if (!array_key_exists($appname, $apps) ||
            ($class = $apps[$appname]::getLogClass()) === null)
            throw new InvalidAppException();   
        
        return $class::CountByInput($this->database, $input);
    }
}

