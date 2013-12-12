
import unittest

from scalrpy.util.validator import Validator


class ValidatorTest(unittest.TestCase):

    def test_match(self):
        assert Validator.match(r'[a-zA-Z0-9]', 'TestString123')
        assert not Validator.match(r'^[a-zA-Z]$', 'Test-String123')


    def test_NFQDN(self):
        assert Validator.NFQDN('sample-01')
        assert not Validator.NFQDN('sample.sample-01.sample1.example.com.')


    def test_domain(self):
        assert Validator.domain('example.com')
        assert Validator.domain('example1.example.com')
        assert Validator.domain('example1.example.com.')


    def test_ip(self):
        assert Validator.ip('127.0.0.1')
        assert not Validator.ip('127.0.0.256')


    def test_a_record(self):
        assert Validator.dns.a_record('int-example', '127.0.0.1')
        assert Validator.dns.a_record('int-example.com', '127.0.0.1')
        assert Validator.dns.a_record('int-example.com.', '127.0.0.1')
        assert Validator.dns.a_record('*.example.com.', '127.0.0.1')
        assert Validator.dns.a_record('@', '127.0.0.1')
        assert Validator.dns.a_record('', '127.0.0.1')
        assert Validator.dns.a_record('*', '127.0.0.1')
        assert not Validator.dns.a_record('.', '127.0.0.1')
        assert not Validator.dns.a_record('int-example', '127.0.0.333')
        assert not Validator.dns.a_record('127.0.0.1', '127.0.0.1')
        assert not Validator.dns.a_record('127.0.0.1.', '127.0.0.1')


    def test_mx_record(self):
        assert Validator.dns.mx_record('int-example.com.', 'excample1.example.com.')
        assert Validator.dns.mx_record('', 'excample1.example.com.')
        assert Validator.dns.mx_record('@', 'excample1.example.com.')
        assert not Validator.dns.mx_record('.', 'excample1.example.com.')
        assert not Validator.dns.mx_record('127.0.0.1', 'excample1.example.com.')


    def test_ns_record(self):
        pass


    def test_cname_record(self):
        pass



if __name__ == "__main__":
	unittest.main()

