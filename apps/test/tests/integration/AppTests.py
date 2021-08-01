
import struct, json

from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "TEST"

    def install(self):
        pass # no install routine

    def runBinOutput(self, data:str, times:int):
        return self.interface.run(
            app='test',action='binoutput',isJson=False,
            params={'data':data, 'times':times})

    def parseMultiBin(self, data):
        offset = 0; retval = []
        while offset < len(data):
            size = struct.unpack(">Q", 
                data[offset:offset+8])[0]; offset += 8
            piece = data[offset:offset+size]; offset += size
            assertEquals(size, len(piece))
            retval.append(piece.decode('utf-8'))
        return retval

    def tryMultiBin(self, data:str, times:int):
        retval = self.parseMultiBin(self.runBinOutput(data, times))
        assertEquals(times+1, len(retval))
        for i, piece in enumerate(retval[:-1]):
            assertEquals(piece, data*i)
        assertOk(json.loads(retval[-1]))

    def testBinaryOutput(self):
        data = "123456789deadbeef"

        test = self.runBinOutput(data, 0)
        assertEquals(0, len(test))
        test = self.runBinOutput(data, 1)
        assertEquals(0, len(test))

        self.tryMultiBin(data, 2)
        self.tryMultiBin(data, 10)
