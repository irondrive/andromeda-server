
from TestUtils import *

class CLITests(BaseTest):

    # TODO test batching (testApp?), --dryrun

    def basicInvalid(self, format=None, debug=0, metrics=0, dbconf=None):
        flags = []
        if format is not None: flags.append('--'+format)
        if debug is not None: flags += ['--debug',str(debug)]
        if metrics is not None: flags += ['--metrics',str(metrics)]
        if dbconf is not None: flags += ['--dbconf',str(dbconf)]
        return self.interface.cliRun('none','none',{},{},flags)
    
    def testDebugFlag(self):
        rval = self.basicInvalid(format="json")
        assertEquals(rval['code'], 400)
        assertNotIn('debug', rval)

        rval = self.basicInvalid(format="json", debug=2)
        assertEquals(rval['code'], 400)
        assertIn('debug', rval)

    def testFormatFlag(self):
        rval = self.basicInvalid(debug=0)
        assertInstance(rval, str)
        assertEquals(rval.strip(),"UNKNOWN_APP")

        rval = self.basicInvalid(format="json")
        assertInstance(rval, object)
        assert(not rval['ok'])

        rval = self.basicInvalid(format="printr")
        assertInstance(rval, str)
        assert(rval.startswith("Array")), rval

    def testMetricsFlag(self):
        assertNotIn('metrics', self.basicInvalid(format="json"))
        assertIn('metrics', self.basicInvalid(format="json", metrics=1))

    def testDbconfFlag(self):
        rval = self.basicInvalid(format="json", debug=1, dbconf="/nonexistent")
        assertError(rval, 500, "SERVER_ERROR")
        assertEquals(rval['debug']['message'], 'DATABASE_CONFIG_MISSING')

    def testVersionCommand(self):
        rval = self.interface.cliRun('','',{},{},['version'])
        assertInstance(rval, str)
        assert(rval.startswith('Andromeda')), rval