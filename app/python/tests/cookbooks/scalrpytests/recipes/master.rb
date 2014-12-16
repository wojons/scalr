package "lxc"
package "lxc-templates"
package "redir"
package "python-pip"
package "git"

execute "pip install fabric"

package "apache2" do
    action :remove
end

execute "cat <<EOC >> /home/vagrant/.ssh/config
Host slave
    User vagrant
    UserKnownHostsFile /dev/null
    StrictHostKeyChecking no
    PasswordAuthentication no
    IdentitiesOnly yes
    LogLevel FATAL   
    Port 2022
EOC"

execute "cat <<EOC >> /etc/hosts

127.0.0.1 slave
EOC"
