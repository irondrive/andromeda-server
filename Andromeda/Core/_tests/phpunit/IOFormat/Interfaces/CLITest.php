<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat\Interfaces; require_once("init.php");

use Andromeda\Core\{Config, Utilities};
use Andromeda\Core\IOFormat\Exceptions\SafeParamInvalidException;
use Andromeda\Core\IOFormat\{/*phpstan*/Input, InputPath, InputStream, Output, OutputHandler};

class CLITest extends \PHPUnit\Framework\TestCase
{
    /** @var array<string> */
    private array $files = array();
    
    public function tearDown() : void
    {
        foreach ($this->files as $file) @unlink($file);
    }
    
    protected function getTmpFile(?string $data = null) : string
    {
        $file = tempnam(sys_get_temp_dir(), 'a2test');
        assert(is_string($file));
        if ($data !== null) file_put_contents($file,$data);
        return $this->files[] = $file;
    }
    
    public function testStatics() : void
    {
        $this->assertTrue(CLI::isPrivileged());
        $this->assertSame(CLI::OUTPUT_PLAIN, CLI::GetDefaultOutmode());
        
        $cli = new CLI();
        $this->assertFalse($cli->isDryRun());
        $this->assertNull($cli->GetDBConfigFile());
        $this->assertSame(Config::ERRLOG_ERRORS, $cli->GetDebugLevel());
        $this->assertSame(0, $cli->GetMetricsLevel());
    }
    
    /** @return resource */
    protected function getStream()
    {
        $stream = fopen("php://memory",'rb+'); 
        assert(is_resource($stream));
        return $stream;
    }
    
    /** @param array<string> $argv */
    protected function checkBadUsage(array $argv) : void
    {
        $stdin = $this->getStream();
        $caught = false; try { (new CLI())->LoadCLIInputs($argv,array(),$stdin); }
        catch (Exceptions\IncorrectCLIUsageException $e) { $caught = true; }
        $this->assertTrue($caught);
    }
    
    /** @param array<string> $argv */
    protected function checkBadParam(array $argv) : void
    {
        $stdin = $this->getStream();
        $caught = false; try { (new CLI())->LoadCLIInputs($argv,array(),$stdin); }
        catch (SafeParamInvalidException $e) { $caught = true; }
        $this->assertTrue($caught);
    }
    
    public function testUsage() : void
    {
        $this->checkBadUsage(array());
        $this->checkBadUsage(array(''));
        
        // invalid global flag
        $this->checkBadUsage(array('','--','app','action'));
        $this->checkBadUsage(array('','--arg','app','action'));
        
        // --debug requires a valid value
        $this->checkBadUsage(array('','--debug'));
        $this->checkBadParam(array('','--debug','test'));
        $this->checkBadUsage(array('','--debug','--dryrun','app','action'));
        
        // --metrics requires a valid value
        $this->checkBadUsage(array('','--metrics'));
        $this->checkBadParam(array('','--metrics','test'));
        $this->checkBadUsage(array('','--metrics','--dryrun','app','action'));
        
        // --outmode requires a valid value
        $this->checkBadUsage(array('','--outmode'));
        $this->checkBadParam(array('','--outmode','test'));
        $this->checkBadUsage(array('','--outmode','--dryrun','app','action'));
        
        // --dbconf requires an fspath value
        $this->checkBadUsage(array('','--dbconf'));
        $this->checkBadParam(array('','--dbconf','http://test'));
        $this->checkBadUsage(array('','--dbconf','--dryrun','app','action'));
        
        // dryrun expects no value, will interpret as app
        $this->checkBadUsage(array('','--dryrun','test','app','action'));
        $this->checkBadUsage(array('','--dryrun','test','--outmode','json','app','action'));
        
        // missing app/action
        $this->checkBadUsage(array('','app'));
        $this->checkBadUsage(array('','--dryrun'));
        $this->checkBadUsage(array('','--dryrun','app'));
        
        // bad/empty action param
        $this->checkBadUsage(array('','app','action','--'));
        $this->checkBadUsage(array('','app','action','test'));
        
        // --@ and --% require a value
        $this->checkBadUsage(array('','app','action','--test%'));
        $this->checkBadUsage(array('','app','action','--test%','--arg2'));
        $this->checkBadUsage(array('','app','action','--test%'));
        $this->checkBadUsage(array('','app','action','--test%','--arg2'));
    }
    
