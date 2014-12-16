#!/bin/bash

LOG=/var/log/role-builder.log
STEP_LOG=/var/log/role-builder-step.log
SCALR_IMPORT_STRING="%SZR_IMPORT_STRING%"
BEHAVIOURS="%BEHAVIOURS%"
PLATFORM="%PLATFORM%"
DEV="%DEV%"
RECIPES="%RECIPES%"
BUILD_ONLY="%BUILD_ONLY%"
SCALARIZR_BRANCH="%SCALARIZR_BRANCH%"

# Chef settings
CHEF_NODENAME=scalr-role-builder-`date +"%s"`

CHEF_SERVER_URL="%CHEF_SERVER_URL%"
CHEF_VALIDATOR_NAME="%CHEF_VALIDATOR_NAME%"
CHEF_VALIDATOR_KEY="%CHEF_VALIDATOR_KEY%"
CHEF_ENVIRONMENT="%CHEF_ENVIRONMENT%"
CHEF_ROLE_NAME="%CHEF_ROLE_NAME%"

# Chef client configuration
CHEF_CLIENT_CNF_TPL="log_level        :info
log_location     STDOUT
node_name		 '$CHEF_NODENAME'
chef_server_url  '$CHEF_SERVER_URL'
environment      '$CHEF_ENVIRONMENT'
validation_client_name '$CHEF_VALIDATOR_NAME'"

# Knife client configuration
KNIFE_RB="chef_server_url  '$CHEF_SERVER_URL'
node_name		 '$CHEF_NODENAME'"


for recipe in $RECIPES; do
	key=`echo $recipe | tr '=' ' ' | awk '{print $1}'`
	value=`echo $recipe | tr '=' ' ' | awk '{print $2}'`
	declare $key=$value
done

