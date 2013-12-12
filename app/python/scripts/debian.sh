#!/bin/bash

apt-get install -y python-dev

# check setuptools
python -c "import setuptools" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    apt-get install -y python-setuptools
fi

# check m2crypto
python -c "import M2Crypto" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    apt-get install -y m2crypto
fi

# check libevent
apt-get install -y libevent-dev

# check netsnmp bindings
python -c "import netsnmp" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "Python netsnmp bindings not install, installing"
    apt-get install -y snmp
    apt-get install -y libsnmp-python
fi

# check rrdtool
rrdtool --version 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "Command rrdtool not found, installing rrdtool"
    apt-get install -y rrdtool
fi

# check rrdcached
which rrdcached 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "Command rrdcached not found, installing rrdtool"
    apt-get install -y rrdcached
    service rrdcached stop
fi

# check rrdtool bindings
python -c "import rrdtool" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "Python rrdtool bindings not install, installing"
    apt-get install -y python-rrdtool
fi


# finally check installation

echo "[REPORT]"

python -c "import setuptools" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import setuptools' failed"
else
    echo "[OK] python setuptools"
fi

python -c "import M2Crypto" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import M2Crypto' failed"
else
    echo "[OK] m2crypto python bindings"
fi

python -c "import netsnmp" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import netsnmp' failed"
else
    echo "[OK] netsnmp python bindings"
fi

rrdtool --version 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] command rrdtool not found"
else
    echo "[OK] rrdtool"
fi

which rrdcached 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] command rrdcached not found"
else
    echo "[OK] rrdcahced"
fi

python -c "import rrdtool" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import rrdtool' failed"
else
    echo "[OK] rrdtool python bindings"
fi