    protected function testSetDebug(string $str, int $val) : void
    {
        $cli = new CLI(); $stdin = $this->getStream();
        $config = $this->createMock(Config::class); $config->expects($this->once())->method('SetDebugLevel')->with($val,true);
        $cli->LoadCLIInputs(array('','--debug',$str,'app','action'), array(),$stdin);
        $this->assertSame($val, $cli->GetDebugLevel());
        $cli->AdjustConfig($config);
    }
    
    protected function testSetMetrics(string $str, int $val) : void
    {
        $cli = new CLI(); $stdin = $this->getStream();
        $config = $this->createMock(Config::class); $config->expects($this->once())->method('SetMetricsLevel')->with($val,true);
        $cli->LoadCLIInputs(array('','--metrics',$str,'app','action'), array(),$stdin);
        $this->assertSame($val, $cli->GetMetricsLevel());
        $cli->AdjustConfig($config);
    }
    
    protected function testSetOutmode(string $str, int $val) : void
    {
        $cli = new CLI(); $stdin = $this->getStream();
        $cli->LoadCLIInputs(array('','--outmode',$str,'app','action'), array(),$stdin);
        $this->assertSame($val, $cli->GetOutputMode());
    }
    
    public function testGlobalFlags() : void
    {
        foreach (Config::DEBUG_TYPES as $str=>$val)
            $this->testSetDebug($str,$val);
        
        foreach (Config::METRICS_TYPES as $str=>$val)
            $this->testSetMetrics($str,$val);

        foreach (CLI::OUTPUT_TYPES as $str=>$val)
            $this->testSetOutmode($str,$val);
        
        $cli = new CLI(); $stdin = $this->getStream();
        
        $cli->LoadCLIInputs(array('','--dbconf','test.php','--dryrun','app','action'), array(),$stdin);
        $this->assertSame('test.php',$cli->GetDBConfigFile());
        $this->assertTrue($cli->isDryRun());
    }

    public function testBasicInputs() : void
    {
        $cli = new CLI(); $stdin = $this->getStream();
        
        $app = Utilities::Random(8); $action = Utilities::Random(8);
        $inputs = $cli->LoadCLIInputs(array('',$app,$action), array(),$stdin);
        $this->assertCount(1, $inputs); $input = $inputs[0];
        $this->assertSame($app,$input->GetApp());
        $this->assertSame($action,$input->GetAction());
        
        $app = Utilities::Random(8); $action = Utilities::Random(8);
        $inputs = $cli->LoadCLIInputs(array('','--outmode','json',"--debug=sensitive",$app,$action), array(),$stdin);
        $this->assertSame(CLI::OUTPUT_JSON, $cli->GetOutputMode());
        $this->assertSame(Config::ERRLOG_SENSITIVE, $cli->GetDebugLevel());
        $this->assertCount(1, $inputs); $input = $inputs[0];
        $this->assertSame($app,$input->GetApp());
        $this->assertSame($action,$input->GetAction());
        
        $app = Utilities::Random(8); $action = Utilities::Random(8);
        $inputs = $cli->LoadCLIInputs(array('',$app,$action,'--myopt','5','--myopt2=6','--myflag'), array(),$stdin);
        $this->assertCount(1, $inputs); $input = $inputs[0]; $params = $input->GetParams();
        $this->assertSame($app,$input->GetApp());
        $this->assertSame($action,$input->GetAction());
        $this->assertSame(5, $params->GetParam('myopt')->GetInt());
        $this->assertSame(6, $params->GetParam('myopt2')->GetInt());
        $this->assertTrue($params->GetParam('myflag')->GetBool());
    }
    
    public function testEnvArgs() : void
    {
        $cli = new CLI(); $stdin = $this->getStream();
        
        $server = array('andromeda_key1'=>false,'andromeda_key2'=>$h='horse','testkey'=>55);
        $inputs = $cli->LoadCLIInputs(array('','app','action','--myopt','5'), $server,$stdin);
        $this->assertCount(1, $inputs); $input = $inputs[0]; $params = $input->GetParams();
        $this->assertSame(5, $params->GetParam('myopt')->GetInt());
        $this->assertFalse($params->GetParam('key1')->GetBool());
        $this->assertSame($h,$params->GetParam('key2')->GetRawString());
        $this->assertFalse($params->HasParam('testkey'));
    }
    
