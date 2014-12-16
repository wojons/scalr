#!/bin/bash

function usage()
{
cat << EOF
usage: $0 [OPTIONS]

OPTIONS:
   -h, --help       Show this message
   --install        Test install script
   --scripts        Test ScalrPy scripts
EOF
}


function log_err()
{
    echo -e "\033[91m[ERROR]\033[0m $1"
}


function log()
{
    echo -e "\033[92m[INFO]\033[0m $1"
}


root_dir=$PWD
install="N"
scripts="N"


while [[ $# > 0 ]]
do
    key="$1"
    shift

    case $key in
        -h|--help)
        usage
        exit 0
        ;;
        --install)
        install="Y"
        shift
        ;;
        --scripts)
        scripts="Y"
        shift
        ;;
        *)
        usage
        exit 1
        ;;
    esac
done


if [ "$install" == "N" ] && [ "$scripts" == "N" ]; then
    usage
    exit 1
fi

start_time=$(python -c "import time;print int(time.time())")

log "vagrant up slave"
vagrant up --provision slave
if [ $? -ne 0 ]; then
    log_err "slave up error"
    exit 1
fi

if [ "$install" == "Y" ]; then
    log "test install"
    fab -i cookbooks/scalrpytests/files/default/id_rsa -H vagrant@127.0.0.1:2022 test_install
    if [ $? -ne 0 ]; then
        log_err "test install failed"
    fi
fi

if [ "$scripts" == "Y" ]; then
    log "test scripts"
    fab -i cookbooks/scalrpytests/files/default/id_rsa -H vagrant@127.0.0.1:2022 test_scripts
    if [ $? -ne 0 ]; then
        log_err "test scripts failed"
    fi
fi


vagrant halt slave
if [ $? -ne 0 ]; then
    log_err "slave shutdown error"
    exit 1
fi


end_time=$(python -c "import time;print int(time.time())")
log "$(python -c "test_time=$end_time-$start_time;print 'all tests done in %s seconds' % test_time")"

