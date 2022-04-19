
from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "CORE"

    def getInstallParams(self):
        return { }

    def install(self):
        pass # already installed by main

    def canAdmin(self):
        return self.interface.isPriv or 'accounts' in self.main.appMap

    def addAdmin(self, params:dict):
        if not self.interface.isPriv and 'accounts' in self.main.appMap:
            return self.main.appMap['accounts'].asAdmin(params)
        return params

    #################################################

    def testUsage(self):
        rval = assertOk(self.interface.run(app='core',action='usage'))
        assertInstance(rval, list)
        assertNotEmpty(rval)

    def testUsageAppname(self):
        if len(self.main.appList) <= 1: 
            return False
        rval1 = assertOk(self.interface.run(app='core',action='usage'))
        rval2 = assertOk(self.interface.run(app='core',action='usage',params={'appname':'core'}))
        assertInstance(rval2, list)
        assertNotEmpty(rval2)
        assert(len(rval1) > len(rval2))

    def testListApps(self):
        if not self.canAdmin(): return False
        rval = assertOk(self.interface.run(app='core',action='scanapps',params=self.addAdmin({})))
        assertEquals(set(rval), set(self.main.appList))

    # TODO need to enable/disable the accounts app and test both ways?