    public function testParamFile() : void
    {
        $cli = new CLI(); $stdin = $this->getStream();
        
        $this->checkBadUsage(array('','app','action','--@'));
        $this->checkBadUsage(array('','app','action','--test@'));
        $this->checkBadUsage(array('','app','action','--test@','--test2')); // want value
        
        $tmpfile1 = $this->getTmpFile($data1 = Utilities::Random(32));
        $tmpfile2 = $this->getTmpFile($data2 = Utilities::Random(32));
        $inputs = $cli->LoadCLIInputs(array('','app','action','--myparam1@',$tmpfile1,'--myparam2@',$tmpfile2), array(),$stdin);
        $this->assertCount(1, $inputs); $input = $inputs[0]; $params = $input->GetParams();
        $this->assertSame($data1, $params->GetParam('myparam1')->GetRawString());
        $this->assertSame($data2, $params->GetParam('myparam2')->GetRawString());
    }
    
    public function testParamStdin() : void
    {
        $cli = new CLI(); $stdin = $this->getStream();
        
        $this->checkBadUsage(array('','app','action','--!'));
        Utilities::CaptureOutput(function(){ $this->checkBadUsage(array('','app','action','--test!','test2')); }); // want no value
        
        $help = 'enter myparam1...'.PHP_EOL.'enter myparam2...'.PHP_EOL;
        $this->assertSame($help, Utilities::CaptureOutput(function()use($stdin,$cli){
            fwrite($stdin, ($data1 = Utilities::Random(32)).PHP_EOL);
            fwrite($stdin, ($data2 = Utilities::Random(32)).PHP_EOL); fseek($stdin, 0);
            $inputs = $cli->LoadCLIInputs(array('','app','action','--myparam1!','--myparam2!'), array(),$stdin);
            $this->assertCount(1, $inputs); $input = $inputs[0]; $params = $input->GetParams();
            $this->assertSame($data1, $params->GetParam('myparam1')->GetRawString());
            $this->assertSame($data2, $params->GetParam('myparam2')->GetRawString());
        }));
    }
     
    public function testFileFile() : void
    {
        $cli = new CLI(); $stdin = $this->getStream();
        
        $this->checkBadUsage(array('','app','action','--%'));
        $this->checkBadUsage(array('','app','action','--test%'));
        $this->checkBadUsage(array('','app','action','--test%','--test2')); // want value
        
        $tmpfile1 = $this->getTmpFile($data1 = Utilities::Random(32));
        $tmpfile2 = $this->getTmpFile($data2 = Utilities::Random(32)); $name2 = 'myfile'; // test renaming
        $inputs = $cli->LoadCLIInputs(array('','app','action','--myfile1%',$tmpfile1,'--myfile2%',$tmpfile2,$name2,'--arg3','5'), array(),$stdin);
        $this->assertCount(1, $inputs); $input = $inputs[0];
        $this->assertSame(5, $input->GetParams()->GetParam('arg3')->GetInt());
        
        $myfile1 = $input->GetFile('myfile1');
        assert($myfile1 instanceof InputPath);
        $this->assertSame($tmpfile1, $myfile1->GetPath());
        $this->assertSame(basename($tmpfile1), $myfile1->GetName());
        $this->assertSame($data1, $myfile1->GetData());
        
        $myfile2 = $input->GetFile('myfile2');
        assert($myfile2 instanceof InputPath);
        $this->assertSame($tmpfile2, $myfile2->GetPath());
        $this->assertSame($name2, $myfile2->GetName());
        $this->assertSame($data2, $myfile2->GetData());
    }
    
