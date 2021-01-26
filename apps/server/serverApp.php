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

use Andromeda\Core\{UnknownActionException, UnknownConfigException, MailSendException};
use Andromeda\Apps\Accounts\Authenticator;

/** Exception indicating that the specified mailer object does not exist */
class UnknownMailerException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_MAILER"; }

/** Client error indicating that the mailer config failed */
class MailSendFailException extends Exceptions\ClientErrorException     { public $message = "MAIL_SEND_FAILURE"; }

/** Client error indicating that the database config failed */
class DatabaseFailException extends Exceptions\ClientErrorException     { public $message = "INVALID_DATABASE"; }

/** Client error indicating authentication failed */
class AuthFailedException extends Exceptions\ClientDeniedException      { public $message = "ACCESS_DENIED"; }

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
            'deletemailer --mailid id'
        );
    }
 
    private ?Authenticator $authenticator = null;
    
    /** if true, the Accounts app is installed and should be used */
    private bool $useAuth;
    
    /** if true, the user has admin access (via the accounts app or if not installed, a privileged interface) */
    private bool $isAdmin;
    
    public function __construct(Main $api)
    {
        parent::__construct($api);
        
        $this->useAuth = file_exists(ROOT."/apps/accounts/Authenticator.php");
        if ($this->useAuth) { require_once(ROOT."/apps/accounts/Authenticator.php"); }
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
        if (!$this->API->GetDatabase())
        {
            if ($input->GetAction() !== 'dbconf')
                throw new DatabaseConfigException();
        }
        // if config is not available, require installing it
        else if (!$this->API->GetConfig() && $input->GetAction() !== 'install')
            throw new UnknownConfigException(static::class);
        
        // if the Accounts app is installed, use it for authentication, else check interface privilege
        if ($this->useAuth && $this->API->GetDatabase())
        {
            $this->authenticator = Authenticator::TryAuthenticate(
                $this->API->GetDatabase(), $input, $this->API->GetInterface());
            $this->isAdmin = $this->authenticator !== null && $this->authenticator->isAdmin();
        }
        else $this->isAdmin = $this->API->GetInterface()->isPrivileged();
                
        switch($input->GetAction())
        {
            case 'random':  return $this->Random($input);
            case 'runtests': return $this->RunTests($input);
            
            case 'dbconf':  return $this->ConfigDB($input);
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
        
        $length = $input->TryGetParam("length", SafeParam::TYPE_INT);
        
        return Utilities::Random($length ?? 16);
    }
        
    /**
     * Collects usage strings from every installed app and returns them
     * @throws UnknownActionException if not debugging or using CLI
     * @return string[] array of possible commands
     */
    protected function GetUsages(Input $input) : array
    {
        if ($this->API->GetDebugLevel() < Config::LOG_DEVELOPMENT &&
            !$this->API->GetInterface()->isPrivileged())
            throw new UnknownActionException();
            
        $want = $input->TryGetParam('app',SafeParam::TYPE_ALPHANUM);
        
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
    protected function Install(Input $input) : array
    {
        if ($this->API->GetConfig()) throw new UnknownActionException();
        
        $database = $this->API->GetDatabase();
        $database->importTemplate(ROOT."/core");        
        
        $apps = array_filter(scandir(ROOT."/apps"),function($e){ return !in_array($e,array('.','..')); });
        
        $config = Config::Create($database);
        foreach ($apps as $app) $config->EnableApp($app);
        
        $enable = $input->TryGetParam('enable', SafeParam::TYPE_BOOL);        
        $config->setEnabled($enable ?? !$this->API->GetInterface()->isPrivileged());
        
        return array('apps'=>array_filter($apps,function($e){ return $e !== 'server'; }));
    }
    
    /**
     * Prints the phpinfo() page
     * @throws AuthFailedException if not admin-level access
     */
    protected function PHPInfo(Input $input) : void
    {
        if (!$this->isAdmin) throw new AuthFailedException();

        $this->API->GetInterface()->RegisterOutputHandler(function(){
            $this->API->GetInterface()->DisableOutput(); phpinfo(); });
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
            'db' => $this->API->GetDatabase()->getInfo()
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
        else $dest = $input->TryGetParam('dest',SafeParam::TYPE_EMAIL);
        
        if ($dest) $dests = array(new EmailRecipient($dest));
        else $dests = $this->authenticator->GetAccount()->GetMailTo();        
        
        $subject = "Andromeda Email Test";
        $body = "This is a test email from Andromeda";
        
        if (($mailer = $input->TryGetParam('mailid', SafeParam::TYPE_RANDSTR)) !== null)
        {
            $mailer = Emailer::TryLoadByID($this->API->GetDatabase(), $mailer);
            if ($mailer === null) throw new UnknownMailerException();
            else $mailer->Activate();
        }
        else $mailer = $this->API->GetConfig()->GetMailer();
        
        try { $mailer->SendMail($subject, $body, $dests); }
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
        
        $this->API->GetConfig()->EnableApp($app);
        
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
     * @return array if admin, `{config:Config::GetClientObject(), database:Database::GetConfig()}` \
         if not admin, `Config::GetClientObject()`
     * @see Config::GetClientObject() 
     * @see Database::GetConfig()
     */
    protected function GetConfig(Input $input) : array
    {
        if (!$this->isAdmin) return $this->API->GetConfig()->GetClientObject();
        
        return array(
            'config' => $this->API->GetConfig()->GetClientObject(true),
            'database' => $this->API->GetDatabase()->getConfig()
        );
    }

    /**
     * Sets server config
     * @throws AuthFailedException if not an admin
     * @return array Config::GetClientObject()
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
     * @return array {[id:Emailer::GetClientObject()]}
     * @see Emailer::GetClientObject()
     */
    protected function GetMailers(Input $input) : array
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        return array_map(function($m){ return $m->GetClientObject(); }, 
            Emailer::LoadAll($this->API->GetDatabase()));
    }
    
    /**
     * Creates a new emailer config
     * @throws AuthFailedException if not an admin
     * @return array Emailer::GetClientObject()
     * @see Emailer::GetClientObject()
     */
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
    
    /**
     * Deletes a configured emailer
     * @throws AuthFailedException if not an admin 
     * @throws UnknownMailerException if given an invalid emailer
     */
    protected function DeleteMailer(Input $input) : void
    {
        if (!$this->isAdmin) throw new AuthFailedException();
        
        $mailid = $input->GetParam('mailid',SafeParam::TYPE_RANDSTR);
        $mailer = Emailer::TryLoadByID($this->API->GetDatabase(), $mailid);
        if ($mailer === null) throw new UnknownMailerException();
        
        $mailer->Delete();
    }
}

