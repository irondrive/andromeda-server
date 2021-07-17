
import requests, subprocess, json

class AJAX():
    def __str__(self):
        return __class__.__name__
    def __init__(self, path, verbose=False):
        self.path = path
        self.verbose = verbose

    def run(self, app, action, params={}, files={}):
        if self.verbose:
            print('running',app,action,params)
        # TODO implement AJAX format
        

class CLI():
    def __str__(self):
        return __class__.__name__
    def __init__(self, path, verbose=False):
        self.path = path
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
            command.append("--{}".format(key))
            command.append(value)

        if self.verbose: print("\t"," ".join(command))

        process = subprocess.run(command, capture_output=True)
        stdout = process.stdout.decode('utf-8')        
        jsonout = json.loads(stdout)

        if self.verbose: print("\t",jsonout)
        return jsonout
        