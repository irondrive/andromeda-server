
from TestUtils import *

class AppTests(BaseTest):
    def __str__(self):
        return "FILES"

    def install(self):
        assertOk(self.interface.run('files','install'))
        assertIn('accounts', self.main.appMap)

    def getAdmin(self):
        return self.main.appMap['accounts'].admin
