#!/bin/bash

PYTHON=python26

yum install -y $PYTHON-devel

# check setuptools
$PYTHON -c 'import setuptools' 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "INSTALLING setuptools"
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
    echo "DOWNLOADING netsnmp"
    wget http://sourceforge.net/projects/net-snmp/files/net-snmp/5.7.2/net-snmp-5.7.2.tar.gz --no-check-certificate
    echo "UNTARING netsnmp"
    tar -xvf net-snmp-5.7.2.tar.gz 1>/dev/null
    cd net-snmp-5.7.2
    echo "CONFIGURING"
    ./configure --prefix=/usr --with-python-modules --libdir=/usr/lib64
    echo "MAKING"
    make 1>/dev/null
    echo "INSTALLING"
    make install 1>/dev/null
    echo "SETUP.PY INSTALL"
    cd python
    $PYTHON setup.py install
    cd ..
    echo "INSTALLING"
    make install 1>/dev/null
    cd /tmp
    rm net-snmp-5.7.2* -rf
fi

# check rrdtool
$PYTHON -c 'import rrdtool' 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    yum install -y pango-devel
    yum install -y cairo-devel
    yum install -y libxml2-devel
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
    make distclean
    echo "CONFIGURING"
    ./configure --prefix=/usr --libdir=/usr/lib64
    echo "MAKING"
    make 1>/dev/null
    echo "INSTALLING"
    make install 1>/dev/null
    echo "SETUP.PY INSTALL"
    cd bindings/python
    $PYTHON setup.py install
    cd /tmp
    rm rrdtool-1.4.8* -rf
fi


# finally check installation

echo "[REPORT]"

$PYTHON -c "import setuptools" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import setuptools' failed"
else
    echo "[OK] python setuptools"
fi

$PYTHON -c "import M2Crypto" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import M2Crypto' failed"
else
    echo "[OK] m2crypto python bindings"
fi

$PYTHON -c "import netsnmp" 1>/dev/null 2>/dev/null
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

$PYTHON -c "import rrdtool" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "[ERROR] 'import rrdtool' failed"
else
    echo "[OK] rrdtool python bindings"
fi
