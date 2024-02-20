
from BaseTest import BaseAppTest
from TestUtils import *

# TODO accounts need to enable/disable the accounts app and test both ways?
    
class AppTests(BaseAppTest):
    def __str__(self):
        return "CORE"

    def requiresInstall(self) -> bool:
        return True
    
    def getInstallParams(self) -> dict:
        return { }

    def installSelf(self):
        return self.util.assertOk(self.interface.run(app='core',action='install',install=True))

    def checkInstallRetval(self, retval):
        self.util.assertSame(None, retval)

    canAdmin:bool = False

    def beforeTests(self): # init
        apps = self.util.assertOk(self.interface.run(app='core',action='getconfig'))['apps']
        self.canAdmin = ('accounts' in apps) or self.interface.isPriv
    
    def addAdmin(self, params:dict):
        """ Adds the required parameters to be admin """
        # TODO accounts see accounts test, do login here
        return params
    
    #################################################
        
    def testDatabase(self):
        """ Runs the testutil database unit test command """
        self.util.assertOk(self.interface.run(app='testutil',action='testdb'))

    def testUsage(self):
        """ Tests the core usage command and appname filter """
        rval1 = self.util.assertOk(self.interface.run(app='core',action='usage')) # core + testutil
        self.util.assertInstance(rval1, list)
        self.util.assertNotEmpty(rval1)
        rval2 = self.util.assertOk(self.interface.run(app='core',action='usage',params={'appname':'core'}))
        self.util.assertAny(len(rval1) > len(rval2))
        rval3 = self.util.assertOk(self.interface.run(app='core',action='usage',params={'appname':'none'}))
        self.util.assertEmpty(rval3)

    def testListApps(self):
        """ Tests the core scanapps command """
        if not self.canAdmin: 
            if self.verbose >= 1: print("cannot admin, skipping")
            return False
        rval = self.util.assertOk(self.interface.run(app='core',action='scanapps',params=self.addAdmin({})))
        self.util.assertIn('core', rval)
        self.util.assertIn('testutil', rval)