    public function testFileStdin() : void
    {
        $cli = new CLI(); $stdin = $this->getStream();
        
        $this->checkBadUsage(array('','app','action','---'));

        fwrite($stdin, $data = Utilities::Random(32)); fseek($stdin, 0);
        $inputs = $cli->LoadCLIInputs(array('','app','action','--myfile1-','--myfile2-','test.txt','--arg3','5'), array(),$stdin);
        $this->assertCount(1, $inputs); $input = $inputs[0]; 
        $this->assertSame(5, $input->GetParams()->GetParam('arg3')->GetInt());
        
        $myfile1 = $input->GetFile('myfile1');
        assert($myfile1 instanceof InputStream);
        $this->assertSame($stdin, $myfile1->GetHandle());
        $this->assertSame("data",$myfile1->GetName());

        $myfile2 = $input->GetFile('myfile2');
        assert($myfile2 instanceof InputStream);
        $this->assertSame($stdin, $myfile2->GetHandle());
        $this->assertSame("test.txt",$myfile2->GetName());
        
        // InputStream can only be read once
        $this->assertSame($data, $myfile1->GetData());
    }
    
    private const batchlines = array(
        "app1 action1 --my1 5 --my2",
        "app2 myact2 --arg3 test",
        "app3 myact3"
    );
    
    public function testBatchInput() : void
    {
        $cli = new CLI(); $stdin = $this->getStream();

        $cmdline = array('','--debug','sensitive','batch',...self::batchlines);
        $inputs = $cli->LoadCLIInputs($cmdline, array(),$stdin);
        
        $this->verifyBatch($inputs);
    }
    
    public function testBatchFile() : void
    {
        $cli = new CLI(); $stdin = $this->getStream();

        $fname = $this->getTmpFile(implode(PHP_EOL,self::batchlines));
        $inputs = $cli->LoadCLIInputs(array('','--debug','sensitive','batch@',$fname), array(),$stdin);

        $this->verifyBatch($inputs);
    }
    
    /** @param array<Input> $inputs */
    protected function verifyBatch(array $inputs) : void
    {
        $this->assertCount(count(self::batchlines),$inputs);
        
        $i0 = $inputs[0]; $p0 = $i0->GetParams();
        $this->assertSame("app1",$i0->GetApp());
        $this->assertSame("action1",$i0->GetAction());
        $this->assertSame(5,$p0->GetParam('my1')->GetInt());
        $this->assertTrue($p0->GetParam('my2')->GetBool());
        
        $i1 = $inputs[1]; $p1 = $i1->GetParams();
        $this->assertSame("app2",$i1->GetApp());
        $this->assertSame("myact2",$i1->GetAction());
        $this->assertSame("test",$p1->GetParam('arg3')->GetAlphanum());
        
        $i2 = $inputs[2]; $p2 = $i2->GetParams();
        $this->assertSame("app3",$i2->GetApp());
        $this->assertSame("myact3",$i2->GetAction());
    }

    /** @param ?array<mixed> $arrval */
    protected function testOutput(CLI $iface, int $mode, ?string $strval, ?array $arrval, string $want) : void
    {
        $iface->SetOutputMode($mode);
        $output = $this->createStub(Output::class);
        if ($strval !== null) $output->method('GetAsString')->willReturn($strval);
        if ($arrval !== null) $output->method('GetAsArray')->willReturn($arrval);
        
        $output = Utilities::CaptureOutput(function()use($iface,$output){
            $iface->FinalOutput($output, false); });
        $this->assertSame($want,$output);
    }

    public function testFinalOutput() : void
    {
        $iface = new CLI();
        
        $this->testOutput($iface, 0, 'mystring', null, '');
        $this->testOutput($iface, CLI::OUTPUT_PLAIN, $str='mystring', null, $str.PHP_EOL);
        $this->testOutput($iface, CLI::OUTPUT_PLAIN, null, $arr=[1,2,3,4], print_r($arr,true).PHP_EOL); // printr fallback
        $this->testOutput($iface, CLI::OUTPUT_PRINTR, null, $arr=[1,2,3,4], print_r($arr,true).PHP_EOL);
        $this->testOutput($iface, CLI::OUTPUT_JSON, $str='[1,2,3]', $arr=[1,2,3], $str.PHP_EOL);
        
        // now test the binary JSON multi output version
        $iface->RegisterOutputHandler(new OutputHandler(function(){ return 0; },function(Output $output){ }));
        $this->testOutput($iface, CLI::OUTPUT_JSON, null, $arr, CLI::formatSize(strlen($str)).$str);
    }
}
