CHANGELOG for 5.0.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 5.0 minor versions.

To get the diff between two versions, go to https://github.com/Scalr/scalr/compare/v4.5.1...5.0

* 5.0.0-rc.1 (2014-08-15)
 * SCALRCORE-911 OpenStack Security Groups do not work on Nebula / CloudScaling (recipe)
 * SCALRCORE-906 Analytics 2 feedback (vdobrovolskiy)
 * SCALRCORE-903 Add Networking Security Groups & Block Device mapping format support to OpenStack library (recipe)
 * SCALARIZR-1531 Support encrypted EBS volumes in scalarizr.storage2.volumes.ebs (igrkio)
 * UI-334 Refactoring GV (bytekot)
 * SCALRCORE-834 Analytics 2 notifications - reports design (igrkio)
 * SCALARIZR-1405 Improve database initialization in HIR (igrkio)
 * Fix BinaryStream multiple reading hexadecimal value (recipe)
 * SCALRCORE-834 Analytics 2 notifications - reports design (recipe)
 * SCALRCORE-901 Tags in governance (igrkio)
 * Fix vulnerability in dbqueue_event (levsh)
 * SCALRCORE-900 Webhooks improvements (levsh)
 * SCALARIZR-1533 Boot instance EBS-SSD instance (igrkio)
 * UI-342 The SSH Launcher is inconsistently referred to in the UI (igrkio)
 * SCALRCORE-834 Analytics 2 notifications - reports design (vdobrovolskiy)
 * Fix HttpRequest should not share connection between different API requests (recipe)
 * UI-334  Refactoring GV (bytekot)
 * SCALARIZR-1453 Execute chef-solo in orchestration (igrkio)
 * SCALRCORE-824 Analytics 2 notifications script (vdobrovolskiy)
 * UI-327 Input is not validated against HTML entities when echoed back to the user on various pages * Screened error messages (bytekot)
 * Fix cloudstack ssl (levsh)
 * UI-334 Refactoring GV (invar)
 * UI-332 Scriptselectfield filtering issue (bytekot)
 * UI-327 Input is not validated against HTML entities when echoed back to the user on various pages (bytekot)
 * SCALRCORE-886 rotate syslog_ backup tables (recipe)
 * Fix cherrypy ssl error (levsh)
 * UI-330 MongoDB status refactoring * Added shadow to the grid (bytekot)
 * SCALRCORE-829 Add support for CloudStack security groups. (igrkio)
 * SCALRCORE-884 Analytics Create project from farm form. (igrkio)
 * UI-330 MongoDB status refactoring * Refactored (bytekot)
 * Fix. Creating pid for analytics database should not interfere with cost analytics installation. (recipe)
 * Fix. My First LAMP Farm should be associated with default project (recipe)
 * SCALRCORE-825 Analytics Feedback on analytics (recipe)
 * Added GVI to SSH console settings (dicsydel)
 * Fixed webhooks endpoints validation. Should be unique within environment. (dicsydel)
 * SCALRCORE-824 Analytics 2 notifications script (recipe)
 * Updated tracking code by Thomas request (dicsydel)
 * UI-329 Minor changes in role import (igrkio)
 * UI-328 Refactor cluster placement group (igrkio)
 * SCALARIZR-1453 Execute chef-solo in orchestration (igrkio)
 * SCALRCORE-870 Switch AWS EC2 library version to the latest (recipe)
 * Removed obsolete ZohoCrm code (recipe)
 * SCALRCORE-668 * Fixed transmission of tags. Added validation. (bytekot)
 * Fix Scalr API. Handle ProjectID. (recipe)
 * SCALRCORE-825 Feedback on analytics (igrkio)
 * add szr_updc_conn_info (levsh)
 * change configure, instances_connection_policy (levsh)
 * add szr_conn_info (levsh)
 * SCALARIZR-1453 Execute chef-solo in orchestration (igrkio)
 * hardcode .cryptokey path (levsh)
 * Fix Invalid argument supplied for foreach(), in /var/www/my.scalr.net-production/src/Scalr/Stats/CostAnaly tics/Forecast.php:455 (recipe)
 * SCALRCORE-854 Analytics Add Effective date feature to pricing * Fixed bug with incorrect saving of prices after first loading (bytekot)
 * UI-309 add reset code for 2FA (invar)
 * Added new t2.* instance type support (dicsydel)
 * SCALRCORE-871 Analytics Custom period provided with dates from different years (igrkio)
 * SCALRCORE-592 check szr version before update (levsh)
 * improve logging (levsh)
 * SCALRCORE-854 Analytics Add Effective date feature to pricing (recipe)
 * SCALRCORE-841 Analytics 2 Show events on Timeline (recipe)
 * SCALRCORE-854 Analytics Add Effective date feature to pricing * Fixed validation/formatting of prices and few little things (bytekot)
 * SCALRCORE-874 Analytics wrong timestamp is sent (igrkio)
 * SCALRCORE-841 Analytics 2 Show events on Timeline (igrkio)
 * Model refactoring (recipe)
 * SCALRCORE-854 Analytics Add Effective date feature to pricing (bytekot)
 * SCALRCORE-841 Show events on Timeline. (vdobrovolskiy)
 * SCALRCORE-868 Analytics reassign our environments to new cost centres (recipe)
 * SCALRCORE-866 Analytics Unassigned resources review (igrkio)
 * SCALRCORE-841 Show events on Timeline. (recipe)
 * SCALRCORE-866 Analytics Unassigned resources review (recipe)
 * Added support for new gp2 (General Purpose SSD) EBS volumes (dicsydel)
 * add base.upd.schedule (levsh)
 * add load farm and farm_role settings (levsh)
 * Added Amazon Linux 2014.03 images (maratkomarov)
 * SCALRCORE-839 Analytics 2 Add Yearly budget widget (recipe)
 * SCALRCORE-840 Analytics 2 Hover Environment name on farms (igrkio)
 * SCALRCORE-862 Analytics Budget Allocation (igrkio)
 * SCALRCORE-840 Analytics 2 Hover Environment name on farms (recipe)
 * comment gce (levsh)
 * SCALARIZR-1493 Multiple rabbitmq roles in farm (igrkio)
 * SCALRCORE-839 Analytics 2 Add Yearly budget widget (igrkio)
 * SCALRCORE-862 Analytics Budget Allocation (recipe)
 * SCALRCORE-859 Analytics More changes (igrkio)
 * SCALARIZR-1495 Scalarizr Timeouts should be made explicit in the logs and the UI (igrkio)
 * Fix exit code, uncomment analytics dependencies (levsh)
 * SCALARIZR-1412 RAID on GCE. Should it work? (igrkio)
 * comment eucalyptus platform (levsh)
 * SCALRCORE-854  Analytics Add Effective date feature to pricing (recipe)
 * UI-322 Minor changes in interface (igrkio)
 * Fix. Corrected url for CloudPricing parser (recipe)
 * SCALRCORE-860 Analytics Forecasting corrections (recipe)
 * SCALRCORE-842 Analytics Financial admin should be emailed on adding a new Cloud to setup pricing for it (recipe)
 * cron/getprices.php has been replaced with cron/cron.php --CloudPricing (recipe)
 * QueryEnv should send 403 and 400 http headers for now (recipe)
 * Analytics prices should be ordered by name (recipe)
 * GovCloud support for Cost Analytics (recipe)
 * Fix Eucalyptus support in Analytics pricing (recipe)
 * SCALRCORE-853 Replace Cost Centre  with Cost Center in interface ewerywhere (igrkio)
 * Fix EucalyptusPlatformModule::getInstanceTypes not working after changing AWS version (recipe)
 * SCALRCORE-847 Analytics Financial admin must have valid email to be notificated (igrkio)
 * rename daemonize to daemon (levsh)
 * change logging, add send_email func (levsh)
 * SCALRCORE-846 cloud_location in server_properties (recipe)
 * SCALRCORE-843 Add GCE to cost analytics pricing (recipe)
 * SCALRCORE-496  * Improve toArray() function to handle more complicated object structure. (vdobrovolskiy)
 * Fix gce regions (levsh)
 * Fix cloudstack, environment duplication (levsh)
 * SCALRCORE-739 upgrade script should block concurrent upgrades (recipe)
 * Fix openstack (levsh)
 * SCALRCORE-829 Add support for CloudStack security groups. (igrkio)
 * SCALRCORE-739  upgrade script should block concurrent upgrades  * Add locking.  * Remove old cloudstack lib. (vdobrovolskiy)
 * Fix openstack ssl hostname verification (levsh)
 * Fix cloudstack instance_type, configure (levsh)
 * SCALRCORE-833 initialize default CC and Project on install (recipe)
 * Fix cost analytics utc (levsh)
 * SCALRCORE-496  * Fix delete volumes controller action. (vdobrovolskiy)
 * SCALRCORE-825 Feedback on analytics (recipe)
 * SCALRCORE-496 * Hot Fix cloudstack. (vdobrovolskiy)
 * SCALRCORE-636 Added the ability to reflect official AWS pricing changes in UI (bytekot)
 * SCALRCORE-835 Price set by user should not be overridden by automatic routine. (recipe)
 * SCALRCORE-838 vpc router validation (igrkio)
 * SCALRCORE-768 make HttpException message more verbose. (vdobrovolskiy)
 * SCALARIZR-1412 RAID on GCE. (recipe)
 * SCALRCORE-809 VPC v2 UI improvements (igrkio)
 * VPC v2 support (dicsydel)
 * SCALRCORE-496 Rewrite cloudstack library (recipe)
 * Fix spentondate (levsh)
 * UI-316 Script Parameters are presented for Local Scripts (igrkio)
 * SCALRCORE-808 Removing Project or CC (recipe)
 * SCALRCORE-808 Removing Projects or Cost centres (recipe)
 * SCALRCORE-769 Removed old code (bytekot)
 * SCALRCORE-609 remove stats_poller section from config (recipe)
 * SCALRCORE-587 Fix quarterly budget query (levsh)
 * SCALRCORE-587 add spentondate (levsh)
 * SCALRCORE-624 get rid of bin/upgrade_xxx files (recipe)
 * SCALRCORE-769 Add delete custom recordset function to delete them with hosted zones. (vdobrovolskiy)
 * SCALRCORE-805 FiterIterator workaround (recipe)
 * SCALRCORE-807 Fix warnings on route53 controller. (vdobrovolskiy)
 * SCALRCORE-531 Cost Analytics (recipe)
 * SCALRCORE-587 Fix unique managed (levsh)
 * Added X-Forwarded-Proto to nginx server template (maratkomarov)
 * SCALRCORE-815 Cost Analytics Budgets page (igrkio)
 * Added support for GV in apache vhost templates (dicsydel)
 * Bundle all EC2 instances on Scalr via CreateImage (dicsydel)
 * SCALRCORE-769 Add filter to list functions. (vdobrovolskiy)
 * SCALARIZR-1427 Build Ubuntu 14.04 shared role on EC2 (igrkio)
 * SCALRCORE-769 Add health checks improvements. (vdobrovolskiy)
 * SCALRCORE-788 Ec2 instances pagination Fix. (vdobrovolskiy)
 * SCALRCORE-812 DB Model performance optimization (recipe)
 * New SSH console launcher (dicsydel)
 * SCALRCORE-788 Upgrade EC2 Api to 2014-02-01. (vdobrovolskiy)
 * SCALRCORE-587 Fix recalculate, add budget (levsh)
 * change version to update to 2.7.7 (levsh)
 * UI-308 Implemented filtration for TreeStore (bytekot)
 * Added CentOS 6.4 HVM images (maratkomarov)
 * SCALRCORE-587 Fix recalculate (levsh)
 * SCALRCORE-801 Additions to projects (igrkio)
 * SCALRCORE-496 Add CloudStack mock tests. (vdobrovolskiy)
 * SCALRCORE-496 CloudStack add Fixtures test support. (vdobrovolskiy)
 * SCALARIZR-1410 Add Ubuntu 14.04 Roles (igrkio)
 * Added Ubuntu 14.04 images (maratkomarov)
 * SCALRCORE-801 Additions to projects (recipe)
 * Add new ACL resources: AWS_ROUTE53 and ANALYTICS_PROJECTS (recipe)
 * SCALRCORE-769 Route53 delete all function Fix. (vdobrovolskiy)
 * SCALRCORE-748 Support for AWS GovCloud (igrkio)
 * amazon linux images temporarily removed per spyke's request (igrkio)
 * Increase rotate log sleep timeout (recipe)
 * SCALRCORE-496 Cloudstack tests improvements. (vdobrovolskiy)
 * Fix. RotateLogs cron does not cleanup tables properly (recipe)
 * SCALRCORE-697 Financial admin (invar)
 * SCALRCORE-479 Add route53 library implementation. (vdobrovolskiy)
 * Added resizing for top dropdown menu (bytekot)
 * Added support for AWS via proxy (dicsydel)
 * SCALRCORE-636 Implemented pricing cache. (bytekot)
 * SCALRCORE-794 Invalid argument supplied for foreach() (recipe)
 * SCALRCORE-793 Fix. array_key_exists() expects parameter 2 to be array, null given (recipe)
 * SCALRCORE-496 Add a few cloudstack bug Fixes. (vdobrovolskiy)
 * SCALRCORE-792 Fix. Argument 1 passed to Scalr\DataType\Iterator\AggregationRecursiveIterator::__construct() must be of the type array, object given (recipe)
 * SCALRCORE-496 Add cloudstack new implementation. (vdobrovolskiy)
 * SCALRCORE-531 Refactored pricing list (bytekot)
 * Added support for new r3.* instance types (dicsydel)
 * Updated Ubuntu 12.04 images (maratkomarov)
 * SCALRCORE-531  Cost Analytics - alpha release (recipe)
 * Remove app/gearman from code (recipe)
 * add create rrdcached dir (levsh)
 * Added autoupdate for changelog (bytekot)
 * Implemented changelog (bytekot)
 * Improved _buildQuery method of AbstractEntityClass. It should accept table alias as third parameter (recipe)
 * SCALRCORE-723 refactor global variables (invar)
 * add port param to load_statistics:img section (levsh)
 * Support for OpenLDAP (dicsydel)
 * Username attribute for LDAP moved to config to be able to support OpenLDAP (dicsydel)
 * new ssh applet (igrkio)
 * Fix tryCall should perform with one attempt. (recipe)
 * UI-305 Unable to add or change Events on a Webhooks Configuration (igrkio)
 * Fix mail hook url adn headers (dicsydel)
 * Fixed missed ephemeral devices on m3.* instances Improved chef integration (dicsydel)
 * change dbqueue_event section (levsh)
 * dbqueue_event switch to webhook (levsh)
 * SCALRCORE-757 Webhooks system (igrkio)
 * Added 2 new events: InstanceLaunchFailed and HostInitFailed (dicsydel)
 * SCALRCORE-479 Add route53 version date Fix. (vdobrovolskiy)
 * SCALRCORE-479 Add route53 review Fixes and improvements. (vdobrovolskiy)
 * Remove uuid2bin and bin2uuid stored function to database. (recipe)
 * Add uuid2bin and bin2uuid stored function to database. (recipe)
 * SCALRCORE-479 Create client for AWS Route53 API (recipe)
 * Fix rrd dir and test (levsh)
 * SCALRCORE-764 Cloudstack client connection improvement (recipe)
 * SCALRCORE-757 Webhooks system (recipe)
 * Fixed bug with "FarmRole replace" feature where list of available roles was incorrect when you're trying to replace shared role. (dicsydel)
 * SCALRCORE-747 Fixed boxselect style (bytekot)
 * UI-298 Fixed (bytekot)
 * SCALRCORE-747 Implemented tagBox field (bytekot)
 * SCALRCORE-737 use EC2 instance type list from Module_Platforms_Ec2::getInstanceTypes() (igrkio)
 * SCALARIZR-1331 Suspend/Resume support (igrkio)
 * Fix delele pid_file (dicsydel)
 * Load statistics. io, img size, new request, hash (levsh)
 * UI-292 Security Group Creation Error Message is improperly positioned (igrkio)
 * SCALRCORE-752 Fix. Invalid argument supplied for foreach(), in /var/www/my.scalr.net-production/src/Scalr/UI/Controller/Platforms/Gce.php (recipe)
 * SCALRCORE-737 Add getInstanceTypes method to platform modules (recipe)
 * SCALRCORE-746 Add support for GCE static IPs (igrkio)
 * SCALRCORE-748 Support for AWS GovCloud (recipe)
 * UI-296 Save farm buton improvement (igrkio)
 * UI-295 LVM storage settings, forbid saving role with no disks selected (igrkio)
 * UI-294 Role details in Role Library disappear when checkbox removed (igrkio)
 * UI-293 Configure Role In Farm does not work when multiple Farm Roles use the same Role (igrkio)
 * SCALRCORE-742 Fix fatal error undefined constant (recipe)
 * UI-278 Improve statuses visualization (igrkio)
 * SCALRCORE-738 Chef configuration on Role level (igrkio)
 * Fix missing pid file (levsh)
 * Added {SCALR_SERVER_HOSTNAME} and {SCALR_EVENT_SERVER_HOSTNAME} system GVs (dicsydel)
 * %vars% support removed from Chef settings  and added GVs support instead (dicsydel)
 * Add ignoreChanges option to Upgrade system. (recipe)
 * Refactored Platform Modules (recipe)
 * UI-286 Show search field in farm designer on shared roles tab (igrkio)
 * Append new Model to trunk (recipe)
 * added Model (recipe)
 * %vars% support removed from AWS instance name field and added GVs support instead (dicsydel)
 * Added GV support for DNS records Added possibility to automatically create root records with private IPs (dicsydel)
 * SCALRCORE-736: removed $environment dependency from getLocation() platform method (dicsydel)
 * UI-291 ClientException without use namespace detected (igrkio)
 * Add Module_Platforms_Ec2::getInstanceTypes() method. (recipe)
 * SCALRCORE-428 scalarizr update scheduller (igrkio)
 * SCALRCORE-712 Support for Openstack security groups in UI (igrkio)
 * Fix. Launch server for openstack platform should always say 'openstack' (not esc nor nebula or etc...) (recipe)
 * SCALRCORE-730 Illegal string offset 'timeout' (recipe)
 * add custom scalarizr update client port (levsh)
 * Improved output for scripting logs. (dicsydel)
 * SCALRCORE-428 add branch filter (levsh)
 * SCALRCORE-726  Implement Contrail extension for OpenStack library (recipe)
 * ScalrPy version bump 0.0.31 (levsh)
 * add rrdcached_sock_path config option (invar)
 * UI-278 Improve statuses visualization (users, dnszones, schedulertasks, server messages) (igrkio)
 * Eucalyptus support (dicsydel)
 * SCALRCORE-644 Roles manager (ex: roles view page) (igrkio)
 * Updated ECS CentOS6 image (maratkomarov)
 * SCALRCORE-539 NEW_UI New Roles Edit/Create page (igrkio)
 * SCALRCORE-694 Create network tab (igrkio)
 * UI-165 * minor Fixes * integrate 2fa form to login form (invar)
 * SCALRCORE-705 Fix. array_keys() expects parameter 1 to be array, null given, in /var/www/my.scalr.net-production/src/Modules/Platforms/Cloudstack/Cloudstack.php:97 (recipe)
 * SCALRCORE-706 Fix. Blog has transitioned to new address. (recipe)
 * SCALRCORE-656 Fix. Workaround for Couldn't find implementation for method RegexIterator::accept in Unknown on line 0 (recipe)
 * SCALARIZR-1102 Xfs does not work with lvm over raid (igrkio)
 * SCALARIZR-1184 roles list Fix (igrkio)
 * UI-281 retina + FF top menu border issue (igrkio)
 * SCALRCORE-694 Create network tab + SCALRCORE-695 VPC router improvements (igrkio)
 * SCALRCORE-690 Add farm_owner to server_properties. Fix - create foreign key to farm_settings (recipe)
 * SCALRCORE-692 ELB support in VPC (igrkio)
 * SCALRCORE-666 VPC improvements (igrkio)
 * Fix. createLoadBalancer method (recipe)
 * Fix. describeLoadBalancer response does not contain correct vpcId (recipe)
 * TTM-14 show times in timezone of task (invar)
 * Adding index on os_family column to roles table (recipe)
 * SCALRCORE-687 Integrate GCE disks/snapshots/addresses to UI (igrkio)
 * UI-285 New Feature. Added ACL resources: AWS S3 and OpenStack LBaaS (recipe)
 * UI-280 Add Load Balancing feature for OpenStack (bytekot)
 * SCALRCORE-665: GCE integration improvements (dicsydel)
 * Improved Chef support (dicsydel)
 * SCALRCORE-620: New operations support (dicsydel)
 * SCALRCORE-686 Fix. Undefined property: Scalr_Messaging_Msg_DbMsr_CreateBackup::$eventId (recipe)
 * SCALRCORE-456: Be able to select server for backups. (dicsydel)
 * Improvements in VPC support (dicsydel)
 * SCALRCORE-680 Fix. Undefined index: dataBundleType (recipe)
 * SCALRCORE-669 Fix. The first argument should be either a string or an integer, in src/Scalr/Service/Aws/EntityStorage.php:160 (recipe)
 * Fixed SCALRCORE-670: recoverable php error (dicsydel)
 * SCALARIZR-1331: Initial support for stop/resume (dicsydel)
 * SCALRCORE-658 Add Pagination to OpenStack library (recipe)
 * SCALRCORE-650 Support for openstack networks (igrkio)
 * UI-239 xss protection *small email Fixes for lease management (invar)
 * SCALRCORE-76: Add tags to volumes and snapshots lists (dicsydel)
 * Added new AWS instance types (dicsydel)
 * UI-282 Retina + loading content icon (igrkio)
 * SCALRCORE-428: New scalarizr update process (dicsydel)
 * Removed hardcoded links to my.scalr.com (dicsydel)
 * UI-72 Store UI preferences with account instead of cookie * add some debug (invar)
 * SCALRCORE-652 Add support for suspend/resume to Openstack client (recipe)
 * SCALRCORE-643 Windows Get Administrator Password does not work when using a Governance-configured SSH keypair (igrkio)
 * SCALRCORE-637 Removing obsolete scripts (recipe)
 * SCALRCORE-633 remove obsolete Scalr_Net_Ssh2_KeyPair class (recipe)
 * Networks support for Openstack clouds (dicsydel)
 * SCALRCORE-649 Add LBaaS support to OpenStack library (recipe)
 * SCALRCORE-642 retrieve aws accountId from amazon (invar)
 * UI-264 Pop-up cloud settings menu (igrkio)
 * Improving GCE support (dicsydel)
 * Fixed SCALARIZR-1336: Double rebundle message on Euca cloud (dicsydel)
 * Added roles deprecation mechanism (dicsydel)
 * UI-277: Fixed list of Rackspace NG US images list for role builder (dicsydel)
 * UI-245 Update grids from optionscolumn to optionscolumn2 (igrkio)
 * UI-249 Orchestration Rules are not ordered (igrkio)
 * Improved LA based scaling (dicsydel)
 * Fixed storage duplication with disabled old EBS (dicsydel)
 * Fixed issue with re-use storage flag and instances reboot. (dicsydel)
 * SCALRCORE-571 Make all graphics in scalr hi-res (For Retina display) (igrkio)
 * SCALARIZR-1154 Soft reboot thru scalarizr (igrkio)
 * SCALARIZR-1154: Added support for soft reboot thru scalarizr (dicsydel)
 * SCALRCORE-559 improve scripting logs page (invar)
 * UI-122 add default timeout value for scripts (invar)
 * Migration to new version of GCE client (dicsydel)
 * UI-275 Allow to create ec2 security group inside VPC (igrkio)
 * Added new Google API client (dicsydel)
 * SCALRCORE-641 UI for server hostname (igrkio)
 * Fix. Scalr_UI_Request should not be initialized from dbFarm object. (recipe)
 * TTM-11 Missed base configuration in query-env (dicsydel)
 * SCALARIZR-1329 farm designer storage tab for eucalyptus Fixes (igrkio)
 * Add search of the client by identifier of the DBServer in the admin section (recipe)
 * Add validation of the AWS Account ID on saving platform (recipe)
 * SALE-20 User Creation Email text is unhelpful (recipe)
 * UI-272 IAM Governance input does not normalize spaces (igrkio)
 * SCALRCORE-401 refactor & improve Scalr_Service_Ssl_Certificate (invar)
 * Fix Key error 'platform' (levsh)
 * Fix Change defatult bind address for CherryPy (levsh)
 * Fix Can't determine IP (levsh)
 * role-builder Add /usr/local/bin to path before importing scalarizr (CakeOfPiece)
 * Fix Change CherryPy socket_host (levsh)
 * Fix MySQL query (levsh)
 * SCALRCORE-634 Removed old unused classes (dicsydel)
 * SCALRCORE-616 Optimizing log rotate queries. (recipe)
 * UI-164 Farm Designer improvements (igrkio)
 * Perform cleanup for chef node and client (CakeOfPiece)
 * SCALRCORE-570 Ability to specify IAM role for ec2 instances (igrkio)
 * SCALRCORE-570 Added governance support to RunInstances method (dicsydel)
 * SCALRCORE-630 Fix not existing directories for store log and pid files (levsh)
 * deploy scalr release script should remove backup_ tables from structure.sql (recipe)
 * add missing tables (recipe)
 * Fix Set mode to 0755 for new folder (levsh)
 * SCALRCORE-495 Remove unused tables from database schema (recipe)
 * Fix Code formatting More PEP-8 (levsh)
 * Add kill() kill_child() method (levsh)
 * Add create directory /var/lib/rrdcached/journal (levsh)
 * SCALRCORE-570 New feature. Ability to specify IAM role for ec2 instances (recipe)
 * make using of recaptcha optional (invar)
 * UI-265 Mask Global Variable is not greyed out at Farm Role Scope (invar)
 * SCALRCORE-623 Error E_WARNING Division by zero, in /var/www/my.scalr.net-production/src/Scalr/UI/Controller/Servers.php:189 (igrkio)