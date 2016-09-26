CHANGELOG for 5.10.21 - 5.11.22
===================

This changelog references the relevant changes (bug and security fixes) done in 5.10 - 5.11 versions.

To get the diff between two versions, go to https://github.com/Scalr/scalr/compare/v5.10.21...v5.11.22


### 5.11.22

###### Bug
- [SCALRCORE-3591] - APIv2 > Multiple consistency issues with servers endpoints
- [SCALRCORE-3637] - Cannot access billing bucket error when enabling detailed billing for new environments

### 5.11.21

###### Improvement
- [SCALRCORE-3286] - Search webhooks by payload
- [SCALRCORE-3503] - UI > Descriptions for Teams combobox
- [SCALRCORE-3508] - Minor UI improvements + removed old tooltips
- [SCALRCORE-1898] - APIv2 > servers endpoints

###### Bug
- [SCALRCORE-3137] - Servers > Search drop-down :: ESC key doesn't close enveloped drop-downs
- [SCALRCORE-3455] - APIv2 > ReadOnly / required issue
- [SCALRCORE-3258] - DB Backups > Database status :: fix incorrect icons
- [SCALRCORE-3453] - Orchestration log :: New search query does not cancel a previous one
- [SCALRCORE-3454] - Global variables > New variable :: categories with the same name appears in dropdown
- [SCALRCORE-3507] - when I change the instance type of a VM in GCE scalr doesn't reflect the change
- [SCALRCORE-3523] - Azure: Suspend/Resume doesn't work
- [SCALRCORE-3526] - APIv2 > Cloning farm fails with 500 error

### 5.11.20

###### Improvement
- [SCALRCORE-3362] - APIv2 > Create Environment's Teams method. List ACL Roles. **BC change in APIv2**
- [SCALRCORE-3405] - CA > Azure > Handle Gateway Timeout errors

###### Bug
- [SCALRCORE-3482] - APIv2 > Multiple issues with GV API methods
 
###### BC changes
Two API calls were affected by the change:

> /environments/{envId}/teams/
>
> /environments/{envId}/teams/{teamId}/

**Before:** Endpoints have used `Team` object.
```
POST http://my.scalr.lo/api/v1beta0/account/environments/1/teams HTTP/1.1 
Content-Type: application/json;charset=utf-8 
X-Scalr-Key-Id: APIKD4SV9XPFCEXAMPLE 
X-Scalr-Date: 2016-04-13T10:42:45Z 
X-Scalr-Signature: V1-HMAC-SHA256 Tok1LNbMzi1OXeOavMFBo2SPs7icCuky7hH7pyTElVQ= 
Content-Length: 10 

{"id":"1"}
```

**After:** Endpoints use `EnvironmentTeam` object. 

```
POST http://my.scalr.lo/api/v1beta0/account/environments/1/teams HTTP/1.1 
Content-Type: application/json;charset=utf-8 
X-Scalr-Key-Id: APIKD4SV9XPFCEXAMPLE 
X-Scalr-Date: 2016-04-13T10:41:40Z 
X-Scalr-Signature: V1-HMAC-SHA256 XnU9ml/X3TKuHsnWaf9itCz0iAMBsF3H8vK+8A3tCxo= 
Content-Length: 19 

{"team":{"id":"1"}}
```

### 5.11.19

###### Bug
- [SCALRCORE-2906] - Combobox > Bug with selection
- [SCALRCORE-3197] - Image create :: wrong icon after switching between cloud tabs
- [SCALRCORE-3280] - PHP Fatal error: Call to a member function getRole on null in app/src/Scalr.php on line 572
- [SCALRCORE-3365] - Images > image creation :: fix text on Cancel confirmation
- [SCALRCORE-3401] - In menu "GCE volumes" field "Used by" is blank always
- [SCALRCORE-3416] - Account > About Scalr : Unable to view About Scalr from Account scope
- [SCALRCORE-3461] - Role Builder: Oracle Enterprise Linux Server 5.X Tikanga does not work
- [SCALRCORE-3462] - APIv2 > POST Endpoint /environments/{envId}/teams/ Broken *affected version 5.11.18*
- [SCALRCORE-3463] - Rackspace/Openstack Suspend and Resume issue for instances without public IP
- [SCALRCORE-3464] - Improper transaction management in Update20151030083847.php
- [SCALRCORE-3466] - Rabbitmq automation not terminating correctly
- [SCALRCORE-3473] - Images Disappearing at the Account Level

