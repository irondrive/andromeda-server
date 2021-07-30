
from BaseAppTests import *
from TestUtils import *

class AppTests(BaseAppTests):
    def __str__(self):
        return "SERVER"

    def install(self):
        pass # already installed by main

    def runTests(self):
        admin = None
        if 'accounts' in self.main.appMap:        
            self.admin = self.main.appMap['accounts'].admin
