<?php namespace Andromeda\Apps\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\{Main, FailedAppLoadException};
require_once(ROOT."/Core/BaseApp.php"); use Andromeda\Core\{BaseApp, InstalledApp};
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\{Config, InvalidAppException, MissingMetadataException};
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/Core/Emailer.php"); use Andromeda\Core\{EmailRecipient, Emailer};
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/Exceptions/ErrorLog.php"); use Andromeda\Core\Exceptions\ErrorLog;
require_once(ROOT."/Core/Database/Database.php"); use Andromeda\Core\Database\{Database, DatabaseException};
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/IOFormat/IOInterface.php"); use Andromeda\Core\IOFormat\{IOInterface, OutputHandler};
require_once(ROOT."/Core/Logging/RequestLog.php"); use Andromeda\Core\Logging\RequestLog;
require_once(ROOT."/Core/Logging/ActionLog.php"); use Andromeda\Core\Logging\ActionLog as BaseActionLog;

use Andromeda\Core\{UnknownActionException, MailSendException};

/** Exception indicating that the specified mailer object does not exist */
class UnknownMailerException extends Exceptions\ClientNotFoundException 
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_MAILER", $details);
    }
}

/** Client error indicating that the mailer config failed */
class MailSendFailException extends Exceptions\ClientErrorException
{
    public function __construct(MailSendException $e) {
        parent::__construct(""); $this->CopyException($e);
    }
}

/** Client error indicating that the database config failed */
class DatabaseFailException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_DATABASE", $details);
    }
}
/** Exception indicating that admin-level access is required */
class AdminRequiredException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ADMIN_REQUIRED", $details);
    }
}

/**
 * Server management/info app included with the framework.
 * 
 * Handles DB config, install, and getting/setting config/logs.
 * @extends InstalledApp<Config>
 */
final class CoreApp extends InstalledApp
{
    public static function getName() : string { return 'core'; }
    
    /** Returns true if the accounts app is available to use */
    private static function hasAccountsApp() : bool
    {
        return array_key_exists('accounts', Main::GetInstance()->GetApps());
    }
    
    /** @return class-string<ActionLog> */
    public static function getLogClass() : string
    { 
        if (self::hasAccountsApp())
             require_once(ROOT."/Apps/Core/ActionLogFull.php");
        else require_once(ROOT."/Apps/Core/ActionLogBasic.php");
        
        return ActionLog::class;
    }
    
    protected static function getConfigClass() : string { return Config::class; }
    
    protected function GetConfig() : Config { return $this->config; }
    
    public static function getVersion() : string { return andromeda_version; }
    
    protected static function getTemplateFolder() : string { return ROOT.'/Core'; }
    
    protected static function getUpgradeScripts() : array
    {
        return require_once(ROOT.'/Core/_upgrade/scripts.php');
    }
    
    protected static function getInstallFlags() : string { return '[--noapps bool]'; }
    
    protected static function getUpgradeFlags() : string { return '[--noapps bool]'; }
    
    protected static function getInstallUsage() : array
    {
        $istr = 'install'; $ustr = 'upgrade';
        
        foreach (Utilities::getClassesMatching(InstalledApp::class) as $class)
        {
            if ($if = $class::getInstallFlags()) $istr .= " $if";
            if ($uf = $class::getUpgradeFlags()) $ustr .= " $uf";
        }

        return array($istr,$ustr);
    }
    
    public static function getUsage() : array
    {
        $retval = array_merge(parent::getUsage(),array(
            'usage [--appname alphanum]',
            'dbconf '.Database::GetInstallUsage(),
            ...array_map(function($u){ return "(dbconf) $u"; }, Database::GetInstallUsages()),
            'scanapps',
            'phpinfo',
            'serverinfo',
            'testmail [--mailid id] [--dest email]',
            'enableapp --appname alphanum',
            'disableapp --appname alphanum',
            'getconfig',
            'getdbconfig',
            'setconfig '.Config::GetSetConfigUsage(),
            'getmailers',
            'createmailer [--test email] '.Emailer::GetCreateUsage(),
            ...array_map(function($u){ return "(createmailer) $u"; },Emailer::GetCreateUsages()),
            'deletemailer --mailid id',
            'geterrors '.ErrorLog::GetPropUsage().' '.ErrorLog::GetLoadUsage(),
            'counterrors '.ErrorLog::GetPropUsage().' '.ErrorLog::GetCountUsage(),
            'getrequests '.RequestLog::GetPropUsage().' '.RequestLog::GetLoadUsage().' [--actions bool [--expand bool]]',
            'countrequests '.RequestLog::GetPropUsage().' '.RequestLog::GetCountUsage(),
            'getactions '.BaseActionLog::GetPropUsage().' '.BaseActionLog::GetLoadUsage().' [--expand bool]',
            ...array_map(function($u){ return "(getactions) $u"; },BaseActionLog::GetAppPropUsages()),
            'countactions '.BaseActionLog::GetPropUsage().' '.BaseActionLog::GetCountUsage(),
            ...array_map(function($u){ return "(countactions) $u"; },BaseActionLog::GetAppPropUsages()),
        ));

        return $retval;
    }

