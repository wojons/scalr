#!/bin/bash


function usage()
{
cat << EOF
usage: $0 [OPTIONS]

OPTIONS:
   -h, --help                       Show this message and exit
   --virtualenv                     Virtualenv directory
   --custom-python                  Path to custom python executable
   --custom-pip                     Path to custom pip executable
   --uninstall                      Uninstall old ScalrPy version
EOF
}


script_dir=$(dirname $(readlink -f $0))
python=$(which python)
if [ $? -ne 0 ]; then
    python="/usr/bin/python"
fi
pip=$(which pip2)
if [ $? -ne 0 ]; then
    pip="/usr/bin/pip2"
fi
rrd_ver="1.4.7"

uninstall="N"


while [[ $# > 0 ]]
do
    key="$1"
    shift

    case $key in
        -h|--help)
        usage
        exit 0
        ;;
        --virtualenv)
        python=$1/bin/python
        pip=$1/bin/pip2
        shift
        ;;
        --custom-python)
        python=$1
        shift
        ;;
        --custom-pip)
        pip=$1
        shift
        ;;
        --uninstall)
        uninstall="Y"
        shift
        ;;
        *)
        usage
        exit 1
        ;;
    esac
done

function log_err()
{
    echo -e "\033[91m[ERROR]\033[0m $1"
}


function log()
{
    echo -e "\033[92m[INFO]\033[0m $1"
}


function install()
{
    log "install $1"
    if [ "$dist" == "ubuntu" ] || [ "$dist" == "debian" ]; then
        apt-get install -y $1
        dpkg-query -W -f='${Status}|${Package}\n' | grep "install ok installed|$1"
        if [ $? -ne 0 ]; then
            log_err "failed"
            exit 1
        fi
    elif [ "$dist" == "redhat" ] || [ "$dist" == "centos" ]; then
        yum install -y $1
        yum list installed | grep $1.x86_64
        EXIT1=$?
        yum list installed | grep $1.noarch
        EXIT2=$?
        if [ $exit1 -ne 0 ] && [ $exit2 -ne 0 ]; then
            log_err "failed"
            exit 1
        fi
    fi
}


function install_pip()
{
    $pip uninstall -y pip
    $python -c "import pip"
    if [ $? -eq 0 ]; then
        if [ "$dist" == "debian" ] || [ "$dist" == "ubuntu" ]; then
            apt-get remove -y python-pip
        fi
    fi
    cd /tmp
    if ! [ -f /tmp/get-pip.py ]; then
        wget https://bootstrap.pypa.io/get-pip.py --no-check-certificate
    fi
    $python get-pip.py
    if [ $? -ne 0 ]; then
        log_err "failed"
        exit 1
    fi
    if [ "$pip" == "/usr/bin/pip2" ]; then
        pip=$(which pip2)
    fi
    rm get-pip.py
}


dist=$($python -c "import platform;print platform.dist()[0].lower()")
dist_ver=$($python -c "import platform;print platform.dist()[1].lower()")


function check_rrd()
{
    version=$(rrdtool --version)
    if [ $? -ne 0 ] || [[ "$version" < "$rrd_ver" ]]; then
        return 1
    fi

    which rrdcached
    if [ $? -ne 0 ]; then
        return 1
    fi

    return 0
}


function debian_rrd()
{
    install rrdtool
    mkdir -p /var/lib/rrdcached/journal
    install rrdcached
    service rrdcached stop
    #if [ -e /etc/default/rrdcached ]; then
    #    sed -i 's/DISABLE=0/DISABLE=1/g' /etc/default/rrdcached
    #fi
    install libpango1.0-dev
    install libcairo2-dev
    install libxml2-dev
    install librrd-dev
}


function debian_base()
{
    install python-dev
    install libssl-dev
    install libevent-dev
    install libffi-dev
    install swig
}


function debian()
{
    debian_base
    check_rrd
    if [ $? -ne 0 ]; then
        debian_rrd
    fi
}


function compile_rrd()
{
    if [ "$dist" == "ubuntu" ] || [ "$dist" == "debian" ]; then
        install libpango1.0-dev
        install libcairo2-dev
        install libxml2-dev
    elif [ "$dist" == "redhat" ] || [ "$dist" == "centos" ]; then
        install pango-devel
        install cairo-devel
        install libxml2-devel
        if [ "$major_ver" != "5" ]; then
            install perl-ExtUtils-MakeMaker
        fi
    fi
    
    cd /tmp
    wget http://oss.oetiker.ch/rrdtool/pub/rrdtool-$rrd_ver.tar.gz --no-check-certificate
    tar -xvf rrdtool-$rrd_ver.tar.gz 1>/dev/null
    cd rrdtool-$rrd_ver
    ./configure --prefix=/usr --libdir=/usr/lib64 --disable-ruby
    make 1>/dev/null
    make install 1>/dev/null
    if [ $? -ne 0 ]; then
        log_err "failed"
        exit 1
    fi
    cd /tmp
    rm rrdtool-$rrd_ver* -rf
    mkdir -p /var/lib/rrdcached/journal
}


