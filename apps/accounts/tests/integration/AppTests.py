
import string

from BaseAppTests import *
from TestUtils import *

class AppTests(BaseAppTests):
    def __str__(self):
        return "ACCOUNTS"

    def __init__(self, main, interface):
        super().__init__(main, interface)
        self.username = ''.join(main.random.choice(string.ascii_letters) for _ in range(8))
        self.password = ''.join(main.random.choice(string.printable) for _ in range(16))

    def install(self):
        self.account = assertOk(self.interface.run('accounts','install',
            {'username':self.username,'password':self.password}))

    def runTests(self):
        pass
