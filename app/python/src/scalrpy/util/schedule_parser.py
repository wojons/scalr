'''
Created on May 18, 2012

@author: marat
'''

import time
import string
import re


class ParseError(Exception):
    def __init__(self, *args):
        if not len(args):
            args = ('Invalid schedule format',)
        Exception.__init__(self, *args)


class Schedule(object):

    def __init__(self, format=None):
        self.schedule = Schedule.parse(format or '* * *')

    @classmethod
    def parse(cls, format):
        re_all = re.compile(r'^\*$')
        re_interval = re.compile(r'^^\*/(\d+)$')
        re_range = re.compile(r'^(\d+)-(\d+)$')

        _all = [
            range(0, 24),
            range(1, 32),
            range(0, 7)
        ]

        schedule = [
            [], [], []
        ]

        tokens = map(string.strip, format.split(' '))
        if len(tokens) != 3:
            raise ParseError()

        i = 0
        for tok_cal in tokens:
            for tok_number in tok_cal.split(','):
                try:
                    number = int(tok_number)
                except:
                    pass
                else:
                    if number in _all[i]:
                        schedule[i].append(number)
                        continue

                if re_all.match(tok_number):
                    schedule[i] = _all[i]
                    continue

                m = re_interval.match(tok_number)
                if m:
                    schedule[i] += _all[i][::int(m.group(1))]
                    continue

                m = re_range.match(tok_number)
                if m:
                    numbers = range(int(m.group(1)), int(m.group(2)) + 1)
                    if set(_all[i]).issuperset(set(numbers)):
                        schedule[i] += numbers
                        continue

                raise ParseError()

            schedule[i].sort()
            i += 1

        return schedule


    def intime(self, now=None):
        now = now or time.localtime()
        return now.tm_wday in self.schedule[2] and \
                now.tm_mday in self.schedule[1] and \
                now.tm_hour in self.schedule[0]
