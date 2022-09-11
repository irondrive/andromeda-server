<?php declare(strict_types=1); namespace Andromeda\Apps\Core; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Emailer.php");
use Andromeda\Core\{ApiPackage, BaseApp, Config, Emailer, EmailRecipient, Utilities};

require_once(ROOT."/Core/Exceptions.php"); use Andromeda\Core\{FailedAppLoadException, InvalidAppException, UnknownActionException, MailSendException};

use Andromeda\Core\Exceptions\ErrorLog;
use Andromeda\Core\Database\Database;
use Andromeda\Core\IOFormat\{Input, Output, SafeParams, OutputHandler, IOInterface};
use Andromeda\Core\Logging\RequestLog;
use Andromeda\Core\Logging\ActionLog as BaseActionLog;

require_once(ROOT."/Apps/Core/Exceptions.php");

use Andromeda\Apps\Accounts\Authenticator;

/**
 * Server management/info app included with the framework.
 * 
 * Handles getting/setting config/logs, app enable/disable.
 * Depends on the accountsApp being present but this could be refactored away easily.
 * If the accountsApp is disabled, only privileged interfaces are considered admin
 */
final class CoreApp extends BaseApp
{
    private Config $config;
    
    public function getName() : string { return 'core'; }
    
    public function getVersion() : string { return andromeda_version; }

    /** @return class-string<ActionLog> */
    public function getLogClass() : string { return ActionLog::class; }

    public function getUsage() : array
    {
        return array(
            'usage [--appname alphanum]',
            'phpinfo',
            'serverinfo',
            'testmail [--mailid id] [--dest email]',
            'scanapps [--enable bool]',
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
        );
    }
    
    public function __construct(ApiPackage $api)
    {
        parent::__construct($api);
        
        $this->config = Config::GetInstance($this->database);
    }
    
