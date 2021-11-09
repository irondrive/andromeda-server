
from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "CORE"

    def install(self):
        pass # already installed by main

    def asAdmin(self, params:dict):
        if 'accounts' in self.main.appMap:
            return self.main.appMap['accounts'].asAdmin(params)
        return params

    def testUsage(self):

        rval = assertOk(self.interface.run(app='core',action='usage'))

        assertInstance(rval, list)
        assertNotEmpty(rval)

        rval2 = assertOk(self.interface.run(app='core',action='usage',params={'appname':'core'}))

        assertInstance(rval2, list)
        assertNotEmpty(rval2)
        if len(self.main.servApps) > 1:
            assert(len(rval) > len(rval2))
    
    def testListApps(self):
        rval = assertOk(self.interface.run(app='core',action='listapps',params=self.asAdmin({})))
        assertEquals(set(rval), set(self.main.servApps))