    /**
     * {@inheritDoc}
     * @throws UnknownActionException if the given action is not valid
     * @see BaseApp::Run()
     */
    public function Run(Input $input)
    {
        $action = $input->GetAction();
        
        if ($action !== 'dbconf' && $action !== 'usage' 
            && ($retval = parent::Run($input)) !== false) return $retval;

        /* if the Accounts app is installed, use it for 
         * authentication, else check interface privilege */
        if (self::hasAccountsApp())
        {
            require_once(ROOT."/Apps/Accounts/Authenticator.php");
            
            $authenticator = \Andromeda\Apps\Accounts\Authenticator::TryAuthenticate(
                $this->database, $input, $this->API->GetInterface());
            
            $isAdmin = $authenticator !== null && $authenticator->isAdmin();
            
            $actionlog = null; if (($reqlog = $this->API->GetRequestLog()) !== null)
            {
                $actionlog = $reqlog->LogAction($input, self::getLogClass())->SetAuth($authenticator);
            }
        }
        else // not using the accounts app
        {
            $authenticator = null; $isAdmin = $this->API->GetInterface()->isPrivileged();
            
            $actionlog = null; if (($reqlog = $this->API->GetRequestLog()) !== null)
            {
                $actionlog = $reqlog->LogAction($input, self::getLogClass())->SetAdmin($isAdmin);
            }
        }

        $params = $input->GetParams();

        switch ($action)
        {
            case 'usage':    return $this->GetUsages($params);
            case 'dbconf':   return $this->ConfigDB($params, $isAdmin);
            case 'scanapps': return $this->ScanApps($isAdmin);
            
            case 'phpinfo':    $this->PHPInfo($isAdmin); return;
            case 'serverinfo': return $this->ServerInfo($isAdmin);
            
            case 'testmail':   $this->TestMail($params, $isAdmin, $authenticator, $actionlog); return;
            
            case 'enableapp':  return $this->EnableApp($params, $isAdmin);
            case 'disableapp': return $this->DisableApp($params, $isAdmin);
            
            case 'getconfig':   return $this->RunGetConfig($isAdmin);
            case 'getdbconfig': return $this->GetDBConfig($isAdmin);
            case 'setconfig':   return $this->RunSetConfig($params, $isAdmin);
            
            case 'getmailers':   return $this->GetMailers($isAdmin); 
            case 'createmailer': return $this->CreateMailer($params, $isAdmin, $authenticator, $actionlog);
            case 'deletemailer': $this->DeleteMailer($params, $isAdmin, $actionlog); return;
            
            case 'geterrors':     return $this->GetErrors($params, $isAdmin);
            case 'counterrors':   return $this->CountErrors($params, $isAdmin);
            
            case 'getrequests':   return $this->GetRequests($params, $isAdmin);
            case 'countrequests': return $this->CountRequests($params, $isAdmin);
            
            case 'getactions':    return $this->GetActions($params, $isAdmin);
            case 'countactions':  return $this->CountActions($params, $isAdmin);
            
            default: throw new UnknownActionException();
        }
    }
        
    /**
     * Collects usage strings from every installed app and returns them
     * @return string[] array of possible commands
     */
    protected function GetUsages(SafeParams $params) : array
    {
        $want = $params->HasParam('appname') ? $params->GetParam('appname')->GetAlphanum() : null;
        
        $output = array(); foreach ($this->API->GetApps() as $name=>$app)
        {
            if ($want !== null && $want !== $name) continue;
            
            array_push($output, ...array_map(function($line)use($name){ 
                return "$name $line"; }, $app::getUsage())); 
        }
        return $output;
    }
    
    /**
     * Creates a database config with the given input
     * @throws DatabaseFailException if the config is invalid
     */
    protected function ConfigDB(SafeParams $params, bool $isAdmin) : ?string
    {
        if (isset($this->database) && !$isAdmin) 
            throw new AdminRequiredException();
        
        if (!$this->allowInstall()) throw new UnknownActionException();
        
        $this->API->GetInterface()->DisallowBatch();
        
        try { return Database::Install($params); }
        catch (DatabaseException $e) { throw new DatabaseFailException($e); }
    }

