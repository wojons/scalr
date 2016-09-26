#cloud-config

apt_update: true
apt_sources:
    - source: "deb {DEB_REPO_URL}"
      keyid: 04B54A2A
      filename: scalr-release.list

yum_repos:
    scalr:
        baseurl: {RPM_REPO_URL}
        enabled: true
        gpgcheck: false
        name: Scalr

packages:
    scalarizr-ec2

write_files:
    - path: /etc/.scalr-user-data 
      owner: root:root
      permissions: '0440'
      content: |
        {SCALR_USER_DATA}

runcmd:
 - [ service, scalr-upd-client, start ]