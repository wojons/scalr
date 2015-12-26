Scalr.regPage('Scalr.ui.db.manager.dashboard', function (loadParams, moduleParams) {
    var isDashboardReadOnly = !Scalr.isAllowed('DB_DATABASE_STATUS', 'manage');

    var ebsTypesStore = Ext.create('Ext.data.ArrayStore', {
        fields: [ 'id', 'name' ],
        data: Scalr.constants.ebsTypes
    });

    var panel = Ext.create('Ext.form.Panel', {
        width: 1140,
        title: 'Database status',
        bodyCls: 'scalr-ui-dbmsrstatus-panel',
        layout: 'auto',
        items: [{
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            cls: 'x-fieldset-separator-bottom',
            items: [{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                minWidth: 400,
                flex: 1.3,
                layout: {
                    type: 'vbox', //extjs doesn't calculate hbox container height properly when using anchor layout
                    align: 'stretch'
                },
                title: 'General',
                items: [{
                    xtype: 'displayfield',
                    name: 'general_dbname',
                    fieldLabel: 'Database type',
                    labelWidth: 130
                },{
                    xtype: 'container',
                    itemId: 'generalExtras',
                    height: 78,
                    defaults: {
                        labelWidth: 130
                    },
                    overflowY: 'hidden'
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    defaults: {
                        flex: 1
                    },
                    items: [{
                        xtype: 'button',
                        itemId: 'manageConfigurationBtn',
                        text: 'Manage configuration',
                        margin: '0 5 0 0',
                        maxWidth: 190,
                        handler: function(){
                            var data = this.up('form').moduleParams;
                            Scalr.event.fireEvent('redirect', '#/services/configurations/manage?farmRoleId=' + data['farmRoleId'] + '&behavior=' + data['dbType']);
                        }
                    },{
                        xtype: 'button',
                        text: 'Connection details',
                        margin: '0 0 0 5',
                        maxWidth: 170,
                        hidden: isDashboardReadOnly,
                        handler: function() {
                            Scalr.utils.Window({
                                title: 'Connection details',
                                width: 700,
                                items: this.up('form').getConnectionDetails(),
                                dockedItems: [{
                                    xtype: 'container',
                                    cls: 'x-docked-buttons',
                                    dock: 'bottom',
                                    layout: {
                                        type: 'hbox',
                                        pack: 'center'
                                    },
                                    items: [{
                                        xtype: 'button',
                                        text: 'Close',
                                        handler: function() {
                                            this.up('#box').close();
                                        }
                                    }]
                                }]
                            });
                        }
                    }]
                }]
            },{
                xtype: 'fieldset',
                itemId: 'phpMyAdminAccess',
                cls: 'x-fieldset-separator-left',
                flex: 1,
                //width: null,
                items: [{
                    xtype: 'image',
                    src: Ext.BLANK_IMAGE_URL,
                    cls: 'scalr-ui-dbmsr-phpmyadmin',
                    margin: '20 0 12 50'
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    defaults: {
                        width: 120,
                        margin: '32 0 0'
                    },
                    items: [{
                        xtype: 'button',
                        itemId: 'setupPMA',
                        text: 'Setup access',
                        hidden: true,
                        disabled: !Scalr.isAllowed('DB_DATABASE_STATUS', 'phpmyadmin'),
                        handler: function(){
                            var form = this.up('form'),
                                data = form.moduleParams;
                            Scalr.Request({
                                processBox: {
                                    type: 'action'
                                },
                                url: '/db/manager/xSetupPmaAccess/',
                                params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                success: function(){
                                    form.down('#setupPMA').hide();
                                    form.down('#PMAinProgress').show();
                                }
                            });
                        }
                    }, {
                        xtype: 'button',
                        itemId: 'launchPMA',
                        margin: '32 10 0 0',
                        text: 'Launch',
                        hidden: true,
                        disabled: !Scalr.isAllowed('DB_DATABASE_STATUS', 'phpmyadmin'),
                        handler: function() {
                            var data = this.up('form').moduleParams,
                                link = document.location.href.split('#');
                            window.open(link[0] + '#/services/mysql/pma?farmId=' + data['farmId']);
                            //Scalr.event.fireEvent('redirect', '#/services/mysql/pma?farmId=' + data['farmId']);
                        }
                    }, {
                        xtype: 'button',
                        cls: 'x-button-text-large',
                        itemId: 'resetPMA',
                        hidden: true,
                        disabled: !Scalr.isAllowed('DB_DATABASE_STATUS', 'phpmyadmin'),
                        text: 'Reset access',
                        handler: function(){
                            var form = this.up('form'),
                                data = form.moduleParams;
                            Scalr.Request({
                                confirmBox: {
                                    type: 'action',
                                    msg: 'Are you sure want to reset PMA access?'
                                },
                                processBox: {
                                    type: 'action'
                                },
                                url: '/db/manager/xSetupPmaAccess/',
                                params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                success: function(){
                                    form.down('#setupPMA').hide();
                                    form.down('#launchPMA').hide();
                                    form.down('#resetPMA').hide();
                                    form.down('#PMAinProgress').show();
                                }
                            });
                        }
                    },{
                        xtype: 'displayfield',
                        hidden: true,
                        itemId: 'PMAinProgress',
                        margin: 0,
                        width: 280,
                        value: 'MySQL access details for PMA requested. Please refresh this page in a couple minutes...'
                    }]
                }]
            },{
                xtype: 'fieldset',
                flex: 1.1,
                minWidth: 400,
                title: 'Master storage',
                cls: 'x-fieldset-separator-left',
                defaults: {
                    labelWidth: 80
                },
                items: [{
                    xtype: 'displayfield',
                    name: 'storage_id',
                    fieldLabel: 'ID'
                },{
                    xtype: 'displayfield',
                    name: 'storage_engine_name',
                    fieldLabel: 'Type'
                },{
                    xtype: 'displayfield',
                    name: 'storage_fs',
                    fieldLabel: 'File system'
                },{
                    xtype: 'fieldcontainer',
                    itemId: 'storageSizeContainer',
                    fieldLabel: 'Usage',
                    layout: 'hbox',
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'left',
                        position: 'outer',
                        icons: {
                            id: 'error',
                            hidden: true,
                            tooltip: ''
                        }
                    }],
                    hideIncreaseButton: function (hidden) {
                        var me = this;

                        me.down('#increaseStorageSizeBtn').setVisible(!hidden);

                        return me;
                    },
                    showGrowError: function (visible, errorText) {
                        errorText = Ext.isString(errorText) ? errorText : '';

                        var me = this;

                        me.getPlugin('fieldicons')
                            .updateIconTooltip('error', errorText)
                            .toggleIcon('error', visible);

                        return me;
                    },
                    afterOperationCompleted: function (serverId) {
                        var me = this;

                        Scalr.Request({
                            url: '/db/manager/xGetDbStorageStatus',
                            params: {
                                serverId: serverId
                            },
                            success: function (response) {
                                if (me.rendered && !me.isDestroyed && !Ext.isEmpty(response) && !Ext.isEmpty(response.storage)) {
                                    var storageData = response.storage;

                                    panel.setStorageData(storageData);

                                    me.down('button').enable();

                                    me.down('progressfield')
                                        .setPending(false)
                                        .setTooltip(false)
                                        .setRawValue(storageData.size);
                                }
                            }
                        });

                        return me;
                    },
                    pollIncreaseOperationStatus: function (serverId, operationId, farmRoleId, pendingValues) {
                        var me = this;

                        me.showGrowError(false);

                        me.down('button').disable();

                        var progressField = me.down('progressfield');
                        progressField.setPending('Changing settings...');

                        if (!Ext.isEmpty(pendingValues)) {
                            progressField.setTooltip(true, pendingValues);
                        }

                        var growVolumeStatusRequest = Ext.create('Scalr.GrowVolumeStatusRequest')
                            .on({
                                success: function () {
                                    me.afterOperationCompleted(serverId);
                                },
                                failure: function (response) {
                                    if (me.rendered && !me.isDestroyed && !Ext.isEmpty(response) && !Ext.isEmpty(response.error)) {
                                        Scalr.message.Error(response.error);
                                        Scalr.Request({
                                            url: '/db/manager/xClearGrowStorageError',
                                            params: {
                                                farmRoleId: farmRoleId
                                            }
                                        });
                                    }
                                    me.afterOperationCompleted(serverId);
                                }
                            })
                            .request({
                                url: '/operations/xGetDetails',
                                params: {
                                    serverId: serverId,
                                    operationId: operationId
                                }
                            });

                        me.on('destroy', function () {
                            if (!growVolumeStatusRequest.isDestroyed) {
                                growVolumeStatusRequest.destroy();
                            }
                        });

                        return me;
                    },
                    items: [{
                        xtype: 'progressfield',
                        flex: 1,
                        maxWidth: 350,
                        name: 'storage_size',
                        valueField: 'used',
                        units: 'Gb',
                        listeners: {
                            boxready: function (field) {
                                field.getEl().tip = Ext.create('Ext.tip.ToolTip', {
                                    target: field.getId(),
                                    trackMouse: false,
                                    owner: field,
                                    disabled: true
                                });
                            }
                        },
                        checkValue: true,
                        invalidValueText: 'Unavailable',
                        checkValueFn: function (value) {
                            return Ext.isObject(value) && value.total !== -1;
                        },
                        setTooltip: function (enabled, storageValues) {
                            var me = this;

                            var tooltip = me.getEl().tip;
                            tooltip.setDisabled(!enabled);

                            if (!enabled) {
                                return me;
                            }

                            var fieldsIds = Ext.Array.remove(Ext.Object.getKeys(storageValues), 'iops');
                            var fieldsNames = {
                                volumeType: 'Volume type',
                                newSize: 'Size'
                            };

                            tooltip.html = 'New storage settings is pending:<br />' + Ext.Array.map(fieldsIds, function (fieldId) {
                                var fieldName = fieldsNames[fieldId];
                                var value = storageValues[fieldId];
                                var result = Ext.String.format('<b>{0}</b>: {1} ',
                                    Ext.isDefined(fieldName) ? fieldName : fieldId,
                                    fieldId !== 'volumeType' ? value : ebsTypesStore.findRecord('id', value).get('name')
                                );
                                if (value === 'io1') {
                                    result += storageValues.iops;
                                } else if (fieldId === 'newSize') {
                                    result += 'GB';
                                }
                                return result;
                            }).join('<br />');

                            return me;
                        }
                    },{
                        xtype: 'button',
                        itemId: 'increaseStorageSizeBtn',
                        iconCls: 'x-btn-icon-increase',
                        margin: '0 0 0 5',
                        tooltip: 'Change storage settings',
                        handler: function (button) {
                            var data = this.up('form').moduleParams;
                            var storageData = panel.getStorageData();
                            var ebsSettings = storageData.ebsSettings;
                            var volumeType = ebsSettings.volumeType;
                            var storageTotalSize = parseInt(ebsSettings.size);

                            Scalr.Confirm({
                                form: {
                                    xtype: 'fieldset',
                                    //cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
                                    title: 'Change storage configuration',
                                    items: [{
                                        xtype: 'combo',
                                        store: ebsTypesStore,
                                        fieldLabel: 'EBS type',
                                        valueField: 'id',
                                        displayField: 'name',
                                        editable: false,
                                        queryMode: 'local',
                                        name: 'volumeType',
                                        value: volumeType,
                                        anchor: '100%',
                                        listeners: {
                                            change: function(comp, value) {
                                                var form = comp.up('form'),
                                                    iopsField = form.down('[name="iops"]');
                                                iopsField.setVisible(value === 'io1').setDisabled(value !== 'io1');
                                                if (value === 'io1') {
                                                    iopsField.reset();
                                                    iopsField.setValue(100);
                                                } else {
                                                    form.down('[name="size"]').isValid();
                                                }
                                            }
                                        }
                                    }, {
                                        fieldLabel: 'IOPS',
                                        xtype: 'numberfield',
                                        name: 'iops',
                                        vtype: 'iops',
                                        allowBlank: false,
                                        hidden: volumeType !== 'io1',
                                        disabled: volumeType !== 'io1',
                                        width: 230,
                                        value: ebsSettings.iops,
                                        listeners: {
                                            change: function(comp, value) {
                                                var form = comp.up('form'),
                                                    sizeField = form.down('[name="size"]');
                                                if (comp.isValid() && comp.prev().getValue() === 'io1') {
                                                    var minSize = Scalr.utils.getMinStorageSizeByIops(value);
                                                    if (sizeField.getValue() * 1 < minSize) {
                                                        sizeField.setValue(minSize);
                                                    }
                                                }
                                            }
                                        }

                                    }, {
                                        xtype: 'fieldcontainer',
                                        layout: {
                                            type: 'hbox',
                                            align: 'middle'
                                        },
                                        items: [{
                                            xtype: 'numberfield',
                                            name: 'size',
                                            fieldLabel: 'Storage size',
                                            width: 230,
                                            value: storageTotalSize,
                                            minValue: storageTotalSize,
                                            minText: 'New storage size must be bigger that previous one.',
                                            vtype: 'ebssize',
                                            getEbsType: function() {
                                                return this.up('form').down('[name="volumeType"]').getValue();
                                            },
                                            getEbsIops: function() {
                                                return this.up('form').down('[name="iops"]').getValue();
                                            }
                                        }, {
                                            xtype: 'label',
                                            text: 'GB',
                                            margin: '0 0 0 6'
                                        }]
                                    }]
                                },
                                formWidth: 400,
                                ok: 'Change',
                                closeOnSuccess: true,
                                success: function (formValues, form) {
                                    if (form.isValid()) {
                                        var growConfig = {};
                                        Ext.Object.each(formValues, function(name, value){
                                            if (!ebsSettings[name] || value != ebsSettings[name]) {
                                                growConfig[name] = value;
                                            }
                                        });
                                        if (Ext.Object.getSize(growConfig) > 0) {
                                            if (growConfig['size'] !== undefined) {
                                                growConfig['newSize'] = growConfig['size'];
                                                delete growConfig['size'];
                                            }

                                            Scalr.Request({
                                                processBox: {
                                                    type: 'action',
                                                    msg: 'Processing ...'
                                                },
                                                url: '/db/manager/xGrowStorage',
                                                params: Ext.apply(growConfig, {
                                                    farmRoleId: data.farmRoleId
                                                }),
                                                success: function (response) {
                                                    Scalr.message.Success('Storage settings change has been successfully initiated');

                                                    button.up('fieldcontainer').pollIncreaseOperationStatus(
                                                        response.serverId,
                                                        response.operationId,
                                                        data.farmRoleId,
                                                        growConfig
                                                    );

                                                    //Scalr.event.fireEvent('redirect', '#/operations/details?' + Ext.Object.toQueryString(data));
                                                }
                                            });

                                        }
                                        return true;
                                    }

                                }
                            })
                        }
                    }]
                }]
            }]
        },{
            xtype: 'fieldset',
            title: 'Cluster map',
            collapsible: true,
            items: [{
                xtype: 'dbmsclustermapfield',
                name: 'clustermap',
                isDashboardReadOnly: isDashboardReadOnly
            }]
        },{
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-right',
                hideOn: 'backupsNotSupported',
                flex: 1.04,
                title: 'Database dumps &nbsp;<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" style="cursor: help;" data-qwidth="400" data-qtip="Scalr will backup the database and store it in SQL form in your cloud. This backup will be taken from a slave. Click on Manage to configure the schedule for backups from the Farm Manager." />',
                items: [{
                    xtype: 'displayfield',
                    showOn: 'backupsDisabled',
                    hidden: true,
                    value: 'Database dumps disabled.'
                },{
                    xtype: 'displayfield',
                    name: 'backup_schedule',
                    fieldLabel: 'Schedule',
                    hideOn: 'backupsDisabled'
                },{
                    xtype: 'displayfield',
                    name: 'backup_next',
                    fieldLabel: 'Next backup',
                    hideOn: 'backupsDisabled'
                },{
                    xtype: 'fieldcontainer',
                    fieldLabel: 'Last backup',
                    hideOn: 'backupsDisabled',
                    layout: 'column',
                    items: [{
                        xtype: 'displayfield',
                        name: 'backup_last'
                    },{
                        xtype: 'displayfield',
                        name: 'backup_last_result',
                        margin: '0 0 0 20',
                        style: 'font-weight:bold;',
                        valueToRaw: function(value) {
                            return value;
                        },
                        renderer: function(rawValue) {
                            var html = '';
                            if (rawValue.status) {
                                if (rawValue.status != 'ok') {
                                    html = '<span style="color:#C00000;text-transform:capitalize">Failed';
                                    if (rawValue.error) {
                                        html += ' <img data-qtip="'+Ext.String.htmlEncode(rawValue.error)+'" src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-question" style="cursor: help;">';
                                    }
                                    html += '</span>';
                                } else {
                                    html = '<span style="color:#008000;text-transform:capitalize">Success</span>';
                                }
                            }
                            return html;
                        }
                    }]
                },{
                    xtype: 'dbmshistoryfield',
                    name: 'backup_history',
                    fieldLabel: 'History',
                    hideOn: 'backupsDisabled'
                },{
                    xtype: 'container',
                    hideOn: 'backupsDisabled',
                    itemId: 'backupsManageCt',
                    layout: {
                        type: 'hbox'
                    },
                    margin: '24 0 12 0',
                    padding: '0 0 0 90',
                    defaults: {
                        margin: '0 16 0 0'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'Manage',
                        width: 140,
                        hidden: !Scalr.isAllowed('DB_BACKUPS'),
                        handler: function(){
                            var data = this.up('form').moduleParams;
                            Scalr.event.fireEvent('redirect', '#/db/backups?farmId='+data['farmId']);
                        }
                    },{
                        xtype: 'button',
                        text: 'Create now',
                        hideOn: 'backupInProgress',
                        margin: 0,
                        hidden: true,
                        width: 140,
                        handler: function(){
                            var data = this.up('form').moduleParams;
                            Scalr.Request({
                                confirmBox: {
                                    type: 'action',
                                    msg: 'Are you sure want to create backup?'
                                },
                                processBox: {
                                    type: 'action',
                                    msg: 'Sending backup request ...'
                                },
                                url: '/db/manager/xCreateBackup/',
                                params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                success: function(){
                                    Scalr.event.fireEvent('refresh');
                                }
                            });
                        }
                    },{
                        xtype: 'container',
                        showOn: 'backupInProgress',
                        hidden: true,
                        layout: {
                            type: 'hbox'
                        },
                        padding: '1 0 0 0',
                        items: [{
                            xtype: 'component',
                            cls: 'scalr-ui-dbmsr-status-inprogress',
                            html: 'In progress...'
                        },{
                            xtype: 'buttonfield',
                            itemId: 'cancelDataBackupBtn',
                            iconCls: 'x-btn-icon-terminate',
                            margin: '0 0 0 12',
                            submitValue: false,
                            handler: function(){
                                var data = this.up('form').moduleParams;
                                Scalr.Request({
                                    confirmBox: {
                                        type: 'action',
                                        msg: 'Are you sure want to cancel running backup?'
                                    },
                                    processBox: {
                                        type: 'action',
                                        msg: 'Sending backup cancel request ...'
                                    },
                                    url: '/db/manager/xCancelBackup/',
                                    params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                    success: function(){
                                        Scalr.event.fireEvent('refresh');
                                    }
                                });
                            }
                        }]
                    }]
                }]
            },{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                flex: 1,
                title: 'Binary storage snapshots &nbsp;<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" style="cursor: help;" data-qwidth="400" data-qtip="Scalr will perform a data bundle to snapshot the database and store it in binary form in your cloud. This snapshot will be taken from the master.">',
                defaults: {
                    labelWidth: 135
                },
                items: [{
                    xtype: 'displayfield',
                    showOn: 'bundleDisabled',
                    hidden: true,
                    value: 'Binary storage snapshots disabled.'
                },{
                    xtype: 'displayfield',
                    name: 'bundles_schedule',
                    fieldLabel: 'Schedule',
                    hideOn: 'bundleDisabled'
                },{
                    xtype: 'displayfield',
                    name: 'bundles_next',
                    fieldLabel: 'Next data bundle',
                    hideOn: 'bundleDisabled'
                },{
                    xtype: 'fieldcontainer',
                    fieldLabel: 'Last data bundle',
                    hideOn: 'bundleDisabled',
                    layout: 'column',
                    items: [{
                        xtype: 'displayfield',
                        name: 'bundles_last'
                    },{
                        xtype: 'displayfield',
                        name: 'bundles_last_result',
                        margin: '0 0 0 20',
                        style: 'font-weight:bold;',
                        valueToRaw: function(value) {
                            return value;
                        },
                        renderer: function(rawValue) {
                            var html = '';
                            if (rawValue.status) {
                                if (rawValue.status != 'ok') {
                                    html = '<span style="color:#C00000;text-transform:capitalize">Failed';
                                    if (rawValue.error) {
                                        html += ' <img data-qtip="'+Ext.String.htmlEncode(rawValue.error)+'" src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-question" style="cursor: help">';
                                    }
                                    html += '</span>';
                                } else {
                                    html = '<span style="color:#008000;text-transform:capitalize">Success</span>';
                                }
                            }
                            return html;
                        }
                    }]
                },{
                    xtype: 'dbmshistoryfield',
                    name: 'bundles_history',
                    fieldLabel: 'History',
                    hideOn: 'bundleDisabled'
                },{
                    xtype: 'container',
                    hideOn: 'bundleDisabled',
                    itemId: 'bundleManageCt',
                    layout: {
                        type: 'hbox'
                    },
                    margin: '24 0 12 0',
                    padding: '0 0 0 160',
                    defaults: {
                        margin: '0 16 0 0'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'Manage',
                        hidden: true,
                        width: 140,
                        handler: function(){
                            Scalr.message.Success('Under construction...');
                        }
                    },{
                        xtype: 'button',
                        hideOn: 'bundleInProgress',
                        text: 'Create now',
                        width: 140,
                        hidden: true,
                        handler: function(){
                            var form = this.up('form'),
                                data = form.moduleParams;
                            Scalr.Request({
                                confirmBox: {
                                    type: 'action',
                                    msg: 'Create data bundle?',
                                    formWidth: 600,
                                    form: form.getConfirmationDataBundleOptions()
                                },
                                processBox: {
                                    type: 'action',
                                    msg: 'Sending data bundle request ...'
                                },
                                url: '/db/manager/xCreateDataBundle/',
                                params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                success: function(){
                                    Scalr.event.fireEvent('refresh');
                                }
                            });
                        }

                    },{
                        xtype: 'container',
                        showOn: 'bundleInProgress',
                        hidden: true,
                        padding: '1 0 0 0',
                        layout: {
                            type: 'hbox'
                        },
                        items: [{
                            xtype: 'component',
                            cls: 'scalr-ui-dbmsr-status-inprogress',
                            html: 'In progress...'
                        },{
                            xtype: 'buttonfield',
                            itemId: 'cancelDataBundleBtn',
                            iconCls: 'x-btn-icon-terminate',
                            margin: '0 0 0 12',
                            submitValue: false,
                            handler: function(){
                                var data = this.up('form').moduleParams;
                                Scalr.Request({
                                    confirmBox: {
                                        type: 'action',
                                        msg: 'Are you sure want to cancel data bundle?'
                                    },
                                    processBox: {
                                        type: 'action',
                                        msg: 'Sending data bundle cancel request ...'
                                    },
                                    url: '/db/manager/xCancelDataBundle/',
                                    params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                    success: function(){
                                        Scalr.event.fireEvent('refresh');
                                    }
                                });
                            }

                        }]
                    }]
                }]
            }]
        }],

        listeners: {
            afterrender: function (panel) { // todo: replace loadData() with something better
                panel
                    .loadData(moduleParams)
                    .initStorage(
                        moduleParams.storage || {},
                        moduleParams.farmRoleId
                    );
            }
        },

        initStorage: function (storageData, farmRoleId) {
            var me = this;

            var storageSizeContainer = me.down('#storageSizeContainer');
            var growLastError = storageData.growLastError;
            var isGrowSupported = storageData.growSupported
                && !Ext.isEmpty(storageData['ebs_settings']);

            me.setStorageData(storageData);

            storageSizeContainer.hideIncreaseButton(!isGrowSupported);

            if (!isGrowSupported) {
                return me;
            }

            var growOperation = storageData.growOperation;

            if (!Ext.isEmpty(growOperation)) {
                storageSizeContainer
                    .pollIncreaseOperationStatus(
                        growOperation.serverId,
                        growOperation.operationId,
                        farmRoleId
                    );
            } else if (!Ext.isEmpty(growLastError)) {
                storageSizeContainer.showGrowError(true,
                    'The latest operation to change the storage settings was passed with an error: "'
                    + growLastError + '".'
                );
            }

            return me;
        },

        setStorageData: function (data) {
            var me = this;

            var ebsSettings = data['ebs_settings'];

            me.storageData = {
                size: data.size,
                engineName: data.engineName,
                ebsSettings: data['ebs_settings']
            };

            return me;
        },

        getStorageData: function () {
            return this.storageData;
        },

        toggleElementsByFeature: function(feature, visible) {
            var c = this.query('component[hideOn='+feature+'], component[showOn='+feature+']');
            for (var i=0, len=c.length; i<len; i++) {
                c[i].setVisible(!!(c[i].showOn && c[i].showOn == feature) === !!visible);
            }
        },

        loadData: function(data) {
            var me = this;

            me.moduleParams = data;

            var formatBackupValues = function(data, prefix) {
                prefix = prefix || 'backup';
                data = data || {};
                var history = data.history || [],
                    values = {};
                values[prefix + '_schedule'] = data['schedule'] || '';
                values[prefix + '_next'] = data['next'] || 'Never';
                if (history.length) {
                    values[prefix + '_last'] = history[history.length-1].date;
                    values[prefix + '_last_result'] = {
                        status: history[history.length-1].status,
                        error: history[history.length-1].error
                    };
                    values[prefix + '_history'] = data.history;
                } else {
                    values[prefix + '_last'] = 'Never';
                }
                return values;
            };

            data['storage'] = data['storage'] || {};
            var formValues = {
                general_dbname: data['name'],

                storage_id: data['storage']['id'] || '',
                storage_engine_name: data['storage']['engineName'] || '',
                storage_fs: data['storage']['fs'] || '',
                storage_size: data['storage']['size'] || 'not available'
            };

            //general extras
            var generalExtrasPanel = this.down('#generalExtras');
            generalExtrasPanel.removeAll();
            if (data['extras']) {
                Ext.Array.each(data['extras'], function(item){
                    generalExtrasPanel.add({
                        xtype: 'displayfield',
                        fieldLabel: item.name,
                        value: item.value
                    });
                });
            }

            if (data['backups']) {
                if (data['backups']['supported']) {
                    Ext.apply(formValues, formatBackupValues(data['backups']));
                }
            }

            if (data['bundles']) {
                Ext.apply(formValues, formatBackupValues(data['bundles'], 'bundles'));
            }

            formValues.clustermap = data['servers'];

            me.refreshElements();
            me.getForm().setValues(formValues);

            return me;
        },

        refreshElements: function() {
            var data = this.moduleParams;

            this.toggleElementsByFeature('bundleDisabled', !data['bundles']);
            if (data['bundles']) {
                this.toggleElementsByFeature('bundleInProgress', data['bundles']['inProgress']['status'] != '0');
            }

            this.toggleElementsByFeature('backupsDisabled', !data['backups']);
            if (data['backups']) {
                this.toggleElementsByFeature('backupsNotSupported', !data['backups']['supported']);
                if (data['backups']['supported']) {
                    this.toggleElementsByFeature('backupInProgress', data['backups']['inProgress']['status'] != '0');
                }
            }
            this.down('#manageConfigurationBtn').setVisible(data['dbType'] != 'mysql' && Scalr.isAllowed('DB_SERVICE_CONFIGURATION'));
            this.down('#increaseStorageSizeBtn').setVisible(!isDashboardReadOnly && !!(data['storage'] && data['storage']['growSupported'] && data['storage']['ebs_settings']));
            this.down('#cancelDataBundleBtn').setVisible(!!(data['storage'] && data['storage']['engine'] == 'lvm'));

            this.down('#cancelDataBackupBtn').setVisible(data['dbType'] == 'mysql2' || data['dbType'] == 'percona');

            if (isDashboardReadOnly) {
                this.down('#backupsManageCt').hide();
                this.down('#bundleManageCt').hide();
            }

            if (data['pma'] && Scalr.isAllowed('DB_DATABASE_STATUS', 'phpmyadmin')) {
                this.down('#phpMyAdminAccess').setVisible(true);
                this.down('#setupPMA').setVisible(!(data['pma']['accessSetupInProgress'] || data['pma']['configured']));
                this.down('#launchPMA').setVisible(data['pma']['configured']);
                this.down('#resetPMA').setVisible(data['pma']['accessError'] || data['pma']['configured']);
                this.down('#PMAinProgress').setVisible(data['pma']['accessSetupInProgress'] && !data['pma']['configured'] ? true : false);
            } else {
                this.down('#phpMyAdminAccess').setVisible(false);
            }

            //this.down('#phpMyAdminAccess').setVisible(data['dbType'] != 'mysql' || data['dbType'] != 'mysql2' || data['dbType'] != 'percona');
        },

        getConfirmationDataBundleOptions: function() {
            var data = this.moduleParams,
                confirmationDataBundleOptions = {};
            if ((data['dbType'] == 'percona' || data['dbType'] == 'mysql2') && (data['storage'] && data['storage']['engine'] == 'lvm')) {
                confirmationDataBundleOptions = {
                    xtype: 'fieldset',
                    title: 'Data bundle settings',
                    items: [{
                        xtype: 'combo',
                        fieldLabel: 'Type',
                        store: [['incremental', 'Incremental'], ['full', 'Full']],
                        valueField: 'id',
                        displayField: 'name',
                        editable: false,
                        queryMode: 'local',
                        value: 'incremental',
                        name: 'bundleType',
                        labelWidth: 80,
                        width: 500
                    }, {
                        xtype: 'combo',
                        fieldLabel: 'Compression',
                        store: [['', 'No compression (Recommended on small instances)'], ['gzip', 'gzip (Recommended on large instances)']],
                        valueField: 'id',
                        displayField: 'name',
                        editable: false,
                        queryMode: 'local',
                        value: 'gzip',
                        name: 'compressor',
                        labelWidth: 80,
                        width: 500
                    }, {
                        xtype: 'checkbox',
                        hideLabel: true,
                        name: 'useSlave',
                        boxLabel: 'Use SLAVE server for data bundle'
                    }]
                };
            } else if (data['dbType'] == 'percona' || data['dbType'] == 'mysql2') {
                confirmationDataBundleOptions = {
                    xtype: 'fieldset',
                    title: 'Data bundle settings',
                    items: [{
                        xtype: 'checkbox',
                        hideLabel: true,
                        name: 'useSlave',
                        boxLabel: 'Use SLAVE server for data bundle'
                    }]
                };
            }
            return confirmationDataBundleOptions;
        },

        getConnectionDetails: function() {
            var data = this.moduleParams,
                items = [];
            items.push({
                xtype: 'fieldset',
                title: 'Credentials',
                defaults: {
                    labelWidth: 170
                },
                items: [{
                    xtype: 'displayfield',
                    fieldLabel: 'Master username',
                    value: data['accessDetails']['username']
                },{
                    xtype: 'displayfield',
                    fieldLabel: 'Master password',
                    value: data['accessDetails']['password']
                }]
            });

            if (data['accessDetails']['dns']) {
                items.push({
                    xtype: 'fieldset',
                    title: 'Endpoints',
                    cls: 'x-fieldset-separator-none',
                    defaults: {
                        labelWidth: 190,
                        width: '100%'
                    },
                    items: [{
                        xtype: 'displayfield',
                        cls: 'x-form-field-info',
                        value: 'Public - To connect to the service from the Internet<br / >Private - To connect to the service from another instance'
                    }, {
                        xtype: 'displayfield',
                        fieldLabel: 'Writes endpoint (Public)',
                        value: data['accessDetails']['dns']['master']['public']
                    }, {
                        xtype: 'displayfield',
                        fieldLabel: 'Reads endpoint (Public)',
                        value: data['accessDetails']['dns']['slave']['public']
                    }, {
                        xtype: 'displayfield',
                        fieldLabel: 'Writes endpoint (Private)',
                        value: data['accessDetails']['dns']['master']['private']
                    }, {
                        xtype: 'displayfield',
                        fieldLabel: 'Reads endpoint (Private)',
                        value: data['accessDetails']['dns']['slave']['private']
                    }]
                });
            }
            return items;
        },

        tools: [{
            type: 'refresh',
            handler: function () {
                Scalr.event.fireEvent('refresh');
            }
        }, {
            type: 'close',
            handler: function () {
                Scalr.event.fireEvent('close');
            }
        }]
    });
    return panel;
});