    /**
     * {@inheritDoc}
     * @see \Andromeda\Core\InstalledApp::Install()
     * @return array map of enabled apps to their install retval
     */
    protected function Install(SafeParams $params) : array
    {
        $retval = array('core'=>parent::Install($params));
        
        if (!$params->GetOptParam('noapps',false)->GetBool())
        {
            // enable all existing apps
            foreach (Config::ScanApps() as $app)
            {
                $retval[$app] = null;
                $this->GetConfig()->EnableApp($app);
            }
            
            // install all enabled apps
            foreach ($this->API->GetApps() as $name=>$app)
            {
                if ($app instanceof InstalledApp && $app !== $this)
                    $retval[$name] = $app->Install($params);
            }
        }

        return $retval;
    }

    /**
     * {@inheritDoc}
     * @see \Andromeda\Core\InstalledApp::Upgrade()
     * @return array map of upgraded apps to their upgrade retval
     */
    protected function Upgrade(SafeParams $params) : array
    {
        $retval = array('core'=>parent::Upgrade($params));
        
        // upgrade all installed apps also
        if (!$params->GetOptParam('noapps',false)->GetBool())
            foreach ($this->API->GetApps() as $name=>$app)
        {
            if ($app instanceof InstalledApp && $app !== $this)
                $retval[$name] = $app->Upgrade($params);
        }
        
        return $retval;
    }
    
    /** @see Config::ScanApps() */
    public function ScanApps(bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException(); 
        
        return Config::ScanApps();
    }
    
    /**
     * Prints the phpinfo() page
     * @throws AdminRequiredException if not admin-level access
     */
    protected function PHPInfo(bool $isAdmin) : void
    {
        $this->API->GetInterface()->SetOutputMode(IOInterface::OUTPUT_PLAIN);
        
        if (!$isAdmin) throw new AdminRequiredException();

        $retval = Utilities::CaptureOutput(function(){ phpinfo(); });
        
        $this->API->GetInterface()->RegisterOutputHandler(new OutputHandler(
            function()use($retval){ return strlen($retval); }, 
            function(Output $output)use($retval){ echo $retval; }));
    }
    
    /**
     * Gets miscellaneous server identity information
     * @throws AdminRequiredException if not admin-level access
     * @return array<mixed> `{uname:string, php_version:string, zend_version:string, server:[various], db:Database::getInfo()}`
     * @see Database::getInfo()
     */
    protected function ServerInfo(bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $server = array_filter($_SERVER, function($key){ 
            return strpos($key, 'andromeda_') !== 0;; }, ARRAY_FILTER_USE_KEY);
        
        unset($server['argv']); unset($server['argc']);
        
        return array(
            'server' => $server,
            'uname' => php_uname(),
            'php_version' => phpversion(),
            'zend_version' => zend_version(),
            'db' => $this->database->GetInternal()->getInfo()
        );
    }
    
    /**
     * Sends a test email via a given mailer
     * @throws AdminRequiredException if not an admin via the accounts app
     * @throws UnknownMailerException if the given mailer is invalid
     * @throws MailSendFailException if sending the email fails
     */
    protected function TestMail(SafeParams $params, bool $isAdmin, $authenticator, ?BaseActionLog $actionlog) : void
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $dest = $params->HasParam('dest') ? $params->GetParam('dest')->GetEmail() : null;
        if (!$authenticator) $params->GetParam('dest'); // mandatory
    
        if ($dest) $dests = array(new EmailRecipient($dest));
        else $dests = $authenticator->GetAccount()->GetContactEmails();
        
        $subject = "Andromeda Email Test";
        $body = "This is a test email from Andromeda";
        
        if ($params->HasParam('mailid'))
        {
            $mailer = $params->GetParam('mailid')->GetRandstr();
            $mailer = Emailer::TryLoadByID($this->database, $mailer);
            
            if ($mailer === null) 
                throw new UnknownMailerException();
        }
        else $mailer = Emailer::LoadAny($this->database);
        
        if ($actionlog) $actionlog->LogDetails('mailer',$mailer->ID());
        
