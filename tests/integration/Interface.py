
import requests, subprocess, json, TestUtils, InterfaceTests

class Interface():
    def __str__(self):
        return self.__class__.__name__

    count = 0
    def run(self):
        self.count += 1


class AJAX(Interface):
        
    def __init__(self, main, config, verbose=False):
        self.main = main
        self.url = config
        self.verbose = verbose

    def run(self, app, action, params={}, files={}, isJson=True):
        super().run()
        if self.verbose:
            print('API <-',app,action,params)

        urlparams = {'app':app,'action':action}
        resp = requests.post(self.url,params=urlparams,data=params,files=files)
        TestUtils.assertEquals(isJson, (resp.headers.get('content-type') == 'application/json'))

        retval = resp.json() if isJson else resp.content
        if self.verbose: print("\tAPI ->", retval)
        return retval        

    def runTests(self):
        InterfaceTests.AJAXTests(self).runTests()


class CLI(Interface):    

    def __init__(self, main, path, config, verbose=False):
        self.main = main
        self.path = path
        self.config = config
        self.verbose = verbose

    def run(self, app, action, params={}, files={}, isJson=True):
        flags = ['--debug', '3']
        if isJson: flags.append('--json')
        return self.cliRun(app, action, params, files, flags)

    def cliRun(self, app, action, params={}, files={}, flags=[]):
        super().run()
        if self.verbose:
            print('API <-',app,action,params)

        command = ["php", self.path+'/index.php'] + flags + [app, action]

        for key, value in params.items():
            command += ["--"+key, str(value)]

        # TODO handling files - input names or handles or what?

        if self.verbose: print("\t(CLI)"," ".join(command))

        retval = subprocess.run(command, capture_output=True).stdout

        if '--json' in flags: 
            retval = json.loads(retval.decode('utf-8'))
        if self.verbose: print("\tAPI ->", retval)
        return retval
        
    def runTests(self):
        InterfaceTests.CLITests(self).runTests()