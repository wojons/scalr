CHANGELOG for 5.3.2 - 5.8.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 5.4 - 5.8 minor versions.

To get the diff between two versions, go to https://github.com/Scalr/scalr/compare/v5.3.2...v5.8.29

* 5.8.29
 * SCALRCORE-1627 - Add support for RDS "Character Set Name"
 * SCALRCORE-1639 - Openstack:: create image always on Scalr side.
 * SCALRCORE-1444 - Dashboard widget shows adodb error
 * SCALRCORE-1599 - The use statement with non-compound name 'SERVER_STATUS' has no effect
 * SCALRCORE-1465 - Account > Users :: Table header has fixed width
 * SCALRCORE-1469 - Environment > Log > System Log :: Inconsistent column headers
 * SCALRCORE-1488 - Environment > Farms > Roles > Execute Script :: Empty element in a dropdown
 * SCALRCORE-1503 - Environment > Roles > New Role :: empty page after clicking on "Role builder" (for new user)
 * SCALRCORE-1552 - Environment > AWS > Route53 (Hosted Zones) :: Impossible to save edited hosted zone
 * SCALRCORE-1618 - Environment > Roles :: Min servers can be set to 0
 * SCALRCORE-1623 - Environment > Dashboard :: Add button is always active
 * SCALRCORE-1632 - Environment > Servers > Scalr internal messaging :: sorting by SERVER ID doesn't work
 * SCALRCORE-1640 - Openstack ip-pool bug

* 5.8.28
 * SCALRCORE-1393 - Infinite grid improvements
 * SCALRCORE-1295 - Create server snapshot bug
 * SCALRCORE-1550 - Reload page on moduleUiHash change doesn't work properly
 * SCALRCORE-1603 - Environment > Apache Virtual Hosts :: "Delete" button is active after deleting
 * SCALRCORE-1614 - Environment > Servers :: sorting by SERVER ID doesn't work
 * SCALRCORE-1616 - Environment > Cost Analytics :: grid-chart : not correct TOTAL for previous periods.

* 5.8.27
 * SCALRCORE-1590 - Analytics 4 belated record issue #2
 * SCALRCORE-1588 - Analytics 4 AWS billing for environments N, month 7 failed
 * SCALRCORE-1595 - Environment > Servers > Server Status > Load Statistics :: rename October to November on "Yearly" graph
 * SCALRCORE-1597 - Environment > Servers > Server Status > Load Statistics > "Graphs to show" :: unchecking no have effect.
 * SCALRCORE-1598 - Environment > Servers > Server Status > Load Statistics :: not correct message if no graphs
 * SCALRCORE-1605 - Environment > Dashboard :: Widgets can overlap each other
 * SCALRCORE-1607 - Environment > Roles > Edit role > Images > Add Images :: sorting doesn't work
 * SCALRCORE-1613 - PHP Fatal error: Call to a member function GetSetting() on a non-object in app/src/Scalr/Modules/Platforms/Ec2/Ec2PlatformModule.php on line 1842

* 5.8.26
 * SCALRCORE-1600 - APIv2 UI improvement
 * SCALRCORE-1608 - Add support for InstanceInitiatedShutdownBehavior flag on EC2
 * RB: update ubuntu 14.04 hvm images
 * SCALRCORE-1583 - Environment :: Farms > Team column cannot be sorted
 * SCALRCORE-1587 - server_terminate: Worker server_terminate failed with ServerNotFoundException
 * SCALRCORE-1602 - Environment > AWS > EC2 EIPS :: active "Delete" button after unchecking
 * SCALRCORE-1606 - No 'get adminstrator password' option for windows 'server status' actions menu
 * SCALRCORE-1303 - rewrite getDefaultEnvironment() method

* 5.8.25
 * SCALRCORE-1509 - Add support for Amazon KMS
 * SCALRCORE-1593 - Scalr Admin > About scalr should include hash, branch
 * SCALRCORE-1535 - APIv2 Implement /{envId}/projects/[...] methods
 * SCALRCORE-1586 - Environment > RDS > DB Snapshots :: Script <br /> present in error message
 
* 5.8.24
 * SCALRCORE-1402 - Global Variables categories
 * SCALRCORE-1425 - APIv2 add GV categories
 * SCALRCORE-1557 - Environment > AWS > Route53 (Hosted Zones) :: "Record Set Details" frame stays on page when no zone is selected
 * SCALRCORE-1564 - Admin > Global variables :: Locked variables are not displayed

