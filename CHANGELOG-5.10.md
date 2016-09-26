CHANGELOG for 5.8.29 - 5.10.21
===================

This changelog references the relevant changes (bug and security fixes) done in 5.8 - 5.10 versions.

To get the diff between two versions, go to https://github.com/Scalr/scalr/compare/v5.8.29...v5.10.21


### 5.10.21

###### Improvement
- [SCALRCORE-2815] - Add server hostname to resources search

###### Bug
- [SCALRCORE-2707] - Farm Designer > Security :: fix sorting by ID and Description
- [SCALRCORE-2719] - Farms > Roles view :: "Terminated" server in the grid after Suspend server
- [SCALRCORE-2724] - impossible to use new gTLDs in email
- [SCALRCORE-2767] - Grid :: Sorting not working for certain fields
- [SCALRCORE-2771] - Farm designer > Security tab :: Uncaught TypeError
- [SCALRCORE-2817] - t3 Unable to get property 'store' of undefined or null reference
- [SCALRCORE-2819] - szr_update_server fix rpm version info

### 5.10.20

###### Improvement
- [SCALRCORE-1273] - Get rid of deprecated Scalr_Service_Cloud_Aws class
- [SCALRCORE-2496] - New audit log events: farm.launch, farm.terminate (BC change in a format of the log messages)

###### Bug
- [SCALRCORE-2759] - Farm designer > Storage : Hiding pane for ephemeral device
- [SCALRCORE-2762] - Admin > Cost Analytics > Cost Centers :: Notification with invalid email is successfully saved
- [SCALRCORE-2803] - It's possible to launch locked Farm through APIv1
- [SCALRCORE-2808] - Failed request to AWS on RebootFinish message handler leads server to stuck in Resuming state

### 5.10.19

###### New Feature
- [SCALRCORE-2280] - [APIv2] Implement CloudCredentials
- [SCALRCORE-2324] - Resources search component

###### Improvement
- [SCALRCORE-2647] - Role creation popup > hide unavailable icons
- [SCALRCORE-2735] - Chef Functionality - Add to Runlist

###### Bug
- [SCALRCORE-2755] - APIv2 > FarmRole issues
- [SCALRCORE-2768] - Suspend/Resume UI/UX improvements/fixes
- [SCALRCORE-2769] - Update AWS instance types
- [SCALRCORE-2770] - Farm Role SG validation issue
- [SCALRCORE-2777] - Role permissions issue
- [SCALRCORE-2778] - Create server snapshot for account-scope role issue

### 5.10.18

###### New Feature
- [SCALRCORE-2528] - CA > Recalculate AWS detailed billing statistics

###### Improvement
- [SCALRCORE-2484] - Implement Ec2 Outbound security rules.
- [SCALRCORE-2628] - Account level roles restrictions
- [SCALRCORE-2637] - Optional security groups in governance (BC changes in behavior) 
- [SCALRCORE-2664] - Improve password reset experience
- [SCALRCORE-2756] - Implement GROUP and DISTINCT in AbstractEntity

