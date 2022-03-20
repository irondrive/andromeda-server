
from json.decoder import JSONDecodeError as CliJsonError
from simplejson.errors import JSONDecodeError as AjaxJsonError
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
        self.isPriv = False

    def run(self, app, action, params={}, files={}, isJson=True):
        super().run()
        if self.verbose:
            print('API <-',app,action,params)

        for key,val in params.items():
            if val is None: params[key] = ""

        urlparams = {'app':app,'action':action}
        resp = requests.post(self.url,params=urlparams,data=params,files=files)
        TestUtils.assertEquals(isJson, (resp.headers.get('content-type') == 'application/json'))

        if isJson:
            try: retval = resp.json()
            except AjaxJsonError: 
                print(resp.content.decode('utf-8')); raise
        else: retval = resp.content

        if self.verbose: 
            print("\tAPI ->", json.dumps(retval, indent=4))

        return retval        

    def runTests(self):
        InterfaceTests.AJAXTests(self).runTests()


class CLI(Interface):    

    def __init__(self, main, path, config, verbose=False):
        self.main = main
        self.path = path
        self.config = config
        self.verbose = verbose
        self.isPriv = True

    def run(self, app, action, params={}, files={}, isJson=True):
        flags = ['--debug','sensitive']
        if isJson: flags.append('--json')
        return self.cliRun(app, action, params, files, flags, isJson)

    def cliRun(self, app, action, params={}, files={}, flags=[], isJson=True, stdin=None):
        super().run()
        if self.verbose:
            print('API <-',app,action,params)

        command = ["php", self.path+'/index.php'] + flags + [app, action]

        for key, value in params.items():
            command.append("--"+key)
            if value is not None:
                command.append(str(value))

        if self.verbose: print("\t(CLI)"," ".join(command))

        if stdin is not None: stdin = bytes(stdin,'utf-8')

        retval = subprocess.run(command, input=stdin, capture_output=True).stdout

        if isJson: 
            retval = retval.decode('utf-8')
            try: retval = json.loads(retval)
            except CliJsonError: 
                print(retval); raise

        if self.verbose: 
            print("\tAPI ->", json.dumps(retval, indent=4))
            
        return retval
        
    def runTests(self):
        InterfaceTests.CLITests(self).runTests()