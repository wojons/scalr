#!/bin/bash
#
# Detects OS and installs scalarizr if OS is supported

E_OS_NOT_SUPPORTED=1
E_REPO_UNKNOWN=2
E_PLATFORM_UNKNOWN=3

SUPPORTED_UBUNTU_VERSION="12.04"
SUPPORTED_DEBIAN_VERSION="6.0"
SUPPORTED_CENTOS_VERSION="5.0"
SUPPORTED_RHEL_VERSION="5.0"
SUPPORTED_OEL_VERSION="5.0"
SUPPORTED_AMZN_VERSION="2014.09"

PACKAGE_TO_INSTALL="scalarizr-{{platform}}"

function err() {
    echo $@ >&2
}

function print_usage() {
    echo "Detects OS and installs scalarizr if OS is supported. "
    echo "Usage: install_scalarizr.sh "
    echo "       install_scalarizr.sh --help "
    echo
}

function get_os_version() {
    # debian and ubuntu version key
    OS_VERSION=`cat /etc/os-release 2>/dev/null | awk '/^VERSION_ID/' | \
        sed -e 's/VERSION_ID=//g'`
    if [[ ! $OS_VERSION ]] ; then
        # centos, rhel and amazon version key
        OS_VERSION=`cat /etc/redhat-release /etc/system-release 2>/dev/null | head -1 | \
            awk '{split($0, a, " release "); $0=a[2]; print $1}'`
    fi
    OS_VERSION=`echo $OS_VERSION | sed -e 's/^"//'  -e 's/"$//'`
}

function get_os_name() {
    # ubuntu and debian name key
    OS_NAME=`cat /etc/os-release 2>/dev/null | awk '/^NAME/' | \
        sed -e 's/NAME=//g'`
    if [[ ! $OS_NAME ]] ; then
        # centos, rhel, oel and amazon name key
        OS_NAME=`cat /etc/redhat-release /etc/system-release 2>/dev/null | head -1 | \
            awk '{split($0, a, " release "); print a[1]}'`
        if [[ $OS_NAME =~ ^"Enterprise" ]] ; then
            OS_NAME="OEL"
        fi
    fi
    OS_NAME=`echo $OS_NAME | sed -e 's/^"//' -e 's/"$//'`
}

function os_supported() {
    #.1 is added for greater or equal effect
    [[ ($OS_NAME =~ "Red Hat" && $OS_VERSION.1 > $SUPPORTED_RHEL_VERSION) ||
       ($OS_NAME =~ "CentOS" && $OS_VERSION.1 > $SUPPORTED_CENTOS_VERSION) ||
       ($OS_NAME =~ "Amazon" && $OS_VERSION.1 > $SUPPORTED_AMZN_VERSION) ||
       ($OS_NAME =~ "OEL" && $OS_VERSION.1 > $SUPPORTED_OEL_VERSION) ||
       ($OS_NAME =~ "Ubuntu" && $OS_VERSION.1 > $SUPPORTED_UBUNTU_VERSION) ||
       ($OS_NAME =~ "Debian" && $OS_VERSION.1 > $SUPPORTED_DEBIAN_VERSION) ]]
}

function install() {
    get_os_name
    get_os_version

    if ! os_supported ; then
        err "$OS_NAME $OS_VERSION is not supported. "
        exit $E_OS_NOT_SUPPORTED
    fi

    if [[ $OS_NAME =~ ("Red Hat"|CentOS|OEL|Amazon) ]] ; then
        REPO_FILE='/etc/yum.repos.d/scalr.repo'
        echo '[scalr]' > $REPO_FILE
        echo 'name=Scalr repo' >> $REPO_FILE
        echo 'baseurl={{rpmRepoUrl}}' >> $REPO_FILE
        echo 'enabled=1' >> $REPO_FILE
        echo 'gpgcheck=0' >> $REPO_FILE
        echo 'sslverify=true' >> $REPO_FILE

        yum check-update
        yum install -y -d0 $PACKAGE_TO_INSTALL

    elif [[ $OS_NAME =~ (Ubuntu|Debian) ]] ; then
        apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 04B54A2A
        echo "deb {{debRepoUrl}}" > /etc/apt/sources.list.d/scalr-{{repo}}.list

        apt-get update
        apt-get install -y -q $PACKAGE_TO_INSTALL
    fi
}

BAD_REPO="{{badRepo}}"
BAD_PLATFORM="{{badPlatform}}"

if [[ $1 == "--help" ]] ; then
    print_usage
elif [[ -n $BAD_REPO ]] ; then
    err "Unknown repository"
    exit $E_REPO_UNKNOWN
elif [[ -n $BAD_PLATFORM ]] ; then
    err "Unknown platform"
    exit $E_PLATFORM_UNKNOWN
else
    install
fi
