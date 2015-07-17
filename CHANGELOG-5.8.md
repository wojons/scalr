CHANGELOG for 5.3.2 - 5.8.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 5.4 - 5.8 minor versions.

To get the diff between two versions, go to https://github.com/Scalr/scalr/compare/v5.3.2...v5.8.29

* 5.8.29
 * SCALRCORE-1627 - Add support for RDS "Character Set Name"
 * SCALRCORE-1639 - Openstack:: create image always on Scalr side.
 * SCALRCORE-1465 - Account > Users :: Table header has fixed width
 * SCALRCORE-1469 - Environment > Log > System Log :: Inconsistent column headers
 * SCALRCORE-1488 - Environment > Farms > Roles > Execute Script :: Empty element in a dropdown
 * SCALRCORE-1503 - Environment > Roles > New Role :: empty page after clicking on "Role builder" (for new user)
 * SCALRCORE-1552 - Environment > AWS > Route53 (Hosted Zones) :: Impossible to save edited hosted zone
 * SCALRCORE-1618 - Environment > Roles :: Min servers can be set to 0
 * SCALRCORE-1623 - Environment > Dashboard :: Add button is always active
 * SCALRCORE-1632 - Environment > Servers > Scalr internal messaging :: sorting by SERVER ID doesn't work

* 5.8.28
 * SCALRCORE-1393 - Infinite grid improvements
 * SCALRCORE-1550 - Reload page on moduleUiHash change doesn't work properly
 * SCALRCORE-1603 - Environment > Apache Virtual Hosts :: "Delete" button is active after deleting
 * SCALRCORE-1614 - Environment > Servers :: sorting by SERVER ID doesn't work
 * SCALRCORE-1616 - Environment > Cost Analytics :: grid-chart : not correct TOTAL for previous periods.

* 5.8.27
 * SCALRCORE-1595 - Environment > Servers > Server Status > Load Statistics :: rename October to November on "Yearly" graph
 * SCALRCORE-1597 - Environment > Servers > Server Status > Load Statistics > "Graphs to show" :: unchecking no have effect.
 * SCALRCORE-1598 - Environment > Servers > Server Status > Load Statistics :: not correct message if no graphs
 * SCALRCORE-1605 - Environment > Dashboard :: Widgets can overlap each other
 * SCALRCORE-1607 - Environment > Roles > Edit role > Images > Add Images :: sorting doesn't work

* 5.8.26
 * SCALRCORE-1600 - APIv2 UI improvement
 * SCALRCORE-1608 - Add support for InstanceInitiatedShutdownBehavior flag on EC2
 * RB: update ubuntu 14.04 hvm images
 * SCALRCORE-1583 - Environment :: Farms > Team column cannot be sorted
 * SCALRCORE-1587 - server_terminate: Worker server_terminate failed with ServerNotFoundException
 * SCALRCORE-1602 - Environment > AWS > EC2 EIPS :: active "Delete" button after unchecking

* 5.8.25
 * SCALRCORE-1509 - Add support for Amazon KMS
 * SCALRCORE-1593 - Scalr Admin > About scalr should include hash, branch
 * SCALRCORE-1535 - APIv2 Implement /{envId}/projects/[...] methods
 * SCALRCORE-1586 - Environment > RDS > DB Snapshots :: Script <br /> present in error message
 
* 5.8.24
 * SCALRCORE-1402 - Global Variables categories
 * SCALRCORE-1557 - Environment > AWS > Route53 (Hosted Zones) :: "Record Set Details" frame stays on page when no zone is selected
 * SCALRCORE-1564 - Admin > Global variables :: Locked variables are not displayed

* 5.8.23
 * SCALRCORE-1558 - Add support for Openstack availability zones.
 * SCALRCORE-1561 - Environment > DNS Zones > Default records :: "ADD DNS RECORD" does not work after using search
 * SCALRCORE-1562 - Environment > Roles > New Role > Images :: sorting on "Image" column doesn't work.
 * SCALRCORE-1566 - Environment > Tasks Scheduler > Edit Task :: negative values for "Run Every" metric.

* 5.8.22
 * SCALRCORE-1504 - DB backups interface improvements
 * SCALRCORE-1545 - Environment > AWS > Route53 (Health Checks) :: "Delete" checkbox > not correct behavior
 * SCALRCORE-1547 - Environment > AWS > Route53 (Health Checks) :: new Health Check appear after pushing on "Save" button
 * SCALRCORE-1551 - When using LDAP with specific configuration scalr can duplicate user objects
 * SCALRCORE-1553 - Environment > Dashboard :: Incorrect link redirection

* 5.8.20
 * SCALRCORE-1138 - Rewrite SSL certificates, scaling metrics to new model
 * SCALRCORE-1460 - Environment > SSL Certificates :: Empty SSL Certificates can be saved
 * SCALRCORE-1475 - Environment > Custom Scaling Metrics :: User unfriendly error message

* 5.8.19
 * SCALRCORE-1513 - Cost Analytics > Projects > edit form issue

