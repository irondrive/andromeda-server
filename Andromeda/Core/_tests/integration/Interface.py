
from json.decoder import JSONDecodeError as CliJsonError
from simplejson.errors import JSONDecodeError as HttpJsonError
import os, requests, subprocess, json, TestUtils, InterfaceTests

class Interface():
    def __str__(self):
        return self.__class__.__name__
    apiCount = 0


class HTTP(Interface):
        
    def __init__(self, main, config, verbose=False):
        self.main = main
        self.url = config
        self.verbose = verbose
        self.isPriv = False

    def run(self, app, action, params={}, files={}, isJson=True):
        self.apiCount += 1
        if self.verbose:
            print('HTTP<-',app,action,params)

        for key,val in params.items():
            if val is None: params[key] = ""

        urlparams = {'_app':app,'_act':action}
        resp = requests.post(self.url,params=urlparams,data=params,files=files)

        if isJson:
            TestUtils.assertEquals('application/json', resp.headers.get('content-type'))
            try: retval = resp.json()
            except HttpJsonError: 
                print("BAD JSON", resp.content.decode('utf-8')); raise
            if self.verbose: 
                print("HTTP->", json.dumps(retval, indent=4))
        else: 
            retval = resp.content
            if self.verbose:
                print("HTTP-> got", len(retval), "bytes")

        return retval        

    def runTests(self):
        return InterfaceTests.HTTPTests(self).runTests()


class CLI(Interface):    

    def __init__(self, main, config, verbose=False):
        self.main = main
        self.path = config
        self.verbose = verbose
        self.isPriv = True

        if not os.path.exists(self.path+'/index.php'):
            raise Exception("cannot find index.php")

    def run(self, app, action, params={}, files={}, isJson=True):
        flags = ['--debug','sensitive']
        if isJson: flags.append('--json')
        return self.cliRun(app, action, params, files, flags, isJson)

    def cliRun(self, app, action, params={}, files={}, flags=[], isJson=True, stdin=None):
        self.apiCount += 1
        if self.verbose:
            print('API<-',app,action,params)

        command = ["php", self.path+'/index.php'] + flags + [app, action]

        for key, value in params.items():
            command.append("--"+key)
            if value is not None:
                command.append(str(value))

        if self.verbose: print("(CLI)"," ".join(command))

        if stdin is not None: stdin = bytes(stdin,'utf-8')

        retval = subprocess.run(command, input=stdin, capture_output=True).stdout

        if isJson: 
            retval = retval.decode('utf-8')
            try: retval = json.loads(retval)
            except CliJsonError: 
                print("BAD JSON", retval); raise
            if self.verbose: 
                print("API->", json.dumps(retval, indent=4))
        elif self.verbose:
            print("API-> got", len(retval), "bytes")

        return retval
        
    def runTests(self):
        return InterfaceTests.CLITests(self).runTests()