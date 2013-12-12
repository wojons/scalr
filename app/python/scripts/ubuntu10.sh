#!/bin/bash

function install_rrd() {
    apt-get install -y libpango1.0-dev
    apt-get install -y libcairo2-dev
    apt-get install -y libxml2-dev
    cd /tmp
    echo "DOWNLOADING rrdtool"
    wget http://oss.oetiker.ch/rrdtool/pub/rrdtool-1.4.8.tar.gz --no-check-certificate
    echo "UNTARING rrdtool"
    tar -xvf rrdtool-1.4.8.tar.gz 1>/dev/null
    cd rrdtool-1.4.8
    echo "CONFIGURING"
    ./configure --prefix=/usr
    echo "MAKING"
    make 1>/dev/null
    echo "INSTALLING"
    make install 1>/dev/null
    echo "SETUP.PY INSTALL"
    cd bindings/python
    python setup.py install
    cd /tmp
    rm rrdtool-1.4.8* -rf
}

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
    install_rrd
fi

# check rrdcached
which rrdcached 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "Command rrdcached not found, installing rrdtool"
    install_rrd
fi

# check rrdtool bindings
python -c "import rrdtool" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "Python rrdtool bindings not install, installing"
    install_rrd
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

