import os
import sys
import time
import urllib2
try:
    import json
except ImportError:
    import simplejson as json

from threading import local

from scalrpy.util import helper
from scalrpy.util import cryptotool

from scalrpy import LOG



class ServiceError(Exception):
    PARSE = -32700
    INVALID_REQUEST = -32600
    METHOD_NOT_FOUND = -32601
    INVALID_PARAMS = -32602
    INTERNAL = -32603
    NAMESPACE_NOT_FOUND = -32099

    def __init__(self, *args):
        self.code, self.message = args[0:2]
        if len(args) > 2:
            self.data = args[2]
        else:
            self.data = str(sys.exc_info()[1])
        Exception.__init__(self, *args)



class NamespaceNotFoundError(ServiceError):
    """A class implements a JSON-RPC 2.0  Error Object: Server error.
    Reserved for implementation-defined server-errors.
    """
    def __init__(self, *data):
        ServiceError.__init__(self, self.NAMESPACE_NOT_FOUND, 'Namespace not found', *data)



class MethodNotFoundError(ServiceError):
    """A class implements a JSON-RPC 2.0  Error Object: Method not found.
    The requested remote-procedure does not exist / is not available.
    """
    def __init__(self, *data):
        ServiceError.__init__(self, self.METHOD_NOT_FOUND, 'Method not found', *data)



class ParseError(ServiceError):
    """A class implements a JSON-RPC 2.0  Error Object: Parse error.
    Invalid JSON. An error occurred on the server while parsing the JSON text.
    """
    def __init__(self, *data):
        ServiceError.__init__(self, self.PARSE, 'Parse error', *data)



class InvalidRequestError(ServiceError):
    """A class implements a JSON-RPC 2.0  Error Object: Invalid Request.
    The received JSON is not a valid JSON-RPC Request..
    """
    def __init__(self, *data):
        ServiceError.__init__(self, self.INVALID_REQUEST, 'Invalid Request', *data)



class InvalidParamsError(ServiceError):
    """A class implements a JSON-RPC 2.0  Error Object: Invalid params.
    Invalid method parameters.
    """
    def __init__(self, *data):
        ServiceError.__init__(self, self.INVALID_PARAMS, 'Invalid params', *data)



class InternalError(ServiceError):
    """A class implements a JSON-RPC 2.0  Error Object: Internal error.
    Internal JSON-RPC error.
    """
    def __init__(self, *data):
        ServiceError.__init__(self, self.INTERNAL, 'Internal error', *data)



class Security(object):

    DATE_FORMAT = "%a %d %b %Y %H:%M:%S UTC"

    def __init__(self, crypto_key, encrypt=True, crypto_algo=None):
        assert crypto_key, 'Crypto key is None'
        self.crypto_key = str(crypto_key)
        self.encrypt = encrypt
        if not crypto_algo:
            self.crypto_algo = dict(name="des_ede3_cbc", key_size=24, iv_size=8)
        else:
            self.crypto_algo = crypto_algo


    def sign_data(self, data, utc_struct_time=None):
        return cryptotool.sign(data, self.crypto_key, utc_struct_time, self.DATE_FORMAT)


    def check_signature(self, signature, data, utc_timestamp):
        utc_struct_time = time.strptime(utc_timestamp, self.DATE_FORMAT)
        calc_signature = self.sign_data(data, utc_struct_time)[0]
        assert signature == calc_signature, "Signature doesn't match"


    def decrypt_data(self, data):
        if not self.encrypt:
            return data
        try:
            return cryptotool.decrypt(self.crypto_algo, data, self.crypto_key)
        except:
            raise InvalidRequestError('Failed to decrypt data. Error:%s' % helper.exc_info())


    def encrypt_data(self, data):
        if not self.encrypt:
            return data
        try:
            return cryptotool.encrypt(self.crypto_algo, data, self.crypto_key)
        except:
            raise InvalidRequestError('Failed to encrypt data. Error:%s' % helper.exc_info())



class ServiceProxy(object):

    def __init__(self):
        self.local = local()


    def __getattr__(self, name):
        try:
            self.__dict__['local'].method.append(name)
        except AttributeError:
            self.__dict__['local'].method = [name]
        return self


    def __call__(self, timeout=None, **kwds):
        try:
            req = json.dumps({'method': self.local.method[-1], 'params': kwds, 'id': time.time()})
            resp = json.loads(self.exchange(req, timeout=timeout))
            if 'error' in resp:
                error = resp['error']
                raise ServiceError(error.get('code'), error.get('message'), error.get('data'))
            return resp['result']
        finally:
            self.local.method = []


    def exchange(self, request, timeout=None):
        raise NotImplementedError()



class HttpServiceProxy(ServiceProxy):

    def __init__(self, endpoint, security=None, headers=None):
        ServiceProxy.__init__(self)
        self.endpoint = endpoint
        self.security = security
        self.headers = headers


    def exchange(self, request, timeout=None):
        if self.security:
            request = self.security.encrypt_data(request)
            sig, date = self.security.sign_data(request)
            headers = {
                'Date': date,
                'X-Signature': sig,
            }
        else:
            headers = {}
        if self.headers:
            headers.update(self.headers)
        namespace = self.local.method[0] if len(self.local.method) > 1 else ''
        http_req = urllib2.Request(
                os.path.join(self.endpoint, namespace),
                request,
                headers)
        response = urllib2.urlopen(http_req, timeout=timeout).read()
        return self.security.decrypt_data(response) if self.security else response

