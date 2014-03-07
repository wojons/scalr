Installation Instructions
=========================

Download the installer
----------------------

Log in to the server you'd like to install Scalr on, and run the following
command, preferably as root.

    curl -O https://raw.github.com/Scalr/installer-ng/master/scripts/install.py


Install Scalr
-------------

Run the following, as root.

    python install.py

Note: we recommend that you run this command using GNU screen, so that the
installation process isn't interrupted if your SSH connection drops.


Use Scalr
---------

Visit your server on port 80 to get started. The output of the install script
contains your login credentials.

All generated credentials are also logged to `/root/solo.json`, so you can
retrieve them there.


Supported OSes
==============

  + Ubuntu 12.04
  + RHEL 6
  + CentOS 6
