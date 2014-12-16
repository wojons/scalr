Scalr.regPage('Scalr.ui.roles.import.view', function (loadParams, moduleParams) {
	if (!moduleParams['platforms'].length) {
        Scalr.message.Flush(true);
		Scalr.message.Warning('Please configure cloud credentials first.');
		Scalr.event.fireEvent('redirect', '#/account/environments', true);
		return false;
	}

	var panel = Ext.create('Ext.panel.Panel', {
        title: 'Create role from non-Scalr running instance',
        cls: 'scalr-ui-role-import',
		scalrOptions: {
			maximize: 'all'
		},
        plugins: {
            ptype: 'localcachedrequest',
            crscope: 'servers.import'
        },
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		items:[{
            xtype: 'container',
            itemId: 'leftcol',
            cls: 'x-panel-column-left',
            autoScroll: true,
            flex: 1,
            maxWidth: 560,
            minWidth: 445,
            items: [{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                title: 'External server info',
                layout: 'anchor',
                defaults: {
                    anchor: '100%',
                    labelWidth: 96
                },
                items: [{
                    xtype: 'textfield',
                    name: 'roleName',
                    allowBlank: false,
                    vtype: 'rolename',
                    fieldLabel: 'Name',
                    listeners: {
                        change: {
                            fn: function() {
                                panel.onServerInfoChange();
                            },
                            buffer: 300
                        }
                    }
                },{
                    xtype: 'checkbox',
                    boxLabel: 'Only create an Image, do not create a Role using that Image',
                    name: 'roleImage',
                    listeners: {
                        boxready: function() {
                            if ('image' in loadParams || (moduleParams['server'] && moduleParams['server']['object'] == 'image')) {
                                this.setValue(true);
                            }
                        }
                    }
                },{
                    xtype: 'label',
                    style: 'display:block;text-align:center',
                    cls: 'x-label-grey',
                    html: 'Select the location of your current infrastructure, then pick a server.',
                    margin: '12 0'
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    margin: '0 0 10 0',
                    items: {
                        xtype: 'cloudlocationmap',
                        itemId: 'locationmap',
                        platforms: Scalr.platforms,
                        size: 'large',
                        autoSelect: true,
                        listeners: {
                            beforeselectlocation: function() {
                                return !panel.serverId;
                            },
                            selectlocation: function(location){
                                panel.down('[name="cloudLocation"]').setValue(location);
                            }
                        }
                    }
                },{
                    xtype: 'combo',
                    margin: '0 0 10 0',
                    name: 'cloudLocation',
                    editable: false,
                    fieldLabel: 'Cloud location',
                    valueField: 'id',
                    displayField: 'name',
                    queryMode: 'local',
                    emptyText: 'No information about locations',
                    store: {
                        fields: ['id', 'name'],
                        proxy: 'object'
                    },
                    listeners: {
                        change: function(comp, value) {
                            panel.down('#locationmap').setLocation(value);
                            panel.fireEvent('selectlocation', value);
                        }
                    }
                },{
                    xtype: 'combo',
                    name: 'cloudServerId',
                    fieldLabel: 'Server',
                    valueField: 'id',
                    displayField: 'id',
                    value: '',

                    forceSelection: true,
                    queryCaching: false,
                    minChars: 0,
                    queryDelay: 10,
                    clearDataBeforeQuery: true,
                    store: {
                        fields: ['id', 'localIp', 'publicIp', 'zone', 'isImporting', 'isManaged', 'serverId'],
                        proxy: {
                            type: 'cachedrequest',
                            crscope: 'servers.import',
                            filterFields: ['id', 'localIp', 'publicIp', 'zone'],//fliterFn
                            url: '/roles/import/xGetCloudServersList/'
                        }
                    },
                    listConfig: {
                        cls: 'x-boundlist-alt',
                        tpl:
                            '<tpl for="."><div class="x-boundlist-item<tpl if="isImporting||isManaged"> x-boundlist-item-unavailable</tpl>" style="height: auto; width: auto">' +
                                '<div><span style="font-weight: bold">{id}</span> ({zone})</div>' +
                                '<div style="font-weight: bold"><tpl if="isImporting"><span style="color:#ae2700">Importing</span><tpl elseif="isManaged"><span style="color:orange">Scalr managed</span><tpl else><span style="color:green">Available to import</span></tpl>'+
                                ', Private IP: <span style="font-weight: bold">{localIp}</span>, Public IP: <span style="font-weight: bold">{publicIp}</span></div>' +
                            '</div></tpl>'
                    },
                    updateEmptyText: function(type){
                        this.emptyText =  type ? 'Please select server' : 'No running servers found, please select another location'
                        this.applyEmptyText();
                    },
                    listeners: {
                        afterrender: function(){
                            var me = this;
                            me.updateEmptyText(true);
                            me.store.on('load', function(store, records, result){
                                me.updateEmptyText(records && records.length > 0);
                            });
                        },
                        beforeselect: function(comp, rec){
                            if (rec.get('isImporting')) {
                                Scalr.message.InfoTip('This server is already being imported. <a href="#/roles/import?serverId=' + rec.get('serverId') + '">Click here</a> to check import status.', comp.inputEl, {anchor: 'bottom', clickable: true, hideDelay: 400});
                                return false;
                            } else if (rec.get('isManaged')) {
                                Scalr.message.InfoTip('Selected server is already under Scalr management.', comp.inputEl, {anchor: 'bottom'});
                                return false;
                            }
                        },
                        change: function(comp, value) {
                            if (value && comp.findRecordByValue(value)) {
                                panel.onServerInfoChange();
                            }
                        }
                    }
                },{
                    xtype: 'container',
                    itemId: 'continue',
                    cls: 'x-docked-buttons',
                    hidden: true,
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: {
                        xtype: 'button',
                        text: 'Start building',
                        handler: function() {
                            panel.startImport();
                        }
                    }
                }]
            },{
                xtype: 'container',
                itemId: 'installinfo',
                cls: 'x-container-fieldset x-fieldset-separator-top',
                hidden: true,
                layout: 'anchor',
                defaults: {
                    anchor: '100%',
                    labelWidth: 80
                },
                items: [{
                    xtype: 'component',
                    cls: 'x-fieldset-subheader',
                    html: 'Setup Scalarizr',
                    margin: '-6 0 6 0'
                },{
                    xtype: 'displayfield',
                    cls: 'x-form-field-info',
                    value: '<b style="position:relative;top:-2px"><a target="_blank" href="https://scalr-wiki.atlassian.net/wiki/x/1w8b">Please follow these instructions to install scalarizr on your server.</a></b><br/><i>Make sure to open TCP port 8013 in your firewall (IPtables,&nbsp;security&nbsp;groups&nbsp;etc.)</i>'
                },{
                    xtype: 'component',
                    cls: 'x-fieldset-subheader',
                    html: 'Launch Scalarizr',
                    margin: '0 0 6 0'
                },{
                    xtype: 'label',
                    cls: 'x-label-grey',
                    html: 'Use this command to launch Scalarizr on your server. Then press "Confirm Scalarizr launch".'
                },{
                    xtype:'textarea',
                    itemId: 'cmd',
                    margin: '6 0 0 0',
                    readOnly: true,
                    fieldStyle: 'color:#000',
                    height: 130
                },{
                    xtype: 'container',
                    itemId: 'confirmLaunch',
                    cls: 'x-docked-buttons',
                    padding: '18 0 0 0',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        width: 220,
                        text: '<img src="/ui2/images/ui/roles/import/confirm-button.png" style="vertical-align:top;width:16px;height;16px" />&nbsp;&nbsp;Confirm Scalarizr launch',
                        handler: function() {
                            panel.down('#rightcol').show();
                            this.up('#confirmLaunch').hide();
                            Scalr.event.fireEvent('redirect', '#/roles/import?serverId=' + panel.serverId);
                        }
                    },{
                        xtype: 'button',
                        itemId: 'cancel',
                        text: 'Cancel',
                        width: 100,
                        handler: function() {
                            panel.cancelImport(true);
                        }
                    }]
                }]
            }]
        },{
            xtype: 'panel',
            itemId: 'rightcol',
            hidden: true,
            autoScroll: true,
            flex: 1,
            items: [{
                xtype: 'fieldset',
                title: (moduleParams['server'] && moduleParams['server']['object'] == 'role' ? 'Role' : 'Image') + ' creation progress',
                cls: 'x-fieldset-separator-none',
                itemId: 'progress',
                layout: {
                    type: 'hbox',
                    align: 'stretch'
                },
                defaults: {
                    xtype: 'component',
                    flex: 1,
                    cls: 'scalr-ui-progress-step',
                    height: 26,
                    maxWidth: 300
                },
                resetProgress: function() {
                    this.items.each(function(item, index){
                        if (index === 0) {
                            item.addCls('inprogress');
                            item.removeCls('complete');
                        } else {
                            item.removeCls('inprogress complete');
                        }
                    });
                },
                updateProgress: function(status, state) {
                    var data = {
                        'failed': -1,
                        'pending': 0,
                        'preparing': 0,
                        'in-progress': 1,
                        'creating-role': 3,
                        'success': 5
                    };
                    if (data[status]) {
                        this.items.each(function(item, index){
                            if (index < data[status]) {
                                item.removeCls('inprogress');
                                item.addCls('complete');
                            } else if (index === data[status]) {
                                item.addCls(state || 'inprogress');
                            } else {
                                item.removeCls('inprogress complete');
                            }
                        });
                    }
                },
                items: [{
                    xtype: 'component',
                    html: 'Connecting'
                },{
                    xtype: 'component',
                    html: 'Creating image'
                },{
                    xtype: 'component',
                    html: 'Setting automation'
                },{
                    xtype: 'component',
                    html: 'Building ' + (moduleParams['server'] ? moduleParams['server']['object'] : '')
                },{
                    xtype: 'component',
                    html: 'Complete!'
                }]
            },{
                xtype: 'component',
                itemId: 'waiting',
                style: 'text-align:center',
                margin: '36 0 0',
                html: '<div style="margin:18px 0;color:#666">Waiting to establish communication with running Scalarizr...</div>'+
                      '<table class="comm-status"><tr>'+
                      '<td><div class="icon-scalr"></div><b>Scalr</b></td>'+
                      '<td width="245"><div class="comm-status-inbound" id="inbound"></div><div class="comm-status-outbound" id="outbound"></div></td>'+
                      '<td><div class="icon-scalarizr"></div><b>Scalarizr</b></td>'+
                      '</tr></table>'+
                      '<div id="comm-error"></div>'
            },{
                xtype: 'fieldset',
                itemId: 'automation',
                cls: 'x-fieldset-separator-top',
                hidden: true,
                title: 'Select Scalr automation <span class="x-fieldset-header-description">Scalr can automate this ' + (moduleParams['server'] ? moduleParams['server']['object'] : '') + ' based on certain installed software:</span>',
                defaults: {
                    anchor: '100%'
                },
                addBehaviors: function(behaviors){
                    var ct = this.down('#behaviors'),
                        automationVisible = moduleParams['step'] == 2;
                    behaviors = behaviors || [];
                    if (!automationVisible) {
                        automationVisible = behaviors.length > 1 || behaviors.length === 1 && behaviors[0] !== 'base';
                    }
                    ct.setVisible(automationVisible);
                    this.down('#noautomation').setVisible(!automationVisible);
                    ct.suspendLayouts();
                    ct.removeAll();
                    for (var i=0, len=behaviors.length; i<len; i++) {
                        var isBaseBehavior = behaviors[i] === 'base' || behaviors[i] === 'chef';
                        ct.add({
                            iconCls: 'x-icon-behavior-large x-icon-behavior-large-' + behaviors[i],
                            text: Scalr.utils.beautifyBehavior(behaviors[i]),
                            behavior: behaviors[i],
                            disabled: isBaseBehavior,
                            pressed: moduleParams['step'] == 2 || isBaseBehavior,
                            tooltip: isBaseBehavior ? 'Enabled by default' : ''
                        });
                    }
                    ct.resumeLayouts(true);
                },
                items: [{
                    xtype: 'displayfield',
                    itemId: 'serverinfo',
                    fieldLabel: 'OS',
                    labelWidth: 60
                },{
                    xtype: 'displayfield',
                    itemId: 'noautomation',
                    cls: 'x-form-field-info',
                    margin: '12 0 0',
                    value: 'No software supported by built-in Scalr automation was found on this server. You can still continue with Server Import.'
                },{
                    xtype: 'fieldcontainer',
                    itemId: 'behaviors',
                    fieldLabel: 'Software:&nbsp;&nbsp;<label class="x-label-grey">(Click on an icon to add the corresponding prebuilt automation)</label>',
                    labelSeparator: '',
                    labelAlign: 'top',
                    margin: '-4 0 0 0',
                    defaults: {
                        xtype: 'button',
                        ui: 'simple',
                        enableToggle: true,
                        cls: 'x-btn-simple-large',
                        iconAlign: 'above',
                        margin: '10 10 0 0'
                    }
                }]
            },{
                xtype: 'fieldset',
                title: (moduleParams['server'] && moduleParams['server']['object'] == 'role' ? 'Role' : 'Image') + ' creation log',
                cls: 'x-fieldset-separator-top-bottom',
                itemId: 'log',
                collapsible: true,
                collapsed: true,
                hidden: true,
                setBundleTaskId: function(bundleTaskId) {
                    var rightcol = this.up('#rightcol');
                    this.bundleTaskId = bundleTaskId;
                    rightcol.down('#progress').updateProgress('in-progress');
                    this.down('#fullLog').update('<a target="_blank" href="#/bundletasks/'+bundleTaskId+'/logs">View full log in new tab</a>');
                    this.loadBundleTaskData();
                },
                loadBundleTaskData: function() {
                    var me = this;
                    me.stopAutoUpdate();
                    me.request = Scalr.Request({
                        params: {
                            bundleTaskId: this.bundleTaskId
                        },
                        url: '/roles/import/xGetBundleTaskData/',
                        success: function (data) {
                            if (!me.isDestroyed) {
                                var rightcol = me.up('#rightcol');
                                if (data['status'] === 'failed') {
                                    rightcol.down('#progress').updateProgress(data['status']);
                                    panel.onBundleTaskFailed(data['failureReason']);
                                } else {
                                    me.down('grid').store.load({data: data['logs']});
                                    rightcol.down('#progress').updateProgress(data['status']);
                                    if (data['status'] !== 'success') {
                                        me.autoUpdateTask = setTimeout(function(){
                                            me.loadBundleTaskData();
                                        }, 5000);
                                    } else {
                                        panel.onBundleTaskSuccess(data);
                                    }
                                }
                            }
                        }
                    });

                },
                autoUpdateTask: null,
                stopAutoUpdate: function(){
                    if (this.request) {
                        Ext.Ajax.abort(this.request);
                    }
                    if (this.autoUpdateTask) {
                        clearTimeout(this.autoUpdateTask);
                        this.autoUpdateTask = null;
                    }
                },
                listeners: {
                    destroy: function() {
                        this.stopAutoUpdate();
                    },
                    hide: function() {
                        this.stopAutoUpdate();
                    }
                },
                items: [{
                    xtype: 'grid',
                    cls: 'x-grid-shadow',
                    plugins: [{
                        ptype: 'gridstore'
                    }, {
                        ptype: 'rowexpander',
                        rowBodyTpl: [
                            '<p><b>Message:</b> {message}</p>'
                        ]
                    }],
                    store: {
                        fields: [
                            {name: 'id', type: 'int'},
                            'dtadded','message'
                        ],
                        proxy: 'object'
                    },
                    viewConfig: {
                        emptyText: 'Log is empty',
                        focusedItemCls: 'x-noselection',
                        selectedItemCls: 'x-noselection',
                        getRowClass: function(record, rowIndex) {
                            return rowIndex === 0 ? 'x-grid-row-new' : '';
                        }
                    },
                    columns: [
                        { header: "Date", width: 165, dataIndex: 'dtadded', sortable: false },
                        { header: "Message", flex: 1, dataIndex: 'message', sortable: false }
                    ]
                },{
                    xtype: 'component',
                    itemId: 'fullLog',
                    margin: '6 0 0'
                }]
            },{
                xtype: 'component',
                itemId: 'success',
                hidden: true,
                cls: 'x-fieldset-subheader',
                style: 'text-align:center',
                margin: '48 0 16',
                html: 'New ' + (moduleParams['server'] ? moduleParams['server']['object'] : '') + ' has successfully been created'
            },{
                xtype: 'container',
                itemId: 'failed',
                hidden: true,
                margin: '32 0 16',
                items: [{
                    xtype: 'component',
                    cls: 'x-fieldset-subheader',
                    style: 'text-align:center',
                    margin: '0 0 16',
                    html: (moduleParams['server'] && moduleParams['server']['object'] == 'role' ? 'Role' : 'Image') + ' creation failed'
                },{
                    xtype: 'component',
                    style: 'text-align:center',
                    itemId: 'failureReason'
                }]
            },{
                xtype: 'container',
                itemId: 'buttons',
                items: [{
                    xtype: 'container',
                    itemId: 'commonButtons',
                    cls: 'x-docked-buttons',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        itemId: 'create',
                        hidden: true,
                        text: 'Create ' + (moduleParams['server'] ? moduleParams['server']['object'] : ''),
                        handler: function() {
                            panel.buildRole();
                        }
                    },{
                        xtype: 'button',
                        itemId: 'cancel',
                        text: 'Cancel',
                        handler: function() {
                            Scalr.utils.Confirm({
                                form: {
                                    xtype: 'component',
                                    style: 'text-align: center',
                                    margin: '36 0 0',
                                    html: '<span class="x-fieldset-subheader">Are you sure want to cancel ' + moduleParams['server']['object'] + ' creation?</span>'
                                },
                                success: function() {
                                    panel.cancelImport(true);
                                }
                            });
                        }
                    }]
                },{
                    xtype: 'container',
                    itemId: 'successButtons',
                    hidden: true,
                    cls: 'x-docked-buttons',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'View ' + (moduleParams['server'] ? moduleParams['server']['object'] : ''),
                        handler: function() {
                            if (moduleParams['server']['object'] == 'role')
                                Scalr.event.fireEvent('redirect', '#/roles/manager?roleId=' + panel.roleId);
                            else
                                Scalr.event.fireEvent('redirect', '#/images/view?platform=' + panel.platform + '&id=' + panel.imageId);
                        }
                    },{
                        xtype: 'button',
                        text: 'Build farm',
                        hidden: moduleParams['server'] ? moduleParams['server']['object'] == 'image' : false,
                        handler: function() {
                            Scalr.event.fireEvent('redirect', '#/farms/build');
                        }
                    }]
                },{
                    xtype: 'container',
                    itemId: 'failedButtons',
                    hidden: true,
                    cls: 'x-docked-buttons',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'Try again',
                        handler: function() {
                            panel.cancelImport();
                        }
                    }]
                }]
            }]
        }],
		dockedItems: {
            xtype: 'container',
            width: 112 + Ext.getScrollbarSize().width,
            itemId: 'tabs',
            dock: 'left',
            cls: 'x-docked-tabs',
            overflowY: 'auto',
            defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'above',
                disableMouseDownPressed: true,
                toggleGroup: 'servers-import-tabs',
                toggleHandler: function(field, state) {
                    panel.fireEvent((!state ? 'de' : '') + 'selectplatform', this.value);
                }
            },
            items: Ext.Array.map(moduleParams['platforms'], function(platform){
                return {
                    iconCls: 'x-icon-platform-large x-icon-platform-large-' + platform,
                    text: Scalr.utils.getPlatformName(platform, true),
                    value: platform
                }
            })
        },
        onServerInfoChange: function(){
            var leftcol = this.getComponent('leftcol'),
                serverIdField = leftcol.down('[name="cloudServerId"]'),
                server = serverIdField.findRecordByValue(serverIdField.getValue());
            leftcol.down('#continue').setVisible(server && !server.get('isImporting') && !server.get('isManaged') && leftcol.down('[name="roleName"]').isValid());
        },
        startImport: function(){
            var me = this,
                leftcol = me.getComponent('leftcol');
            Scalr.Request({
                processBox: {
                    type: 'action',
                    msg: 'Initializing import ...'
                },
                params: {
                    platform: this.platform,
                    cloudLocation: leftcol.down('[name="cloudLocation"]').getValue(),
                    cloudServerId: leftcol.down('[name="cloudServerId"]').getValue(),
                    name: leftcol.down('[name="roleName"]').getValue(),
                    createImage: leftcol.down('[name="roleImage"]').getValue()
                },
                url: '/roles/import/xInitiateImport/',
                success: function (data) {
                    me.onImportStarted(data);
                }
            });
        },
        onImportStarted: function(data) {
            var leftcol = this.getComponent('leftcol');
            this.serverId = data['serverId'];
            leftcol.down('#installinfo').show();
            this.down('#confirmLaunch').show();
            leftcol.down('#continue').hide();
            leftcol.down('#cmd').setValue(data['command']);
            leftcol.down('[name="cloudServerId"]').setDisabled(true);
            leftcol.down('[name="cloudLocation"]').setDisabled(true);
            leftcol.down('[name="roleName"]').setDisabled(true);
            leftcol.down('[name="roleImage"]').setDisabled(true);
            this.getDockedComponent('tabs').items.each(function(){
                this.disable();
            });
            this.startCommunicationCheck(true);
        },
        cancelImport: function(serverCancelOperation){
            var me = this,
                leftcol = me.getComponent('leftcol'),
                rightcol = me.getComponent('rightcol');

            this.clearCheckCommTaskDelayed();
            if (serverCancelOperation) {
                Scalr.Request({
                    processBox: {
                        type: 'action'
                    },
                    url: '/servers/xServerCancelOperation/',
                    params: { serverId: this.serverId },
                    success: function (data) {

                    }
                });
            }
            if (loadParams['serverId']) {
                Scalr.event.fireEvent('redirect', '#/roles/import');
                return;
            }

            leftcol.down('#installinfo').hide();
            leftcol.down('#continue').hide();
            leftcol.down('#cmd').setValue('');

            leftcol.down('[name="cloudServerId"]').setValue(null);
            leftcol.down('[name="cloudServerId"]').setDisabled(false);
            leftcol.down('[name="cloudLocation"]').setDisabled(false);

            if (leftcol.down('[name="roleImage"]').getValue()) {
                leftcol.down('[name="roleImage"]').setDisabled(false);
            } else {
                leftcol.down('[name="roleName"]').setDisabled(false);
                leftcol.down('[name="roleImage"]').setDisabled(false);
            }
            this.getDockedComponent('tabs').items.each(function(){
                this.enable();
            });
            rightcol.down('#log').hide();
            rightcol.down('#failed').hide();
            rightcol.down('#automation').hide();
            rightcol.down('#successButtons').hide();
            rightcol.down('#failedButtons').hide();
            rightcol.down('#commonButtons').show();
            rightcol.down('#create').hide();
            rightcol.hide();

            this.serverId = null;

        },
        checkCommTask: null,
        startCommunicationCheck: function(dontShowRightColumn) {
            var rightcol = this.getComponent('rightcol');
            if (!dontShowRightColumn) {
                rightcol.show();
                this.down('#confirmLaunch').hide();
            }
            rightcol.down('#progress').resetProgress();
            rightcol.down('#waiting').show();
            this.setCheckCommTaskDelayed();
        },
        setCheckCommTaskDelayed: function() {
            var me = this;
            me.clearCheckCommTaskDelayed();
            me.checkCommTask = setTimeout(function() {
                me.request = Scalr.Request({
                    params: {
                        serverId: panel.serverId
                    },
                    url: '/roles/import/xCheckCommunication/',
                    success: function (data) {
                        if (data['inbound'] && data['outbound']) {
                            if (loadParams['serverId']) {
                                me.startBehaviorSelection(data);
                            } else {
                                Scalr.event.fireEvent('redirect', '#/roles/import?serverId=' + panel.serverId);
                            }
                        } else {
                            me.updateCheckCommStatus(data);
                            me.setCheckCommTaskDelayed();
                        }
                    }
                });
            }, 2000);
        },
        updateCheckCommStatus: function(data){
            var ct = this.down('#waiting'),
                error = ct.el.down('#comm-error'),
                inbound = ct.el.down('#inbound'),
                outbound = ct.el.down('#outbound');
            if (data['inbound']) {
                inbound.addCls('success');
                if (data['outbound']) {
                    outbound.removeCls('error progress');
                    outbound.addCls('success');
                } else if (data['connectionError']) {
                    outbound.removeCls('success progress');
                    outbound.addCls('error');
                    error.setHTML(
                        '<div class="x-tip x-tip-message x-tip-message-warning" style="position:static;margin:0 auto;width:370px;color:#b20000;text-align:left;margin:4px auto"><div class="x-tip-body">'+data['connectionError']+'</div></div>'+
                        '<div style="margin:18px 0;color:#666">Retrying in a few minutes...</div>'
                    );
                } else {
                    outbound.removeCls('success error');
                    outbound.addCls('progress');
                }
            } else {
                inbound.removeCls('success');
                outbound.removeCls('success error progress');
            }
        },
        clearCheckCommTaskDelayed: function() {
            if (this.checkCommTask) {
                clearTimeout(this.checkCommTask);
                this.checkCommTask = null;
            }
            if (this.request) {
                Ext.Ajax.abort(this.request);
            }
        },
        startBehaviorSelection: function(data) {
            var rightcol = this.getComponent('rightcol'),
                automation = rightcol.down('#automation'),
                os = data['os'];
            rightcol.show();
            panel.down('#confirmLaunch').hide();
            this.bundleTaskId = data['bundleTaskId'];
            rightcol.down('#serverinfo').setValue(os ? '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-osfamily-small x-icon-osfamily-small-'+os['family']+'"/> ' + Scalr.utils.beautifyOsFamily(os['family']) + ' ' + os['version'] + ' ' + os['name']: 'unknown');
            automation.addBehaviors(data.behaviors);
            automation.show();
            rightcol.down('#waiting').hide();
            rightcol.down('#progress').updateProgress('in-progress', 'waiting');
            rightcol.down('#create').show();
            if (moduleParams['step'] == 2) {
                this.startDataBundleCheck(data['bundleTaskId'])
            }
        },
        buildRole: function() {
            var me = this,
                rightcol = me.getComponent('rightcol'),
                behaviorsCt = rightcol.down('#behaviors'),
                defaultBehaviors = [],
                behaviors = [];
            behaviorsCt.items.each(function(){
                if (this.pressed) {
                    behaviors.push(this.behavior);
                }
                if (this.behavior === 'base' || this.behavior === 'chef') {
                    defaultBehaviors.push(this.behavior);
                }
            });
            if (behaviors.length === 0) {
                if (behaviorsCt.items.length > 0 && behaviorsCt.items.length === defaultBehaviors.length) {
                    behaviors = defaultBehaviors;
                } else {
                    behaviors.push('base');
                }
            }
            Scalr.Request({
                params: {
                    serverId: panel.serverId,
                    bundleTaskId: panel.bundleTaskId,
                    behaviors: behaviors.join(',')
                },
                url: '/roles/import/xSetBehaviors/',
                success: function (data) {
                    me.startDataBundleCheck(data['bundleTaskId']);
                }
            });

        },
        startDataBundleCheck: function(bundleTaskId) {
            var automation = this.down('#automation');
            automation.down('#behaviors').items.each(function(){
                this.disable();
            });
            this.down('#rightcol').down('#create').hide();
            this.down('#log').show().setBundleTaskId(bundleTaskId);
        },
        onBundleTaskSuccess: function(data) {
            var rightcol = this.getComponent('rightcol');
            rightcol.down('#successButtons').show();
            rightcol.down('#commonButtons').hide();
            rightcol.down('#log').hide();
            rightcol.down('#success').show();
            this.roleId = data['roleId'];
            this.platform = data['platform'];
            this.imageId = data['imageId'];
        },
        onBundleTaskFailed: function(failureReason) {
            var rightcol = this.getComponent('rightcol'),
                failed = rightcol.down('#failed');
            rightcol.down('#failedButtons').show();
            rightcol.down('#commonButtons').hide();
            rightcol.down('#log').hide();
            failed.down('#failureReason').update('Error: '+failureReason);
            failed.show();
        },
        loadServer: function() {
            var me = this,
                leftcol = me.getComponent('leftcol'),
                rightcol = me.getComponent('rightcol'),
                server = moduleParams['server'];
            leftcol.down('[name="roleName"]').setRawValue(server['roleName']);
            leftcol.down('[name="cloudLocation"]').setValue(server['cloudLocation']);
            leftcol.down('[name="cloudServerId"]').setRawValue(server['cloudServerId']);

            this.serverId = loadParams['serverId'];
            leftcol.down('#installinfo').show();
            leftcol.down('#continue').hide();
            leftcol.down('#cmd').setValue(moduleParams['command']);
            leftcol.down('[name="cloudServerId"]').setDisabled(true);
            leftcol.down('[name="cloudLocation"]').setDisabled(true);
            leftcol.down('[name="roleName"]').setDisabled(true);
            leftcol.down('[name="roleImage"]').setDisabled(true);
            this.getDockedComponent('tabs').items.each(function(){
                this.disable();
            });

            this.startCommunicationCheck();
        },

        listeners: {
			boxready: function () {
                var items = this.getDockedComponent('tabs').items,
                    defaultPlatform = loadParams['platform'] || 'ec2',
                    defaultItem;
                if (moduleParams['step'] && moduleParams['server']) {
                    items.each(function(){
                        if (this.value == moduleParams['server']['platform']) {
                            defaultItem = this;
                            return false;
                        }
                    });
                } else {
                    items.each(function(){
                        if (this.value == defaultPlatform) {
                            defaultItem = this;
                            return false;
                        }
                    })
                    defaultItem = defaultItem || items.first();
                }
                defaultItem.toggle(true);
			},
            destroy: function() {
                this.clearCheckCommTaskDelayed();
            },
            selectplatform: function(platform){
                var me = this,
                    leftcol = me.getComponent('leftcol'),
                    serverId = leftcol.down('[name="cloudServerId"]'),
                    defaultLocations = {/*ec2: 'us-east-1',*/ gce: 'us-central1-a'},
                    callback = function(locations){
                        var cloudLocationField = leftcol.down('[name="cloudLocation"]'),
                            locationIds = Ext.Object.getKeys(locations),
                            location = defaultLocations[platform] || (locationIds.length ? locationIds[0] : '');
                        serverId.reset();
                        serverId.store.getProxy().params = {
                            platform: platform,
                            cloudLocation: location
                        }
                        leftcol.down('#locationmap').selectLocation(platform, location, locationIds, 'world');
                        cloudLocationField.store.load({data: locations});
                        cloudLocationField.setValue(location);
                        cloudLocationField.setDisabled(Ext.Object.getSize(locations) === 0);
                        me.onServerInfoChange();
                        if (moduleParams['step'] && moduleParams['server']) {
                            me.loadServer();
                        }
                        me.isLoading = false;
                    };
                me.isLoading = true;
                me.platform = platform;
                if (platform === 'gce') {
                    leftcol.el.mask();
                    Scalr.cachedRequest.load(
                        {
                            url: '/platforms/gce/xGetOptions',
                            params: {}
                        },
                        function(data, status){
                            var locations = {};
                            if (status) {
                                Ext.Array.each(data['zones'], function(zone){
                                    if (zone.state === 'UP') {
                                        locations[zone.name] = zone.name;
                                    }
                                });
                            }
                            callback(locations);
                            leftcol.el.unmask();
                        },
                        me
                    );
                } else {
                    Scalr.loadCloudLocations(platform, function(){console.log(Scalr.platforms[platform].locations)
                        callback(Scalr.platforms[platform] ? Scalr.platforms[platform].locations : null);
                    });
                }
            },
            selectlocation: function(location) {
                var serverId = this.down('[name="cloudServerId"]');
                serverId.updateEmptyText(true);
                serverId.reset();
                serverId.store.getProxy().params.cloudLocation = location;
                if (!this.isLoading) {
                    serverId.store.load();
                }
            }
        }
    });
    return panel;
})