* 5.8.23
 * SCALRCORE-1558 - Add support for Openstack availability zones.
 * SCALRCORE-1289 - APIv2 Implement /:envId/scripts API methods
 * SCALRCORE-1510 - Add Amazon KMS support to AWS Client
 * SCALRCORE-1416 -  Remove SNMP dependency test
 * SCALRCORE-1561 - Environment > DNS Zones > Default records :: "ADD DNS RECORD" does not work after using search
 * SCALRCORE-1562 - Environment > Roles > New Role > Images :: sorting on "Image" column doesn't work.
 * SCALRCORE-1566 - Environment > Tasks Scheduler > Edit Task :: negative values for "Run Every" metric.

* 5.8.22
 * SCALRCORE-1504 - DB backups interface improvements
 * SCALRCORE-1545 - Environment > AWS > Route53 (Health Checks) :: "Delete" checkbox > not correct behavior
 * SCALRCORE-1547 - Environment > AWS > Route53 (Health Checks) :: new Health Check appear after pushing on "Save" button
 * SCALRCORE-1551 - When using LDAP with specific configuration scalr can duplicate user objects
 * SCALRCORE-1553 - Environment > Dashboard :: Incorrect link redirection
 * SCALRCORE-1554 - Fix SQL debug in UI
 * SCALRCORE-1556 - role_categories should be in data.sql
 * SCALRCORE-1546 - Analytics 4 Bandwidth wrong numbers

* 5.8.21
 * Analytics 4 Fix detailed billing analytics processor

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
 * SCALRCORE-1426 - APIv2 import swagger.yaml into scalr
 * SCALRCORE-1537 - Add Azure icon to cloud list
 * SCALRCORE-1541 - SSL certificates bug
 * Route53 minor changes (removed forceFit, fixed healthChecks toolbar)

* 5.8.16
 * SCALRCORE-1522 - Analytics 4 AWS Detailed billing > belated record issue

* 5.8.15
 * Release Date: 22/Jun/15
 * SCALRCORE-1511 - Add support for snapshot tagging (Governance)
 * Add VMWare VIO icon to the list of supported clouds
 * SCALRCORE-1477 - Account dashboards :: Spinner is placed incorrectly
 * SCALRCORE-1532 - Environment > AWS > Route53 (Health Checks) :: sorting doesn't work.
 * SCALRCORE-1536 - E_WARNING yaml_emit_file(app/cache/v1beta0/user-autogenerated.yaml): failed to open stream: No such file or directory, in app/src/Scalr/Util/Api/Describer.php:118
 * SCALRCORE-1520 - APIv2 x-powered-by and content-type headers

* 5.8.14
 * Add AWS t2.large instance types
 * SCALRCORE-1462 - Account > Account dashboard > Message is needed when user deletes all widgets and columns
 * SCALRCORE-1517 - Environment > Task Scheduler :: Message is missing in the table
 * SCALRCORE-1523 - Environment > Farms > Farm Role > "Add security group to farm role" window :: two similar columns in table
 * SCALRCORE-1524 - Environment > AWS > Route53 :: unexpected behavior off "info" icons and text-link on "Health check details"
 * SCALRCORE-1529 - Environment > DNS zones > Add DNS record :: Resource is not loading
 * SCALRCORE-1530 - Analytics Fiscal Quarter 1 Feb issue

* 5.8.13
 * SCALRCORE-1447 - Account > Dashboard :: Add widget button is blinking
 * SCALRCORE-1514 - Find workaround for ExtJs bug(firefox only)
 * SCALRCORE-1518 - Environment > Tasks > Pop-up message is missing styles
 * SCALRCORE-1521 - Environment > Dashboard ::"AWS health status" widget : sorting doesn't work
 * SCALRCORE-1526 - Environment > DNS Zones :: Delete button wrong behaviour
 * SCALRCORE-1528 - initialize OS table for new install
 * SCALRCORE-1471 - Analytics 4 DataTransfer should count in MB in database