function ubuntu10_rrd()
{
    compile_rrd
    if [ -e /etc/default/rrdcached ]; then
        sed -i 's/DISABLE=0/DISABLE=1/g' /etc/default/rrdcached
    fi
}


function ubuntu10()
{
    debian_base
    check_rrd
    if [ $? -ne 0 ]; then
        ubuntu10_rrd
    fi
}


function centos_base()
{
    install libevent-devel
    install libffi-devel
    install openssl-devel
    install swig
}


function centos5_rrd()
{
    compile_rrd
}


function centos6_rrd()
{
    compile_rrd
}


function centos_rrd()
{
    install pango-devel
    install cairo-devel
    install libxml2-devel
    install rrdtool
    install rrdtool-devel
}


function centos()
{
    centos_base
    check_rrd
    if [ $? -ne 0 ]; then
        centos_rrd
    fi
}


function centos5()
{
    centos_base 
    install python26-devel
    install groff
    install file
    check_rrd
    if [ $? -ne 0 ]; then
        centos5_rrd
    fi
}

function centos6()
{
    centos_base 
    install python-devel
    check_rrd
    if [ $? -ne 0 ]; then
        centos6_rrd
    fi
}


###
log "install ScalrPy dependencies on $dist $dist_ver"
log "PYTHON: $python"
log "PIP: $pip"
log "RRD_VER: $rrd_ver"

if [ "$dist" == "ubuntu" ]; then
    log "run apt-get update"
    apt-get update
    install wget
    major_ver=$(echo "$dist_ver" | awk -F'.' '{print $1}')
    if [ "$major_ver" == "10" ]; then
        ubuntu10
    else
        debian
    fi
elif [ "$dist" == "debian" ]; then
    log "run apt-get update"
    apt-get update
    install wget
    debian
elif [ "$dist" == "redhat" ] || [ "$dist" == "centos" ]; then
    install wget
    major_ver=$(echo "$dist_ver" | awk -F'.' '{print $1}')
    if [ "$major_ver" == "5" ]; then
        centos5
    elif [ "$major_ver" == "6" ]; then
        centos6
    else
        centos
    fi
else
    log_err "unsupported os: $dist"
    exit 1
fi


log "check python pip"
pip_info=$($pip --version | tr ' ' '\n')
if [ $? -ne 0 ]; then
    log "python pip package is not installed. Installing latest python pip package"
    install_pip
else
    readarray -t array <<<"$pip_info"
    pip_version=${array[1]}
    if [[ "$pip_version" < "1.4" ]]; then
        log "required python pip version >= 1.4. Installing latest python pip package"
        install_pip
    fi
fi


log "check python setuptools"
$python -c "import sys;import setuptools"
if [ $? -ne 0 ]; then
    log "python setuptools package is not installed. Installing latest python setuptools package"
    $pip install setuptools
fi
setuptools_info=$($pip show setuptools)
readarray -t array <<<"$setuptools_info"
setuptools_info=$(echo ${array[2]} | tr ' ' '\n')
readarray -t array <<<"$setuptools_info"
setuptools_version=${array[1]}
if [[ "$setuptools_version" < "5.5" ]]; then
    log "required python setuptools version >= 5.5. Installing latest python setuptools package"
    $pip uninstall -y setuptools
    $python -c "import setuptools"
    if [ $? -eq 0 ]; then
        if [ "$dist" == "ubuntu" ] && [ "$major_ver" == "10" ]; then
            apt-get purge -y python-setuptools
            apt-get purge -y python python2.6 python-dev python2.6-dev
            apt-get install -y python python-dev
        fi
    fi
    log "install latest setuptools"
    $pip install -U setuptools
fi


$pip install -U --no-use-wheel -r $script_dir/requirements.txt
if [ $? -ne 0 ]; then
    exit 1
fi


if [ "$uninstall" == "Y" ]; then
    while true; do
        $pip freeze | grep ScalrPy >/dev/null
        if [ $? -ne 0 ]; then
            break
        fi
        log "remove old ScalrPy installation"
        $pip uninstall -y ScalrPy
    done
fi


log "complete on $dist $dist_ver"
log "Thank You for using Scalr"

