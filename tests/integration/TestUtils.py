
def assertOk(result):
    assert('ok' in result)
    assert(result['ok'])
    assert('code' in result)
    assert(result['code'] == 200)
    assert('appdata' in result)
    return result['appdata']

def assertError(result, code, message):
    assert('ok' in result)
    assert(not result['ok'])
    assert('code' in result)
    assert(result['code'] == code)
    assert('message' in result)
    assert(result['message'] == message)
    return result['message']
