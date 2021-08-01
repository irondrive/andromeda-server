
import tempfile

from TestUtils import *

class CLITests(BaseTest):

    def fullCliRun(self, app='none', action='none', params={}, files={},
        format="json", debug=None, metrics=None, dbconf=None, dryrun=False, flags=None):
        
        if flags is None: flags = [] # don't want to modify default
        if format is not None: flags.append('--'+format)
        if debug is not None: flags += ['--debug',str(debug)]
        if metrics is not None: flags += ['--metrics',str(metrics)]
        if dbconf is not None: flags += ['--dbconf',str(dbconf)]
        if dryrun: flags.append('--dryrun')

        return self.interface.cliRun(app, action, params, files, flags)
    
    def testDebugFlag(self):
        rval = self.fullCliRun(debug=0)
        assertEquals(rval['code'], 400)
        assertNotIn('debug', rval)

        rval = self.fullCliRun(debug=2)
        assertEquals(rval['code'], 400)
        assertIn('debug', rval)

    def testFormatFlag(self):
        rval = self.fullCliRun()
        assertInstance(rval, object)
        assert(not rval['ok'])

        rval = self.fullCliRun(format=None).decode('utf-8')
        assertEquals(rval.strip(),"UNKNOWN_APP")

        rval = self.fullCliRun(format="printr").decode('utf-8')
        assert(rval.startswith("Array")), rval

    def testMetricsFlag(self):
        assertNotIn('metrics', self.fullCliRun(metrics=0))
        assertIn('metrics', self.fullCliRun(metrics=1))

    def testDbconfFlag(self):
        rval = self.fullCliRun(debug=1, dbconf="/nonexistent")
        assertError(rval, 500, "SERVER_ERROR")
        assertEquals(rval['debug']['message'], 'DATABASE_CONFIG_MISSING')

    def testVersionCommand(self):
        rval = self.interface.cliRun('','',{},{},['version']).decode('utf-8')
        assert(rval.startswith('Andromeda')), rval

    def testDryrunFlag(self):
        if not 'test' in self.main.servApps: return False
        assert(not assertOk(self.fullCliRun(app='test',action='check-dryrun')))
        assert(assertOk(self.fullCliRun(app='test',action='check-dryrun',dryrun=True)))

    def testFileInput(self):
        # tests only the --file@ specific to CLI
        with tempfile.NamedTemporaryFile() as tmp:
            val = "myvalue!"
            with open(tmp.name,'w') as tmpfile:
                tmpfile.write(val)
            rval = assertOk(self.fullCliRun(
                app='test', action='getinput',
                params={'myfile@': tmp.name}))
            assertEquals(val, rval['myfile'])