###### Bug
- [SCALRCORE-1956] - Environment > Server > Server Status > "Statistic" graphs :: problem with "y" axis for "Yearly" period
- [SCALRCORE-2508] - Environment > SSH Keys :: unable to download SSH Keys for deleted Farm
- [SCALRCORE-2625] - GCE windows import > setDiskAutoDelete(false) is not applied
- [SCALRCORE-2638] - Dashboards :: "Delete column" button should not be under widget
- [SCALRCORE-2644] - Environment > Dashboard > "AWS health status" widget :: fix unchecking all locations
- [SCALRCORE-2665] - Undefined value in teamOwner field in farm builder.
- [SCALRCORE-2676] - Environment > Images > Use in existing Role :: Scroll bar is missing in the grid
- [SCALRCORE-2695] - Login : Admins with own accounts cannot log in as Admins
- [SCALRCORE-2698] - Amazon EC2 Security groups :: "Used by" is empty when group is used in Farm Role
- [SCALRCORE-2714] - CA > Fix KeyError exception for removed cloud credentials
- [SCALRCORE-2715] - CA > Farm Designer > Current spend rate misuses suspended servers
- [SCALRCORE-2716] - Validate Name Field on the front end side
- [SCALRCORE-2726] - Fix comments for ec2 inbound rules
- [SCALRCORE-2750] - AWS EC2 ELB Module Error: Unable to Load Data (Farm Role ID # not found)

### 5.10.17

###### Improvement
- [SCALRCORE-2600] - Scalr user security improvements
- [SCALRCORE-2651] - CA > Project Name uniqueness

###### Bug
- [SCALRCORE-2657] - Server status :: CloudWatch statistics does not work
- [SCALRCORE-2668] - GV -> Reboot After Host Init Setting Not Working
- [SCALRCORE-2673] - RDS > Errors handled incorrectly
- [SCALRCORE-2684] - FarmDesigner > Scaling :: "Consider suspended servers" should not be selectable if "Scaling behavior" == Resume / Suspend
- [SCALRCORE-2690] - CA > Analytics processing > AWS payer account query fix
- [SCALRCORE-2696] - RDS DB Snapshots :: impossible to Restore Amazon Aurora Instance
- [SCALRCORE-2709] - Lost Functionality to Suspend Servers
- [SCALRCORE-2711] - Admin > Accounts > Account edit :: Cannot save changes

### 5.10.16

###### Bug
- [SCALRCORE-2670] - Fix broken logger RotateFileHandler in latest Python2.7
- [SCALRCORE-2674] - Add use of proxy to deprecated Scalr_Service_Cloud_Aws_Transports_Query
- [SCALRCORE-2678] - Release "Re-create storage if one or more volumes missing" feature
- [SCALRCORE-2682] - CA > Analytics Processing month issue

### 5.10.15

###### Improvement
- [SCALRCORE-1398] - UI improvements
- [SCALRCORE-2114] - [APIv2] Add Owner and TeamOwner to Farm definition
- [SCALRCORE-2178] - Make cloud credentials separate multi-scope object
- [SCALRCORE-2626] - Minor changes on Сlouds page
- [SCALRCORE-2645] - Add system GV on server scope with ELB name

###### Bug
- [SCALRCORE-2469] - Allow to switch farm repo from latest to stable
- [SCALRCORE-2531] - APIv2 > Governance should be used in the API
- [SCALRCORE-2574] - AWS > Route53 :: Deleted records don't leave grid
- [SCALRCORE-2586] - PHP Fatal error: Call to a member function getEnabledPlatforms() on null in app/src/Scalr/UI/Controller/Platforms.php on line 44
- [SCALRCORE-2596] - Account > Global variables :: Uncaught TypeError after "Save" and navigate to Roles
- [SCALRCORE-2597] - Budget consumption issue
- [SCALRCORE-2602] - Guarantee strong password for Azure instances
- [SCALRCORE-2605] - Account/Environment > Images :: Incorrect OS sorting
- [SCALRCORE-2612] - Global Variables :: right form for Locked variable should be closed if "Show locked variables" is off
- [SCALRCORE-2613] - Make uptime sortable on servers
- [SCALRCORE-2615] - PHP Fatal error: Uncaught exception 'ADODB_Exception'
- [SCALRCORE-2622] - SSL Certificates :: Unable to add more than 1 certificate
- [SCALRCORE-2624] - Farm cost metering min/max values
- [SCALRCORE-2627] - AWS Error. Request CreateBucket failed. Region 'us-gov-west-1' is wrong: expecting 'us-east-1'
- [SCALRCORE-2630] - Environments :: SSL Certificates :: It is possible to create certificates with identical names
- [SCALRCORE-2634] - Add hash to list of variables in reset password email template
- [SCALRCORE-2635] - Account > Users :: fix possibility to change Active Status for himself
- [SCALRCORE-2640] - Global Variables :: add Refresh button
- [SCALRCORE-2646] - Environment > FarmDesigner > Scaling :: Incorrect number of "Min instances" in the Grid

### 5.10.14

###### Improvement
- [SCALRCORE-2036] - Global Variables to back configuration of Farm Role settings

###### Bug
- [SCALRCORE-2595] - RDS DB Instances :: newly created instance is shown in the wrong location

### 5.10.13

###### Improvement
- [SCALRCORE-2433] - ged rid of ingore changes flag in upgrades

###### Bug
- [SCALRCORE-1984] - Environment > RDS > DB Instance :: Impossible to create snapshot of DB instance based on Amazon Aurora engine
- [SCALRCORE-2516] - Environment> Dashboard :: You can add Farm widget for the same farm more than once
- [SCALRCORE-2548] - t5 TypeError: data is null form
- [SCALRCORE-2549] - t5 Uncaught TypeError: Cannot read property 'onCloseForm' of null
- [SCALRCORE-2556] - Change size of type fields in servers and servers_history to varchar(45)
- [SCALRCORE-2557] - Temporary Server > Server status :: Role should be hidden
- [SCALRCORE-2561] - t3 console is not defined
- [SCALRCORE-2568] - PHP Fatal error: Call to a member function GetSetting() on null in Scalr/Modules/Platforms/Ec2/Helpers/EipHelper.php on line 138
- [SCALRCORE-2571] - Environment> Images :: Impossible to view newly created GCE image in list after building
- [SCALRCORE-2572] - CA > Invalid value of year field on Budgets page.
- [SCALRCORE-2575] - PHP Fatal error: Call to a member function associateIpAddress() on null in Scalr/Modules/Platforms/Cloudstack/Helpers/CloudstackHelper.php on line 228
- [SCALRCORE-2576] - AWS Security Group Error
- [SCALRCORE-2580] - t5 Uncaught TypeError: Cannot read property 'subnetAvailabilityZone' of null
- [SCALRCORE-2587] - pymysql.err.OperationalError (2013, "Lost connection to MySQL server during query)

### 5.10.12

###### New Feature
- [SCALRCORE-2539] - Add farm_index property for instances and expose it in GVs

###### Improvement
- [SCALRCORE-1836] - Improve grow up volume interface
- [SCALRCORE-2171] - Python services improvements
- [SCALRCORE-2331] - APIv2 > endpoints should start with version
- [SCALRCORE-2449] - Improve usage of image messages
- [SCALRCORE-2546] - For lack of cryptokey denial of the service should be triggered

###### Bug
- [SCALRCORE-2238] - Replacement Roles :: not accessible "Instance Type" can be assigned to Role
- [SCALRCORE-2470] - roles.behaviors does not update in some cases
- [SCALRCORE-2507] - Environments drop-down > Pinned rows :: Switching environments with arrow keys is impossible
- [SCALRCORE-2519] - Dashboard :: Empty error message on Add widget window
- [SCALRCORE-2530] - Budgets by Cost Center for Quarters and for Fiscal Year
- [SCALRCORE-2533] - APIv2 > Field type validation
- [SCALRCORE-2541] - Error E_WARNING array_shift() expects parameter 1 to be array, null given, in app/src/Scalr/Service/OpenStack/Client/AuthToken.php:272
- [SCALRCORE-2542] - PHP Fatal error: Call to a member function GetCloudServerID() on null in app/src/api/class.ScalrAPI_2_0_0.php on line 1145
- [SCALRCORE-2552] - APIv2 > Farms creation issues
- [SCALRCORE-2554] - Read Only Access for Servers Tab
- [SCALRCORE-2555] - Scheduler tasks issue
- [SCALRCORE-2559] - APIv2 > No validation at updating placement configuration
- [SCALRCORE-2560] - APIv2 > Internal server error on Farm creation with description being set

### 5.10.11

###### Improvement
- [SCALRCORE-2321] - Create UI for new IP management on EC2
- [SCALRCORE-2512] - Separate ELB/EC2 security group governance

###### Bug
- [SCALRCORE-1763] - Environment > Tasks Scheduler :: add Warnings after actions with Tasks with different statuses
- [SCALRCORE-2252] - GV > check user defined validator before saving
- [SCALRCORE-2532] - Permissions Issue - Orchestration
- [SCALRCORE-2535] - Incorrect endpoints usage with Openstack Identity v3

### 5.10.10

###### Improvement
- [SCALRCORE-2117] - Make download private key configurable by user
- [SCALRCORE-2349] - Add GCE API library dependency to composer.json

###### Bug
- [SCALRCORE-2316] - Add Content-Length header for empty posts on Azure
- [SCALRCORE-2394] - Incorrect Scaling decision for some built-in automation roles.
- [SCALRCORE-2472] - APIv1 > GlobalVariableSet issue
- [SCALRCORE-2495] - FarmDesigner issues in China region with Shared Roles
- [SCALRCORE-2504] - UI > Performance issue
- [SCALRCORE-2514] - Environment > FarmsDesigner > Add farm role :: error in console after filtering by tag <a>

### 5.10.9

###### Improvement
- [SCALRCORE-2082] - UI without rounded corners
- [SCALRCORE-2179] - Improve experience with accounts that have a lot of enviornments
- [SCALRCORE-2237] - Password length validation
- [SCALRCORE-2263] - Improve verification of the Images
- [SCALRCORE-2276] - APIv2 > Global Variables in Farm-Role scope
- [SCALRCORE-2352] - Cloud poller imporvements
- [SCALRCORE-2418] - CA > increase aws_billing_records table cleanup period
- [SCALRCORE-2420] - Show verbose message if there are no Subscriptions in current tenant
- [SCALRCORE-2435] - Allow users to specify ELB name on create ELB dialog
- [SCALRCORE-2451] - Automatically create tags on AMIs
- [SCALRCORE-2483] - Show warning when account role chef settings conflicting with governance

###### Bug
- [SCALRCORE-1714] - Environment > AWS :: "Delete" buttons are active after deleting
- [SCALRCORE-2355] - VPC Governance for ELB is ignored
- [SCALRCORE-2377] - Environment > GCE Snapshots :: sorting by "Date" and "Size (GB)" is not correct.
- [SCALRCORE-2401] - Tasks scheduler start date issue in Firefox
- [SCALRCORE-2423] - UI > server terminate option caching
- [SCALRCORE-2425] - Bundle tasks > check farms permission
- [SCALRCORE-2443] - Farm Designer > Network > ELB problems
- [SCALRCORE-2467] - Cloning environment causes GV validation error
- [SCALRCORE-2471] - TypeError: null is not an object (evaluating 'Ext.isObject')
- [SCALRCORE-2473] - Fatal error: Call to undefined method Ec2PlatformModule::getInstanceTypeVcpus()
- [SCALRCORE-2493] - In Openstack Identity v3 project is equal to tenant in v2, not user
- [SCALRCORE-2494] - Security groups are not working with Openstack IceHouse

### 5.10.8

###### Improvement
- [SCALRCORE-1921] - Read-only ACL (ACL roles)
- [SCALRCORE-2395] - Support for domains in Keystone v3 on Openstack
- [SCALRCORE-2451] - Automatically create tags on AMIs
- [SCALRCORE-2453] - Add Farm's Team owner to the list of system GVs

###### Bug
- [SCALRCORE-2152] - Admin user > CA > Dashboard :: Text is cut by table borders
- [SCALRCORE-2159] - Farms Designer > Farm settings :: "Launch this farm inside Amazon VPC" checkbox : wrong behavior if unchecking is not confirmed
- [SCALRCORE-2275] - Cloudwatch statistics :: Widget names are spelled together
- [SCALRCORE-2313] - Issue with Create button on page Scripts
- [SCALRCORE-2332] - Old properties used for ELB permission checks.
- [SCALRCORE-2333] - Allow empty chef environment to be set on Role level
- [SCALRCORE-2363] - Missing Instance Type in the Servers grid
- [SCALRCORE-2364] - Account > Users :: Unable to disable 'Allow to manage environments'
- [SCALRCORE-2367] - roles/xGetListAction returns images for disabled clouds
- [SCALRCORE-2396] - Unable to create environment scope Role with the same name in different Environments
- [SCALRCORE-2397] - Improve role replacement
- [SCALRCORE-2419] - Create upgrade script to fix Security groups policies
- [SCALRCORE-2428] - Subsequent obtaining Windows password
- [SCALRCORE-2438] - t3 Uncaught TypeError: Cannot read property 'setHtml' of undefined
- [SCALRCORE-2445] - Unable to Create Aurora db instance
- [SCALRCORE-2455] - CA > Two detailed billing files on the same month collision error
- [SCALRCORE-2459] - Environment > Farms > Roles :: "Servers" link not work

### 5.10.7

###### Improvement
- [SCALRCORE-2314] - At filtering Project or CostCenters by name or billing code is needed to show the archived objects
- [SCALRCORE-2317] - Remove temporary restriction for storage configuration

###### Bug
- [SCALRCORE-1990] - Scheduler task cron issue
- [SCALRCORE-2089] - Role builder issue
- [SCALRCORE-2236] - Environment > Roles list :: Role tooltips are overlapping
- [SCALRCORE-2258] - Scalr __autoload() conflicts with phpunit
- [SCALRCORE-2273] - GCE append rather than replace metadata on receiving a password for windows servers
- [SCALRCORE-2315] - isAccountSuperAdmin parameter remains active after isAccountAdmin switched OFF
- [SCALRCORE-2318] - APIv2 > When farm role removed on running farm, servers from old farm role should be terminated
- [SCALRCORE-2327] - Environment > Webhooks edit :: add Scope icon for Endpoints
- [SCALRCORE-2328] - CA > Budgets > Invalid Projects indent
- [SCALRCORE-2334] - Environment > Farm Designer > Role :: "Uncaught TypeError" after switching from Global variables to Advanced
- [SCALRCORE-2338] - RDS > Security groups :: Duplicate SGs in 'Add SG' grid
- [SCALRCORE-2339] - Environment> RDS> DB Instances> Add SG to DB :: Asterisk pattern for SG fire even if SG in governance doesn't marked with asterisk
- [SCALRCORE-2373] - RDS > Manage Snapshots > All DB snapshots are returned
- [SCALRCORE-2393] - Azure fixes

### 5.10.6

###### Improvement
- [SCALRCORE-1585] - Better error handling in JSON message serializer
- [SCALRCORE-2093] - CA > minor improvements to better performance
- [SCALRCORE-2295] - Add Cost Center ID to the UI
- [SCALRCORE-2320] - Add team description to the teams dropdown in FarmDesigner

###### Bug
- [SCALRCORE-2162] - Environment > Global Variables > New Variable :: add error tooltip if required field is blank
- [SCALRCORE-2265] - Environment > Farms > Servers :: Servers do not return to the Running state after suspend
- [SCALRCORE-2287] - E_WARNING json_decode() expects parameter 1 to be string in UI/Request/JsonData.php:23
- [SCALRCORE-2303] - Environment > Role Edit > Orchestration :: error after delete Rule and navigate to Role overview
- [SCALRCORE-2309] - Farms > MySQL status > Database dumps:: fix "Failed" icon.
- [SCALRCORE-2316] - Add Content-Length header for empty posts on Azure
- [SCALRCORE-2325] - Environment > Tasks Scheduler :: add tooltips for Types of Tasks
- [SCALRCORE-2326] - Wrong link for newly added image
- [SCALRCORE-2335] - Environment > Role > Orchestration :: added Rule(s) do not saved after pressing "Save" on Global variables

### 5.10.5

###### Improvement
- [SCALRCORE-1919] - RDS DB Isntances UI improvements
- [SCALRCORE-1980] - User-agent for cloud libraries
- [SCALRCORE-2190] - APIv2 > Role / Image account scope endpoints
- [SCALRCORE-2257] - RDS: Add support for MariaDB
- [SCALRCORE-2272] - Show warning if user trying to use system mountpoints
- [SCALRCORE-2294] - APIv2 > Add cost-centers endpoint to Account level

###### Bug
- [SCALRCORE-1806] - Remove applet console launcher
- [SCALRCORE-2030] - Optimize ui_errors table
- [SCALRCORE-2167] - Suspend/Resume race condition
- [SCALRCORE-2234] - UI > Farm Builder > Able to add any Role
- [SCALRCORE-2251] - Scaling cron launches extra instance after Resume action on Suspended instance
- [SCALRCORE-2266] - Can't change GC root volume type from standart to ssd.
- [SCALRCORE-2283] - Instance Resize Functionality not indicated in Servers View Dashboard.
- [SCALRCORE-2284] - ACL > Regression in Farm level Scripts Execution
- [SCALRCORE-2288] - FarmRoleStorageConfig::validate() result always interpreted as error
- [SCALRCORE-2291] - VPC ID should be optional when adding Farm
- [SCALRCORE-2292] - APIv2 > Able to create Farm without a Project attached
- [SCALRCORE-2296] - Empty menu item AWS is visible
- [SCALRCORE-2298] - Fatal error: Call to a member function getInstanceTypeVcpus() on null in Update20150818090745.php
- [SCALRCORE-2310] - Environment > Role Edit > "Configure Scalr automation" :: "MariaDB" should exclude possibility to selecting other DB

### 5.10.4

###### New Feature
- [SCALRCORE-2123] - Support for instance resize on Amazon EC2

###### Improvement
- [SCALRCORE-2166] - Add team/group description when LDAP is used

###### Bug
- [SCALRCORE-1796] - Environment > Dashboard :: fix "Load statistics" widget for different number of Dashboard columns
- [SCALRCORE-2163] - Environment > SSH Keys :: sorting by Location doesn't work
- [SCALRCORE-2170] - CA > Download billing file error > Check Access Permissions to Object
- [SCALRCORE-2261] - Can't access script-versions from account scope
- [SCALRCORE-2271] - Problems when using same credentials with different tenants on Openstack

### 5.10.3

###### Improvement
- [SCALRCORE-2079] - Return error code if database upgrade fails
- [SCALRCORE-2137] - Add pattern(with asterisk) support to the security group governance
- [SCALRCORE-2249] - Rotate api_log table

###### Bug
- [SCALRCORE-1699] - Environment > DB Backups :: It is possible to select any year
- [SCALRCORE-1968] - Environment > Roles :: Search results do not change even though the search parameter "quick start roles" is changed
- [SCALRCORE-2061] - Environment > Roles > Add to farm :: Farm list is missing scroll-bar
- [SCALRCORE-2120] - Global variables scope descriptions
- [SCALRCORE-2124] - AWS: The instance does not have an 'ebs' root device type and cannot be stopped
- [SCALRCORE-2151] - RDS DB Instances > New DB Instance :: wrong behavior "Used on" setting if we use NAME from deleted Instance
- [SCALRCORE-2197] - Account > Roles > Orchestration :: Script scope icon inconsistency
- [SCALRCORE-2202] - Roles > Roles builder :: Unable to create instance-store role
- [SCALRCORE-2207] - Methods xListServersAction and xListServersUpdateAction return different status for the same server
- [SCALRCORE-2232] - Method DBFarmRole->getReplacementRoles still uses AND env_id IN(0, ?)
- [SCALRCORE-2244] - Undefined function getResponseHeader in new pecl version
- [SCALRCORE-2250] - [APIv2] Unable to edit existing scripts
- [SCALRCORE-2253] - [APIv2] Listing scripts is not limited to the current Environment
- [SCALRCORE-2254] - ScalrPy tuple object is not callable

### 5.10.2

###### New Feature
- [SCALRCORE-1539] - Roles/Images on account scope
- [SCALRCORE-1991] - Feature: Orphaned servers (EC2 only)

###### Improvement
- [SCALRCORE-2077] - Do not ask for filesystem if auto-mount is not checked
- [SCALRCORE-2165] - Implement Entity::deleteBy() method
- [SCALRCORE-1929] - Server Terminate audit - on what basis

###### Bug
- [SCALRCORE-1861] - VPC Governance and FarmDesigner issues
- [SCALRCORE-2058] - Cron > ServerTerminate > disableAPITermination flag corrections
- [SCALRCORE-2095] - Block device mapping for instances launch
- [SCALRCORE-2100] - Add AMZN linux support to one-liner install scripts
- [SCALRCORE-2122] - Obsolete links in analytics notifications emails
- [SCALRCORE-2129] - Farms > Farm Designer > Scaling > Load Averages :: Broken Statistics screen
- [SCALRCORE-2148] - API allows multiple scripts with the same name
- [SCALRCORE-2153] - CA > analytics processing does not work on GovCloud
- [SCALRCORE-2154] - Governance cache must be cleared on warmup
- [SCALRCORE-2160] - Public cost center report > Detailed statistic :: Navigation buttons are not rendered correctly
- [SCALRCORE-2161] - AWS > EC2 ELB > Default Security Groups are missing
- [SCALRCORE-2177] - Correct environment doesn't load when logging in
- [SCALRCORE-2188] - pecl_http V2 URL with underscore issue
- [SCALRCORE-2194] - RDS Farm Association Bug
- [SCALRCORE-2198] - Environment > Global Variables :: "undefined" scope in tooltip

### 5.10.1

###### New Feature
- [SCALRCORE-1572] - CA > Azure detailed billing
- [SCALRCORE-1846] - CLI script to track and report Scalr EE usage

###### Improvement
- [SCALRCORE-1573] - Datepicker improvements
- [SCALRCORE-2059] - Scaling fixes and impovements
- [SCALRCORE-2076] - Event dropdown looks ugly

###### Bug
- [SCALRCORE-2051] - Environment > AWS > Security groups :: Pagination does not reset when switching locations
- [SCALRCORE-2060] - xGetVpcListAction returns unnecessary data for some pages
- [SCALRCORE-2075] - Incorrect OS sorting
- [SCALRCORE-2111] - RDS DB Instances > Modify :: Uncaught ReferenceError in console when "AWS Error...." message appears
- [SCALRCORE-2113] - Allow szradm to set variables on Farm scope.
- [SCALRCORE-2116] - Environment > AWS > EC2 ELB :: "Create LB inside" drop-down categories overlapping
- [SCALRCORE-2118] - Environment > AWS > EC2 ELB :: Newly added security group disappears after sorting
- [SCALRCORE-2133] - Scalr admin > Operating systems :: Uncaught TypeError after clicking on Families/OS selector
- [SCALRCORE-2135] - Deleting images for which no AMI on cloud lead to seg fault in php
- [SCALRCORE-2140] - Cannot decrypt message issue
- [SCALRCORE-2141] - Can't delete script from UI when added through APIv2

### 5.10.0

###### Improvement
- [SCALRCORE-601] - pecl_http version 2.5 support
- [SCALRCORE-1611] - Monitoring UI improvements
- [SCALRCORE-1612] - Scripts manager UI improvements
- [SCALRCORE-1637] - Remove getParam use from tools/aws/[route53,rds]
- [SCALRCORE-1792] - Notifications list improvements
- [SCALRCORE-1889] - Small DB backups fixes
- [SCALRCORE-1916] - [APIv2] Manage api spec cloud platforms according to allowed_clouds configuration
- [SCALRCORE-2092] - Add support for ANY port on ELB listener
- [SCALRCORE-2096] - Update menu icons for AWS

###### Bug
- [SCALRCORE-1961] - Environment > Server > Load Statistic :: align message if No graphs were selected
- [SCALRCORE-2050] - Environment > Custom scaling metrics :: sorting for "Inverted" column not work
- [SCALRCORE-2070] - Chef servers :: Missing scroll on Edit form while resized
- [SCALRCORE-2097] - Heisenbug: Instances stuck in Pending
- [SCALRCORE-2099] - APIv2 > Removing server > Exception not found
- [SCALRCORE-2106] - Placement Group selection not saving
- [SCALRCORE-2107] - AuditLogger connection problems should not block Scalr workflows

### 5.9.18

###### Improvement
- [SCALRCORE-1472] - Global Variables validation improvements
- [SCALRCORE-2063] - Separate EC2 SG governance by OS type (windows vs linux)
- [SCALRCORE-1278] - Minor change in tagField

###### Bug
- [SCALRCORE-1718] - Environment > Global Variables :: add scrolling for the "Global Variables details" (right) form while resize
- [SCALRCORE-1912] - Environment > Farm Designer > Global Variables :: Uncaught TypeError
- [SCALRCORE-1977] - RDS > New instance : Master password field does not accept allowed symbols
- [SCALRCORE-2065] - CA > Detailed billing bucket another region issue
- [SCALRCORE-2073] - CA > Detailed billing > Memory usage issue
- [SCALRCORE-2078] - Cannot clone environment > database error
- [SCALRCORE-2094] - Role images without images
- [SCALRCORE-2095] - Block device mapping for instances launch

### 5.9.17

###### Improvement
- [SCALRCORE-2035] - Prohibit to use "SCALR_" prefix for global variables. It should be reserved.
- [SCALRCORE-2031] - Windows disk label, improved mpoint picker, EBS mount

###### Bug
- [SCALRCORE-1601] - Case Insensitivity in GV
- [SCALRCORE-2004] - CA > Project must be set when cloning a Farm
- [SCALRCORE-2057] - 10 same queries: SELECT value FROM governance
- [SCALRCORE-2068] - Linux install one-liner fails on Debian 7/8

### 5.9.16

###### Improvement
- [SCALRCORE-1519] - Topmenu improvement
- [SCALRCORE-1790] - Remove trailing slashes from base URL for Azure services
- [SCALRCORE-1998] - Separate RDS/EC2 security group governance
- [SCALRCORE-2007] - Make AWS Security groups limit configurable
- [SCALRCORE-2023] - Set maxCount for RDS security groups in governance
- [SCALRCORE-2032] - Remove log4php library from Scalr's codebase
- [SCALRCORE-2034] - Add MINIMUM calc function for custom scaling metrics
- [SCALRCORE-2039] - APIv2 mysql queries stats
- [SCALRCORE-2043] - Better error reporting for detailed billing settings
- [SCALRCORE-2048] - Provide ACL for Scripts (environment scope) resource

###### Bug
- [SCALRCORE-1747] - CA > cloud_location isn't set in servers_history table
- [SCALRCORE-1748] - Environment > Cost Analytics > Farms :: problem with resize of column
- [SCALRCORE-1779] - Wrong IP logged in Audit Log
- [SCALRCORE-1784] - Add new install script to role import for all platforms and OSes
- [SCALRCORE-1843] - Can't remove ssh key
- [SCALRCORE-1884] - Vulnerabilities fix
- [SCALRCORE-1930] - Max EBS size
- [SCALRCORE-1966] - Environment > Farm > Farm Designer > Network :: sorting by "Elastic IP" doesn't work
- [SCALRCORE-1995] - Environment > Servers :: sorting by "Public IP" and "Private IP" is not correct.
- [SCALRCORE-2005] - CA > rotate aws_billing_records table
- [SCALRCORE-2015] - Environment > Farms :: Incorrect text in filter drop-down
- [SCALRCORE-2022] - puttygen is missed in the packages
- [SCALRCORE-2028] - Environment > Servers :: Error in console if FarmName contains double quotes
- [SCALRCORE-2033] - SCALR_CLOUD_LOCATION_ZONE is missed on Openstack
- [SCALRCORE-2038] - Temporary disable Windows EBS mount on Storage tab

### 5.9.15

###### Improvement
- [SCALRCORE-410] - Get rid of log4php
- [SCALRCORE-1845] - ACL(s) to limit lists/usage of EC2 EBS by scalr-meta tag
- [SCALRCORE-1978] - Include RequestID to all errors from AWS
- [SCALRCORE-1987] - Improve performance for `messages` and `events`
- [SCALRCORE-2000] - Mount Windows devices on EC2

###### Bug
- [SCALRCORE-1692] - Manage Account > Cost Analytics > Projects :: Missed "year" range labels in chart
- [SCALRCORE-1812] - Environment > Farm's Roles :: add verification for inputs
- [SCALRCORE-1905] - Environments > EC2 ELB > "New Load Balancer" :: Placeholder inconsistency
- [SCALRCORE-1949] - Server list filtering bug
- [SCALRCORE-1979] - Get rid of auto_incremented id in the client_environment_properties table
- [SCALRCORE-1983] - RDS DB Clusters > Modify > Add Security Groups to DB Instance :: sorting is not case sensitive
- [SCALRCORE-1986] - Environment :: "RDS DB Instances" page is opened instead of "RDS DB Snapshots"
- [SCALRCORE-1988] - Servers view - sshkey not found
- [SCALRCORE-1994] - Environment > Images > "Select role to add an image" :: sorting by "ID" and "Status" does not work
- [SCALRCORE-1996] - Environment > Farms Designer > Scaling metric :: wrong captions for "BandWidth"
- [SCALRCORE-1997] - Environment > Farms Designer > Security :: right form is open after filtering when nothing found
- [SCALRCORE-2001] - Environment > SSH Keys :: Farm is not found after clicking on link (on tooltip over Status)
- [SCALRCORE-2002] - Environment > FarmsDesigner > Storage :: please align title on "Select Snapshot" pop up window
- [SCALRCORE-2006] - Error E_WARNING in_array() expects parameter 2 to be array, boolean given, in /app/src/class.DBServer.php:989
- [SCALRCORE-2008] - CA > Detailed billing errors
- [SCALRCORE-2014] - Environment > Farms > Roles :: wrong Farm Name in title of "Extended role information"
- [SCALRCORE-2018] - Azure roleslibrary bug
- [SCALRCORE-2019] - Oneliner doesn't work on Azure Windows 2008
- [SCALRCORE-2021] - Log records don't come > Column 'serverid' cannot be null

### 5.9.14

###### Improvement
- [SCALRCORE-1925] - CA > AWS detailed billing improvements
- [SCALRCORE-1973] - CA > Keep usage_h stats for one month

###### Bug
- [SCALRCORE-1899] - Farm Role -> Bootstrap with Chef: role reverts to empty run list when changing the chef environment
- [SCALRCORE-1942] - mysqli error: 2014: Commands out of sync; you can't run this command now
- [SCALRCORE-1944] - GCE windows password doesn't match
- [SCALRCORE-1953] - RDS Aurora doesn't work with uppercase characters in DB identifier
- [SCALRCORE-1981] - Sync shared roles no longer creates quick start ones.

### 5.9.13

###### Improvement
- [SCALRCORE-1144] - [APIv2] Implement API Logging and Rate limiting
- [SCALRCORE-1263] - Roles list performance optimization
- [SCALRCORE-1411] - [Analytics 4] Update analytics_demo service to new db structure
- [SCALRCORE-1725] - Servers list performance optimization
- [SCALRCORE-1756] - Display server index on create server snapshot page

###### Bug
- [SCALRCORE-1693] - Account > Teams > Members :: tooltip for ACL contain useless links
- [SCALRCORE-1696] - Environment > Farms > Configure > Using "Filter Farm Role" :: add message if No Found
- [SCALRCORE-1749] - Environment > Farms > Configure > Roles :: Mistake in an error message
- [SCALRCORE-1758] - Environment > Bookmarks Bar:: add possibility to Add (fix) "Amazon EC2 Security groups" to bookmarks bar
- [SCALRCORE-1761] - Environment > Webhooks :: fix message after removing
- [SCALRCORE-1762] - Account > Users :: fix messages after actions with user
- [SCALRCORE-1767] - Environment > Roles > New > New Role > Configure Scalr automation :: active "OK" button before select software
- [SCALRCORE-1770] - Environment > Roles > New Role > Role from non-Scalr server :: add "Cancel" button
- [SCALRCORE-1793] - Environment > Server > Load statistics :: errors in console when server with status Pending
- [SCALRCORE-1794] - Environment > Server > Load statistics :: add message after filtering if Nothing found
- [SCALRCORE-1801] - Environment > Farm > Roles > Security :: Security groups disappear from table after clicking on "Farm details" and return to Role
- [SCALRCORE-1802] - Environment > Farm > Roles > Security > Add security groups :: please align title on "Add security..." pop up window
- [SCALRCORE-1807] - Environment > AWS > EBS Snapshots :: fix sorting on "Size (GB)" column
- [SCALRCORE-1808] - Environment > Scripts :: prohibit to create( by fork) script with the same name
- [SCALRCORE-1809] - Account > Scripts :: exclude "Environment scope" from switch-control
- [SCALRCORE-1810] - Environment > Images :: Images without Name appear after copying
- [SCALRCORE-1811] - Environment > Images > Image details :: "Name" field should be required
- [SCALRCORE-1821] - Environment > AWS > EBS Volumes :: fix sorting on "Used by" column
- [SCALRCORE-1822] - Environment > AWS > EBS Volumes > Autosnapshot Settings :: add verification for inputs
- [SCALRCORE-1824] - Environment > AWS > EBS Volumes :: fix default width for "Snapshot ID" column
- [SCALRCORE-1825] - Environment > Roles > Add to Farm :: please align title on "Select farm to add a role" pop up window
- [SCALRCORE-1835] - Environment > Farm > Add Farm Role :: right form is open, while selected Role is not in table
- [SCALRCORE-1867] - Azure specific :: Role creating > Selecting Azure image :: "All location" text is absent
- [SCALRCORE-1873] - Environment > GCE Static IPs :: Capitalization inconsistency
- [SCALRCORE-1878] - Environment > RDS > DB Instances :: please align title on "Add Security Groups to DB Instance" pop up window
- [SCALRCORE-1887] - Environment > Images :: Image in Deleting status can be 'removed' again
- [SCALRCORE-1927] - [APIv2] API Key creation > uniqueness issue
- [SCALRCORE-1936] - replaceServerId is not removed from Server entity
- [SCALRCORE-1938] - CA > It is possible to save Farm without Project on HS
- [SCALRCORE-1939] - CA > ec2.detailed_billing.enabled is not enabled
- [SCALRCORE-1941] - newRoleId property is not removed from FarmRole entity
- [SCALRCORE-1945] - ec2.account_id should not be encrypted.
- [SCALRCORE-1951] - Don't pass ephemeral0 -> /mnt on Windows
- [SCALRCORE-1952] - Instance type definitons fix

### 5.9.12

###### Improvement
- [SCALRCORE-1787] - Manage ephemeral devices from Storage tab.

###### Bug
- [SCALRCORE-1902] - CA > Analytics demo cron fatal error on Azure Farm
- [SCALRCORE-1909] - Bandwidth scaling metric doesn't work
- [SCALRCORE-1910] - CA > Payer account > Analytics processor downloads wrong CSV file

### 5.9.11

###### Improvement
- [SCALRCORE-1788] - Add support for development branches in Azure

###### Bug
- [SCALRCORE-1859] - Optimize task scheduler columns
- [SCALRCORE-1890] - CloudPoller > Environment Cloud platform suspension on Error: OpenStack. The request you have made requires authentication
- [SCALRCORE-1891] - No validation for security groups with spaces on Azure
- [SCALRCORE-1896] - Incorrect root device mapping on Amazon Linux 201# 5.03
- [SCALRCORE-1900] - Invalid log message format: Clould not allocate/update floating IP: %s (%s, %s)
- [SCALRCORE-1901] - CA > Use BlendedCost for AWS LinkedAccount

### 5.9.10

###### Improvement
- [SCALRCORE-1351] - [APIv2] implement /{envId}/farms/{farmId}/global-variables/[{variableName}] methods
- [SCALRCORE-1753] - RDS Improvements (Support for KMS Encryption and Aurora cluster)
- [SCALRCORE-1765] - Custom scaling metric improvements (Support for inverted metrics)
- [SCALRCORE-1862] - Support for AWS consolidated account in CA with detailed billing
- [SCALRCORE-1864] - Cloud poller optimization (Decrease amount of cloud API calls)

###### Bug
- [SCALRCORE-1751] - Environment > Governance > Scalr > Policy > LEASE MANAGEMENT :: add "Delete" button
- [SCALRCORE-1774] - Filter field bugs
- [SCALRCORE-1813] - Action to remove disableAPITermination flag was missed
- [SCALRCORE-1814] - Admin images cannot be removed
- [SCALRCORE-1869] - AuditLoggerException wrong namespace
- [SCALRCORE-1877] - [APIv2] Farm Global Variables wrong scope
- [SCALRCORE-1881] - Azure error. Subscription is not registered.
- [SCALRCORE-1885] - broker crash triggers workers cancer
- [SCALRCORE-913] - Scalr does not work with PECL event >= 1.2.6-beta installed
- [SCALRCORE-1791] - Governance validation bug on ELB creation

### 5.9.9

###### Improvement
- [SCALRCORE-1745] - QuickStart/Deprecated roles management
- [SCALRCORE-1760] - Scalarizr Linux/Windows installation one-liner

###### Bug
- [SCALRCORE-1804] - SSH keys are not priovisioned on Azure
- [SCALRCORE-1823] - [CA] detailed billing account causes data corruption for another accounts for dates late than 14 days
- [SCALRCORE-1826] - UI Error: "VariableField: uncaught TypeError"

### 5.9.8

###### New Feature
- [SCALRCORE-1309] - Azure support in scalr

###### Improvement
- [SCALRCORE-1197] - Suspend cloud platforms with invalid credentials
- [SCALRCORE-1689] - ELB Enhancements

###### Bug
- [SCALRCORE-1368] - Regenerate sessionid on login
- [SCALRCORE-1668] - Environment > Farms > Roles :: add verification for "MIN INSTANCES" and "MAX INSTANCES" inputs
- [SCALRCORE-1712] - Environment > Images > Select role to add an image > Grid columns to show :: useless checkbox without description
- [SCALRCORE-1716] - Environment > Farms > Roles > Instance type :: exclude possibility to input not-active type
- [SCALRCORE-1729] - Manage Account > Users :: User can be activated/deactivated multiple times
- [SCALRCORE-1736] - Environment > Bundle Tasks :: add "close" icon for very long "Failure reason"
- [SCALRCORE-1737] - Environment > Bundle Tasks :: add scrolling for full "Failure reason" while resize
- [SCALRCORE-1739] - Environment > Images > Search :: unexpected behavior
- [SCALRCORE-1757] - Account level webhook endpoint bug
- [SCALRCORE-1766] - Prohibit to create role with the same name
- [SCALRCORE-1768] - Environment > Roles > New > New Role > Images :: fix message in table if no image added yet
- [SCALRCORE-1771] - Environment > Roles > New Role > Role from non-Scalr server > Server dropdown :: link on tooltip for server with status Importing does not work
- [SCALRCORE-1775] - Environment > Webhooks > Endpoints :: error after endpoint creation
- [SCALRCORE-1776] - Environment > Roles > New > Role Builder :: "View full log in new tab" does not work > Page not found
- [SCALRCORE-1785] - Provide user authorization during configuration Azure credentials
- [SCALRCORE-1800] - Fix server status column
- [SCALRCORE-1805] - Handler RequestLimitExceeded AWS Response is broken
 

### 5.9.7

###### Improvement
- [SCALRCORE-1691] - EBS enhancements
- [SCALRCORE-1732] - server_terminate > Handle OpenStack "Username or api key is invalid" Error
- [SCALRCORE-1743] - CloudPoller > make configurable to replicate by cloud

###### Bug
- [SCALRCORE-1534] - [APIv2] RoleScriptsTest error
- [SCALRCORE-1568] - Environment > Tasks Scheduler > Edit Task :: add possibility to change "START FROM" for existing Task
- [SCALRCORE-1629] - crontab > workers can't start due to memory limit error
- [SCALRCORE-1672] - Environment > Farms :: tooltip for Locked farm display not-full comment
- [SCALRCORE-1687] - scalr scripting > incorrect script name
- [SCALRCORE-1707] - CA > Analytics poller > instantiating CloudStack driver error
- [SCALRCORE-1708] - Environment > Servers > Server messages :: sorting by Status doesn't work
- [SCALRCORE-1710] - Environment > Images :: "Delete" button on the right form doesn't work
- [SCALRCORE-1722] - Invalid argument supplied for foreach(), in app/src/Scalr/UI/Controller/Account2/Environments/Clouds.php:615
- [SCALRCORE-1731] - Argument 1 passed to Scalr_Util_DateTime::convertTimeZone() must be an instance of DateTime, boolean given, called in app/src/Scalr/UI/Controller/Logs.php on line 325 and defined, in app/src/Scalr/Util/DateTime.php:5
- [SCALRCORE-1734] - Account > Cost analytics > Projects :: add message after project removing
- [SCALRCORE-1744] - API failed to auth if OpenLDAP is used
- [SCALRCORE-1746] - Memory limit issue when polling AWS cloud with 1000+ instances

### 5.9.6

###### Bug
- [SCALRCORE-1706] - [APIv2] Transform dedicated API endpoint /{envId}/farm-roles/ into related endpoint /{envId}/farms/{farmId}/farm-roles/
- [SCALRCORE-1717] - Add union_script_executor flag under the Development tab in Farm Designer
- [SCALRCORE-1720] - SQL error in Logs list
- [SCALRCORE-1723] - Update AWS instance types definitions
- [SCALRCORE-1724] - Regression in DNS

### 5.9.5

###### Improvement
- [SCALRCORE-1207] - Impovements in role builder, bundletasks
- [SCALRCORE-1343] - SSH Keys Formats

###### Bug
- [SCALRCORE-1589] - E_WARNING Invalid argument supplied for foreach(), in app/src/Scalr/Modules/Platforms/Ec2/Ec2PlatformModule.php:1039
- [SCALRCORE-1615] - Environment > Apache VH :: Domain name validation does not pass on test environments
- [SCALRCORE-1677] - [APIv2] Projects list returns projects from another accounts
- [SCALRCORE-1700] - [APIv2] user.yaml does not correspond to UC
- [SCALRCORE-1711] - [APIv2] GET /env-id/cost-centers wrong filtering

### 5.9.4

###### Bug
- [SCALRCORE-1575] - Manage Account > Cost Analytics > Projects :: not correct date range on graph
- [SCALRCORE-1701] - [APIv2] Farms > remove farm > Error in sql query 
- [SCALRCORE-1702] - CA > AWS Detailed Billing > Analytics processing > Missing Scalr meta
- [SCALRCORE-1703] - CA > AWS Detailed Billing > UsageType 'HeavyUsage' is not processed

### 5.9.3

###### Improvement
- [SCALRCORE-1626] - [APIv2] Implement Launch/Terminate farm methods

###### Bug
- [SCALRCORE-711] - [MySQL Optimization] logentries filtering
- [SCALRCORE-1644] - Environment > Cost Analytics :: tooltips on graph doesn't appear after clicking on Events (on Windows OS)
- [SCALRCORE-1652] - Last login time returned incorrectly at logout event
- [SCALRCORE-1656] - Manage Account > Cost Analytics > Projects > New :: “Project Name” field can contain only spaces
- [SCALRCORE-1664] - Snapshots Not Appearing in Farm
- [SCALRCORE-1674] - Environment > Cost Analytics :: Table misalignment
- [SCALRCORE-1675] - Account > Teams :: error in console after filtering and action (add/remove) with user
- [SCALRCORE-1676] - [APIv2] It is possible to remove running farm
- [SCALRCORE-1698] - crontab > cloudPoller > E_WARNING sprintf(): Too few arguments, in Scalr/System/Zmq/Cron/Task/CloudPoller.php:226

### 5.9.2

###### Improvement
- [SCALRCORE-1642] - Add support for KMS and encrypted volumes on Volumes manage page

###### Bug
- [SCALRCORE-1667] - Server snapshot on Openstack doesn't work
- [SCALRCORE-1670] - Instance Removed from Chef on Suspend
- [SCALRCORE-1679] - Haproxy bug

### 5.9.1

###### Improvement
- [SCALRCORE-1350] - [APIv2] Implement /{envId}/farms & farm-roles methods

###### Bug
- [SCALRCORE-1645] - Ajax upload response issue
- [SCALRCORE-1648] - [APIv2] Achived Projects & Cost Centers issue
- [SCALRCORE-1653] - New environment :: First login attempt fails
- [SCALRCORE-1661] - Bug with TagField (lastRecord)

### 5.9.0

###### New Feature
- [SCALRCORE-1630] - Stop/Resume refactoring (+ Support on Windows). ResumeComple event. (BC changes in behavior)
- [SCALRCORE-1378] - Audit log streaming

###### Improvement
- [SCALRCORE-1543] - Global variables UI/UX improvements
- [SCALRCORE-1638] - AWS > xListVolumes performance optimization
- [SCALRCORE-1641] - AWS Client > update EC2 API client to the latest version

###### Bug
- [SCALRCORE-1473] - Account > Users :: Last Login time is shown incorrectly
- [SCALRCORE-1645] - Ajax upload response issue
- [SCALRCORE-1649] - [APIv2] POST /{envId}/projects/ : error in yaml
- [SCALRCORE-1654] - Division by zero, in app/src/Scalr/Stats/CostAnalytics/Usage.php