* 5.8.12
 * SCALRCORE-1414 - Combobox: check and fix bug with auto search
 * SCALRCORE-1487 - Environment > Farms > Roles > View Statistics :: permanent "Loading page".
 * SCALRCORE-1489 - Environment > RDS > DB Instance > New DB Instance :: Availability zone 'No preference' cannot be reselected
 * SCALRCORE-1490 - Environment > RDS > DB Instance > New DB Instance :: Non-userfriendly validation on incorrect Initial DB Name
 * SCALRCORE-1497 - Environment > RDS > Parameter groups > Edit group :: UI tries to overwrite default parameter
 * SCALRCORE-1501 - database name in upgrade script
 * SCALRCORE-1505 - Environment > RDS > DB Instances :: Clipped control for replica
 * SCALRCORE-1506 - Environment > Farms :: search by PROJECT ID doesn't work correctly
 * SCALRCORE-1508 - Environment > DNS Zones ::inconsistency "LAST MODIFIED" and current time zone

* 5.8.11
 * SCALRCORE-1483 - Role drop-down and IP address field in Nginx proxy settings
 * SCALRCORE-1502 - Environment > RDS > DB Instances > Modify :: Unable to save changes
 * SCALRCORE-1492 - Error in upgrade

* 5.8.10
 * SCALRCORE-1371 - Issue with OpenStack Load Balancers
 * SCALRCORE-1461 - Scheduler task > load scripts on task load
 * SCALRCORE-1476 - Environment > Roles :: Sorting by Cloud Location doesn't work
 * SCALRCORE-1481 - Analytics 4 Wrong EBS IOPS usage type dimension
 * SCALRCORE-1485 - Account > Teams > ACL dropdown isn't hidden automatically
 * SCALRCORE-1486 - Account > Teams > Default ACL dropdown and table ACL dropdown do not match
 * SCALRCORE-1493 - Orchestration: order does not increment anymore when adding new rule
 * SCALRCORE-1494 - Farm Designer name issue
 * SCALRCORE-1470 - Analytics 4 Empty instance name
 * SCALRCORE-1491 - Add support for new instance types: g2.* and M4
 * SCALRCORE-1353 - APIv2 Implement events methods
 
* 5.8.9
 * Fix Governance no longer works (igrkio)
 * SCALRCORE-1371 hide openstack load balancers menu (invar)
 * SCALRCORE-1486 Account > Teams > Default ACL dropdown and table ACL dropdown do not match (igrkio)
 * SCALRCORE-1485 Account > Teams > ACL dropdown isn't hidden automatically (igrkio)
 * SCALRCORE-1476 Environment > Roles :: Sorting by Cloud Location doesn't work (igrkio)
 * SCALRCORE-1454 fixed broken links in testenvironment.php; (koloda)
 * SCALRCORE-1481 fix occurence (gb-hours to operations); (koloda)
 * SCALRCORE-1493 Orchestration: order does not increment anymore when adding new rule (igrkio)
 * SCALRCORE-1494 FArmDesigner name issue (igrkio)
 
* 5.8.8
 * SCALRCORE-1452 Account > Users :: GUI issue Column header overlapping (bytekot)
 * SCALRCORE-1453 Environment > Upper toolbar (menu) :: User can pin one page several times (igrkio)
 * SCALRCORE-1461 Scheduler task > load scripts on task load (bytekot)
 * SCALRCORE-1466 Account > ACL ::  ACL NAME field can contain only spaces (igrkio) 
 
* 5.8.7
 * SCALRCORE-1455 Analytics 4 empty usage in cost distribution type breakdown (igrkio)
 * SCALRCORE-1448 Teams :: Empty element in a dropdown (igrkio)
 * SCALRCORE-1459 Account Management Dashboard :: Billing widget cannot be added (igrkio)
 * SCALRCORE-1457 ALL Division by zero, in app/src/Scalr/Stats/CostAnalytics/Forecast.php:415 (recipe) 
 
* 5.8.6
 * SCALRCORE-1445 E_ALL in_array() expects parameter 2 to be array, null given, in src/Scalr/Stats/CostAnalytics/Usage.php:3003 (recipe) 
 
* 5.8.4
 * SCALRCORE-1398 Most noticable interface improvements (igrkio)
 * SCALRCORE-1336 get rid of scalr.aws.use_signature_v4 (p5ych0)
 * SCALRCORE-1353 APIv2 Implement events methods (vdobrovolskiy) 
 
* 5.8.3
 * SCALRCORE-1403 Allow override Role Chef environment on FarmRole level (igrkio)
 * SCALRCORE-1439 System (and others) logs filter by date issue (bytekot) 
 
