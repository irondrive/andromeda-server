<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; require_once("init.php");

use Andromeda\Core\Utilities;

require_once(ROOT."/Core/IOFormat/Exceptions.php");

class PretendInterface extends IOInterface
{
    /** @var non-empty-array<Input> */
    private array $myinputs;
    private int $numcalls = 0;
    /** @param non-empty-array<Input> $inputs */
    public function __construct(array $inputs) { $this->myinputs = $inputs; parent::__construct(); }
    public function getInputCalls() : int { return $this->numcalls; }
    
    public static function isPrivileged(): bool     { return true; }
    protected function subLoadInputs(): array       { $this->numcalls++; return $this->myinputs; }
    public static function GetDefaultOutmode(): int { return 75; }
    public static function isApplicable(): bool     { return true; }
    public function getUserAgent(): string          { return ""; }
    public function getAddress(): string            { return ""; }
    public function FinalOutput(Output $output): void { }
}

class IOInterfaceTest extends \PHPUnit\Framework\TestCase
{
    public function testLoadInputs() : void
    {
        $inputs = array($this->createMock(Input::class));
        $iface = new PretendInterface($inputs);
        
        $this->assertSame($inputs, $iface->LoadInputs());
        $this->assertSame($inputs, $iface->LoadInputs());
        $this->assertSame(1, $iface->getInputCalls());
        $this->assertSame(75, $iface->GetOutputMode());
    }
    
    public function testDisallowBatch() : void
    {
        $inputs = array(
            $this->createMock(Input::class), 
            $this->createMock(Input::class));
        $iface = new PretendInterface($inputs);
        
        $this->expectException(BatchNotAllowedException::class);
        $iface->DisallowBatch();
    }
    
    public function testOutputMode() : void
    {
        $inputs = array($this->createMock(Input::class));
        $iface = new PretendInterface($inputs);
        
        foreach (IOInterface::OUTPUT_TYPES as $mode)
            $this->assertSame($mode,$iface->SetOutputMode($mode)->GetOutputMode());

        $iface->SetOutputMode(IOInterface::OUTPUT_PLAIN);
        $iface->RegisterOutputHandler(new OutputHandler(function(){ return null; },function(Output $output){ }));
        $this->assertSame(IOInterface::OUTPUT_PLAIN, $iface->GetOutputMode()); // null bytes does not affect outmode
        $this->assertFalse($iface->isMultiOutput());
        
        $iface->RegisterOutputHandler(new OutputHandler(function(){ return 0; },function(Output $output){ }));
        $this->assertSame(0, $iface->GetOutputMode()); // 1st user func sets null
        $this->assertFalse($iface->isMultiOutput());
        
        $iface->SetOutputMode(IOInterface::OUTPUT_PLAIN);
        $this->assertTrue($iface->isMultiOutput()); // 1 user func + outmode = multi output
        
        $iface->RegisterOutputHandler(new OutputHandler(function(){ return 0; },function(Output $output){ }));
        $this->assertSame(IOInterface::OUTPUT_JSON, $iface->GetOutputMode()); // 2nd user func sets json
        $this->assertTrue($iface->isMultiOutput());
        
        $this->expectException(MultiOutputJSONException::class);
        $iface->SetOutputMode(IOInterface::OUTPUT_PLAIN);
    }
    
    public function testFormatSize() : void
    {
        $this->assertSame("0000000000000000",bin2hex(IOInterface::formatSize(0)));
        $this->assertSame("0000000000000001",bin2hex(IOInterface::formatSize(1)));
        $this->assertSame("00000000016be398",bin2hex(IOInterface::formatSize(23847832)));
        $this->assertSame("211873b8b585612f",bin2hex(IOInterface::formatSize(2384783239849271599)));
    }

    protected function testUserOutput(int $numfuncs, ?int $nullIdx, ?int $zeroIdx, bool $multi) : void
    {
        $iface = new PretendInterface(array($this->createMock(Input::class)));
        $output = $this->createStub(Output::class);
        
        $expect = ""; for ($i = 0; $i < $numfuncs; $i++)
        {
            if ($nullIdx === $i) $len = null;
            else if ($zeroIdx === $i) $len = 0;
            else $len = random_int(0, 16);
            
            $outfunc = $this->createMock(OutputHandler::class);
            $outfunc->method('GetBytes')->willReturn($len);
            
            if ($len !== null)
            {
                if ($multi) $expect .= IOInterface::formatSize($len);
                
                $expect .= ($outdata = Utilities::Random($len));
                $outfunc->method('DoOutput')->willReturnCallback(
                    function()use($outdata){ echo $outdata; });
            }
            
            $outfunc->expects($this->once())->method('DoOutput')->with($output);
            $iface->RegisterOutputHandler($outfunc);
        }
        
        $this->assertSame($expect, Utilities::CaptureOutput(function()use($iface,$output,$numfuncs){
            $this->assertSame($numfuncs ? true : false, $iface->UserOutput($output));
        }));
    }
    
    public function testUserOutputs() : void
    {
        $this->testUserOutput(0, null, null, false);
        $this->testUserOutput(1, null, null, false);
        $this->testUserOutput(1, 0, null, false);
        $this->testUserOutput(1, null, 0, false);
        
        $this->testUserOutput(2, null, null, true);
        $this->testUserOutput(2, 0, null, false);
        $this->testUserOutput(2, null, 0, true);
        $this->testUserOutput(2, 1, null, false);
        $this->testUserOutput(2, null, 1, true);
        $this->testUserOutput(2, 0, 1, false);
        $this->testUserOutput(2, 1, 0, false);
        
        $this->testUserOutput(3, null, null, true);
        $this->testUserOutput(3, 1, null, true);
        $this->testUserOutput(3, null, 1, true);
        $this->testUserOutput(3, 2, null, true);
        $this->testUserOutput(3, null, 2, true);
        $this->testUserOutput(3, 0, 2, true);
        $this->testUserOutput(3, 2, 0, true);
        
        $this->testUserOutput(10, 0, 3, true);
        $this->testUserOutput(50, 7, 3, true);
    }
}
