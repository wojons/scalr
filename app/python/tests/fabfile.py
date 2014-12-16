from fabric.api import run, local, cd, lcd, env, put, hosts
from fabric.contrib.files import append

import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
test_dir  = '/home/vagrant/test'


def _create_vagrant_file():
    content = '''
# -*- mode: ruby -*-
# vi: set ft=ruby :

ENV["VAGRANT_DEFAULT_PROVIDER"] = "lxc"

Vagrant.configure("2") do |config|
    config.vm.define 'ubuntu14' do |c|
        c.vm.hostname = "ubuntu14"
        c.vm.box = "ubuntu14-scalrpy"
        c.vm.box_url = "https://s3.amazonaws.com/scalr-labs/ubuntu1404-scalrpy.box"
        c.vm.provision :shell, inline: "apt-get install -y git"
    end
    config.vm.define 'ubuntu12' do |c|
        c.vm.hostname = "ubuntu12"
        c.vm.box = "ubuntu12-scalrpy"
        c.vm.box_url = "https://s3.amazonaws.com/scalr-labs/ubuntu1204-scalrpy.box"
        c.vm.provision :shell, inline: "apt-get install -y git"
    end
    config.vm.define 'ubuntu10' do |c|
        c.vm.hostname = "ubuntu10"
        c.vm.box = "ubuntu10-scalrpy"
        c.vm.box_url = "https://s3.amazonaws.com/scalr-labs/ubuntu1004-scalrpy.box"
        c.vm.provision :shell, inline: "echo 'nameserver 8.8.8.8' >>/etc/resolv.conf"
        c.vm.provision :shell, inline: "apt-get install -y git-core"
    end
    config.vm.define 'debian6' do |c|
        c.vm.hostname = "debian6"
        c.vm.box = "debian6-scalrpy"
        c.vm.box_url = "https://s3.amazonaws.com/scalr-labs/debian6-scalrpy.box"
        c.vm.provision :shell, inline: "apt-get install -y git"
    end
    config.vm.define 'debian7' do |c|
        c.vm.hostname = "debian7"
        c.vm.box = "debian7-scalrpy"
        c.vm.box_url = "https://s3.amazonaws.com/scalr-labs/debian7-scalrpy.box"
        c.vm.provision :shell, inline: "apt-get install -y git"
    end
    config.vm.define 'centos5' do |c|
        c.vm.hostname = "centos5"
        c.vm.box = "centos5-scalrpy"
        c.vm.box_url = "https://s3.amazonaws.com/scalr-labs/centos5-scalrpy.box"
        c.vm.provision :shell, inline: "yum install -y git"
    end
    config.vm.define 'centos6' do |c|
        c.vm.hostname = "centos6"
        c.vm.box = "centos6-scalrpy"
        c.vm.box_url = "https://s3.amazonaws.com/scalr-labs/centos6-scalrpy.box"
        c.vm.provision :shell, inline: "yum install -y git"
    end
    config.vm.define 'test' do |c|
        c.vm.hostname = "test"
        c.vm.box = "scalrpy"
        c.vm.box_url = "https://s3.amazonaws.com/scalr-labs/scalrpy.box"
        c.vm.provision :shell, inline: "apt-get update"
        c.vm.provision :shell, inline: "apt-get install -y git-core"
        c.vm.provision :shell, inline: "/vagrant/install.sh"
    end
end'''
    append('Vagrantfile', content)


def _export():
    archive_name = 'archive.tar.gz'
    archive_path = os.path.join('/tmp', archive_name)

    with lcd(os.path.join(cwd, '..')):
        local("tar -cjf {0} .".format(archive_path))
    run('rm -rf {0}'.format(test_dir))
    run('mkdir {0}'.format(test_dir))
    put(archive_path, test_dir)
    with cd(test_dir):
        run("tar -xf {0}".format(archive_name))

    local('rm -f {0}'.format(archive_path))
    run('rm -f {0}'.format(os.path.join(test_dir, archive_name)))


def _test_install():
    with cd(test_dir):
        _create_vagrant_file()
        boxes = [
            'ubuntu10',
            'ubuntu12',
            'ubuntu14',
            'debian6',
            'debian7',
            'centos5',
            'centos6',
        ]
        for box in boxes:
            try:
                run('vagrant up %s' % box)
                if box == 'centos5':
                    cmd = (
                            '''vagrant ssh {0} -c "sudo /vagrant/install.sh '''
                            '''--custom-python /usr/bin/python26 '''
                            '''--custom-pip /usr/bin/pip2"'''
                    ).format(box)
                    run(cmd)
                else:
                    run('vagrant ssh {0} -c "sudo /vagrant/install.sh"'.format(box))
                packages = [
                    'pytz',
                    'docopt',
                    'boto',
                    'yaml',
                    'gevent',
                    'psutil',
                    'pymysql',
                    'httplib2',
                    'cherrypy',
                    'requests',
                    'rrdtool',
                    'M2Crypto',
                    'Crypto',
                    'OpenSSL',
                    'libcloud',
                    'apiclient',
                ]
                for package in packages:
                    if box == 'centos5':
                        run('''vagrant ssh %s -- "python26 -c 'import %s'"''' % (box, package))
                    else:
                        run('''vagrant ssh %s -- "python -c 'import %s'"''' % (box, package))
                run('''vagrant ssh %s -- "sudo rrdcached"''' % box)
            except:
                raise Exception("Test failed on {0}, reason: {1}".format(box, sys.exc_info()))
            finally:
                run('vagrant destroy -f %s' % box)


def _test_scripts():
    with cd(test_dir):
        _create_vagrant_file()
        scripts = [
            'msg_sender',
            'load_statistics',
            'load_statistics_cleaner',
            'dbqueue_event',
            'szr_upd_service',
            'analytics_processing',
        ]
        run('vagrant up test')
        for script in scripts:
            try:
                cmd = 'vagrant ssh test -c "cd /vagrant/tests/scalrpytests/{0} && sudo lettuce {0}.feature"'
                cmd = cmd.format(script)
                run(cmd)
            except:
                raise Exception("'%s' failed" % script)
        run('vagrant destroy -f test')


def _cleanup():
    run('rm -rf %s' % test_dir)


def test_install():
    _export()
    try:
        _test_install()
    finally:
        _cleanup()
    

def test_scripts():
    _export()
    try:
        _test_scripts()
    finally:
        _cleanup()

