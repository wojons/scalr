CHANGELOG for 5.1.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 5.1 minor versions.

To get the diff between two versions, go to https://github.com/Scalr/scalr/compare/5.0.1...5.1.0

* 5.1.0 
 * UI-342 The SSH Launcher is inconsistently referred to in the UI (igrkio)
 * UI-335 Openstack server type shows as integer in servers view instead of name (igrkio)
 * SCALRCORE-73 Autoupdates UI improvements (igrkio)
 * SCALRCORE-901 Tags in governance (igrkio)
 * SCALRCORE-940 Openstack security groups issue (vlad)
 * SCALRCORE-943 Add authentication AWS Signature Version 4 to library (recipe)
 * SCALARIZR-1541 Add local / public ip switch in Nginx proxy settings (igrkio)
 * UI-314 Instance-type combobox: ignore existing value when typing (igrkio)
 * SCALARIZR-1453 Execute chef-solo in orchestration (igrkio)
 * SCALRCORE-954 Timeline events improvements (vlad)
 * SCALRCORE-957 Fix. API handlers should set request object to DI container (recipe)
 * SCALRCORE-951 AbstractEntity::save() issue. Multiple unique keys against on duplicate key update (recipe)
 * SCALRCORE-950 Custom events improvements (igrkio)
 * SCALRCORE-974 Add support for new EU region (eu-central-1) (recipe)
 * Added Ubuntu 14.04 in eu-central-1 (maratkomarov)
 * UI-334 Refactoring GV (bytekot)
 * SCALRCORE-955 Webhooks scopes (igrkio)
 * SCALRCORE-800 Roles / Images refactoring (invar)
 * SCALRCORE-986 Improve SSH keys manager (igrkio)
 * Added CentOS 6.6 role builder images (except ap-southeast-2 and us-gov-west-1) (maratkomarov)
 * SCALRCORE-992 Improve security + couple minor issues (igrkio)
 * Updated gce centos6 role builder image (maratkomarov)
 * SCALRCORE-952 Validate and repair database schema update (pyscht)
 * Updated RHEL 6.5 images (maratkomarov)
 * SCALRCORE-1014 Fix fatal error in CloudPricing process (recipe)
 * UI-377 Rendering issues on Role Editor Page (igrkio)
 * UI-380 Webhooks improvements (igrkio)
 * SCALRCORE-927 govcloud status dashboard (pyscht)
 * SCALRCORE-1013 OpenStack Instance Type Governance issue (recipe)
 * SCALRCORE-826 Introduce ServerNotFoundException to CloudModule Layer (pyscht)
 * SCALRCORE-1000 MySQL timezone check (pyscht)
