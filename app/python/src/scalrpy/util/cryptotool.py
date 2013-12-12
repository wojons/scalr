import time
import hmac
import hashlib
import binascii

from M2Crypto.EVP import Cipher
from M2Crypto.Rand import rand_bytes


READ_BUF_SIZE = 1024 * 1024


def keygen(length=40):
    return binascii.b2a_base64(rand_bytes(length))


def decrypt_key(key):
    return binascii.a2b_base64(key)


def read_key(key_path):
    return open(key_path).read().strip()


"""
crypto_algo = dict(name="des_ede3_cbc", key_size=24, iv_size=8)
crypto_algo = dict(name="des_ede3_cfb", key_size=24, iv_size=8)
"""


def _init_cipher(crypto_algo, key, op_enc=1):
    skey = key[0:crypto_algo["key_size"]]
    iv = key[-crypto_algo["iv_size"]:]
    return Cipher(crypto_algo["name"], skey, iv, op_enc)


def encrypt (crypto_algo, s, key):
    c = _init_cipher(crypto_algo, key, 1)
    ret = c.update(s)
    ret += c.final()
    del c
    return binascii.b2a_base64(ret)


def decrypt (crypto_algo, s, key):
    c = _init_cipher(crypto_algo, key, 0)
    ret = c.update(binascii.a2b_base64(s))
    ret += c.final()
    del c
    return ret


def digest_file(digest, f):
    while 1:
        buf = f.read(READ_BUF_SIZE)
        if not buf:
            break
        digest.update(buf)
    return digest.final()


def crypt_file(cipher, in_file, out_file):
    while 1:
        buf = in_file.read(READ_BUF_SIZE)
        if not buf:
            break
        out_file.write(cipher.update(buf))
    out_file.write(cipher.final())


def sign(data, crypto_key, timestamp=None, date_format="%a %d %b %Y %H:%M:%S %Z"):
    date = time.strftime(date_format, timestamp if timestamp else time.gmtime())
    canonical_string = data + date
    digest = hmac.new(crypto_key, canonical_string, hashlib.sha1).digest()
    sign_ = binascii.b2a_base64(digest)
    if sign_.endswith('\n'):
        sign_ = sign_[:-1]
    return sign_, date


def check_signature(signature, data, timestamp, date_format="%a %d %b %Y %H:%M:%S %Z"):
    calc_signature = sign(data, time.strptime(timestamp, date_format))[0]
    assert signature == calc_signature, "Signature doesn't match"
