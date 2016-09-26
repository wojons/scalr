arch = "x86_64"
vagrant_ver = "1.6.3"

vagrant_sources = {
    "1.6.3" => "https://dl.bintray.com/mitchellh/vagrant/vagrant_1.6.3_#{arch}",
}

package "ruby"
package "ruby-dev"
package "patch"
package "make"

remote_file "/tmp/vagrant_#{vagrant_ver}_#{arch}.deb" do
    source "#{vagrant_sources[vagrant_ver]}.deb"
    not_if "test $(vagrant --version | tr ' ' '\n' | tail -n1) == '#{vagrant_ver}'"
    action :create_if_missing
end
package "vagrant" do
    source "/tmp/vagrant_#{vagrant_ver}_#{arch}.deb"
    provider Chef::Provider::Package::Dpkg
    not_if "test $(vagrant --version | tr ' ' '\n' | tail -n1) == '#{vagrant_ver}'"
    action :install
end

execute "su - vagrant -c 'vagrant plugin install vagrant-lxc'" do
    command "su - vagrant -c 'vagrant plugin list | grep vagrant-lxc || vagrant plugin install vagrant-lxc --plugin-version 1.0.0.alpha.2'"
end

execute "su - vagrant -c 'vagrant plugin install vagrant-omnibus'" do
    command "su - vagrant -c 'vagrant plugin list | grep vagrant-omnibus || vagrant plugin install vagrant-omnibus'"
end
