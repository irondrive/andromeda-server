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
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/IOFormat/IOInterface.php"); use Andromeda\Core\IOFormat\{IOInterface, OutputHandler};
require_once(ROOT."/Core/Logging/RequestLog.php"); use Andromeda\Core\Logging\RequestLog;
require_once(ROOT."/Core/Logging/ActionLog.php"); use Andromeda\Core\Logging\ActionLog as BaseActionLog;

require_once(ROOT."/Apps/Core/ActionLog.php");

use Andromeda\Core\{UnknownActionException, MailSendException};

/** Exception indicating that the specified mailer object does not exist */
class UnknownMailerException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_MAILER"; }

/** Client error indicating that the mailer config failed */
class MailSendFailException extends Exceptions\ClientErrorException { public $message = "MAIL_SEND_FAILURE"; use Exceptions\Copyable; }

/** Client error indicating that the database config failed */
class DatabaseFailException extends Exceptions\ClientErrorException { public $message = "INVALID_DATABASE"; }

/** Exception indicating that admin-level access is required */
class AdminRequiredException extends Exceptions\ClientDeniedException { public $message = "ADMIN_REQUIRED"; }

/**
 * Server management/info app included with the framework.
 * 
 * Handles DB config, install, and getting/setting config/logs.
 * @extends InstalledApp<Config>
 */
class CoreApp extends InstalledApp
{
    public static function getName() : string { return 'core'; }
    
    /** Returns true if the accounts app is available to use */
    private static function hasAccountsApp() : bool
    {
        return array_key_exists('accounts', Main::GetInstance()->GetApps());
    }
    
    public static function getLogClass() : string
    { 
        if (self::hasAccountsApp())
        {
            require_once(ROOT."/Apps/Core/ActionLogFull.php");
            return ActionLogFull::class;
        }
        else return ActionLog::class;
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
        
        $useAuth = array_key_exists('accounts', Main::GetInstance()->GetApps());
        
        /* if the Accounts app is installed, use it for 
         * authentication, else check interface privilege */
        if ($useAuth)
        {
            require_once(ROOT."/Apps/Accounts/Authenticator.php");
            
            $authenticator = \Andromeda\Apps\Accounts\Authenticator::TryAuthenticate(
                $this->database, $input, $this->API->GetInterface());
            
            $isAdmin = $authenticator !== null && $authenticator->isAdmin();
        }
        else // not using the accounts app
        {
            $authenticator = null; $isAdmin = $this->API->GetInterface()->isPrivileged();
        }

        if (($reqlog = $this->API->GetRequestLog()) !== null)
        {
            $actionlog = $reqlog->LogAction($input, self::getLogClass());
            
            if ($actionlog instanceof ActionLog)
                $actionlog->SetAdmin($isAdmin);
            else if ($actionlog instanceof ActionLogFull)
                $actionlog->SetAuth($authenticator);
        }
        else $actionlog = null;

        switch ($action)
        {
            case 'usage':    return $this->GetUsages($input);
            case 'dbconf':   return $this->ConfigDB($input, $isAdmin);
            case 'scanapps': return $this->ScanApps($input, $isAdmin);
            
            case 'phpinfo':    $this->PHPInfo($input, $isAdmin); return;
            case 'serverinfo': return $this->ServerInfo($input, $isAdmin);
            
            case 'testmail':   $this->TestMail($input, $isAdmin, $authenticator, $actionlog); return;
            
            case 'enableapp':  return $this->EnableApp($input, $isAdmin);
            case 'disableapp': return $this->DisableApp($input, $isAdmin);
            
            case 'getconfig':   return $this->RunGetConfig($input, $isAdmin);
            case 'getdbconfig': return $this->GetDBConfig($input, $isAdmin);
            case 'setconfig':   return $this->RunSetConfig($input, $isAdmin);
            
            case 'getmailers':   return $this->GetMailers($input, $isAdmin); 
            case 'createmailer': return $this->CreateMailer($input, $isAdmin, $authenticator, $actionlog);
            case 'deletemailer': $this->DeleteMailer($input, $isAdmin, $actionlog); return;
            
            case 'geterrors':     return $this->GetErrors($input, $isAdmin);
            case 'counterrors':   return $this->CountErrors($input, $isAdmin);
            
            case 'getrequests':   return $this->GetRequests($input, $isAdmin);
            case 'countrequests': return $this->CountRequests($input, $isAdmin);
            
            case 'getactions':    return $this->GetActions($input, $isAdmin);
            case 'countactions':  return $this->CountActions($input, $isAdmin);            
            
            default: throw new UnknownActionException();
        }
    }
        
