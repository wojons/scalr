class IterationTimeoutError(Exception):
    pass


class AlreadyRunningError(Exception):
    pass


class TimeoutError(Exception):
    pass


class MissingCredentialsError(Exception):
    pass


class IncompleteCredentialsError(Exception):
    pass


class FileNotFoundError(Exception):
    pass