* 5.8.2
 * SCALRCORE-1421 Add @functional annotation support to phpunit's TestCase class (recipe)
 * SCALRCORE-1214 Bug with filterField's form (bytekot)
 * SCALRCORE-1431 Call to a member function IsServerExists() on a non-object (recipe)
 * SCALRCORE-1422 Event Log Cannot Be Sorted (igrkio)
 * SCALRCORE-1433 Security Groups Menu Should Have a Scrollbar (igrkio)
 * SCALRCORE-1430 Required Global Variables in the Farm Scope Result in Broken Error Message (igrkio)
 * GE-73 Windows - Suspend Button Enabled (igrkio)
 * SCALRCORE-1432 Farm Designer Global Variables Panel Does Not Reload After Reopening Farm Designer (bytekot)
 * SCALRCORE-1428 Global Variable Case-Sensitiveness Works Unexpectedly (bytekot)
 * SCALRCORE-1414 Combobox: check and fix bug with auto search (bytekot)
 * Updated ubuntu 14.04 RB images (maratkomarov)
 * SCALRCORE-1402 Global Variables categories (bytekot)
 * SCALRCORE-1423 Consistency: "Location" columns/fields (bytekot)
 * SCALRCORE-1426 import swagger.yaml into scalr (pyscht)
 * SCALRCORE-802 Analytics 4 Detailed billing S3 feature (vdobrovolskiy)
 * Update chef_import.tpl (CakeOfPiece)
 * Role builder tpl updated (indent using spaces, ubuntu universe repos) (CakeOfPiece)
 * SCALARIZR-1809 Debian 8 Support (igrkio)
 * SCALRCORE-1440 Cron > ScalarizrMessaging > OpenStack module > PHP Fatal error: Call to a member function getName() (recipe) 
 
* 5.8.1
 * SCALRCORE-1400 Remove unused fields from scheduler table (invar)
 * SCALRCORE-1406 Fix. Team name is too short (recipe)
 * SCALRCORE-1407 dropdown does not select single value by default (igrkio)
 * SCALRCORE-1408 UI input box height squeezes (igrkio)
 * SCALRCORE-1409 Farm Designer - EBS optimized flag changes (igrkio)

* 5.8.0
 * SCALRCORE-1391 Various UI(mostly css) improvements (igrkio)
   
* 5.7.2
 * SCALRCORE-1289 scripts API methods (pyscht)
 * SCALRCORE-1383 Cloud location/ec2 region info for terminated servers (vdobrovolskiy)

