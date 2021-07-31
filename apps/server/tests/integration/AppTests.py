
from TestUtils import *

class AppTests(BaseTest):
    def __str__(self):
        return "SERVER"

    def install(self):
        pass # already installed by main

    def getAdmin(self):
        if 'accounts' in self.main.appMap:        
            return self.main.appMap['accounts'].admin
        else: return None        
