import unittest
import mock

import gevent

from scalrpy import msg_sender

class MsgSenderTest(unittest.TestCase):

    def setUp(self):
        msg_sender.LOG = mock.MagicMock()

    def test_encrypt(self):
        daemon = msg_sender.MsgSender()
        server_id = 'a' * 32
        crypto_key = 'k' * 32
        daemon._encrypt(server_id, crypto_key, 'message')
        self.assertRaises(AssertionError, daemon._encrypt, server_id, crypto_key, '')
    
    def test_db_update_ok(self):
        daemon = msg_sender.MsgSender()
        daemon._db.execute = mock.MagicMock()
        message = dict(
                messageid='id',
                server_id='id',
                event_id='id',
                message_format='json',
                handle_attempts=0,
                message='message',
                message_name=''
                )
        message_exec_script = dict(
                messageid='id_exec',
                server_id='id_exec',
                event_id='id_exec',
                message_format='json',
                handle_attempts=0,
                message='message',
                message_name='ExecScript'
                )
        daemon.db_update([message, message, message_exec_script], 'ok')
        assert daemon._db.execute.mock_calls[0][1][0] == \
                "DELETE FROM `messages` WHERE `messageid` IN ('id_exec')"
        print daemon._db.execute.mock_calls[1][1][0]
        assert daemon._db.execute.mock_calls[1][1][0] == \
                "UPDATE `messages` SET `status`=1,`message`='',`dtlasthandleattempt`=NOW() WHERE `messageid` IN ('id', 'id')" 
        assert daemon._db.execute.mock_calls[2][1][0] == \
                "UPDATE `events` SET `msg_sent`=`msg_sent`+1 WHERE `event_id` IN ('id', 'id')"

    def test_db_update_fail(self):
        daemon = msg_sender.MsgSender()
        daemon._db.execute = mock.MagicMock()
        message = dict(
                messageid='id',
                server_id='id',
                event_id='id',
                message_format='json',
                handle_attempts=0,
                message='message',
                message_name=''
                )
        daemon.db_update([message]*2, 'fail')
        assert daemon._db.execute.mock_calls[0][1][0] == \
        "UPDATE `messages` SET `status`=0,`handle_attempts`=1,`dtlasthandleattempt`=NOW() WHERE `messageid`='id'"
        assert daemon._db.execute.mock_calls[1][1][0] == \
        "UPDATE `messages` SET `status`=0,`handle_attempts`=1,`dtlasthandleattempt`=NOW() WHERE `messageid`='id'"

    def test_db_update_wrong(self):
        daemon = msg_sender.MsgSender()
        daemon._db.execute = mock.MagicMock()
        message = dict(
                messageid='id',
                server_id='id',
                event_id='id',
                message_format='json',
                handle_attempts=0,
                message='message',
                message_name=''
                )
        daemon.db_update([message]*2, 'wrong')
        assert daemon._db.execute.mock_calls[0][1][0] == \
        "UPDATE `messages` SET `status`=3,`handle_attempts`=1,`dtlasthandleattempt`=NOW() WHERE `messageid` IN ('id', 'id')"


if __name__ == "__main__":
	unittest.main()