* 5.5.0
 * SCALRCORE-1198 Fix error: Property "value" does not exist for the class "Scalr\Model\Entity\ServerTerminationError" (recipe)
 * UI-428 Improved validation for Global variables (vdobrovolskiy)
 * SCALRCORE-1205 Scheduler task time issue (bytekot)
 * SCALRCORE-1200 OS initialization upgrade script (recipe)
 * SCALRCORE-1210 RDS: mark all manual snapshots (vdobrovolskiy)
 * SCALRCORE-1222 RDS: deleting manual snapshots only allowed (bytekot)
 * SCALRCORE-1216 Grid empty text (bytekot)
 * SCALRCORE-1154 Modify role_images unique key (pyscht)
 * SCALRCORE-1230 GV: improve variableField (bytekot)
 * SCALRCORE-1148 Initial values for  `images`.`dtadded` for ordering (pyscht)
 * Update Cost Center / Project default labels (krallin)
 * SCALRCORE-1234 Scheduler UI issues (bytekot)
 * Track exceptions in UI controllers into default php log (recipe)
 * SCALRCORE-1251 Add governance to RDS: back-end part (bytekot)
 * SCALRCORE-1194 APIv2 Implement global-variables controller (vdobrovolskiy)
 * SCALRCORE-1224 E_USER_WARNING Cloudstack error. Unexpected stdObject class received in property: resourcedetails app/src/Scalr/Service/CloudStack/Services/Zone/V26032014/ZoneApi.php:118 (vdobrovolskiy)
 * SCALRCORE-1218 New widget on account scope (igrkio)
 * SCALRCORE-1256 RDS Security groups must be used (bytekot)
 * SCALRCORE-1268 UI - Move "Get Administrator Password" (igrkio)
 * SCALRCORE-1269 Poor Layout on Environments Screen (igrkio)
 * Remove deprecated classes Scalr_Service_Cloud_Openstack (recipe)
 * SCALRCORE-1271 Use Role Alias in SSH Launcher page (igrkio)
 * SCALRCORE-1270 Fix Deadlock while removing messages of terminated farms (recipe)
 * SCALRCORE-1277 phpunit test could not find RESOURCE_SERVICES_CHEF constant (igrkio)
 * SCALRCORE-1272 Farm settings tab / Farm name (igrkio)
 * SCALRCORE-1280 Bug with roles rules select (igrkio)
 * SCALRCORE-1282 API LDAP Authorization Does Not Work (vdobrovolskiy)
 * SCALRCORE-1284 *  deselect records, which were hidden by local filter (invar)
 * SCALRCORE-1285 Previous and Next Links in APIv2 - Hostname and Port (vdobrovolskiy)
 * SCALRCORE-1042 Improvements on VPC Router Role Error Message (igrkio)
 * SCALRCORE-1279 Server Internal Errors respond with error envelope (pyscht)
 * SCALRCORE-1261 Nginx proxy settings do not validate against empty destination (igrkio)
 * SCALRCORE-1292 Select record after filtering by id (bytekot)
 * SCALRCORE-1288 APIv2 DELETE success response (vdobrovolskiy)
 * SCALRCORE-1287 APIv2 Deleting an Image shouldn't work if it's used by a Role (vdobrovolskiy)
 * SCALRCORE-1247 Tags refactoring (igrkio)
 * SCALRCORE-1257 Multiple minor fixes (igrkio)
 * SCALRCORE-1260 Empty SSL certificate in Proxy settings (igrkio)
 * SCALRCORE-1259 Nginx port verification (igrkio)
 * SCALRCORE-1294 Booleans are represented as ints (vdobrovolskiy)
 * UI-396 New HAProxy features (igrkio)
 * SCALRCORE-1301 Security vulnerability (recipe)
 * SCALRCORE-1275 Private Global Variables are not supported in the Farm Role Scope (bytekot)
 * SCALRCORE-1147 Refactor env_id and client_id columns of roles table (pyscht)
 * SCALRCORE-1299 SSH Launcher: add information about NPAPI Deprecation (invar)
 * SCALRCORE-1310 Change SSH Launcher icon visibility conditions (igrkio)
 * SCALARIZR-1698 ssl_verify_mode in Chef (igrkio)
 * SCALRCORE-1225 Add governance to RDS (vdobrovolskiy)
 * Remove 'eucalyptus' support in CA and SCALRCORE-1316 (levsh)
 * SCALRCORE-1322 Change EC2 VPC governance beahavior (igrkio)
 * SCALRCORE-1311 New read-only objects design (igrkio)
 * SCALRCORE-1314 Rotate crypto key script (pyscht)
 * SCALRCORE-1318 Chef - Recipe Filtering Does Not Work (igrkio)
 * SCALRCORE-1235 Descriptions for GVs (igrkio)
 * SCALRCORE-1330 account_tag_values mysql error fix (pyscht)
 * SCALRCORE-1312 Remove admin logs (bytekot)
 * SCALRCORE-1328 Replace strings with constants in rds/instances (vdobrovolskiy)
 * SCALRCORE-1325 Improvements in RDS client library (vdobrovolskiy)
 * SCALRCORE-1332 corrections in tests (vdobrovolskiy)
 * SCALRCORE-1313 zmq should be mandatory in testenvironment.php (vdobrovolskiy)
 * SCALRCORE-1333 fix account_tag_values (pyscht)
 * SCALRCORE-1306 APIv2 Improve API keys UI (igrkio)
 * SCALRCORE-1326 Minor improvement for Scripts page (bytekot)
 * SCALRCORE-1321 consecutive start of roles in the farm (recipe)
 * SCALRCORE-1334 Global Variable Colors For Locked Variables Are Incorrect (bytekot)
 * Merge origin/master into scalrcore-1276 (igrkio)
 * SCALRCORE-1324 APIv2 replace version with /v1beta0 (recipe)
 * SCALRCORE-1276 Scripts improvements (igrkio)
 * SCALRCORE-1335 Fix 'Undefined index: description' error in GV unit tests (igrkio)
 * JPL-31 Prevents from error: Column 'cc_id' cannot be null (recipe)
 * SCALRCORE-1304 load config unserialize error resolution (recipe)
 * SCALRCORE-1337 Update EBS Configuration Validation To New AWS Limits (igrkio)
 * SCALRCORE-1307 APIv2 Corrections on feedback 2 (vdobrovolskiy)
 * SCALRCORE-1265 fix session warning (invar)
 * SCALRCORE-1320 lease management improvement (invar)
 * SCALRCORE-1339 Bug with selectedmodel (igrkio)
 * SCALRCORE-1342 Project Save Error Results in Email (igrkio)
 * SCALRCORE-780 add lease statistics (invar)
 * SCALRCORE-1346 Dashboard farms widget icon bug (igrkio)
 * SCALRCORE-1348 Name column consistency in grids (bytekot)
 * SCALRCORE-1347 Scope filter consistency (bytekot)
 * SCALRCORE-1341 APIv2 add support of GV description (vdobrovolskiy)
 * SCALRCORE-1217 "Add new" buttons behavior (bytekot)
 * SCALRCORE-1343 refactor SshKey model (invar)
 * SCALRCORE-1356 Backward compatibility of shared-roles (pyscht)
 * SCALRCORE-1245 "Add new" buttons behavior (bytekot)
 * SCALRCORE-1244 Make governance settings permanently available on client (igrkio)
 * SCALRCORE-1317 APIv2 error messages corrections (vdobrovolskiy)
 * SCALRCORE-1340 Storage tab improvements (igrkio)
 * SCALRCORE-1213 Collect field icons (igrkio)
 * SCALRCORE-1130 "My IP" in SG Editor does not work when reverse proxy is in use (igrkio)
 * SCALRCORE-1364 VPC governance minor improvement (igrkio)
 * SCALRCORE-1300 APIv2 Adjust filtering according to recent changes in spec (vdobrovolskiy)
 * SCALRCORE-1215 Pricing: handle moving between sections (bytekot)
 * SCALRCORE-1283 Tagfield: bugs and issues (bytekot)
 * SCALRCORE-1183 security issue (XSS) (bytekot)
 * SCALRCORE-1367 GV in farm builder (bytekot)
 * GE-59 RDS Security Group Governance (bytekot)
 * Fixed GE-61: ELB list in Farm designer shows only first 20 LBs. (discydel)
 * Code that used to tie cloud resources with Farms/Farm roles has been refactored. (discydel)
 * SCALRCORE-1327 Refactor SSH page to new layout (bytekot)
 * SCALRCORE-1204 Menu favorites (igrkio)
 * SCALRCORE-1308 Add support for agentless servers (UI) (igrkio)
 * SCALRCORE-1379 fix OS sort order (invar)
 * SCALRCORE-1365 Add beta support for new SSH app (igrkio)
 * SCALRCORE-1380 Create server snapshot on Windows instances (igrkio)
 * SCALRCORE-1381 E_WARNING Invalid argument supplied for foreach(), in app/src/Scalr/UI/Controller/Platforms/Cloudstack.php:236 (igrkio)
 * SCALRCORE-1369 RDS improvements (vdobrovolskiy)
 * SCALRCORE-1384 ApiErrorException should not be thrown outside API Controller (recipe)
 * GE-62 Unable to Add Storage on Windows (igrkio)
 * SCALRCORE-1390 Cloudstack platform module issue. (vdobrovolskiy)
 * SCALRCORE-1249 Consistency on pages with two column layout (bytekot)
 * SCALRCORE-1211 applyParams: replace filterIgnoreParams (bytekot)
 * SCALRCORE-1231 Load stats: loading message (bytekot)
 * SCALARIZR-1826 fix action menu (invar)
 * SCALRCORE-1392 Scripting log name increase column size (recipe)
 * SCALRCORE-1319 Team ownership of Farms (invar)
 * Add ./tools/warmup-cache.php (recipe)
 * SCALRCORE-1395 support for use_proxy to Openstack clouds (pyscht)
 * SCALRCORE-1370 Associate RDS with Farms (igrkio)
 * SCALRCORE-1372 New layout for RDS instances grid (bytekot)
 * SCALRCORE-1396 Role Cloning does not work: a foreign key constraint fails (recipe)

