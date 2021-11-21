
from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "FILES"

    def getInstallParams(self):
        assertIn('accounts', self.main.appMap)
        return { }

    def install(self):
        assertIn('accounts', self.main.appMap)
        assertError(self.interface.run(app='files',action='getconfig'),500,'APP_INSTALL_REQUIRED: files')
        assertOk(self.interface.run('files','install'))

    def getAdmin(self):
        return self.main.appMap['accounts'].admin
