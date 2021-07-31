
import inspect

def assertEquals(left, right):
    assert (left == right), (left, right)

def assertIn(key, arr):
    assert (key in arr), (key, arr)

def assertNotIn(key, arr):
    assert (not key in arr), (key, arr)

def assertInstance(obj, want):
    assert isinstance(obj, want), (want, type(obj))

def assertOk(result):
    assertIn('ok', result)
    assert(result['ok'])
    assertIn('code', result)
    assertEquals(result['code'], 200)
    assertIn('appdata', result)
    return result['appdata']

def assertError(result, code, message):
    assertIn('ok', result)
    assert(not result['ok'])
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
        for attr in (getattr(self, name) for name in dir(self)):
            if inspect.ismethod(attr) and attr.__name__.startswith("test"):
                attr() # run the test* method
                if not self.main.verbose: print('.',end='')
        if not self.main.verbose: print()