### 5.11.18

###### New Feature
- [SCALRCORE-2995] - Multiple Teams Assigned to a Farm **BC change in APIv2 and AuditLog**

###### Improvement
- [SCALRCORE-1208] - UI > Change Environment improvement
- [SCALRCORE-2421] - Add script.execute event to AuditLog
- [SCALRCORE-3335] - Scalr Health Dashboard > Manage hosts
- [SCALRCORE-3417] - APIv1 : Backward compatibility break with instance-type for FarmRole configuration

###### Bug
- [SCALRCORE-2675] - Pressed disabled button doesn't look like button
- [SCALRCORE-2680] - Environment > Role edit/create > It is possible to add an image out of the availability list
- [SCALRCORE-2824] - Add usage csv format to the usage-report tool / GCE vcpu not collected
- [SCALRCORE-3269] - Sorting does not work properly on some pages
- [SCALRCORE-3319] - AWS > Route 53 > New Zone :: Record set table is broken
- [SCALRCORE-3321] - Create Role from non-scalr server on GCE. Validate Role name
- [SCALRCORE-3382] - Expand button is not working in the codemirror component
- [SCALRCORE-3395] - Dashboard and "What's new in Scalr" > Announcements :: Visual defect with tooltip
- [SCALRCORE-3402] - DNS not updated after resuming instances
- [SCALRCORE-3418] - Servers stuck in RESUMING state after resuming from hibernation *without os restart*
- [SCALRCORE-3439] - APIv2 > Farm scope GV should not require Environment scope permission
- [SCALRCORE-3440] - Configure runlist not working

###### BC changes
1 APIv2 `teamOwner` field of the Farm object has been replaced with teams field, and latter represents the list of the Teams which share Farm ownership.

**Before:** `Farm` object had `teamOwner` property which stood for the `Team` ID that the `Farm` is owned by. `NULL` was accepted as well.

**After:** `Farm` object has `teams` property which holds the list of the `Team` IDs which share `Farm` ownership. `NULL` is accepted as well.

2 Some Audit Log fields have been changed:

`farm.launch.owner.team_id` was replaced with `farm.launch.owner.teams`

`farm.terminate.owner.team_id` was replaced with `farm.terminate.owner.teams`

It contains either array of the identifiers of the `Teams` by which the `Farm` is owned or `NULL` if `Farm` has no `Team` owner.

### 5.11.17

###### New Feature
- [SCALRCORE-2652] - Account Announcement Functionality
- [SCALRCORE-3283] - New custom scaling metric type: URL-Request

###### Improvement
- [SCALRCORE-3276] - Discovery manager UX improvements
- [SCALRCORE-3394] - Add FarmRole min/max instances to szradm

###### Bug
- [SCALRCORE-2960] - pecl_http notice w/ curl 7.43
- [SCALRCORE-3317] - AWS S3 : Proper error message on creating bucket that already exists
- [SCALRCORE-3341] - RDS > Security Groups > Issues with Security Rules
- [SCALRCORE-3352] - CA > Analytics processor MySQL integrity error 
 
###### Updated PHP libraries
- pecl_http 2.5.6

### 5.11.16

###### Improvement
- [SCALRCORE-3130] - Set default Environment list for Account *safe Environments*
- [SCALRCORE-3351] - Support installing on Scientific Linux 5-7 in scalarizr one-liner

