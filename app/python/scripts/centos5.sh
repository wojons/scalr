#!/bin/bash

PYTHON=python26

yum install -y $PYTHON-devel

# check setuptools
$PYTHON -c 'import setuptools' 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    cd /tmp
    wget https://bitbucket.org/pypa/setuptools/raw/0.8/ez_setup.py --no-check-certificate
    $PYTHON ez_setup.py
    rm ez_setup.py
fi

# check m2crypto
$PYTHON -c "import M2Crypto" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    yum install -y python26-m2crypto
fi

# check libevent
yum install -y libevent-devel

# check netsnmp python bindings
$PYTHON -c 'import netsnmp' 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    cd /tmp
    wget http://sourceforge.net/projects/net-snmp/files/net-snmp/5.7.2/net-snmp-5.7.2.tar.gz --no-check-certificate
    tar -xvf net-snmp-5.7.2.tar.gz 1>/dev/null
    cd net-snmp-5.7.2
    ./configure --prefix=/usr --with-python-modules --libdir=/usr/lib64
    make 1>/dev/null
    make install 1>/dev/null
    cd python
    $PYTHON setup.py install
    cd ..
    make install 1>/dev/null
    cd /tmp
    rm net-snmp-5.7.2* -rf
fi

# make /var/lib/rrdcached/journal directory for rrdcached
mkdir -p /var/lib/rrdcached/journal

# check rrdtool
$PYTHON -c 'import rrdtool' 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    yum install -y pango-devel
    yum install -y cairo-devel
    yum install -y libxml2-devel
    cd /tmp
    wget http://oss.oetiker.ch/rrdtool/pub/rrdtool-1.4.8.tar.gz --no-check-certificate
    tar -xvf rrdtool-1.4.8.tar.gz 1>/dev/null
    cd rrdtool-1.4.8
    ./configure --prefix=/usr
    make 1>/dev/null
    make install 1>/dev/null
    make distclean
    ./configure --prefix=/usr --libdir=/usr/lib64
    make 1>/dev/null
    make install 1>/dev/null
    cd bindings/python
    $PYTHON setup.py install
    cd /tmp
    rm rrdtool-1.4.8* -rf
fi

# finally check installation

$PYTHON -c "import setuptools" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import setuptools' failed"
    exit 1
fi

$PYTHON -c "import M2Crypto" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import M2Crypto' failed"
    exit 1
fi

$PYTHON -c "import netsnmp" 1>/dev/null 2>/dev/null
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

$PYTHON -c "import rrdtool" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import rrdtool' failed"
    exit 1
fi

echo "Done"
