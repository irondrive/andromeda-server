
import tempfile, json, os

from TestUtils import *

class AJAXTests(BaseTest):
    pass

class CLITests(BaseTest):

    def fullCliRun(self, app='none', action='none', params={}, files={}, stdin=None, flags=None, 
        isJson=True, format="json", debug=None, metrics=None, dbconf=None, dryrun=False):
        
        if flags is None: flags = [] # don't want to modify default
        if format is not None: flags.append('--'+format)
        if debug is not None: flags += ['--debug',debug]
        if metrics is not None: flags += ['--metrics',metrics]
        if dbconf is not None: flags += ['--dbconf',dbconf]
        if dryrun: flags.append('--dryrun')

        return self.interface.cliRun(app=app, action=action, params=params, files=files, 
            flags=flags, isJson=isJson, stdin=stdin)
    
    def testDebugFlag(self):
        rval = self.fullCliRun(debug="none")
        assertEquals(rval['code'], 400)
        assertNotIn('debug', rval)

        rval = self.fullCliRun(debug="basic")
        assertEquals(rval['code'], 400)
        assertNotIn('debug', rval)

        rval = self.fullCliRun(debug="details")
        assertEquals(rval['code'], 400)
        assertIn('debug', rval)

    def testFormatFlag(self):
        rval = self.fullCliRun()
        assertInstance(rval, object)
        assert(not rval['ok'])

        rval = self.fullCliRun(isJson=False,format=None).decode('utf-8')
        assertEquals(rval.strip(),"UNKNOWN_APP")

        rval = self.fullCliRun(isJson=False,format="printr").decode('utf-8')
        assert(rval.startswith("Array")), rval

    def testMetricsFlag(self):
        assertNotIn('metrics', self.fullCliRun(metrics="none"))
        assertIn('metrics', self.fullCliRun(metrics="basic"))
        assertIn('metrics', self.fullCliRun(metrics="extended"))

    def testDbconfFlag(self):
        rval = self.fullCliRun(dbconf="/nonexistent")
        assertError(rval, 503, "DATABASE_CONFIG_MISSING")

    def testVersionCommand(self):
        rval = self.interface.cliRun(app='',action='',
            flags=['version'],isJson=False).decode('utf-8')
        assert(rval.startswith('Andromeda')), rval

    def testDryrunFlag(self):
        if not 'test' in self.main.servApps: return False
        assert(not assertOk(self.fullCliRun(app='testutil',action='check-dryrun')))
        assert(assertOk(self.fullCliRun(app='testutil',action='check-dryrun',dryrun=True)))

    def testFileInput(self):
        # tests only the --file@ specific to CLI
        with tempfile.NamedTemporaryFile() as tmp:
            val = "myvalue!"
            with open(tmp.name,'w') as tmpfile:
                tmpfile.write(val)
            rval = assertOk(self.fullCliRun(
                app='testutil', action='getinput',
                params={'myfile@': tmp.name}))
            assertEquals(val, rval['params']['myfile'])

    def testStdinInput(self):
        key = "mystdin"
        val = "myvalue"

        rval = self.fullCliRun(
            app='testutil', action='getinput', isJson=False,
            params={key+'!':None}, stdin=val).decode('utf-8')
        
        expect = "enter {}...".format(key)+os.linesep
        assertEquals(expect, rval[0:len(expect)])
        rval = assertOk(json.loads(rval[len(expect):]))
        assertEquals(val, rval['params'][key])