###### Bug
- [SCALRCORE-3181] - Environment > Custom Scaling Metrics :: Misleading message is present.
- [SCALRCORE-3226] - Servers: User should not be able to initiate snapshot creation from Pending server
- [SCALRCORE-3246] - Could not bulk remove EBS Volumes
- [SCALRCORE-3259] - Validate vhost config before save
- [SCALRCORE-3288] - APIv2 > Security improvement
- [SCALRCORE-3315] - Servers > Cloudstack : DB Error on Execute script
- [SCALRCORE-3327] - Cloud credentials are not copied during environment cloning.
- [SCALRCORE-3328] - Farm/Roles view > Actions :: add icon for action "Execute xxxx script"
- [SCALRCORE-3371] - Server stuck in Rebooting state after suspend during rebundle
- [SCALRCORE-3374] - Security groups issue for OpenStack

### 5.11.15

###### Improvement
- [SCALRCORE-3263] - Apply OEL fix for scalarizr one-liner
- [SCALRCORE-3330] - Chef cookbooks not showing in scalr

###### Bug
- [SCALRCORE-3036] - AWS > Route 53 > Alias target : AWS error
- [SCALRCORE-3212] - APIv2 > Security Improvements
- [SCALRCORE-3219] - GCE Resuming Server :: fix error tooltip on "View console output"
- [SCALRCORE-3290] - Missing FK on analytics.managed table
- [SCALRCORE-3323] - t3 TypeError: governanceSecurityGroups.split is not a function. *In 'governanceSecurityGroups.split(',')', 'governanceSecurityGroups.split' is undefined*
- [SCALRCORE-3324] - Ext4 is not available in UI for debian
- [SCALRCORE-3329] - Farm Designer > Storage :: incorrect error message
- [SCALRCORE-3332] - Download (AWS S3) DB backups via https
- [SCALRCORE-3352] - CA > Analytics processor MySQL integrity error
- [SCALRCORE-3354] - CA > analytics_processing UnboundLocalError issue
- [SCALRCORE-3359] - ListRoles Scalr scope: error in your SQL syntax
- Fix error: Undefined class constant 'RESOURCE\_ORPHANED\_SERVERS' while upgrading from Scalr version 5.8

### 5.11.14

###### Improvement
- [SCALRCORE-3301] - Improve UI when creating new APIv2 key
- [SCALRCORE-3282] - APIv2 > Documentation And Specification Deployment Automation

###### Bug
- [SCALRCORE-3204] - Azure Image :: "Remove image from cloud" check-box should be removed
- [SCALRCORE-3314] - GCE > Role Builder error > Farm Role ID #0 not found (affected version 5.11.13)
- [SCALRCORE-3320] - Broken date in orchestration log (affected version 5.11.13)
- [SCALRCORE-3238] - APIv2 > Documentation deployment > sorting rules correction

### 5.11.13

###### New Feature
- [SCALRCORE-3270] - GCE instance permissions

###### Improvement
- [SCALRCORE-2534] - Orchestration log refactoring: Track who and how executed script. Improved UI performance and usability. 
- [SCALRCORE-3097] - Force Scalarizr to init after reboot on CloudStack to handle stop/resume + hibenation
- [SCALRCORE-3105] - Roles List Performance Improvements
- [SCALRCORE-3206] - Storage > Mount options for Linux disks
- [SCALRCORE-3222] - CA > Analytics Poller logging improvement
- [SCALRCORE-3256] - msg_sender reliability improvement
- [SCALRCORE-3272] - Remove Rackspace First Gen Cloud

###### Bug
- [SCALRCORE-3059] - APIv2 > Forbid to remove custom events that is used by some Role, Farm Role or Webhook.
- [SCALRCORE-3213] - APIv2 > Scaling does not work for GCE Role
- [SCALRCORE-3241] - Access-Control-Allow-Origin missing in APIv2 (Add scalr.system.api.allowed_origins: "*" to config.yaml)
- [SCALRCORE-3248] - OpenStack error keystone v3. Could not find token
- [SCALRCORE-3249] - Usage report > AWS VCPU statistics not collected
- [SCALRCORE-3252] - PHP Fatal error: Call to a member function get() on null in app/src/Scalr/Modules/Platforms/Ec2/Helpers/Ec2Helper.php on line 69
- [SCALRCORE-3253] - Manage Account > Cost Analytics > Notifications :: Sorting by "status" column doesn't work
- [SCALRCORE-3265] - Cannot change "Enable SSL certificate" for Openstack platform
- [SCALRCORE-3267] - APIv2 > Server import > cloudServerId must be in Request BODY (BC change in Farm clone / terminate and import-server API Requests)
- [SCALRCORE-3277] - ELB Permissions issue