* 5.8.18
 * Update 14.04 images from RB
 * SCALRCORE-1499 - Environment > Cost Analytics > Environment :: incorrect value of "SPEND (% OF TOTAL) for one day.

* 5.8.17
 * SCALRCORE-1541 - SSL certificates bug
 * Route53 minor changes (removed forceFit, fixed healthChecks toolbar)

* 5.8.16
 * SCALRCORE-1522 - Analytics 4 AWS Detailed billing > belated record issue

* 5.8.15
 * SCALRCORE-1511 - Add support for snapshot tagging (Governance)
 * SCALRCORE-1477 - Account dashboards :: Spinner is placed incorrectly
 * SCALRCORE-1532 - Environment > AWS > Route53 (Health Checks) :: sorting doesn't work.
 * SCALRCORE-1520 - APIv2 x-powered-by and content-type headers

* 5.8.14
 * Add AWS t2.large instance types
 * SCALRCORE-1462 - Account > Account dashboard > Message is needed when user deletes all widgets and columns
 * SCALRCORE-1517 - Environment > Task Scheduler :: Message is missing in the table
 * SCALRCORE-1523 - Environment > Farms > Farm Role > "Add security group to farm role" window :: two similar columns in table
 * SCALRCORE-1524 - Environment > AWS > Route53 :: unexpected behavior off "info" icons and text-link on "Health check details"
 * SCALRCORE-1529 - Environment > DNS zones > Add DNS record :: Resource is not loading

* 5.8.13
 * SCALRCORE-1447 - Account > Dashboard :: Add widget button is blinking
 * SCALRCORE-1514 - Find workaround for ExtJs bug (firefox only)
 * SCALRCORE-1518 - Environment > Tasks > Pop-up message is missing styles
 * SCALRCORE-1521 - Environment > Dashboard ::"AWS health status" widget : sorting doesn't work
 * SCALRCORE-1526 - Environment > DNS Zones :: Delete button wrong behaviour
 * SCALRCORE-1471 - Analytics 4 DataTransfer should count in MB in database

* 5.8.12
 * SCALRCORE-1414 - Combobox: check and fix bug with auto search
 * SCALRCORE-1487 - Environment > Farms > Roles > View Statistics :: permanent "Loading page".
 * SCALRCORE-1489 - Environment > RDS > DB Instance > New DB Instance :: Availability zone 'No preference' cannot be reselected
 * SCALRCORE-1490 - Environment > RDS > DB Instance > New DB Instance :: Non-userfriendly validation on incorrect Initial DB Name
 * SCALRCORE-1497 - Environment > RDS > Parameter groups > Edit group :: UI tries to overwrite default parameter
 * SCALRCORE-1505 - Environment > RDS > DB Instances :: Clipped control for replica
 * SCALRCORE-1506 - Environment > Farms :: search by PROJECT ID doesn't work correctly
 * SCALRCORE-1508 - Environment > DNS Zones ::inconsistency "LAST MODIFIED" and current time zone

* 5.8.11
 * SCALRCORE-1483 - Role drop-down and IP address field in Nginx proxy settings
 * SCALRCORE-1502 - Environment > RDS > DB Instances > Modify :: Unable to save changes

* 5.8.10
 * SCALRCORE-1461 - Scheduler task > load scripts on task load
 * SCALRCORE-1476 - Environment > Roles :: Sorting by Cloud Location doesn't work
 * SCALRCORE-1481 - Analytics 4 Wrong EBS IOPS usage type dimension
 * SCALRCORE-1485 - Account > Teams > ACL dropdown isn't hidden automatically
 * SCALRCORE-1486 - Account > Teams > Default ACL dropdown and table ACL dropdown do not match
 * SCALRCORE-1493 - Orchestration: order does not increment anymore when adding new rule
 * SCALRCORE-1470 - Analytics 4 Empty instance name
 * SCALRCORE-1491 - Add support for new instance types: g2.* and M4
 
* 5.8.9
 * SCALRCORE-1486 Account > Teams > Default ACL dropdown and table ACL dropdown do not match
 * SCALRCORE-1485 Account > Teams > ACL dropdown isn't hidden automatically
 * SCALRCORE-1476 Environment > Roles :: Sorting by Cloud Location doesn't work
 * SCALRCORE-1454 fixed broken links in testenvironment.php;
 * SCALRCORE-1481 fix occurence (gb-hours to operations);
 * SCALRCORE-1493 Orchestration: order does not increment anymore when adding new rule
 
* 5.8.8
 * SCALRCORE-1452 Account > Users :: GUI issue Column header overlapping
 * SCALRCORE-1453 Environment > Upper toolbar (menu) :: User can pin one page several times
 * SCALRCORE-1461 Scheduler task > load scripts on task load
 * SCALRCORE-1466 Account > ACL ::  ACL NAME field can contain only spaces
 
* 5.8.7
 * SCALRCORE-1455 Analytics 4 empty usage in cost distribution type breakdown
 * SCALRCORE-1448 Teams :: Empty element in a dropdown
 * SCALRCORE-1459 Account Management Dashboard :: Billing widget cannot be added
 