if (!Ext.ClassManager.isCreated('Scalr.ui.FormFieldDbmsHistory')) {
    Ext.define('Scalr.ui.FormFieldDbmsHistory', {
        extend: 'Ext.form.field.Display',
        alias: 'widget.dbmshistoryfield',

        fieldSubTpl: [
            '<div id="{id}"',
            '<tpl if="fieldStyle"> style="{fieldStyle}"</tpl>',
            ' class="{fieldCls}"></div>',
            {
                compiled: true,
                disableFormats: true
            }
        ],

        fieldCls: Ext.baseCSSPrefix + 'form-dbmshistory-field',

        setRawValue: function(value) {
            var me = this;
            me.rawValue = value;
            if (me.rendered) {
                var html = [],
                    list = value.slice(-8);
                for (var i=0, len=list.length; i<len; i++) {
                    html.push('<div title="'+Ext.String.htmlEncode(list[i].date + (list[i].error ? ' - ' + list[i].error : ''))+'" class="item'+(list[i].status != 'ok' ? ' failed' : '')+'"></div>');
                }
                Ext.DomHelper.append(me.inputEl.dom, html.join(''), true);
                me.updateLayout();
            }
            return value;
        },

        valueToRaw: function(value) {
            return value;
        }
    });
}

if (!Ext.ClassManager.isCreated('Scalr.ui.FormFieldDbmsClusterMap')) {
    Ext.define('Scalr.ui.FormFieldDbmsClusterMap', {
        extend: 'Ext.form.FieldContainer',
        alias: 'widget.dbmsclustermapfield',

        mixins: {
            field: 'Ext.form.field.Field'
        },

        baseCls: 'x-container x-form-dbmsclustermapfield',
        allowBlank: false,

        layout: {
            type: 'vbox',
            align: 'center'
        },
        currentServerId: null,

        buttonConfig: {
            xtype: 'custombutton',
            cls: 'x-dbmsclustermapfield-btn',
            overCls: 'x-dbmsclustermapfield-btn-over',
            pressedCls: 'x-dbmsclustermapfield-btn-pressed',
            enableToggle: true,
            width: 192,
            height: 90,
            margin: 0,
            allowDepress: true,
            toggleGroup: 'dbmsclustermapfield',
            handler: function() {
                var comp = this.up('dbmsclustermapfield');
                if (this.pressed) {
                    comp.showServerDetails(this.serverInfo);
                } else if (!Ext.ButtonToggleManager.getPressed('dbmsclustermapfield')){
                    comp.hideServerDetails();
                }
            },
            renderTpl:
                '<div class="x-btn-el x-dbmsclustermapfield-inner x-dbmsclustermapfield-{type}" id="{id}-btnEl">'+
                    '<div><span class="title">{title}:</span> {ip}</div>'+
                    '<div>{location}</div>'+
                    '<div class="status status-{status}">{status_title}</div>'+
                '</div>'
        },
        initComponent: function() {
            var me = this;
            me.callParent();
            me.initField();
            if (!me.name) {
                me.name = me.getInputId();
            }
        },

        getValue: function() {
            var me = this,
                val = me.getRawValue();
            me.value = val;
            return val;
        },

        setValue: function(value) {
            var me = this;
            me.setRawValue(value);
            return me.mixins.field.setValue.call(me, value);
        },

        getRawValue: function() {
            var me = this;
            return me.rawValue;
        },

        setRawValue: function(value) {
            var me = this;
            me.rawValue = me.valueToRaw(value);
            if (me.rendered) {
                me.renderButtons(me.rawValue);
            }
            return value;
        },

        valueToRaw: function(data) {
            var rawValue = {master: {}, slaves: []};
            if (data) {
                for (var i=0, len=data.length; i<len; i++) {
                    if (data[i].serverRole == 'master') {
                        rawValue.master = data[i];
                    } else {
                        rawValue.slaves.push(data[i]);
                    }
                }
            }
            return rawValue;
        },

        getServerStatus: function(status){
            var result;
            switch (status) {
                case 'Pending':
                case 'Initializing':
                    result = '<span style="color:#f79501">' + status.toLowerCase() + '</span>';
                break;
                case 'Running':
                    result = '<span style="color:#008000">' + status.toLowerCase() + '</span>';
                break;
                default:
                    result = '<span style="color:#EA5535">down</span>';
                break;
            }
            return result;
        },

        getReplicationStatus: function(status) {
            var result;
            switch (status) {
                case 'up':
                    result = 'ok';
                break;
                case 'down':
                    result = 'broken';
                break;
                default:
                    result = status || 'error'
                break;
            }
            return result;
        },

        renderButtons: function(data) {
            this.suspendLayouts();
            this.removeAll();

            //render master button
            var master = {
                height: 85,
                serverInfo: Ext.clone(data.master),
                disabled: data.master.status !== 'Running',
                renderData: {
                    type: 'master',
                    title: 'Master',
                    location: data.master.cloudLocation || '',
                    ip: data.master.remoteIp || '',
                    serverid: data.master.serverId || '',
                    status_title: 'Server is ' + this.getServerStatus(data.master.status)
                }
            };
            master.serverInfo.title = master.renderData.title;
            this.add({
                xtype: 'container',
                cls: 'x-dbmsclustermapfield-container',
                padding: 12,
                items: Ext.applyIf(master, this.buttonConfig)
            });

            //render slaves buttons
            var slavesRowsCount = Math.ceil((data.slaves.length + 1)/5);//
            for (var row=0; row<slavesRowsCount; row++) {
                var slave, status, replication, statusTitle, slaves, limit;

                slaves = this.add({
                    xtype: 'container',
                    cls: 'x-dbmsclustermapfield-container',
                    width: '100%',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    padding: 12,
                    margin: '2 0 0 0'
                });

                limit = row*5 + 5 > data.slaves.length ? data.slaves.length : row*5 + 5;
                for (var i=row*5; i<limit; i++) {
                    replication = data.slaves[i].replication || {};

                    if (data.slaves[i].status == 'Running') {
                        status = this.getReplicationStatus(replication.status);
                        statusTitle = status == 'error' ? 'Can\'t get replication status' : 'Replication is ' + status;
                    } else {
                        status = 'down';
                        statusTitle = 'Server is ' + this.getServerStatus(data.slaves[i].status);
                    }

                    slave = {
                        serverInfo: Ext.clone(data.slaves[i]),
                        renderData: {
                            type: 'slave',
                            title: 'Slave #' + (i+1),
                            location: data.slaves[i].cloudLocation || '',
                            ip: data.slaves[i].remoteIp || 'Not available',
                            serverid: data.slaves[i].serverId || '',
                            status: status,
                            status_title: statusTitle
                        },
                        margin: '0 5'

                    };
                    slave.serverInfo.title = slave.renderData.title;
                    slaves.add(Ext.applyIf(slave, this.buttonConfig));
                }
            }

            //render launch new slave button
            var addBtn = {
                cls: 'x-dbmsclustermapfield-add',
                overCls: 'x-dbmsclustermapfield-btn-over',
                pressedCls: 'x-dbmsclustermapfield-add-pressed',
                toggleGroup: null,
                renderTpl:
                    '<div class="x-btn-el x-dbmsclustermapfield-inner" id="{id}-btnEl">'+
                        'Launch new slave'+
                    '</div>',
                margin: '0 5',
                handler: function(){
                    var data = this.up('form').moduleParams,
                        r = {
                            confirmBox: {
                                msg: 'Launch new slave?',
                                type: 'launch'
                            },
                            processBox: {
                                type: 'launch'
                            },
                            url: '/farms/' + data['farmId'] + '/roles/' + data['farmRoleId'] + '/xLaunchNewServer',
                            params: {
                                increaseMinInstances: 1,
                                needConfirmation: 0
                            },
                            success: function (data) {
                                Scalr.event.fireEvent('refresh');
                            }
                        };
                    Scalr.Request(r);
                }
            };
            if (!this.isDashboardReadOnly) {
                slaves.add(Ext.applyIf(addBtn, this.buttonConfig));
            }

            //server details form
            this.detailsForm = this.add({
                xtype: 'form',
                cls: 'x-dbmsclustermapfield-container',
                width: '100%',
                padding: 0,
                margin: '2 0 0 0',
                hidden: true,
                items: [{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        align: 'stretch'
                    },
                    defaults: {
                        flex: 1
                    },
                    cls: 'x-fieldset-separator-bottom',
                    items: [{
                        xtype: 'fieldset',
                        title: 'Basic info',
                        cls: 'x-fieldset-separator-none',
                        defaults: {
                            labelWidth: 180
                        },
                        items: [{
                            xtype: 'displayfield',
                            fieldLabel: 'ID',
                            name: 'server_id'
                        },{
                            xtype: 'displayfield',
                            fieldLabel: 'Public IP',
                            name: 'server_remote_ip'
                        },{
                            xtype: 'displayfield',
                            fieldLabel: 'Private IP',
                            name: 'server_local_ip'
                        }]
                    },{
                        xtype: 'toolfieldset',
                        title: 'General metrics',
                        itemId: 'generalMetrics',
                        cls: 'x-fieldset-separator-left',
                        defaults: {
                            labelWidth: 120
                        },
                        items: [{
                            xtype: 'progressfield',
                            fieldLabel: 'Memory usage',
                            name: 'server_metrics_memory',
                            width: 360,
                            units: 'Gb',
                            emptyText: 'Loading...',
                            fieldCls: 'x-form-progress-field x-form-progress-field-small'
                        },{
                            xtype: 'progressfield',
                            fieldLabel: 'CPU load',
                            name: 'server_metrics_cpu',
                            width: 360,
                            emptyText: 'Loading...',
                            fieldCls: 'x-form-progress-field x-form-progress-field-small'
                        },{
                            xtype: 'displayfield',
                            fieldLabel: 'Load averages',
                            name: 'server_load_average'
                        }],
                        tools: [{
                            type: 'refresh',
                            style: 'margin-left:12px',
                            handler: function () {
                                this.up('dbmsclustermapfield').loadGeneralMetrics();
                            }
                        }]
                    }]
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        align: 'stretch'
                    },
                    defaults: {
                        flex: 1
                    },
                    items: [{
                        xtype: 'fieldset',
                        title: 'Database metrics',
                        itemId: 'serverMetrics',
                        cls: 'x-fieldset-separator-none',
                        defaults: {
                            labelWidth: 180
                        }
                    },{
                        xtype: 'toolfieldset',
                        title: 'Statistics',
                        cls: 'x-fieldset-separator-left',
                        items: [{
                            xtype: 'chartpreview',
                            itemId: 'chartPreview'
                        }],
                        tools: [{
                            type: 'refresh',
                            style: 'margin-left:12px',
                            handler: function () {
                                this.up('dbmsclustermapfield').loadChartsData();
                            }
                        }]
                    }]
                }]
            });
            this.resumeLayouts(true);

        },

        hideServerDetails: function() {
            var form = this.up('form');
            if (this.detailsForm) {
                var scrollTop = form.body.getScroll().top;
                form.suspendLayouts();
                this.detailsForm.hide();
                form.resumeLayouts(true);
                form.body.scrollTo('top', scrollTop);
                this.currentServerId = null;
            }
        },

        showServerDetails: function(data) {
            if (this.detailsForm) {
                var form = this.up('form'),
                    scrollTop = form.body.getScroll().top,
                    metricsPanel = this.detailsForm.down('#serverMetrics'),
                    replication = data['replication'] || {};
                form.suspendLayouts();
                this.detailsForm.getForm().setValues({
                    server_id: data['disabledServerPermission'] ? (data.serverId || '') : ('<a href="#/servers/' + data.serverId + '/dashboard">' + (data.serverId || '') + '</a>'),
                    server_remote_ip: data.remoteIp || '',
                    server_local_ip: data.localIp || '',
                    server_metrics_memory: null,
                    server_metrics_cpu: null,
                    server_load_average: ''
                });

                metricsPanel.removeAll();

                if (replication['status'] == 'error' || !replication['status']) {
                    var message = replication['message'] ? replication['message'] : 'Can\'t get replication status'
                    metricsPanel.add({
                        xtype: 'displayfield',
                        value: '<span style="color:#C00000">' + message + '</span>'
                    });
                } else if (replication[form.moduleParams['dbType']]) {
                    Ext.Object.each(replication[form.moduleParams['dbType']], function(name, value){
                        if (form.moduleParams['dbType'] === 'redis' && Ext.isObject(value)) {
                            metricsPanel.add({
                                xtype: 'label',
                                html: '<b>' + name + ':</b>'
                            });
                            var c = metricsPanel.add({
                                xtype: 'container',
                                margin: '12 0 20 0',
                                defaults: {
                                    labelWidth: 180
                                }
                            });
                            Ext.Object.each(value, function(key, val) {
                                c.add({
                                    xtype: 'displayfield',
                                    fieldLabel: Ext.String.capitalize(key),
                                    value: val,
                                    margin: '0 0 6 0'
                                });
                            });
                        } else if (!Ext.isEmpty(value)) {
                            metricsPanel.add({
                                xtype: 'displayfield',
                                fieldLabel: Ext.String.capitalize(name),
                                value: value
                            });
                        }
                    });
                }
                metricsPanel.show();

                this.detailsForm.show();
                form.resumeLayouts(true);
                delete this.currentServerInfo;
                this.currentServerInfo = data;
                this.loadGeneralMetrics();
                this.loadChartsData();
                form.body.scrollTo('top', scrollTop);
            }
        },

        loadChartsData: function() {
            var me = this,
                chartPreview = me.down('#chartPreview');

            if (me.currentServerInfo && me.currentServerInfo['monitoring']) {
                var params = me.currentServerInfo['monitoring'];
                var hostUrl = params['hostUrl'];
                var farmId = params['farmId'];
                var farmRoleId = params['farmRoleId'];
                var index = params['index'];
                var farmHash = params['hash'];
                var metrics = 'mem,cpu,la,net';
                var period = 'daily';
                var paramsForStatistic = {farmId: farmId, farmRoleId: farmRoleId, index: index, hash: farmHash, period: period, metrics: metrics};

                var callback = function() {
                    me.lcdDelayed = Ext.Function.defer(me.loadChartsData, 60000, me);
                };

                if (chartPreview) {
                    chartPreview.up('toolfieldset').show();
                    chartPreview.loadStatistics(hostUrl, paramsForStatistic, callback);
                }
            } else {
                if (chartPreview) {
                    chartPreview.up('toolfieldset').hide();
                }
            }
        },
        loadGeneralMetrics: function() {
            var me = this,
                serverId = me.currentServerInfo.serverId,
                comp = me.down('#generalMetrics'),
                form = me.up('form'),
                scrollTop = form.body.getScroll().top;
            if (me.currentServerInfo['disabledServerPermission']) {
                comp.hide();
            } else if (serverId) {
                me.detailsForm.getForm().setValues({
                    server_load_average: null,
                    server_metrics_memory: null,
                    server_metrics_cpu: null
                });
                form.body.scrollTo('top', scrollTop);
                Scalr.Request({
                    url: '/servers/xGetHealthDetails',
                    params: {
                        serverId: serverId
                    },
                    success: function (res) {
                        if (
                            !form.isDestroyed && !me.isDestroyed && serverId == me.currentServerInfo.serverId &&
                            res.data['memory'] && res.data['cpu']
                        ) {
                            form.suspendLayouts();
                            me.detailsForm.getForm().setValues({
                                server_load_average: res.data['la'],
                                server_metrics_memory: {
                                    total: res.data['memory']['total']*1,
                                    value: Ext.util.Format.round(res.data['memory']['total'] - res.data['memory']['free'], 2)
                                },
                                server_metrics_cpu: (100 - res.data['cpu']['idle'])/100
                            });
                            form.resumeLayouts(false);
                        }
                    },
                    failure: function() {
                        if (!form.isDestroyed && !me.isDestroyed ) {
                            form.suspendLayouts();
                            me.detailsForm.getForm().setValues({
                                server_load_average: 'not available',
                                server_metrics_memory: 'not available',
                                server_metrics_cpu: 'not available'
                            });
                            form.resumeLayouts(false);
                        }
                    }
                });
            }
        },

        getInputId: function() {
            return this.inputId || (this.inputId = this.id + '-inputEl');
        }
    });
}

Ext.define('Scalr.GrowVolumeStatusRequest', {
    extend: 'Scalr.RepeatingRequest',

    timeout: 30000,

    onSuccess: function (response) {
        var me = this;

        if (!Ext.isEmpty(response)) {
            var operationStatus = response.status;

            if (operationStatus === 'Completed') {
                me.fireEvent('success');
                me.destroy();
                return me;
            } else if (operationStatus === 'Failed') {
                me.fireEvent('failure', response.debug);
                me.destroy();
                return me;
            }
        }

        Ext.Function.defer(
            me.doRequest,
            me.getTimeout(),
            me
        );

        return me;
    },

    onFailure: function (response) {
        var me =  this;

        /*me.fireEvent('failure', response);
        me.destroy();*/

        Ext.Function.defer(
            me.doRequest,
            me.getTimeout(),
            me
        );

        return me;
    }
});
