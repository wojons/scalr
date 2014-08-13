#!/bin/bash

function install_rrd() {
    apt-get install -y libpango1.0-dev
    apt-get install -y libcairo2-dev
    apt-get install -y libxml2-dev
    # make /var/lib/rrdcached/journal directory for rrdcached
    mkdir -p /var/lib/rrdcached/journal
    cd /tmp
    wget http://oss.oetiker.ch/rrdtool/pub/rrdtool-1.4.8.tar.gz --no-check-certificate
    tar -xvf rrdtool-1.4.8.tar.gz 1>/dev/null
    cd rrdtool-1.4.8
    ./configure --prefix=/usr
    make 1>/dev/null
    make install 1>/dev/null
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
    apt-get install -y snmp
    apt-get install -y libsnmp-python
fi

# check rrdtool
rrdtool --version 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    install_rrd
fi

# check rrdcached
which rrdcached 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    install_rrd
fi

# check rrdtool bindings
python -c "import rrdtool" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    install_rrd
fi

# finally check installation

python -c "import setuptools" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import setuptools' failed"
    exit 1
fi

python -c "import M2Crypto" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import M2Crypto' failed"
    exit 1
fi

python -c "import netsnmp" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import netsnmp' failed"
    exit 1
fi

rrdtool --version 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] command rrdtool not found"
    exit 1
fi

which rrdcached 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] command rrdcached not found"
    exit 1
fi

python -c "import rrdtool" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import rrdtool' failed"
    exit 1
fi

echo "Done"