CHEF_RUNLIST='{ "scalarizr": { "behaviour": [ "'$(echo $BEHAVIOURS | sed 's/ /\", \"/g')'" ], 
"platform" : "'$PLATFORM'", "branch" : "'$SCALARIZR_BRANCH'"}, "run_list": [ "recipe[base]", '

get_behaviour() {
	bhv="$1"
	if [ -n "${!bhv}" ]; then
		echo "\"recipe[${!bhv}]\", "
	elif [ "$bhv" = "app" ]; then
            echo "\"recipe[apache2]\", "
    elif [ "$bhv" = "mysql" -o "$bhv" = "mysql2" ]; then
            echo "\"recipe[mysql::server]\", "
    elif [ "$bhv" = "www" ]; then
            echo "\"recipe[nginx]\", "
    elif [ "$bhv" = "memcached" ]; then
            echo "\"recipe[memcached]\", "
	elif [ "$bhv" = "postgresql" ]; then
			echo "\"recipe[postgresql]\", "
	elif [ "$bhv" = "redis" ]; then
			echo "\"recipe[redis]\", "
	elif [ "$bhv" = "rabbitmq" ]; then
			echo "\"recipe[rabbitmq]\", "
	elif [ "$bhv" = "mysqlproxy" ]; then
			echo "\"recipe[mysqlproxy]\", "
	elif [ "$bhv" = "mongodb" ]; then
			echo "\"recipe[mongodb]\", "
	elif [ "$bhv" = "percona" ]; then
			echo "\"recipe[percona]\", "
	elif [ "$bhv" = "mariadb" ]; then
			echo "\"recipe[mariadb]\", "
	elif [ "$bhv" = "tomcat" ]; then
			echo "\"recipe[tomcat]\", "
	elif [ "$bhv" = "haproxy" ]; then
			echo "\"recipe[haproxy]\", "			
    fi	
}

for bh in $BEHAVIOURS; do
	recipe=`get_behaviour $bh`
    CHEF_RUNLIST="$CHEF_RUNLIST $recipe"
done

CHEF_RUNLIST="$CHEF_RUNLIST\"recipe[scalarizr]\" ] }"

exec 2>$LOG

action () {
	if tty >/dev/null 2>&1; then
		_col=$(stty -a | grep columns | awk '{print $7}' | sed 's/;//')
	else
		_col=''
	fi
	echo -ne "$1"
	len=${#1}
	eval $2 2>&1 | tee -a $LOG  1>$STEP_LOG
	
	if [ "${PIPESTATUS[0]}" -ne 0 ]; then
		if [ -n "$_col" ]; then
			printf "%$[_col-20-len]s [ Failed ]\r\nSee $LOG fore more info.\r\n"
		else
			printf " [ Failed ]\r\nSee $LOG fore more info.\r\n"
		fi
		exit 1
	else
		if [ -n "$_col" ]; then
			printf "%$[_col-20-len]s [ OK ]\r\n"
		else
			printf " [ OK ]\r\n"
		fi
	fi
	
	echo -e '\r\n\r\n' >> $LOG
}


rhel=$(python -c "import platform; d = platform.dist(); print int(d[0].lower() in ['centos', 'rhel', 'redhat'] and d[1].split('.')[0])")
fedora=$(python -c "import platform; d = platform.dist(); print int((d[0].lower() == 'fedora' or (d[0].lower() == 'redhat' and d[2].lower() == 'werewolf')) and d[1].split('.')[0])")
ubuntu=$(python -c "import platform; d = platform.dist(); print int(d[0].lower() == 'ubuntu')")
amazon=$(grep -i amazon /etc/system-release 2>/dev/null | wc -l)

if [ "$rhel" -eq 0 ] && [ "$fedora" -eq 0 ] && [ "$amazon" -eq 0 ]; then
	if [ "$ubuntu" -eq 1 ]; then
		codename=$(python -c "import platform; d = platform.dist(); print d[2]")
		universe_repos=`grep ^[:space:]*[^#].*$codename.*universe /etc/apt/sources.list`
		if [ -z "$universe_repos" ]; then
			repo="http://us.archive.ubuntu.com/ubuntu/"
			add_to_apt="deb $repo $codename universe\ndeb $repo $codename-updates universe"
			action "Enabling universe repository" 'echo -e "$add_to_apt" >> /etc/apt/sources.list'
		fi
	fi
	action "Installing essential packages" "apt-get update && apt-get install -y curl git-core"
else
	rpm -e rightscale > /dev/null 2>&1 || rpm --noscripts -e rightscale > /dev/null 2>&1
	userdel -r rightscale > /dev/null 2>&1
	rm -rf /etc/rightscale.d > /dev/null 2>&1
	echo -n > /etc/motd 
	if [ "$rhel" -lt 6 ]; then
		action "Removing unnecessary packages" "yum -y remove mysql*"
	fi
	action "Installing curl" "yum -y install curl git-core"
fi

cd /tmp

action "Downloading chef installation script" 'curl -O https://www.opscode.com/chef/install.sh'
action "Installing chef" 'chmod +x install.sh; /tmp/install.sh -v 11.6.0-1'

mkdir -p /tmp/chef-solo
action "Creating chef configuration file" "echo -e 'file_cache_path \"/tmp/chef-solo/cookbooks\"\r\ncookbook_path \"/tmp/chef-solo/cookbooks\"' > /tmp/solo.rb"
action "Retrieving cookbooks from scalr's public repo" "git clone git://github.com/Scalr/cookbooks.git /tmp/chef-solo"
action "Creating runlist" 		'echo $CHEF_RUNLIST | tee /tmp/soft.json'
chef_solo_exec=`which chef-solo`
if [ -z "$chef_solo_exec" ]; then
	chef_solo_exec=`gem contents chef | grep bin/chef-solo | head -1`
fi

# Chef crashes on low-memory instances

memory_total=`grep MemTotal /proc/meminfo | awk '{print $2}'`
if [[ "$memory_total" -lt 1048576 ]]; then
    swapfile=/tmp/swapfile
    fallocate -l1G $swapfile 
    mkswap $swapfile > /dev/null 2>&1
    swapon $swapfile > /dev/null 2>&1
fi

action "Installing software" "$chef_solo_exec -c /tmp/solo.rb -j /tmp/soft.json"

if [ -n "$swapfile" ]; then
    swapoff $swapfile 
    rm -f $swapfile
fi

if [ -n "$CHEF_VALIDATOR_KEY" ]; then
	mkdir -p /etc/chef/
	echo "$CHEF_VALIDATOR_KEY" > /etc/chef/validation.pem
	echo '{"run_list": [ "role['$CHEF_ROLE_NAME']" ]}'  > /tmp/attributes.json
    
	action "Creating chef-client configuration file" 'echo "$CHEF_CLIENT_CNF_TPL" > /etc/chef/client.rb'
	action "Creating new chef API client using validaton key" 'chef-client'
    action "Creating knife configuration" 'echo "$KNIFE_RB" > /etc/chef/knife.rb'

	# Deleting validation key
	rm -f /etc/chef/validation.pem

    function cleanup_chef_node {
        knife node -y -c /etc/chef/knife.rb delete $CHEF_NODENAME > /dev/null 2>&1
        knife client -y -c /etc/chef/knife.rb delete $CHEF_NODENAME > /dev/null 2>&1
    }
    # Add exit hook
    trap cleanup_chef_node EXIT
	
	action "Applying specified runlist" "chef-client --json-attribute /tmp/attributes.json"
	
	# Remove hook
	trap - EXIT
	# Perform cleanup 
	cleanup_chef_node

	rm -f /etc/chef/client.pem
fi


if [ "0" = "$BUILD_ONLY" ]; then
	export PATH=$PATH:/usr/local/bin
	action "Starting importing to Scalr" "$SCALR_IMPORT_STRING &"
	tail -f /var/log/scalarizr.log | while read LINE; do
	        [[ "${LINE}" =~ 'Rebundle complete!' ]] && break
	        [[ "${LINE}" =~ 'Traceback (most recent call last):' ]] && echo "Scalarizr import   [ Failed ]" && exit 1
	done
fi
exit 0
