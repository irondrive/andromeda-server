
import requests, subprocess, json

class AJAX():
    def __str__(self):
        return __class__.__name__
    def __init__(self, config, verbose=False):
        self.url = config
        self.verbose = verbose

    def run(self, app, action, params={}, files={}):
        if self.verbose:
            print('running',app,action,params)

        urlparams = {'app':app,'action':action}
        resp = requests.post(self.url,params=urlparams,data=params,files=files)

        isJson = resp.headers.get('content-type') == 'application/json'
        retval = resp.json() if isJson else resp.content
        if self.verbose: print("\t", retval)
        return retval        

class CLI():
    def __str__(self):
        return __class__.__name__
    def __init__(self, path, config, verbose=False):
        self.path = path
        self.config = config
        self.verbose = verbose

    def run(self, app, action, params={}, files={}):
        if self.verbose:
            print('running',app,action,params)

        command = ["php", self.path+'/index.php']

        # TODO not always the case!
        opts = "--json --debug 2"
        for opt in opts.split(' '):
            command.append(opt)

        command.append(app)
        command.append(action)

        for key, value in params.items():
            command.append("--"+key)
            command.append(str(value))

        # TODO handling files

        if self.verbose: print("\t"," ".join(command))

        process = subprocess.run(command, capture_output=True)
        stdout = process.stdout.decode('utf-8')        
        jsonout = json.loads(stdout)

        if self.verbose: print("\t",jsonout)
        return jsonout
        