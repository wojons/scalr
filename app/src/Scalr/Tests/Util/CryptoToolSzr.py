#!/usr/bin/python
from M2Crypto.EVP import Cipher
import binascii
import sys


crypto_algo = dict(name="des_ede3_cbc", key_size=24, iv_size=8)


def _init_cipher(key, op_enc=1):
    skey = key[0:crypto_algo["key_size"]]   # Use first n bytes as crypto key
    iv = key[-crypto_algo["iv_size"]:]              # Use last m bytes as IV
    return Cipher(crypto_algo["name"], skey, iv, op_enc)


def decrypt (s, key):
    c = _init_cipher(key, 0)
    ret = c.update(binascii.a2b_base64(s))
    ret += c.final()
    del c
    return ret

def encrypt (s, key):
    c = _init_cipher(key, 1)
    ret = c.update(s)
    ret += c.final()
    del c
    return binascii.b2a_base64(ret)


if __name__ == "__main__":
    if len(sys.argv) != 4:
        print ('Usage: decrypt.py [encrypt|decrypt] <str> <key>')
        sys.exit(1)

    crypted_str, crypto_key = sys.argv[2:]
    crypto_key = binascii.a2b_base64(crypto_key)

    if sys.argv[1] == 'encrypt':
        fn = encrypt
    else:
        fn = decrypt

    print fn(crypted_str, crypto_key)