        try { $mailer->SendMail($subject, $body, false, $dests, false); }
        catch (MailSendException $e) { throw new MailSendFailException($e); }
    }

    /**
     * Registers (enables) an app
     * @throws AdminRequiredException if not an admin
     * @return string[] array of enabled apps (not core)
     */
    protected function EnableApp(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();

        $app = $params->GetParam('appname',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        
        try { $this->GetConfig()->EnableApp($app); }
        catch (FailedAppLoadException | MissingMetadataException $e){ 
            throw new InvalidAppException(); }

        return $this->GetConfig()->GetApps();
    }
    
    /**
     * Unregisters (disables) an app
     * @throws AdminRequiredException if not an admin
     * @return string[] array of enabled apps (not core)
     */
    protected function DisableApp(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $app = $params->GetParam('appname',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        
        $this->GetConfig()->DisableApp($app);
        
        return $this->GetConfig()->GetApps();
    }
    
    /**
     * Loads server config
     * @return array Config
     * @see Config::GetClientObject() 
     */
    protected function RunGetConfig(bool $isAdmin) : array
    {
        return $this->GetConfig()->GetClientObject($isAdmin);
    }
    
    /**
     * Loads server DB config
     * @return array Database
     * @see Database::GetClientObject()
     */
    protected function GetDBConfig(bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return $this->database->GetInternal()->GetConfig();
    }

    /**
     * Sets server config
     * @throws AdminRequiredException if not an admin
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function RunSetConfig(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return $this->GetConfig()->SetConfig($params)->GetClientObject(true);
    }
    
    /**
     * Returns a list of the configured mailers
     * @throws AdminRequiredException if not an admin
     * @return array [id:Emailer]
     * @see Emailer::GetClientObject()
     */
    protected function GetMailers(bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return array_map(function($m){ return $m->GetClientObject(); }, 
            Emailer::LoadAll($this->database));
    }
    
    /**
     * Creates a new emailer config
     * @throws AdminRequiredException if not an admin
     * @return array Emailer
     * @see Emailer::GetClientObject()
     */
    protected function CreateMailer(SafeParams $params, bool $isAdmin, $authenticator, ?BaseActionLog $actionlog) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $emailer = Emailer::Create($this->database, $params)->Save();
        
        if ($params->HasParam('test'))
        {
            $dest = $params->GetParam('test')->GetEmail();
            
            $params->AddParam('mailid',$emailer->ID())->AddParam('dest',$dest);
            
            $this->TestMail($params, $isAdmin, $authenticator, $actionlog);
        }

        if ($actionlog) $actionlog->LogDetails('mailer',$emailer->ID()); 
        
        return $emailer->GetClientObject();
    }
    
    /**
     * Deletes a configured emailer
     * @throws AdminRequiredException if not an admin 
     * @throws UnknownMailerException if given an invalid emailer
     */
    protected function DeleteMailer(SafeParams $params, bool $isAdmin, ?BaseActionLog $actionlog) : void
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $mailid = $params->GetParam('mailid',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $mailer = Emailer::TryLoadByID($this->database, $mailid);
        if ($mailer === null) throw new UnknownMailerException();
        
        if ($actionlog && ActionLog::isFullDetails()) 
            $actionlog->LogDetails('mailer', $mailer->GetClientObject());
        
        $mailer->Delete();
    }
    
    /**
     * Returns the server error log, possibly filtered
     * @throws AdminRequiredException if not an admin 
     */
    protected function GetErrors(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return array_map(function(ErrorLog $e){ return $e->GetClientObject(); },
            ErrorLog::LoadByParams($this->database, $params));
    }
    
    /**
     * Counts server error log entries, possibly filtered
     * @throws AdminRequiredException if not an admin
     * @return int error log entry count
     */
    protected function CountErrors(SafeParams $params, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return ErrorLog::CountByParams($this->database, $params);
    }
    
    /**
     * Returns all request logs matching the given input
     * @throws AdminRequiredException if not admin
     * @return array RequestLog
     * @see RequestLog::GetFullClientObject()
     */
    protected function GetRequests(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $actions = $params->GetOptParam('actions',false)->GetBool();
        $expand = $params->GetOptParam('expand',false)->GetBool();

        $logs = RequestLog::LoadByParams($this->database, $params);
        
        $retval = array(); foreach ($logs as $log)
        {
            $retval[] = $log->GetFullClientObject($actions,$expand);
        }
        
        return $retval;
    }
    
    /**
     * Counts all request logs matching the given input
     * @throws AdminRequiredException if not admin
     * @return int log entry count
     */
    protected function CountRequests(SafeParams $params, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return RequestLog::CountByParams($this->database, $params);
    }
    
    /**
     * Returns all action logs matching the given input
     * @throws AdminRequiredException if not admin
     * @return array ActionLog
     * @see ActionLog::GetFullClientObject()
     */
    protected function GetActions(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $expand = $params->GetOptParam('expand',false)->GetBool();
        
        $logs = BaseActionLog::LoadByParams($this->database, $params);
        
        $retval = array(); foreach ($logs as $log)
        {
            $retval[] = $log->GetFullClientObject($expand);
        }
        
        return $retval;
    }
    
    /**
     * Counts all action logs matching the given input
     * @throws AdminRequiredException if not admin
     * @return int log entry count
     */
    protected function CountActions(SafeParams $params, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return BaseActionLog::CountByParams($this->database, $params);
    }
}