    /**
     * {@inheritDoc}
     * @throws UnknownActionException if the given action is not valid
     * @see BaseApp::Run()
     */
    public function Run(Input $input)
    {
        // if the accounts app is installed, use it for authentication
        if (array_key_exists('accounts', $this->API->GetAppRunner()->GetApps()))
        {
            $authenticator = Authenticator::TryAuthenticate(
                $this->database, $input, $this->API->GetInterface());
            
            $isAdmin = $authenticator !== null && $authenticator->isAdmin();
        }
        else // not using the accounts app, check interface privilege
        {
            $authenticator = null; $isAdmin = $this->API->GetInterface()->isPrivileged();
        }
        
        $actionlog = null; if (($reqlog = $this->API->GetAppRunner()->GetRequestLog()) !== null)
        {
            $actionlog = $reqlog->LogAction($input, $this->getLogClass());
            
            $actionlog->SetAuth($authenticator)->SetAdmin($isAdmin);
        }

        $params = $input->GetParams();

        switch ($input->GetAction())
        {
            case 'usage':    return $this->GetUsages($params);
            
            case 'phpinfo':    $this->PHPInfo($isAdmin); return;
            case 'serverinfo': return $this->ServerInfo($isAdmin);
            
            case 'testmail':   $this->TestMail($params, $isAdmin, $authenticator, $actionlog); return;
            
            case 'scanapps':    return $this->ScanApps($params, $isAdmin);
            case 'enableapp':   return $this->EnableApp($params, $isAdmin);
            case 'disableapp':  return $this->DisableApp($params, $isAdmin);
            
            case 'getconfig':   return $this->GetConfig($isAdmin);
            case 'getdbconfig': return $this->GetDBConfig($isAdmin);
            case 'setconfig':   return $this->SetConfig($params, $isAdmin);
            
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
     * @return array<string> array of possible commands
     */
    protected function GetUsages(SafeParams $params) : array
    {
        $want = $params->HasParam('appname') ? $params->GetParam('appname')->GetAlphanum() : null;
        
        $apps = $this->API->GetAppRunner()->GetApps();

        $output = array(); foreach ($apps as $name=>$app)
        {
            if ($want !== null && $want !== $name) continue;
            
            array_push($output, ...array_map(function($line)use($name){ 
                return "$name $line"; }, $app->getUsage())); 
        }
        return $output;
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
     * @return array<mixed> `{uname:string, php_version:string, zend_version:string, 
        server:[various], load:[int,int,int], db:Database::getInfo()}`
     * @see Database::getInfo()
     */
    protected function ServerInfo(bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $server = array_filter($_SERVER, function($key){ 
            return strpos($key, 'andromeda_') !== 0; }, ARRAY_FILTER_USE_KEY);
        
        unset($server['argv']); unset($server['argc']);
        
        return array(
            'server' => $server,
            'uname' => php_uname(),
            'php_version' => phpversion(),
            'zend_version' => zend_version(),
            'load' => sys_getloadavg(),
            'db' => $this->database->GetInternal()->getInfo()
        );
    }
    
    /**
     * Sends a test email via a given mailer
     * @throws AdminRequiredException if not an admin via the accounts app
     * @throws UnknownMailerException if the given mailer is invalid
     * @throws MailSendFailException if sending the email fails
     */
    protected function TestMail(SafeParams $params, bool $isAdmin, ?Authenticator $authenticator, ?BaseActionLog $actionlog) : void
    {
        if (!$isAdmin) throw new AdminRequiredException();

        if ($authenticator === null || $params->HasParam('dest'))
        {
            $dest = $params->GetParam('dest')->GetEmail();
            $dests = array(new EmailRecipient($dest));
        }
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
     * Scans for available apps, optionally enabling all
     * @see Config::ScanApps() 
     * @return array<string>
     */
    protected function ScanApps(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $apps = Config::ScanApps();
        
        if ($params->GetOptParam('enable',false,SafeParams::PARAMLOG_ALWAYS)->GetBool())
        {
            foreach ($apps as $app) $this->config->EnableApp($app);
        }
        
        return $apps;
    }
    
    /**
     * Registers (enables) an app
     * @throws AdminRequiredException if not an admin
     * @return array<string> array of enabled apps (not core)
     */
    protected function EnableApp(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();

        $app = $params->GetParam('appname',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        
        try { $this->config->EnableApp($app); }
        catch (FailedAppLoadException $e) { 
            throw new InvalidAppException(); }

        return $this->config->GetApps();
    }
    
    /**
     * Unregisters (disables) an app
     * @throws AdminRequiredException if not an admin
     * @return array<string> array of enabled apps (not core)
     */
    protected function DisableApp(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        $app = $params->GetParam('appname',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        
        $this->config->DisableApp($app);
        
        return $this->config->GetApps();
    }
    
    /**
     * Loads server config
     * @return array<mixed> Config
     * @see Config::GetClientObject() 
     */
    protected function GetConfig(bool $isAdmin) : array
    {
        return $this->config->GetClientObject($isAdmin);
    }
    
    /**
     * Loads server DB config
     * @return array<mixed> Database
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
     * @return array<mixed> Config
     * @see Config::GetClientObject()
     */
    protected function SetConfig(SafeParams $params, bool $isAdmin) : array
    {
        if (!$isAdmin) throw new AdminRequiredException();
        
        return $this->config->SetConfig($params)->GetClientObject(true);
    }
    
    /**
     * Returns a list of the configured mailers
     * @throws AdminRequiredException if not an admin
     * @return array<string, array<mixed>> [id:Emailer]
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
     * @return array<mixed> Emailer
     * @see Emailer::GetClientObject()
     */
    protected function CreateMailer(SafeParams $params, bool $isAdmin, ?Authenticator $authenticator, ?BaseActionLog $actionlog) : array
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
        
        if ($actionlog && $actionlog->isFullDetails()) 
            $actionlog->LogDetails('mailer', $mailer->GetClientObject());
        
        $mailer->Delete();
    }
    
    /**
     * Returns the server error log, possibly filtered
     * @throws AdminRequiredException if not an admin 
     * @return array<string, array<mixed>>
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
     * @return array<array<mixed>> RequestLog
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
     * @return array<array<mixed>> ActionLog
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

