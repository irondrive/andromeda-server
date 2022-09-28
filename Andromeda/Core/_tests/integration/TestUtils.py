
import inspect, re

def assertSame(left, right): # TODO use me
    assertEquals(type(left), type(right))
    assertEquals(left, right)

def assertEquals(left, right):
    assert (left == right), (left, right)

def assertIn(key, arr):
    assert (key in arr), (key, arr)

def assertNotIn(key, arr):
    assert (not key in arr), (key, arr)

def assertCount(size, arr):
    assert (len(arr) == size), (size, len(arr))

def assertNotEmpty(arr):
    assert(len(arr) > 0), len(arr)

def assertInstance(obj, want):
    assert isinstance(obj, want), (want, type(obj))

def assertOk(result):
    assertIn('ok', result)
    assert(result['ok'] is True), result
    assertIn('code', result)
    assertEquals(result['code'], 200)
    assertIn('appdata', result)
    return result['appdata']

def assertError(result, code, message):
    assertIn('ok', result)
    assert(result['ok'] is False), result
    assertIn('code', result)
    assertEquals(result['code'], code)
    assertIn('message', result)
    assertEquals(result['message'], message)
    return result['message']

class BaseTest():

    main = None
    interface = None

    def __init__(self, interface):
        self.interface = interface
        self.main = interface.main

    def runTests(self):
        testCount = 0

        attrs = (getattr(self, name) for name in dir(self))
        funcs = list(filter(lambda attr: 
            inspect.ismethod(attr) and attr.__name__.startswith("test"), attrs))
        funcs = list(filter(lambda func: self.main.testMatch is None or
            re.search(self.main.testMatch, func.__name__) is not None, funcs))
        self.main.random.shuffle(funcs)

        for func in funcs:
            if self.main.verbose: 
                print('RUN TEST: ',func.__name__+'()')
            rval = func()
            if rval is False: # return False is skipped test
                if self.main.verbose:
                    print('SKIPPED',func.__name__+'()')
                else: print('S',end='')
            else:
                testCount += 1
                if self.main.verbose:
                    print('COMPLETE',func.__name__+'()')
                else: print('.',end='')
        if not self.main.verbose: print()
        return testCount

class BaseAppTest(BaseTest):

    config = None
    def __init__(self, interface, config):
        super().__init__(interface)
        self.config = config