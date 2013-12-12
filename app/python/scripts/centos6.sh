#!/bin/bash

function install_rrd {
    cd /tmp
    if [ "$(uname -m)" == "x86_64" ]; then
        PLATFORM=x86_64
    else 
        PLATFORM=i386
    fi
    wget -c http://packages.express.org/rrdtool/rrdtool-1.4.7-1.el6.wrl.$PLATFORM.rpm --no-check-certificate
    wget -c http://packages.express.org/rrdtool/rrdtool-perl-1.4.7-1.el6.wrl.$PLATFORM.rpm --no-check-certificate
    wget -c http://packages.express.org/rrdtool/rrdtool-python-1.4.7-1.el6.wrl.$PLATFORM.rpm --no-check-certificate

    yum remove -y rrdtool

    yum install -y cairo
    yum install -y pango
    yum install -y gettext
    yum install -y perl-Time-HiRes

    rpm -i --excludedocs /tmp/rrdtool-1.4.7-1.el6.wrl.$PLATFORM.rpm /tmp/rrdtool-perl-1.4.7-1.el6.wrl.$PLATFORM.rpm /tmp/rrdtool-python-1.4.7-1.el6.wrl.$PLATFORM.rpm

    rm /tmp/rrdtool-1.4.7-1.el6.wrl.$PLATFORM.rpm
    rm /tmp/rrdtool-perl-1.4.7-1.el6.wrl.$PLATFORM.rpm
    rm /tmp/rrdtool-python-1.4.7-1.el6.wrl.$PLATFORM.rpm
}

yum install -y python-devel

# check setuptools
python -c "import setuptools" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    yum install -y python-setuptools
fi

# check m2crypto
python -c "import M2Crypto" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    yum install -y m2crypto
fi

# check libevent
yum install -y libevent-devel

# check netsnmp bindings
python -c "import netsnmp" 1>/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
    echo "Python netsnmp bindings not install, installing"
    yum install -y net-snmp
    yum install -y net-snmp-python
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

