#!/bin/bash

wget http://stridercd.scalr-labs.com/scalarizr/apt-plain/develop/feature-SCALARIZR-1891-azure/scalarizr-azure_3.7.b216.734db39-1_amd64.deb
wget http://stridercd.scalr-labs.com/scalarizr/apt-plain/develop/feature-SCALARIZR-1891-azure/scalarizr_3.7.b216.734db39-1_amd64.deb
dpkg -i *.deb
service scalarizr start