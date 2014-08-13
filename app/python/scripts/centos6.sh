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

    # make /var/lib/rrdcached/journal directory for rrdcached
    mkdir -p /var/lib/rrdcached/journal

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
    yum install -y net-snmp
    yum install -y net-snmp-python
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