    /**
     * Collects usage strings from every installed app and returns them
     * @return string[] array of possible commands
     */
    protected function GetUsages(Input $input) : array
    {
        $params = $input->GetParams();
        
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
    protected function ConfigDB(Input $input, bool $isAdmin) : ?string
    {
        if (isset($this->database) && !$isAdmin) throw new AdminRequiredException();
        
        if (!$this->allowInstall()) throw new UnknownActionException();
        
        $this->API->GetInterface()->DisallowBatch();
        
        try { return Database::Install($input->GetParams()); }
        catch (DatabaseException $e) { throw new DatabaseFailException($e); }
    }

    /**
     * {@inheritDoc}
     * @see \Andromeda\Core\InstalledApp::Install()
     * @return array map of enabled apps to their install retval
     */
    protected function Install(Input $input) : array
    {
        $params = $input->GetParams();
        
        $retval = array('core'=>parent::Install($input));
        
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
                    $retval[$name] = $app->Install($input);
            }
        }

        return $retval;
    }

    /**
     * {@inheritDoc}
     * @see \Andromeda\Core\InstalledApp::Upgrade()
     * @return array map of upgraded apps to their upgrade retval
     */
    protected function Upgrade(Input $input) : array
    {
        $params = $input->GetParams();
        
        $retval = array('core'=>parent::Upgrade($input));
        
        // upgrade all installed apps also
        if (!$params->GetOptParam('noapps',false)->GetBool())
            foreach ($this->API->GetApps() as $name=>$app)
        {
            if ($app instanceof InstalledApp && $app !== $this)
                $retval[$name] = $app->Upgrade($input);
        }
        
        return $retval;
    }
    
    /** @see Config::ScanApps() */
    public function ScanApps(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException(); 
        
        return Config::ScanApps();
    }
    
    /**
     * Prints the phpinfo() page
     * @throws AdminRequiredException if not admin-level access
     */
    protected function PHPInfo(Input $input, bool $isAdmin) : void
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
     * @return array `{uname:string, server:[various], db:Database::getInfo()}`
     * @see Database::getInfo()
     */
    protected function ServerInfo(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $server = array_filter($_SERVER, function($key){ 
            return strpos($key, 'andromeda_') !== 0;; }, ARRAY_FILTER_USE_KEY);
        
        unset($server['argv']); unset($server['argc']);
        
        return array(
            'uname' => php_uname(), 
            'server' => $server,
            'db' => $this->database->GetInternal()->getInfo()
        );
    }
    
    /**
     * Sends a test email via a given mailer
     * @throws AdminRequiredException if not an admin via the accounts app
     * @throws UnknownMailerException if the given mailer is invalid
     * @throws MailSendFailException if sending the email fails
     */
    protected function TestMail(Input $input, bool $isAdmin, $authenticator, ?BaseActionLog $actionlog) : void
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $params = $input->GetParams();
        
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
            
            if ($mailer === null) throw new UnknownMailerException();
            else $mailer->Activate();
        }
        else $mailer = $this->GetConfig()->GetMailer();
        
        if ($actionlog) $actionlog->LogDetails('mailer',$mailer->ID());
        
        try { $mailer->SendMail($subject, $body, false, $dests, false); }
        catch (MailSendException $e) { throw MailSendFailException::Copy($e); }
    }

    /**
     * Registers (enables) an app
     * @throws AdminRequiredException if not an admin
     * @return string[] array of enabled apps
     */
    protected function EnableApp(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();

        $app = $input->GetParams()->GetParam('appname',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        
        try { $this->GetConfig()->EnableApp($app); }
        catch (FailedAppLoadException | MissingMetadataException $e){ 
            throw new InvalidAppException(); }

        return $this->GetConfig()->GetApps();
    }
    
    /**
     * Unregisters (disables) an app
     * @throws AdminRequiredException if not an admin
     * @return string[] array of enabled apps
     */
    protected function DisableApp(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $app = $input->GetParams()->GetParam('appname',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        
        $this->GetConfig()->DisableApp($app);
        
        return $this->GetConfig()->GetApps();
    }
    
    /**
     * Loads server config
     * @return array Config
     * @see Config::GetClientObject() 
     */
    protected function RunGetConfig(Input $input, bool $isAdmin) : array
    {
        return $this->GetConfig()->GetClientObject($isAdmin);
    }
    
    /**
     * Loads server DB config
     * @return array Database
     * @see Database::GetClientObject()
     */
    protected function GetDBConfig(Input $input, bool $isAdmin) : array
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
    protected function RunSetConfig(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return $this->GetConfig()->SetConfig($input->GetParams())->GetClientObject(true);
    }
    
    /**
     * Returns a list of the configured mailers
     * @throws AdminRequiredException if not an admin
     * @return array [id:Emailer]
     * @see Emailer::GetClientObject()
     */
    protected function GetMailers(Input $input, bool $isAdmin) : array
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
    protected function CreateMailer(Input $input, bool $isAdmin, $authenticator, ?BaseActionLog $actionlog) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $params = $input->GetParams();
        
        $emailer = Emailer::Create($this->database, $params)->Save();
        
        if ($params->HasParam('test'))
        {
            $dest = $params->GetParam('test')->GetEmail();
            
            $params->AddParam('mailid',$emailer->ID())->AddParam('dest',$dest);
            
            $this->TestMail($input, $isAdmin, $authenticator, $actionlog);
        }

        if ($actionlog) $actionlog->LogDetails('mailer',$emailer->ID()); 
        
        return $emailer->GetClientObject();
    }
    
    /**
     * Deletes a configured emailer
     * @throws AdminRequiredException if not an admin 
     * @throws UnknownMailerException if given an invalid emailer
     */
    protected function DeleteMailer(Input $input, bool $isAdmin, ?BaseActionLog $actionlog) : void
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $mailid = $input->GetParams()->GetParam('mailid',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
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
    protected function GetErrors(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return array_map(function(ErrorLog $e){ return $e->GetClientObject(); },
            ErrorLog::LoadByParams($this->database, $input->GetParams()));
    }
    
    /**
     * Counts server error log entries, possibly filtered
     * @throws AdminRequiredException if not an admin
     * @return int error log entry count
     */
    protected function CountErrors(Input $input, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return ErrorLog::CountByParams($this->database, $input->GetParams());
    }
    
    /**
     * Returns all request logs matching the given input
     * @throws AdminRequiredException if not admin
     * @return array RequestLog
     * @see RequestLog::GetFullClientObject()
     */
    protected function GetRequests(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $params = $input->GetParams();
        
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
    protected function CountRequests(Input $input, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return RequestLog::CountByParams($this->database, $input->GetParams());
    }
    
    /**
     * Returns all action logs matching the given input
     * @throws AdminRequiredException if not admin
     * @return array ActionLog
     * @see ActionLog::GetFullClientObject()
     */
    protected function GetActions(Input $input, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $params = $input->GetParams();
        
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
    protected function CountActions(Input $input, bool $isAdmin) : int
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return BaseActionLog::CountByParams($this->database, $input->GetParams());
    }
}

