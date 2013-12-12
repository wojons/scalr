
import unittest
import mock

import gevent

from scalrpy import messaging

class MessagingTest(unittest.TestCase):

    def setUp(self):
        messaging.CONFIG['connections'] = mock.MagicMock()
        messaging.LOGGER = mock.MagicMock()


    def test_encrypt(self):
        daemon = messaging.Messaging()
        server_id = 'a' * 32
        crypto_key = 'k' * 32
        daemon._encrypt(server_id, crypto_key, 'message')

        self.assertRaises(AssertionError, daemon._encrypt, server_id, crypto_key, '')


    @mock.patch('scalrpy.util.dbmanager.DBManager')
    @mock.patch('scalrpy.messaging.Messaging._encrypt')
    @mock.patch('scalrpy.messaging.Messaging._get_vpc_router_roles')
    @mock.patch('scalrpy.messaging.Messaging._get_ctrl_ports')
    @mock.patch('scalrpy.messaging.Messaging._get_srz_keys')
    @mock.patch('scalrpy.messaging.Messaging._get_servers')
    @mock.patch('scalrpy.messaging.Messaging._get_messages')
    def test_produce_tasks(self,
            mock_get_messages,
            mock_get_servers,
            mock_get_srz_keys,
            mock_get_ctrl_ports,
            mock_get_vpc_router_roles,
            mock_encrypt,
            mock_DBManager):
        daemon = messaging.Messaging()
        daemon._produce_tasks()

        with mock.patch('scalrpy.messaging.Messaging._db_update') as mock_db_update:
            mock_db_update.side_effect = Exception('TestException')
            mock_encrypt.side_effect = Exception('test')

            messaging.CONFIG['verbosity'] = 0
            daemon._produce_tasks()

            messaging.CONFIG['verbosity'] = 4
            daemon._produce_tasks()


    @mock.patch('scalrpy.util.dbmanager.DBManager')
    @mock.patch('scalrpy.messaging.Messaging._db_update')
    @mock.patch('scalrpy.messaging.urllib2.urlopen')
    def test_send_task_ok(self, mock_urlopen, mock_db_update, mock_DBManager):
        task = {
                'msg':{
                    'messageid':'1',
                    'message_name':'name',
                    'handle_attempts':0},
                'req':mock.MagicMock()}
        ret = mock.MagicMock()

        ret.getcode.return_value = 201
        mock_urlopen.return_value = ret

        daemon = messaging.Messaging()

        daemon._send(task)
        mock_db_update.assert_called_once_with(True, task['msg'])


    @mock.patch('scalrpy.util.dbmanager.DBManager')
    @mock.patch('scalrpy.messaging.Messaging._db_update')
    @mock.patch('scalrpy.messaging.urllib2.urlopen')
    def test_send_task_false(self, mock_urlopen, mock_db_update, mock_DBManager):
        task = {
                'msg':{
                    'messageid':'1',
                    'message_name':'name',
                    'handle_attempts':0},
                'req':mock.MagicMock()}
        ret = mock.MagicMock()

        ret.getcode.return_value = 403 
        mock_urlopen.return_value = ret

        daemon = messaging.Messaging()

        daemon._send(task)
        mock_db_update.assert_called_once_with(False, task['msg'])


    @mock.patch('scalrpy.util.dbmanager.DBManager')
    def test_db_update(self, mock_DBManager):
        daemon = messaging.Messaging()

        msg = {
                'messageid':'1',
                'message_name':'name',
                'handle_attempts':0,
                'event_id':777,
                }
        daemon._db_update(True, msg)

        msg = {
                'messageid':'1',
                'message_name':'ExecScript',
                'handle_attempts':0,
                'event_id':777,
                }
        daemon._db_update(True, msg)

        daemon._db_update(False, msg)


    @mock.patch('scalrpy.util.dbmanager.DBManager')
    @mock.patch('scalrpy.messaging.Messaging._produce_tasks')
    @mock.patch('scalrpy.messaging.Messaging._process_tasks')
    def test_run(self, mock_produce_tasks, mock_process_tasks, mock_DBManager):
        pass



if __name__ == "__main__":
	unittest.main()