###### Updated Python packages
- oauth2client 2.0.1
- boto 2.39.0
- pymysql 0.7.1
- cherrypy 5.0.1
- apache-libcloud 0.20.1
- google-api-python-client 1.5.0
 
###### BC changes
**Before:** Following API requests expected parameters to be provided in the URI.

> POST user/{envId}/farms/{farmId}/actions/clone?name=test-api-clone  
>
> POST user/{envId}/farms/{farmId}/actions/terminate?force=1
>
> POST user/{envId}/farm-roles/{farmRoleId}/actions/import-server?cloudServerId=i-288498349

**After:** Following API requests require parameters to be provided in the body as JSON
> POST user/{envId}/farms/{farmId}/actions/clone
>
> {name: test-api-clone}
>
> POST user/{envId}/farms/{farmId}/actions/terminate
>
> {force: true}
>
> POST user/{envId}/farm-roles/{farmRoleId}/actions/import-server
>
> {cloudServerId: "i-288498349"}
 
### 5.11.12

###### New Feature
- [SCALRCORE-2685] - "Warning Banner" between login and dashboard.
- [SCALRCORE-2860] - Project Brownfield (Phase 2: Manual import non-scalr servers)

###### Improvement
- [SCALRCORE-2452] - CA > Account scope budget notifications
- [SCALRCORE-3122] - Support multiple RabbitMQ roles within a farm
- [SCALRCORE-3188] - Remove deprecated features (Deployments, ServiceConfigPresetsV1)
- [SCALRCORE-3199] - Azure > Add support for the paid Marketplace images
- [SCALRCORE-3227] - Azure > Governance tasks
- [SCALRCORE-3228] - Azure > use configure repo instead of always latest
- [SCALRCORE-3229] - Add a config option to ignore missing servers on Openstack
- [SCALRCORE-3231] - Message sender reliability improvement
- [SCALRCORE-3236] - APIv2 > Be consistent with scope parameter for POST /ENV_ID/scripts/ (BC change: It's impossible to override scope anymore for environment level scripts endpoint)

###### Bug
- [SCALRCORE-3195] - GCE :: Servers do not return to the Running state after suspend
- [SCALRCORE-3198] - Scalr grabbing unroutable floating IP addresses for OpenStack
- [SCALRCORE-3211] - Image builder scope issue
- [SCALRCORE-3237] - Pass Openstack domain_name in Scalarizr access data
- [SCALRCORE-3240] - APIv2 > Farms & FarmRoles validation issues
- [SCALRCORE-3242] - Scaling > SQS Queue size validation correction

### 5.11.11

###### New Feature
- [SCALRCORE-1964] - Scalr Health dashboard widget (Scalr scope)

###### Improvement
- [SCALRCORE-1897] - [APIv2] Extend ScalingConfiguration with ScalingRules
- [SCALRCORE-2688] - Add Suspend/Resume bulk action on Servers view page.
- [SCALRCORE-3119] - Support for subnetworks on GCE
- [SCALRCORE-3179] - CA > Poller logging improvements
- [SCALRCORE-3183] - Allow to add custom Azure images from the Marketplace

###### Bug
- [SCALRCORE-2047] - Farms > Scaling validation
- [SCALRCORE-3121] - Hostname is randomly changed on GCE
- [SCALRCORE-2842] - Account Dashboard > "New user checklist" widget :: add message in the grid if user have no access to Environments
- [SCALRCORE-2963] - UI > Forbid some kinds of Actions for Images with Deleting state
- [SCALRCORE-2988] - APIv2 > FarmRoles issues
- [SCALRCORE-3009] - ESC button doesn't close drop-downs properly
- [SCALRCORE-3034] - AWS > Route 53 use proper cloud location
- [SCALRCORE-3091] - Admin :: links "Wiki" and "Support" do not work
- [SCALRCORE-3109] - Incorrect Cloud Location in FarmRole Storage Device
- [SCALRCORE-3142] - SettingsCollection issue : Column 'name' cannot be null
- [SCALRCORE-3150] - Security groups > Missing required argument: cloudLocation
- [SCALRCORE-3185] - MongoDB - scaling broken
- [SCALRCORE-3189] - Azure instance type policy issue

### 5.11.10

###### Improvement
- [SCALRCORE-2888] - Add JSON to GV format list
- [SCALRCORE-3088] - Better scaling logging
- [SCALRCORE-2566] - APIv2 > Role object should include builtinAutomation property

###### Bug
- [SCALRCORE-2992] - Farm > Extended information : Disabled Project field dropdown can be expanded
- [SCALRCORE-3015] - Reset password pop-up is covered by login pop-up
- [SCALRCORE-3037] - Environment > Dashboard > "Last errors" widget :: fix link to not-existing server after Esc
- [SCALRCORE-3044] - Farm Designer > Scaling > DateAndTime :: problem with link "Change" for Time zone
- [SCALRCORE-3084] - DB Backups > Backup details :: fix link for "Type"
- [SCALRCORE-3093] - Environment > Roles > pop up "Select farm to add a role" :: "Roles" link does not work

### 5.11.9

###### Improvement
- [SCALRCORE-2783] - Create server snapshot for account-scope role
- [SCALRCORE-2823] - Allow to change Role name and category
- [SCALRCORE-2989] - Core > SettingCollection improvement
- [SCALRCORE-3076] - Do not show DNS related stuff in the UI if DNS functionality is disabled

###### Bug
- [SCALRCORE-3010] - Servers > Execute script : [E] Layout run failed
- [SCALRCORE-3039] - Popup focus isn't set properly
- [SCALRCORE-3045] - APIv2 > Forbid to remove Role that is used in Farm
- [SCALRCORE-3073] - PHP error on EBS Volumes/Snapshot (Attach/Detach/Create snapshot)
- [SCALRCORE-3074] - DN escape issues with LDAP auth
- [SCALRCORE-3080] - CloudCredentials cache is not updated when environment reconfigure
- [SCALRCORE-3081] - ELB - Machines Not Registering After Suspend/Resume

### 5.11.8

###### Improvement
- [SCALRCORE-2461] - Rename Image status delete to pending_delete
- [SCALRCORE-2639] - New log streams. Added API log streaming and User log streaming. 
(BC change in configuration for AuditLog, also changed format of AuditLog entries)

###### Bug
- [SCALRCORE-2923] - RDS Instance Type Not Updating After Modify
- [SCALRCORE-2952] - Environment > Dashboard > "AWS health status" widget :: fix extra space in error-tooltip
- [SCALRCORE-3000] - ESC button doesn't close popups properly yet
- [SCALRCORE-3001] - APIv2 > Security impovements
- [SCALRCORE-3014] - Farm builder > Mongo DB role > SSL certificate :: Uncaught TypeError: Cannot read property 'dom' of null
- [SCALRCORE-3040] - Suppress E\_WARNING session\_start(): Memcached: Failed to read session data: NOT FOUND

### 5.11.7

###### New Feature
- [SCALRCORE-2955] - Add governance policy - Require EBS volume encryption
- [SCALRCORE-2956] - Proxy support for Webhooks, Azure and Cloudstack
- [SCALRCORE-2981] - Security groups - Protocol ANY

###### Improvement
- [SCALRCORE-2607] - RDS > Improve functional tests
- [SCALRCORE-2852] - APIv2 > Implement POST/PATCH/DELETE endpoints in consistency test
- [SCALRCORE-2909] - Add more details about instances registered on ELBs
- [SCALRCORE-2935] - APIv2 > Implement Images deleteFromCloud flag
- [SCALRCORE-2942] - Add live filter to timezone field in Farms Builder
- [SCALRCORE-2974] - Add more information about server to query-env interface
- [SCALRCORE-2977] - Remove old upgrades <= Scalr Version 5.1
- [SCALRCORE-2984] - APIv2 > FarmRole endpoints contains excessive ACL checks

###### Bug
- [SCALRCORE-2474] - Messaging DST issue
- [SCALRCORE-2509] - Remove scheduler task endTime
- [SCALRCORE-2789] - Account Dashboard > widget "Environments in this account" :: sorting by Farms is not correct
- [SCALRCORE-2841] - Account Dashboard > "Environments .." widget :: extra space if one Env. in the grid
- [SCALRCORE-2911] - RDS - Microsoft SQL Server Mirroring
- [SCALRCORE-2920] - Admin > Dashboard :: Broken First Steps list in FF
- [SCALRCORE-2926] - Dashboard :: Roles are not aligned in Farm widget (Firefox)
- [SCALRCORE-2933] - CA > Pricing > no table of prices for some locations
- [SCALRCORE-2936] - CA > Pricing > Impossible to put pointer in input (Firefox)
- [SCALRCORE-2944] - RDS - Parameter Group Not Appearing
- [SCALRCORE-2949] - Issue with moving ELB between Farm Roles
- [SCALRCORE-2950] - Farm Designer > Storage :: add "Delete" button for Additional storage (Firefox)
- [SCALRCORE-2964] - Security group info is missing under Security tab in Farm Designer
- [SCALRCORE-2965] - Dashboard :: Uncaught TypeError after actions with widgets
- [SCALRCORE-2978] - Bug > Type Acl not found
- [SCALRCORE-2979] - Bug > The type AzurePlatformModule cannot be resolved

### 5.11.6

###### Improvement
- [SCALRCORE-1035] - Remove legacy UsageStatsPoller
- [SCALRCORE-2934] - Add search by Farm ID to Search component

###### Bug
- [SCALRCORE-2781] - Trim Development->SCM Branch values in Farm Designer
- [SCALRCORE-2946] - Additional storage should be disabled for agentless FarmRoles
- [SCALRCORE-2954] - Openstack client doesn't work if Keystone is using self signed certificate.
- [SCALRCORE-2958] - Improve saving data in Farm Designer

### 5.11.5

###### Improvement
- [SCALRCORE-2809] - BandWidth scaling sensor improvements
- [SCALRCORE-2820] - Minor change in dialog message on adding a new User

###### Bug
- [SCALRCORE-2855] - Start Farm with prohibited (in Governance) instance type
- [SCALRCORE-2870] - APIv2 > POST/PATCH/DELETE issues
- [SCALRCORE-2892] - PHP Fatal error: Call to a member function keychain() on null in app/src/Scalr/UI/Controller/Roles.php on line 378
- [SCALRCORE-2899] - Improve replace image in role edit
- [SCALRCORE-2908] - Remove Azure cloud from Import non-scalr server page
- [SCALRCORE-2913] - RDS Event logs :: sorting by Time doesn't work
- [SCALRCORE-2918] - RDS > DB Instances :: Idle session does not end after timeout period with 'Remember me' unchecked
- [SCALRCORE-2929] - Farm Designer > Orchestration :: problem with Target "Selected roles:"
- [SCALRCORE-2937] - t3 TypeError: null is not an object (evaluating 'field.store.load')
- [SCALRCORE-2943] - APIv1 Farm Create broken

### 5.11.4

###### Improvement
- [SCALRCORE-1399] - Minor UI improvements
- [SCALRCORE-2884] - Python services > Script config options should have higher priority over config.yaml

###### Bug
- [SCALRCORE-1917] - More control over session management
- [SCALRCORE-2369] - RDS > show pending values
- [SCALRCORE-2413] - Environment > Farms/Designer :: Orchestration and Network should be available if "NO ACCESS" in ACL
- [SCALRCORE-2875] - Role builder fails for new AWS (Seoul) region
- [SCALRCORE-2879] - Multiple issues with migration from version 5.8 to 5.10
- [SCALRCORE-2896] - Add db.t2.large instance type value to drop down in RDS
- [SCALRCORE-2902] - Python scripts logger incompatibility with system logrotate
- [SCALRCORE-2903] - CA > AWS Detailed billing > Recalculate past periods for files without scalr-meta
- [SCALRCORE-2915] - UI > Create account password special characters
- [SCALRCORE-2916] - Loading locations stuck when mouseover
- [SCALRCORE-2924] - Handle situation when server in Resuming state in scalr but stopped on EC2

### 5.11.3

###### Improvement
- [SCALRCORE-2802] - Add option for enhanced networking support
- [SCALRCORE-2877] - Add route53 hosted zone id for new AWS ap-northeast-2 (Seoul) region
- [SCALRCORE-2878] - The MongoDB automated role has been deprecated, but it is still presented as an option in Role Builder, it should be removed
- [SCALRCORE-2880] - Add Project ID on Project settings/edit page

###### Bug
- [SCALRCORE-2811] - EC2 instance resize > instance\_type\_name is not updated
- [SCALRCORE-2862] - Servers > LA column > fix progress icon
- [SCALRCORE-2871] - CA > AWS Detailed Billing > UnBlendedCost should be used
- [SCALRCORE-2876] - CA > analytics_poller to support AWS ap-northeast-2 region
- [SCALRCORE-2882] - CA > Regression with cloud_credentials in analytics_poller
- [SCALRCORE-2883] - CA > Regression with pricing in UI
- [SCALRCORE-2900] - Can't Update Openstack Cloud Keys

### 5.11.2

###### Improvement
- [SCALRCORE-2874] - Add support of the AWS Asia Pacific (Seoul) Region

###### Bug
- [SCALRCORE-2380] - GCE - Can't hard reboot server from Scalr UI
- [SCALRCORE-2872] - Scalarizr deployment via cloud-init doesn't work on Ubuntu

### 5.11.1

###### Bug
- [SCALRCORE-1982] - Role editor fixes
- [SCALRCORE-2837] - ESC button doesn't close popups properly
- [SCALRCORE-2853] - Multiple records selection using keyboard doesn't work
- [SCALRCORE-2857] - Servers :: problem with "LA" column if "Servers" is unchecked in ACL
- [SCALRCORE-2861] - CLI v2 Configure Issue
- [SCALRCORE-2864] - Farm Designer > Add farm role:: Uncaught TypeError after press on "Chef" icon

### 5.11.0

###### New Feature
- [SCALRCORE-2066] - APIv2 > Add, Remove, Modify the Orchestration rules on Farm Roles
- [SCALRCORE-2067] - APIv2 > Environment Creation
- [SCALRCORE-2457] - APIv2 > Implement clone Farm operation
- [SCALRCORE-2588] - APIv2 > Implement API consistency test
- [SCALRCORE-1170] - Project Brownfield (Phase 1: Agentless servers, cloud-init support)

###### Improvement
- [SCALRCORE-2601] - ACL to disable Farm Creation
- [SCALRCORE-2621] - Improve Role edit > add Image UI
- [SCALRCORE-2663] - ACL > Separate CA Projects resource
- [SCALRCORE-2677] - CA > Move existing Projects between Cost Centers
- [SCALRCORE-2763] - Reduce severity level for: Cannot start service, another one is already running
- [SCALRCORE-2801] - DI cloudCredentials service consistency
- [SCALRCORE-2813] - Add runlist builder to additional runlist

###### Bug
- [SCALRCORE-2604] - CA > Quarterly periodic report issue
- [SCALRCORE-2705] - Scalr > New Account :: Required fields are highlighted in odd order
- [SCALRCORE-2751] - Farm designer > Add role > VPC subnet : Extra space on a shorter list
- [SCALRCORE-2784] - APIv2 > API specification mistakes
- [SCALRCORE-2828] - Roles :: Azure Role "In use" can be saved without Image
- [SCALRCORE-2839] - When creating a server snapshot of a role in account scope the image loses most of its details like HVM
- [SCALRCORE-2840] - APIv2 > ADODB_Exception: mysqli error: [1048: Column 'has\_cloud\_init' cannot be null]
- [SCALRCORE-2843] - Servers :: remove action "Create server snapshot" if no access for "Servers" in ACL
- [SCALRCORE-2847] - ERROR platform: cloudstack, env_id:N, reason: <type 'exceptions.AttributeError'> 'str' object has no attribute 'values'