* 5.8.4
 * SCALRCORE-1336 get rid of scalr.aws.use_signature_v4
 
* 5.8.3
 * SCALRCORE-1403 Allow override Role Chef environment on FarmRole level
 * SCALRCORE-1439 System (and others) logs filter by date issue
 
* 5.8.2
 * SCALRCORE-1421 Add @functional annotation support to phpunit's TestCase class
 * SCALRCORE-1214 Bug with filterField's form
 * SCALRCORE-1431 Call to a member function IsServerExists() on a non-object
 * SCALRCORE-1422 Event Log Cannot Be Sorted
 * SCALRCORE-1433 Security Groups Menu Should Have a Scrollbar
 * SCALRCORE-1430 Required Global Variables in the Farm Scope Result in Broken Error Message
 * SCALRCORE-1432 Farm Designer Global Variables Panel Does Not Reload After Reopening Farm Designer
 * SCALRCORE-1428 Global Variable Case-Sensitiveness Works Unexpectedly
 * SCALRCORE-1414 Combobox: check and fix bug with auto search
 * Updated ubuntu 14.04 RB images
 * SCALRCORE-1423 Consistency: "Location" columns/fields
 * SCALRCORE-802 Analytics 4 Detailed billing S3 feature
 * SCALARIZR-1809 Debian 8 Support
 
* 5.8.1
 * SCALRCORE-1400 Remove unused fields from scheduler table
 * SCALRCORE-1406 Fix. Team name is too short
 * SCALRCORE-1407 dropdown does not select single value by default
 * SCALRCORE-1408 UI input box height squeezes
 * SCALRCORE-1409 Farm Designer - EBS optimized flag changes

* 5.8.0
 * SCALRCORE-1391 Various UI(mostly css) improvements
   
* 5.7.2
 * SCALRCORE-1383 Cloud location/ec2 region info for terminated servers

* 5.5.0
 * UI-428 Improved validation for Global variables
 * SCALRCORE-1205 Scheduler task time issue
 * SCALRCORE-1216 Grid empty text
 * SCALRCORE-1234 Scheduler UI issues
 * SCALRCORE-1270 Fix Deadlock while removing messages of terminated farms
 * SCALRCORE-1280 Bug with roles rules select
 * SCALRCORE-1261 Nginx proxy settings do not validate against empty destination
 * SCALRCORE-1292 Select record after filtering by id
 * SCALRCORE-1247 Tags refactoring
 * SCALRCORE-1260 Empty SSL certificate in Proxy settings
 * SCALRCORE-1259 Nginx port verification
 * UI-396 New HAProxy features
 * SCALRCORE-1275 Private Global Variables are not supported in the Farm Role Scope
 * SCALRCORE-1299 SSH Launcher: add information about NPAPI Deprecation
 * SCALARIZR-1698 ssl_verify_mode in Chef
 * SCALRCORE-1225 Add governance to RDS
 * SCALRCORE-1318 Chef - Recipe Filtering Does Not Work
 * SCALRCORE-1326 Minor improvement for Scripts page
 * SCALRCORE-1337 Update EBS Configuration Validation To New AWS Limits
 * SCALRCORE-1348 Name column consistency in grids
 * SCALRCORE-1347 Scope filter consistency
 * SCALRCORE-1217 "Add new" buttons behavior
 * SCALRCORE-1340 Storage tab improvements
 * SCALRCORE-1327 Refactor SSH page to new layout
 * SCALRCORE-1204 Menu favorites
 * SCALRCORE-1249 Consistency on pages with two column layout
 * SCALRCORE-1211 applyParams: replace filterIgnoreParams
 * SCALRCORE-1319 Team ownership of Farms
 * SCALRCORE-1370 Associate RDS with Farms
 * SCALRCORE-1372 New layout for RDS instances grid
 * SCALRCORE-1396 Role Cloning does not work: a foreign key constraint fails

* 5.3.3
 * UI-407 New Cloud + location filter
 * SCALRCORE-1161 Use Signature V4 for AWS Services option
 * UI-397 Add warning label regarding chef daemonize on Windows
 * UI-374 Minor improvement on Orchestration tab (FarmRole and Role)
 * UI-426 Ui bugs and issues
 * UI-429 Scalr Agent Update Settings
 * UI-424 New dashboard widget
 * SCALRCORE-1191 CA - Impossible to switch month using calendar dropdown
 * UI-401 Add new scheduler task type
 * SCALRCORE-1119 Improve ServerStatusManager cronjob
 * SCALRCORE-1073 Analytics 3 Consider matching Farm Roles colors
 * SCALRCORE-1142 PropertyFilterIterator for EntityIterator filterBy{PropertyName}
 * UI-412 Orchestration tab target_roles related changes
 * UI-413 Tags refactoring (part 1 - Add governance checkbox "Allow user to specify additional tags")
 * SCALRCORE-1139 Validate ACL permissions for all actions
 * New UI
 * SCALRCORE-863 Cost Analytics 3 phase
