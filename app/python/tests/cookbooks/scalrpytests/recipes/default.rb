execute "fix resolv.conf" do
    command "echo 'nameserver 8.8.8.8' > /etc/resolv.conf"
    action :run
end

cookbook_file "/home/vagrant/.ssh/id_rsa" do
    source "id_rsa"
    mode 0400
    owner "vagrant"
    group "vagrant"
end

cookbook_file "/home/vagrant/.ssh/id_rsa.pub" do
    source "id_rsa.pub"
    mode 0400
    owner "vagrant"
    group "vagrant"
end

cookbook_file "/home/vagrant/.ssh/config" do
    source 'ssh_config'
    owner "vagrant"
    group "vagrant"
end