* 5.3.3
 * UI-407 New Cloud + location filter (igrkio)
 * SCALRCORE-1160 Fix Cannot obtain endpoint url. Unavailable service "network" of "2" version (recipe)
 * SCALRCORE-1161 Use Signature V4 for AWS Services option (recipe)
 * UI-416 SSH launcher page improvements (igrkio)
 * UI-423 outdated link to farms/events in AclIntegrityTest.php (igrkio)
 * UI-397 Add warning label regarding chef daemonize on Windows (igrkio)
 * SCALRCORE-1163 Role behaviors fix (pyscht)
 * Fix. Catchable exceptions from crontab workers should go to std error log. (recipe)
 * UI-374 Minor improvement on Orchestration tab (FarmRole and Role) (igrkio)
 * UI-426 Ui bugs and issues (igrkio)
 * UI-427 security issue (XSS) (bytekot)
 * UI-429 Scalr Agent Update Settings (igrkio)
 * UI-424 New dashboard widget (vdobrovolskiy)
 * SCALRCORE-1169 Check whether credentials defined before making Cloud API call (recipe)
 * UI-410 "Add new" buttons behavior (bytekot)
 * UI-419 Consistency on pages with two column layout (bytekot)
 * SCALRCORE-1175 Role editor changes (igrkio)
 * UI-425 Roles grid shouldn't refresh after editing role (bytekot)
 * Fix. ServerTerminate service. Handle unavailable cloudLocation for Openstack cloud. (recipe)
 * SCALRCORE-1187 Admin Icon is cut off (igrkio)
 * SCALRCORE-1188 Create new Farm dashboard widget does not check Create Permission of ACL Farms resource (igrkio)
 * SCALRCORE-1190 Account CA - Budgets - Impossible to scroll down (igrkio)
 * SCALRCORE-1191 CA - Impossible to switch month using calendar dropdown (igrkio)
 * SCALRCORE-1193 resize charts to 510x300px (levsh)
 * SCALRCORE-1121 Implement LDAP based authorization (vdobrovolskiy)
 * SCALRCORE-1145 Implement Functional Tests (vdobrovolskiy)
 * Fix E_RECOVERABLE_ERROR should raise exception (recipe)
 * UI-336 Refactor EC2 instance types (igrkio)
 * UI-402 Storefront P1 (igrkio)
 * UI-401 Add new scheduler task type (igrkio)
 * SCALRCORE-1111 API Implement /os controller (recipe)
 * UI-400 OS refactoring UI part. (igrkio)
 * SCALRCORE-1112 Implement /{envId}/images/ methods (recipe)
 * SCALRCORE-1137 Create UI to manage new API keys (igrkio)
 * SCALRCORE-1113 Implement /{env-id}/roles methods (igrkio)
 * SCALRCORE-1140 Misc bugs in API (recipe)
 * SCALRCORE-1122: Undefined variables in ScalrApi_2_3_0 (discydel)
 * SCALRCORE-1118 Log DB errors to file instead of ui_errors table (vdobrovolskiy)
 * SCALRCORE-1133 Fix E_STRICT Only variables should be passed by reference, in app/src/Scalr/UI/Controller/Platforms.php:76 (recipe)
 * SCALRCORE-1119 Improve ServerStatusManager cronjob (pyscht)
 * SCALRCORE-1136 cloud_pricing old generation instance types prices are not pulled (vdobrovolskiy)
 * SCALRCORE-1131 Full Access ACL has some permissions turned off for new accounts (recipe)
 * SCALRCORE-1141 Move Role based entities under Scalr\Model namespace (recipe)
 * UI-408 Operations details requires serverId (igrkio)
 * UI-384 Get rid of  FEATURE_* and isFeatureEnabled (igrkio)
 * SCALRCORE-1073 Analytics 3 Consider matching Farm Roles colors (igrkio)
 * SCALRCORE-1142 PropertyFilterIterator for EntityIterator filterBy{PropertyName} (pyscht)
 * SCALRCORE-984 Write tests for GV (vdobrovolskiy)
 * UI-412 Orchestration tab target_roles related changes (igrkio)
 * UI-413 Tags refactoring (part 1 - Add governance checkbox "Allow user to specify additional tags") (igrkio)
 * SCALRCORE-1139 Validate ACL permissions for all actions (recipe) 
 * New UI
 * SCALRCORE-863 Cost Analytics 3 phase
