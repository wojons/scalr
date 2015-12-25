Ext.define('Scalr.ui.FarmRoleEditorTab.Network', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Network',
    itemId: 'network',
    layout: 'anchor',
    cls: 'x-panel-column-left-with-tabs',

    isEnabled: function (record) {
        return this.callParent(arguments) && this.getHandlerName(record);
    },
    settings: {
        'aws.vpc_subnet_id': undefined
    },

    onFarmLoad: function() {
        this.originalElbIds = {};
    },

    getHandlerName: function(record) {
        var platform = record.get('platform');
        if (Scalr.isOpenstack(platform)) {
            return 'openstack';
        } else if (Scalr.isCloudstack(platform)) {
            return 'cloudstack';
        } else if (platform === 'gce') {
            return 'gce';
        } else if (platform === 'ec2') {
            return 'ec2';
        } else if (platform === 'azure') {
            return 'azure';
        }
    },
    getDefaultValues: function(record) {
        var values;
        switch (this.getHandlerName(record)) {
            case 'openstack':
                values = {
                    'openstack.networks': '[]',
                    'openstack.use_floating_ips': 0,
                    'openstack.floating_ips.map': ''
                };
            break;
            case 'cloudstack':
                values = {
                    'cloudstack.network_id': '',
                    //'cloudstack.shared_ip.id': undefined,
                    //'cloudstack.shared_ip.address': undefined,
                    'cloudstack.use_static_nat': 0,
                    'cloudstack.static_nat.map': '',
                    'cloudstack.static_nat.private_map': '',
                };
            break;
            case 'gce':
                values = {
                    'gce.network': 'default',
                    'gce.use_static_ips': 0,
                    'gce.static_ips.map': ''
                };
            break;
            case 'ec2':
                values = {
                    //'aws.vpc_subnet_id': undefined,
                    //'aws.vpc_avail_zone': undefined,
                    //'aws.vpc_internet_access': undefined,
                    //'aws.vpc_routing_table_id': undefined,
                    //'aws.elb.enabled': undefined,
                    //'aws.elb.id': undefined,
                    //'aws.elb.remove': undefined,
                    //'router.scalr.farm_role_id': undefined,
                    //'router.vpc.networkInterfaceId': undefined
                    'aws.use_elastic_ips': 0,
                    'aws.elastic_ips.map': ''
                };
            break;
            case 'azure':
                values = {
                    //'azure.virtual-network': undefined,
                    //'azure.subnet': undefined,
                    'azure.use_public_ips': 0
                };
            break;
        }
        return values;
    },

    beforeShowTab: function (record, handler) {
        var tabParams = this.up('#farmDesigner').moduleParams.tabParams;
        delete this.currentHandler;
        this.removeAll();
        this.currentHandler = this.add({xtype: 'farmrolenetwork' + this.getHandlerName(record), moduleTabParams: tabParams});
        this.currentHandler.beforeShowTab(record, handler);
    },

    showTab: function (record) {
        this.currentHandler.showTab(record);
    },

    hideTab: function (record) {
        this.currentHandler.hideTab(record);
    }
});

Ext.define('Scalr.ui.FarmRoleNetworkGce', {
	extend: 'Ext.container.Container',
    alias: 'widget.farmrolenetworkgce',
    itemId: 'gce',

    beforeShowTab: function (record, handler) {
        var settings = record.get('settings', true);
        Scalr.cachedRequest.load(
            {
                url: '/platforms/gce/xGetOptions',
                params: {}
            },
            function(data, status){
                if (status) {
                    this.tabData = data;
                    if (false && Scalr.flags['betaMode']) {//disable gce staticips
                        Scalr.CachedRequestManager.get('farmDesigner').load(
                            {
                                url: '/platforms/gce/xGetFarmRoleStaticIps',
                                params: {
                                    region: settings['gce.region'],
                                    cloudLocation: settings['gce.cloud-location'],
                                    farmRoleId: record.get('new') ? '' : record.get('farm_role_id')
                                }
                            },
                            function(data, status){
                                if (status) {
                                    Ext.apply(this.tabData, data);
                                    handler();
                                } else {
                                    this.up().deactivateTab();
                                }
                            },
                            this
                        );
                    } else {
                        handler();
                    }
                } else {
                    this.up().deactivateTab();
                }
            },
            this
        );
    },

    showTab: function (record) {
        var settings = record.get('settings', true),
            data = this.tabData,
            field;
        //gce network
        field = this.down('[name="gce.network"]');
        field.store.load({data: data['networks'] || []});
        field.setValue(settings['gce.network'] || 'default');

        //static ips
        var sipsFieldset = this.down('[name="gce.use_static_ips"]');
        if (false && Scalr.flags['betaMode'] && data['staticIps']) {//disable gce staticips
            var staticIpMap = [];
            if (settings['gce.static_ips.map']) {
                Ext.each((settings['gce.static_ips.map']).split(';'), function(value) {
                    if (value) {
                        value = value.split('=');
                        if (value.length === 2) {
                            var tmp = {
                                serverIndex: value[0]*1,
                                staticIp: value[1]
                            };
                            Ext.each(data['staticIps'].map, function(value) {
                                if (value['serverIndex'] == tmp['serverIndex']) {
                                    Ext.applyIf(tmp, value);
                                    return false;
                                }
                            })
                            staticIpMap.push(tmp);
                        }
                    }
                });
            } else {
                Ext.each(data['staticIps'].map, function(value) {
                    staticIpMap.push(Ext.apply({}, value));
                });
            }
            var maxInstances = settings['scaling.enabled'] == 1 ? settings['scaling.max_instances'] : record.get('running_servers');
            if (staticIpMap.length > maxInstances) {
                var removeIndex = maxInstances;
                for (var i=staticIpMap.length-1; i>=0; i--) {
                    removeIndex = i + 1;
                    if (staticIpMap[i]['serverId'] || removeIndex == maxInstances) {
                        break;
                    }
                }
                staticIpMap.splice(removeIndex, staticIpMap.length - removeIndex);
            } else if (staticIpMap.length < maxInstances) {
                for (var i = staticIpMap.length; i < maxInstances; i++)
                    staticIpMap.push({ serverIndex: i + 1 });
            }

            field = this.down('[name="gce.static_ips.map"]');
            field.store.load({ data: staticIpMap });
            field['ipAddressEditorIps'] = Ext.Array.merge(data['staticIps']['ips'], [{ipAddress: '0.0.0.0'}]);
            this.down('[name="gce.static_ips.warning"]').hide();

            sipsFieldset[settings['gce.use_static_ips'] == 1 ? 'expand' : 'collapse']();
            sipsFieldset.show();
        } else {
            sipsFieldset.hide();
        }

    },

    hideTab: function (record) {
        var settings = record.get('settings'),
            sipsFieldset = this.down('[name="gce.use_static_ips"]');
        settings['gce.network'] = this.down('[name="gce.network"]').getValue();

        //static ips
        if (sipsFieldset.isVisible()) {
            if (!sipsFieldset.collapsed) {
                settings['gce.use_static_ips'] = 1;
                settings['gce.static_ips.map'] = '';
                this.down('[name="gce.static_ips.map"]').store.each(function(record) {
                    settings['gce.static_ips.map'] += record.get('serverIndex') + '=' + record.get('staticIp') + ';';
                });
            } else {
                settings['gce.use_static_ips'] = 0;
            }
        }

        record.set('settings', settings);
    },

    items: [{
        xtype: 'fieldset',
        layout: 'anchor',
        defaults: {
            anchor: '100%',
            maxWidth: 600
        },
        items: [{
            xtype: 'combo',
            store: {
                fields: [ 'name', 'description' ],
                proxy: 'object'
            },
            valueField: 'name',
            displayField: 'description',
            fieldLabel: 'Network',
            editable: false,
            queryMode: 'local',
            name: 'gce.network'
        }]
    },{
        xtype: 'fieldset',
        title: 'Static IPs',
        name: 'gce.use_static_ips',
        checkboxToggle: true,
        toggleOnTitleClick: true,
        collapsible: true,
        collapsed: true,
        defaults: {
            maxWidth: 820
        },
        items: [{
            xtype: 'displayfield',
            cls: 'x-form-field-warning',
            anchor: '100%',
            name: 'gce.static_ips.warning',
            hidden: true,
            value: ''
        }, {
            xtype: 'grid',
            name: 'gce.static_ips.map',
            plugins: [{
                ptype: 'cellediting',
                clicksToEdit: 1,
                listeners: {
                    beforeedit: function(comp, e) {
                        var editor = this.getEditor(e.record, e.column);
                        for (var i = 0, len = e.grid['ipAddressEditorIps'].length; i < len; i++) {
                            e.grid['ipAddressEditorIps'][i]['fieldInstanceId'] = e.record.get('instanceId') && (e.grid['ipAddressEditorIps'][i]['instanceId'] == e.record.get('instanceId'));
                        }
                        editor.field.store.load({ data: e.grid['ipAddressEditorIps'] });
                    },
                    edit: function(comp, e) {
                        if (e.value == null) {
                            e.record.set('staticIp', '');
                        }

                        if (e.record.get('staticIp')) {
                            var editor = this.getEditor(e.record, e.column);
                            var r = editor.field.store.findRecord('ipAddress', e.record.get('staticIp'));
                            if (r && r.get('instanceId') && r.get('instanceId') != e.record.get('instanceId') && r.get('ipAddress') != e.record.get('remoteIp'))
                                e.grid.up('[tab="tab"]').down('[name="gce.static_ips.warning"]').setValue(
                                    'IP address \'' + e.record.get('staticIp') + '\' is already in use, and will be re-associated with selected server. IP address on old server will revert to dynamic IP.'
                                ).show();
                            else
                                e.grid.up('[tab="tab"]').down('[name="gce.static_ips.warning"]').hide();
                        }
                    }
                }
            }],
            disableSelection: true,
            store: {
                proxy: 'object',
                fields: [ 'staticIp', 'instanceId', 'serverId', 'serverIndex', 'remoteIp', 'warningInstanceIdDoesntMatch' ]
            },
            columns: [
                { header: 'Server Index', width: 130, sortable: true, dataIndex: 'serverIndex' },
                { header: 'Server ID', flex: 1, sortable: true, dataIndex: 'serverId', xtype: 'templatecolumn', tpl:
                    '<tpl if="serverId"><a href="#/servers/{serverId}/dashboard">{serverId}</a> <tpl if="instanceId">({instanceId})</tpl><tpl else>Not running</tpl>'
                }, {
                    header: 'Static IP', width: 250, sortable: true, dataIndex: 'staticIp', editable: true, tdCls: 'x-grid-cell-editable',
                    renderer: function(value, metadata, record) {
                        metadata.tdAttr = 'title="Click here to change"';
                        metadata.style = 'line-height: 16px; padding-top: 4px; padding-bottom: 2px';

                        if (value == '0.0.0.0')
                            value = 'Allocate new';
                        else if (!value)
                            value = 'Not allocated yet';

                        value = '<span style="float: left">' + value + '</span>';

                        if (record.get('warningInstanceIdDoesntMatch'))
                            value += '<div style="margin-left: 5px; float: left; height: 15px; width: 16px; background-image: url(/ui2/images/icons/warning_icon_16x16.png)" title="This IP address is out of sync and associated with another instance">&nbsp;</div>'

                        return value;
                    },
                    editor: {
                        xtype: 'combobox',
                        forceSelection: true,
                        editable: false,
                        displayField: 'ipAddress',
                        valueField: 'ipAddress',
                        matchFieldWidth: false,
                        store: {
                            proxy: 'object',
                            fields: ['ipAddress', 'instanceId', 'farmName' , 'roleName', 'serverIndex', 'fieldInstanceId']
                        },
                        displayTpl: '<tpl for="."><tpl if="values.ipAddress == \'0.0.0.0\'">Allocate new<tpl else>{[values.ipAddress]}</tpl></tpl>',
                        listConfig: {
                            minWidth: 250,
                            cls: 'x-boundlist-alt',
                            tpl: '<tpl for="."><div class="x-boundlist-item" style="font: bold 13px arial; height: auto; padding: 5px;">' +
                                    '<tpl if="ipAddress == \'0.0.0.0\'"><span>Allocate new</span>' +
                                    '<tpl elseif="ipAddress != \'\'">' +
                                        '<tpl if="!fieldInstanceId">' +
                                            '<tpl if="farmName"><span style="color: #F90000">{ipAddress}</span>' +
                                            '<tpl else><span style="color: #138913">{ipAddress}</span> (free)</tpl>' +
                                        '<tpl else><span>{ipAddress}</span></tpl>' +
                                    '<tpl else>Not allocated yet</tpl>' +
                                    '<tpl if="ipAddress && farmName"><br /><span style="font-weight: normal">used by: {farmName} &rarr; {roleName} # {serverIndex}</span></tpl>' +
                                '</div></tpl>'
                        }
                    }
                }
            ]
        }]
    }]
});

Ext.define('Scalr.ui.FarmRoleNetworkCloudstack', {
	extend: 'Ext.container.Container',
    alias: 'widget.farmrolenetworkcloudstack',
    tabData: null,
    itemId: 'cloudstack',

    beforeShowTab: function(record, handler) {
        Scalr.CachedRequestManager.get('farmDesigner').load(
            {
                url: '/platforms/cloudstack/xGetOfferingsList/',
                params: {
                    cloudLocation: record.get('cloud_location'),
                    platform: record.get('platform'),
                    farmRoleId: record.get('new') ? '' : record.get('farm_role_id')
                }
            },
            function(data, status){
                this.tabData = data;
                status ? handler() : this.up().deactivateTab();
            },
            this
        );
    },

    showTab: function (record) {
        var me = this,
            settings = record.get('settings'),
            eipsData = (this.tabData) ? this.tabData['eips'] : null,
            eipsFieldset = this.down('[name="cloudstack.use_static_nat"]'),
            field, defaultValue;

        Ext.Object.each({
            'cloudstack.network_id': 'networks',
            'cloudstack.shared_ip.id': 'ipAddresses'
        }, function(fieldName, dataFieldName){
            var storeData,
                cloudLocation = record.get('cloud_location'),
                limits = Scalr.getGovernance(record.get('platform'), fieldName);
            if (limits && limits.value && limits.value[cloudLocation] && limits.value[cloudLocation].length > 0) {
                storeData = [];
                Ext.Array.each(me.tabData ? me.tabData[dataFieldName] : [], function(item) {
                    if (Ext.Array.contains(limits.value[cloudLocation], item.id)) {
                        storeData.push(item);
                    }
                });
            } else {
                storeData = me.tabData ? me.tabData[dataFieldName] : [];
            }

            defaultValue = null
            field = me.down('[name="' + fieldName + '"]');
            field.store.load({ data: storeData });
            if (field.store.getCount() == 0) {
                field.hide();
            } else {
                defaultValue = field.store.getAt(0).get('id');
                field.show();
            }
            field.setValue(!Ext.isEmpty(settings[fieldName]) ? settings[fieldName] : defaultValue);
            if (Ext.isFunction(field.toggleIcon)) {
                field.toggleIcon('governance', !!limits);
            }
        });

        //static nat
        var elasticIpMap = [];
        if (eipsData) {
            if (settings['cloudstack.static_nat.map']) {
                Ext.each((settings['cloudstack.static_nat.map']).split(';'), function(value) {
                    if (value) {
                        value = value.split('=');
                        if (value.length === 2) {
                            var tmp = {
                                serverIndex: value[0]*1,
                                elasticIp: value[1]
                            };
                            Ext.each(eipsData.map, function(value) {
                                if (value['serverIndex'] == tmp['serverIndex']) {
                                    Ext.applyIf(tmp, value);
                                    return false;
                                }
                            })
                            elasticIpMap.push(tmp);
                        }
                    }
                });
            } else {
                Ext.each(eipsData.map, function(value) {
                    elasticIpMap.push(Ext.apply({}, value));
                });
            }
            var maxInstances = settings['scaling.enabled'] == 1 ? settings['scaling.max_instances'] : record.get('running_servers');
            if (elasticIpMap.length > maxInstances) {
                var removeIndex = maxInstances;
                for (var i=elasticIpMap.length-1; i>=0; i--) {
                    removeIndex = i + 1;
                    if (elasticIpMap[i]['serverId'] || removeIndex == maxInstances) {
                        break;
                    }
                }
                elasticIpMap.splice(removeIndex, elasticIpMap.length - removeIndex);
            } else if (elasticIpMap.length < maxInstances) {
                for (var i = elasticIpMap.length; i < maxInstances; i++)
                    elasticIpMap.push({ serverIndex: i + 1 });
            }

            field = this.down('#staticNatGrid');
            field.store.load({ data: elasticIpMap });
            field['ipAddressEditorIps'] = Ext.Array.merge(eipsData['ips'], [{ipAddress: '0.0.0.0'}]);

            eipsFieldset[settings['cloudstack.use_static_nat'] == 1 ? 'expand' : 'collapse']();
            eipsFieldset.setVisible(settings['cloudstack.network_id'] != 'SCALR_MANUAL');
        } else {
            eipsFieldset.hide();
        }


        if (settings['cloudstack.network_id'] == 'SCALR_MANUAL') {
        	/*
            var elasticIpMapStr = '';
            Ext.each(elasticIpMap, function(item, index){
                elasticIpMapStr += item.serverIndex + '=' + (item.elasticIp||'') + ';';
            });
            */
            this.down('[name="cloudstack.shared_ip.id"]').hide();
            this.down('[name="cloudstack.static_nat.private_map"]').show().setValue(settings['cloudstack.static_nat.private_map']);
        } else {
            this.down('[name="cloudstack.static_nat.private_map"]').hide();
        }

    },

    hideTab: function (record) {
        var me = this,
            settings = record.get('settings'),
            eipsFieldset = this.down('[name="cloudstack.use_static_nat"]');
        settings['cloudstack.network_id'] = this.down('[name="cloudstack.network_id"]').getValue();

        settings['cloudstack.shared_ip.id'] = this.down('[name="cloudstack.shared_ip.id"]').getValue();
        if (settings['cloudstack.shared_ip.id']) {
            var r = this.down('[name="cloudstack.shared_ip.id"]').findRecordByValue(settings['cloudstack.shared_ip.id']);
            settings['cloudstack.shared_ip.address'] = r ? r.get('name') : '';
        } else {
            settings['cloudstack.shared_ip.address'] = '';
        }

        //static nat
        if (settings['cloudstack.network_id'] == 'SCALR_MANUAL') {
            settings['cloudstack.shared_ip.id'] = '';
            settings['cloudstack.shared_ip.address'] = '';
            settings['cloudstack.use_static_nat'] = 0;
            settings['cloudstack.static_nat.map'] = '';
            settings['cloudstack.static_nat.private_map'] = this.down('[name="cloudstack.static_nat.private_map"]').getValue();
        } else if (eipsFieldset.isVisible()) {
            if (!eipsFieldset.collapsed) {
                settings['cloudstack.shared_ip.id'] = '';
                settings['cloudstack.shared_ip.address'] = '';
                settings['cloudstack.use_static_nat'] = 1;
                settings['cloudstack.static_nat.map'] = '';
                settings['cloudstack.static_nat.private_map'] = '';
                this.down('#staticNatGrid').store.each(function(record) {
                    settings['cloudstack.static_nat.map'] += record.get('serverIndex') + '=' + record.get('elasticIp') + ';';
                });
            } else {
                settings['cloudstack.use_static_nat'] = 0;
            }
        }
        this.down('[name="cloudstack.static_nat.warning"]').hide();
        record.set('settings', settings);
    },

    items: [{
        xtype: 'fieldset',
        layout: 'anchor',
        defaults: {
            labelWidth: 100,
            maxWidth: 650,
            anchor: '100%'
        },
        items: [{
        }, {
            xtype: 'combo',
            store: {
                model: Scalr.getModel({fields: [ 'id', 'name' ]}),
                proxy: 'object'
            },
            matchFieldWidth: false,
            listConfig: {
                width: 'auto',
                minWidth: 350
            },
            valueField: 'id',
            displayField: 'name',
            fieldLabel: 'Network',
            plugins: {
                ptype: 'fieldicons',
                position: 'outer',
                icons: ['governance']
            },
            editable: false,
            queryMode: 'local',
            name: 'cloudstack.network_id',
            listeners: {
                change: function(comp, value) {
                    var eipsFieldset = comp.up('#cloudstack').down('[name="cloudstack.use_static_nat"]');
                    comp.next('[name="cloudstack.static_nat.private_map"]').setVisible(value == 'SCALR_MANUAL');
                    comp.next('[name="cloudstack.shared_ip.id"]').setVisible(value != 'SCALR_MANUAL' && eipsFieldset.collapsed);
                    eipsFieldset.setVisible(value != 'SCALR_MANUAL');
                }
            }
        }, {
            xtype: 'textfield',
            name: 'cloudstack.static_nat.private_map',
            fieldLabel: 'Static IPs',
            emptyText: 'ex. 1=192.168.0.1;2=192.168.0.2'
        }, {
            xtype: 'combo',
            store: {
                model: Scalr.getModel({fields: [ 'id', 'name' ]}),
                proxy: 'object'
            },
            matchFieldWidth: false,
            listConfig: {
                width: 'auto',
                minWidth: 350
            },
            valueField: 'id',
            displayField: 'name',
            fieldLabel: 'Shared IP',
            editable: false,
            queryMode: 'local',
            name: 'cloudstack.shared_ip.id'
        }]
    }, {
        xtype: 'fieldset',
        title: 'Static NAT',
        name: 'cloudstack.use_static_nat',
        checkboxToggle: true,
        toggleOnTitleClick: true,
        collapsible: true,
        collapsed: true,
        listeners: {
            expand: function() {
                this.up().down('[name="cloudstack.shared_ip.id"]').hide().setValue('');
            },
            collapse: function() {
                this.up().down('[name="cloudstack.shared_ip.id"]').show();
            }
        },
        defaults: {
            maxWidth: 820
        },
        items: [{
            xtype: 'displayfield',
            cls: 'x-form-field-info',
            hidden: true,
            value:   'Enable to have Scalr automatically assign an ElasticIP to each instance of this role ' +
                '(this requires a few minutes during which the instance is unreachable from the public internet) ' +
                'after HostInit but before HostUp. If out of allocated IPs, Scalr will request more, but never remove any.'
        }, {
            xtype: 'displayfield',
            cls: 'x-form-field-warning',
            anchor: '100%',
            name: 'cloudstack.static_nat.warning',
            hidden: true,
            value: ''
        }, {
            xtype: 'grid',
            id: 'staticNatGrid',
            plugins: [{
                ptype: 'cellediting',
                clicksToEdit: 1,
                listeners: {
                    beforeedit: function(comp, e) {
                        var editor = this.getEditor(e.record, e.column);
                        for (var i = 0, len = e.grid['ipAddressEditorIps'].length; i < len; i++) {
                            e.grid['ipAddressEditorIps'][i]['fieldInstanceId'] = e.record.get('instanceId') && (e.grid['ipAddressEditorIps'][i]['instanceId'] == e.record.get('instanceId'));
                        }
                        editor.field.store.load({ data: e.grid['ipAddressEditorIps'] });
                    },
                    edit: function(comp, e) {
                        if (e.value == null) {
                            e.record.set('elasticIp', '');
                        }

                        if (e.record.get('elasticIp')) {
                            var editor = this.getEditor(e.record, e.column);
                            var r = editor.field.store.findRecord('ipAddress', e.record.get('elasticIp'));
                            if (r && r.get('instanceId') && r.get('instanceId') != e.record.get('instanceId') && r.get('ipAddress') != e.record.get('remoteIp'))
                                e.grid.up('[tab="tab"]').down('[name="cloudstack.static_nat.warning"]').setValue(
                                    'IP address \'' + e.record.get('elasticIp') + '\' is already in use, and will be re-associated with selected server. IP address on old server will revert to dynamic IP.'
                                ).show();
                            else
                                e.grid.up('[tab="tab"]').down('[name="cloudstack.static_nat.warning"]').hide();
                        }
                    }
                }
            }],
            disableSelection: true,
            store: {
                proxy: 'object',
                fields: [ 'elasticIp', 'instanceId', 'serverId', 'serverIndex', 'remoteIp', 'warningInstanceIdDoesntMatch' ]
            },
            columns: [
                { header: 'Server Index', width: 130, sortable: true, dataIndex: 'serverIndex' },
                { header: 'Server ID', flex: 1, sortable: true, dataIndex: 'serverId', xtype: 'templatecolumn', tpl:
                    '<tpl if="serverId"><a href="#/servers/{serverId}/dashboard">{serverId}</a> <tpl if="instanceId">({instanceId})</tpl><tpl else>Not running</tpl>'
                }, {
                    header: 'Static IP', width: 250, sortable: true, dataIndex: 'elasticIp', editable: true, tdCls: 'x-grid-cell-editable',
                    renderer: function(value, metadata, record) {
                        metadata.tdAttr = 'title="Click here to change"';
                        metadata.style = 'line-height: 16px; padding-top: 4px; padding-bottom: 2px';

                        if (value == '0.0.0.0')
                            value = 'Allocate new';
                        else if (!value)
                            value = 'Not allocated yet';

                        value = '<span style="float: left">' + value + '</span>';

                        if (record.get('warningInstanceIdDoesntMatch'))
                            value += '<div style="margin-left: 5px; float: left; height: 15px; width: 16px; background-image: url(/ui2/images/icons/warning_icon_16x16.png)" title="This IP address is out of sync and associated with another instance">&nbsp;</div>'

                        return value;
                    },
                    editor: {
                        xtype: 'combobox',
                        forceSelection: true,
                        editable: false,
                        displayField: 'ipAddress',
                        valueField: 'ipAddress',
                        matchFieldWidth: false,
                        store: {
                            proxy: 'object',
                            fields: ['ipAddress', 'instanceId', 'farmName' , 'roleName', 'serverIndex', 'fieldInstanceId']
                        },
                        displayTpl: '<tpl for="."><tpl if="values.ipAddress == \'0.0.0.0\'">Allocate new<tpl else>{[values.ipAddress]}</tpl></tpl>',
                        listConfig: {
                            minWidth: 250,
                            cls: 'x-boundlist-alt',
                            tpl: '<tpl for="."><div class="x-boundlist-item" style="font: bold 13px arial; height: auto; padding: 5px;">' +
                                    '<tpl if="ipAddress == \'0.0.0.0\'"><span>Allocate new</span>' +
                                    '<tpl elseif="ipAddress != \'\'">' +
                                        '<tpl if="!fieldInstanceId">' +
                                            '<tpl if="farmName"><span style="color: #F90000">{ipAddress}</span>' +
                                            '<tpl else><span style="color: #138913">{ipAddress}</span> (free)</tpl>' +
                                        '<tpl else><span>{ipAddress}</span></tpl>' +
                                    '<tpl else>Not allocated yet</tpl>' +
                                    '<tpl if="ipAddress && farmName"><br /><span style="font-weight: normal">used by: {farmName} &rarr; {roleName} # {serverIndex}</span></tpl>' +
                                '</div></tpl>'
                        }
                    }
                }
            ]
        }]
    }]
});


Ext.define('Scalr.ui.FarmRoleNetworkOpenstack', {
	extend: 'Ext.container.Container',
    alias: 'widget.farmrolenetworkopenstack',
    tabData: null,
    itemId: 'openstack',

    beforeShowTab: function(record, handler) {
        Scalr.cachedRequest.load(
            {
                url: '/platforms/openstack/xGetNetworkResources',
                params: {
                    cloudLocation: record.get('cloud_location'),
                    platform: record.get('platform'),
                    farmRoleId: record.get('new') ? '' : record.get('farm_role_id'),
                }
            },
            function(data, status){
                this.tabData = data;
                if (status) {
                    if (Scalr.getPlatformConfigValue(record.get('platform'), 'ext.floating_ips_enabled') == 1) {
                        Scalr.cachedRequest.load(
                            {
                                url: '/platforms/openstack/xGetOpenstackResources',
                                params: {
                                    cloudLocation: record.get('cloud_location'),
                                    platform: record.get('platform')
                                }
                            },
                            function(data, status){
                                var field,
                                    ipPools = data ? data['ipPools'] : null;
                                this.cache = data;
                                field = this.down('[name="openstack.ip-pool"]');
                                if (ipPools) {
                                    field.store.load({ data:  ipPools});
                                    field.show();
                                } else {
                                    field.hide();
                                }
                                handler();
                            },
                            this
                        );
                    } else {
                        handler();
                    }
                } else {
                    this.up().deactivateTab();
                }
            },
            this
        );
    },

    showTab: function (record) {
        var me = this,
            settings = record.get('settings', true),
            networksField = me.down('[name="openstack.networks"]'),
            storeData,
            eipsData = (this.tabData) ? this.tabData['floatingIps'] : null,
            eipsFieldset = this.down('[name="openstack.use_floating_ips"]'),
            field,
            cloudLocation = record.get('cloud_location'),
            limits = Scalr.getGovernance(record.get('platform'), 'openstack.networks');
        if (limits && limits.value && limits.value[cloudLocation] && limits.value[cloudLocation].length > 0) {
            storeData = [];
            Ext.Array.each(me.tabData ? me.tabData['networks'] : [], function(item) {
                if (Ext.Array.contains(limits.value[cloudLocation], item.id)) {
                    storeData.push(item);
                }
            });
        } else {
            storeData = me.tabData ? me.tabData['networks'] : [];
        }

        networksField.store.load({data: storeData});
        networksField.setValue(Ext.decode(settings['openstack.networks']));
        networksField.toggleIcon('governance', !!limits);

        field = this.down('[name="openstack.ip-pool"]');
        if (field.isVisible()) {
            field.setValue(settings['openstack.ip-pool'] || '');
        }

      	eipsFieldset.hide();

	    //static nat
	    if (eipsData) {
	        var elasticIpMap = [];
	        if (settings['openstack.floating_ips.map']) {
	            Ext.each((settings['openstack.floating_ips.map']).split(';'), function(value) {
	                if (value) {
	                    value = value.split('=');
	                    if (value.length === 2) {
	                        var tmp = {
	                            serverIndex: value[0]*1,
	                            elasticIp: value[1]
	                        };
	                        Ext.each(eipsData.map, function(value) {
	                            if (value['serverIndex'] == tmp['serverIndex']) {
	                                Ext.applyIf(tmp, value);
	                                return false;
	                            }
	                        })
	                        elasticIpMap.push(tmp);
	                    }
	                }
	            });
	        } else {
	            Ext.each(eipsData.map, function(value) {
	                elasticIpMap.push(Ext.apply({}, value));
	            });
	        }
	        var maxInstances = settings['scaling.enabled'] == 1 ? settings['scaling.max_instances'] : record.get('running_servers');
	        if (elasticIpMap.length > maxInstances) {
	            var removeIndex = maxInstances;
	            for (var i=elasticIpMap.length-1; i>=0; i--) {
	                removeIndex = i + 1;
	                if (elasticIpMap[i]['serverId'] || removeIndex == maxInstances) {
	                    break;
	                }
	            }
	            elasticIpMap.splice(removeIndex, elasticIpMap.length - removeIndex);
	        } else if (elasticIpMap.length < maxInstances) {
	            for (var i = elasticIpMap.length; i < maxInstances; i++)
	                elasticIpMap.push({ serverIndex: i + 1 });
	        }

	        field = this.down('[name="openstack.floating_ips.map"]');
	        field.store.load({ data: elasticIpMap });
	        field['ipAddressEditorIps'] = Ext.Array.merge(eipsData['ips'], [{ipAddress: '0.0.0.0'}]);
	        this.down('[name="openstack.floating_ips.warning"]').hide();

	        //eipsFieldset[settings['openstack.use_floating_ips'] == 1 ? 'expand' : 'collapse']();
	        //eipsFieldset.show();
	    } else {
	        eipsFieldset.hide();
	    }
    },

    hideTab: function (record) {
        var me = this,
            eipsFieldset = this.down('[name="openstack.use_floating_ips"]'),
            field,
            settings = record.get('settings');
        settings['openstack.networks'] = Ext.encode(me.down('[name="openstack.networks"]').getValue());

        if (eipsFieldset.isVisible()) {
            if (!eipsFieldset.collapsed) {
            	settings['openstack.use_floating_ips'] = 1;
                settings['openstack.floating_ips.map'] = '';
                this.down('[name="openstack.floating_ips.map"]').store.each(function(record) {
                    settings['openstack.floating_ips.map'] += record.get('serverIndex') + '=' + record.get('elasticIp') + ';';
                });
            } else {
                settings['openstack.use_floating_ips'] = 0;
            }
        }

        field = me.down('[name="openstack.ip-pool"]');
        if (field.isVisible()) {
            settings['openstack.ip-pool'] = field.getValue();
        }

        record.set('settings', settings);
    },

    items: [{
        xtype: 'fieldset',
        layout: 'anchor',
        cls: 'x-fieldset-separator-none',
        defaults: {
            labelWidth: 130,
            maxWidth: 650,
            anchor: '100%'
        },
        items: [{
            xtype: 'tagfield',
            name: 'openstack.networks',
            store: {
                fields: [ 'id', 'name' ],
                proxy: 'object'
            },
            flex: 1,
            valueField: 'id',
            displayField: 'name',
            fieldLabel: 'Networks',
            plugins: {
                ptype: 'fieldicons',
                icons: ['governance']
            },
            queryMode: 'local',
            columnWidth: 1,
            emptyText: 'Default networks configuration'
        },{
            xtype: 'combo',
            store: {
                model: Scalr.getModel({fields: [ 'id', 'name' ]}),
                proxy: 'object'
            },
            valueField: 'id',
            displayField: 'name',
            fieldLabel: 'Floating IPs pool',
            editable: false,
            hidden: true,
            queryMode: 'local',
            name: 'openstack.ip-pool'
        }]
    }, {
        xtype: 'fieldset',
        title: 'Assign one FloatingIP per instance <img class="x-icon-info" src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' + Ext.String.htmlEncode('Enable to have Scalr automatically assign an FloatingIP to each instance of this role (this requires a few minutes during which the instance is unreachable from the public internet) after HostInit but before HostUp. If out of allocated IPs, Scalr will request more.') + '"/>',
        name: 'openstack.use_floating_ips',
        checkboxToggle: true,
        hidden: true,
        toggleOnTitleClick: true,
        collapsible: true,
        collapsed: true,
        defaults: {
            maxWidth: 820
        },
        items: [{
            xtype: 'displayfield',
            cls: 'x-form-field-info',
            hidden: true,
            value:   'Enable to have Scalr automatically assign an FloatingIP to each instance of this role ' +
                '(this requires a few minutes during which the instance is unreachable from the public internet) ' +
                'after HostInit but before HostUp. If out of allocated IPs, Scalr will request more, but never remove any.'
        }, {
            xtype: 'displayfield',
            cls: 'x-form-field-warning',
            anchor: '100%',
            name: 'openstack.floating_ips.warning',
            hidden: true,
            value: ''
        }, {
            xtype: 'grid',
            name: 'openstack.floating_ips.map',
            plugins: [{
                ptype: 'cellediting',
                clicksToEdit: 1,
                listeners: {
                    beforeedit: function(comp, e) {
                        var editor = this.getEditor(e.record, e.column);
                        for (var i = 0, len = e.grid['ipAddressEditorIps'].length; i < len; i++) {
                            e.grid['ipAddressEditorIps'][i]['fieldInstanceId'] = e.record.get('instanceId') && (e.grid['ipAddressEditorIps'][i]['instanceId'] == e.record.get('instanceId'));
                        }
                        editor.field.store.load({ data: e.grid['ipAddressEditorIps'] });
                    },
                    edit: function(comp, e) {
                        if (e.value == null) {
                            e.record.set('elasticIp', '');
                        }

                        if (e.record.get('elasticIp')) {
                            var editor = this.getEditor(e.record, e.column);
                            var r = editor.field.store.findRecord('ipAddress', e.record.get('elasticIp'));
                            if (r && r.get('instanceId') && r.get('instanceId') != e.record.get('instanceId') && r.get('ipAddress') != e.record.get('remoteIp'))
                                e.grid.up('[tab="tab"]').down('[name="openstack.floating_ips.warning"]').setValue(
                                    'IP address \'' + e.record.get('elasticIp') + '\' is already in use, and will be re-associated with selected server. IP address on old server will revert to dynamic IP.'
                                ).show();
                            else
                                e.grid.up('[tab="tab"]').down('[name="openstack.floating_ips.warning"]').hide();
                        }
                    }
                }
            }],
            viewConfig: {
                disableSelection: true
            },
            store: {
                proxy: 'object',
                fields: [ 'elasticIp', 'instanceId', 'serverId', 'serverIndex', 'remoteIp', 'warningInstanceIdDoesntMatch' ]
            },
            columns: [
                { header: 'Server Index', width: 130, sortable: false, dataIndex: 'serverIndex' },
                { header: 'Server ID', flex: 1, sortable: false, dataIndex: 'serverId', xtype: 'templatecolumn', tpl:
                    '<tpl if="serverId"><a href="#/servers/{serverId}/dashboard">{serverId}</a> <tpl if="instanceId">({instanceId})</tpl><tpl else>Not running</tpl>'
                }, {
                    header: 'Elastic IP', width: 250, sortable: false, dataIndex: 'elasticIp', editable: true, tdCls: 'x-grid-cell-editable',
                    renderer: function(value, metadata, record) {
                        metadata.tdAttr = 'title="Click here to change"';
                        metadata.style = 'line-height: 16px; padding-top: 4px; padding-bottom: 2px';

                        if (value == '0.0.0.0')
                            value = 'Allocate new';
                        else if (!value)
                            value = 'Not allocated yet';

                        value = '<span style="float: left">' + value + '</span>';

                        if (record.get('warningInstanceIdDoesntMatch'))
                            value += '<div style="margin-left: 5px; float: left; height: 15px; width: 16px; background-image: url(/ui2/images/icons/warning_icon_16x16.png)" title="This IP address is out of sync and associated with another instance on EC2">&nbsp;</div>'

                        return value;
                    },
                    editor: {
                        xtype: 'combobox',
                        forceSelection: true,
                        editable: false,
                        displayField: 'ipAddress',
                        valueField: 'ipAddress',
                        matchFieldWidth: false,
                        store: {
                            proxy: 'object',
                            fields: ['ipAddress', {name: 'instanceId', defaultValue: null}, {name: 'farmName', defaultValue: null} , 'roleName', 'serverIndex', 'fieldInstanceId']
                        },
                        displayTpl: '<tpl for="."><tpl if="values.ipAddress == \'0.0.0.0\'">Allocate new<tpl else>{[values.ipAddress]}</tpl></tpl>',
                        listConfig: {
                            minWidth: 250,
                            cls: 'x-boundlist-alt',
                            tpl: '<tpl for="."><div class="x-boundlist-item" style="font: bold 13px arial; height: auto; padding: 5px;">' +
                                    '<tpl if="ipAddress == \'0.0.0.0\'"><span>Allocate new</span>' +
                                    '<tpl elseif="ipAddress != \'\'">' +
                                        '<tpl if="!fieldInstanceId">' +
                                            '<tpl if="farmName"><span style="color: #F90000">{ipAddress}</span>' +
                                            '<tpl else><span style="color: #138913">{ipAddress}</span> (free)</tpl>' +
                                        '<tpl else><span>{ipAddress}</span></tpl>' +
                                    '<tpl else>Not allocated yet</tpl>' +
                                    '<tpl if="ipAddress && farmName"><br /><span style="font-weight: normal">used by: {farmName} &rarr; {roleName} # {serverIndex}</span></tpl>' +
                                '</div></tpl>'
                        }
                    }
                }
            ]
        }]
    }]

});

Ext.define('Scalr.ui.FarmRoleNetworkEc2', {
	extend: 'Ext.container.Container',
    alias: 'widget.farmrolenetworkec2',
    tabData: null,
    itemId: 'ec2',

    beforeShowTab: function (record, handler) {
        this.vpc = this.up('#farmDesigner').getVpcSettings();
        this.isVpcRouter = Ext.Array.contains(this.up('#network').currentRole.get('behaviors').split(','), 'router');
        Scalr.CachedRequestManager.get('farmDesigner').load(
            {
                url: '/platforms/ec2/xGetPlatformData',
                params: {
                    cloudLocation: record.get('cloud_location'),
                    farmRoleId: record.get('new') ? '' : record.get('farm_role_id'),
                    vpcId: this.vpc ? this.vpc.id : null
                }
            },
            function(data, status){
                if (!status) {
                    this.up().deactivateTab();
                } else {
                    this.tabData = data;
                    //elb list
                    Scalr.CachedRequestManager.get('farmDesigner').load(
                        {
                            url: '/tools/aws/ec2/elb/xListElasticLoadBalancers',
                            params: {
                                cloudLocation: record.get('cloud_location'),
                                placement: this.vpc ? this.vpc.id : 'ec2'
                            }
                        },
                        function(data, status) {
                            if (!status) {
                                this.up().deactivateTab();
                            } else {
                                this.tabData['elb'] = data;
                                if (this.vpc !== false) {
                                    var store = this.down('[name="aws.vpc_subnet_id"]').store;
                                    //we must to preload subnets here
                                    store.on('load', function(){handler();}, store, {single: true});
                                    store.getProxy().params = {
                                        cloudLocation: this.vpc.region,
                                        vpcId: this.vpc.id,
                                        extended: 1
                                    };
                                    store.load();
                                } else {
                                    handler();
                                }
                            }
                        },
                        this,
                        0
                    );
                }
            },
            this,
            0
        );
    },

    showTab: function (record) {
        var me = this,
            settings = record.get('settings', true),
            eipsData = me.tabData['eips'],
            limits = Scalr.getGovernance('ec2'),
            value,
            field, eipsFieldset, vpcFieldset;

        me.isLoading = true;
        me.suspendLayouts();

        me.down('#vpcoptions').hide();
        me.down('#subnetsWarning').hide();

        eipsFieldset = me.down('#elasticIps');
        eipsFieldset.down('[name="aws.use_elastic_ips"]').setReadOnly(false);
        vpcFieldset = me.down('#vpcoptions');
        if (me.vpc !== false) {
            var subnetId = Ext.decode(settings['aws.vpc_subnet_id']);
            subnetId = Ext.isArray(subnetId) ? subnetId : (settings['aws.vpc_subnet_id'] ? [settings['aws.vpc_subnet_id']] : null)

            field = me.down('[name="router.scalr.farm_role_id"]');
            Ext.apply(field.store.getProxy(), {
                params: {vpcId: me.vpc.id}
            });
            field.setValue(settings['router.scalr.farm_role_id']);
            field.isFieldAvailable = me.moduleTabParams['scalr.instances_connection_policy'] !== 'local';
            if (settings['router.scalr.farm_role_id']) {
                field.store.load();
            }

            field = me.down('[name="aws.vpc_subnet_id"]');
            field.isVpcRouter = me.isVpcRouter;
            field.maxCount = me.isVpcRouter ? 1 : 0;

            field.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + me.vpc.region + '&vpcId=' + me.vpc.id;
            field.getPlugin('comboaddnew').setDisabled(limits['aws.vpc'] && limits['aws.vpc']['ids'] && Ext.isArray(limits['aws.vpc']['ids'][me.vpc.id]));
            //field.store.loadData(Ext.Array.map(subnetId || [], function(id){return {id: id, description: id}}));
            field.setValue(subnetId);

            field = me.down('[name="router.vpc.networkInterfaceId"]');
            field.store.proxy.params = {
                cloudLocation: me.vpc.region,
                vpcId: me.vpc.id,
                subnetId: subnetId
            };
            field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(field.store.proxy.params);
            field.setVisible(me.isVpcRouter);
            if (me.isVpcRouter) {
                field.setValue(settings['router.vpc.networkInterfaceId']);
                if (settings['router.vpc.networkInterfaceId'] && settings['aws.vpc_subnet_id']) {
                    field.store.load();
                }
            }


            vpcFieldset.setTitle(vpcFieldset.baseTitle + (limits['aws.vpc']?'&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' + Ext.String.htmlEncode(Scalr.strings['farmbuilder.vpc.enforced']) + '" class="x-icon-governance" />':''));
            vpcFieldset.show();
        } else {
            vpcFieldset.hide();
        }

        //elastic IPs
        eipsFieldset.setVisible(!me.isVpcRouter);
        eipsFieldset.down('grid').onScalingEnabledChange(settings['scaling.enabled']);
        var elasticIpMap = {},
            elasticIps = Ext.isArray(eipsData['ips']) ? Ext.clone(eipsData['ips']) : [];
        elasticIps.unshift({ipAddress: '0.0.0.0'});

        if (settings['aws.elastic_ips.map']) {
            Ext.each((settings['aws.elastic_ips.map']).split(';'), function(value) {
                if (value) {
                    value = value.split('=');
                    if (value.length === 2) {
                        elasticIpMap[value[0]*1] = {
                            serverIndex: value[0]*1,
                            elasticIp: value[1] || '0.0.0.0'
                        };
                    }
                }
            });
        }
        if (settings['aws.private_ips.map']) {
            Ext.Object.each(Ext.decode(settings['aws.private_ips.map'], true) || {}, function(serverIndex, privateIp) {
                elasticIpMap[serverIndex*1] = {
                    serverIndex: serverIndex*1,
                    privateIp: privateIp || ''
                };
            });
        }
        if (Ext.isArray(eipsData.map)) {
            Ext.each(eipsData.map, function(value) {
                if (value['elasticIp'] || value['serverId']) {
                    elasticIpMap[value['serverIndex']*1] = Ext.applyIf(elasticIpMap[value['serverIndex']*1] || {}, value);
                }
            });
        } else {
            Ext.Object.each(eipsData.map, function(key, value) {
                if (value['elasticIp'] || value['serverId']) {
                    elasticIpMap[value['serverIndex']*1] = Ext.applyIf(elasticIpMap[value['serverIndex']*1] || {}, value);
                }
            });
        }
        //fill the gaps between indexes
        if (Ext.Object.getSize(elasticIpMap)) {
            var maxIndex = Ext.Array.max(Ext.Object.getKeys(elasticIpMap)) * 1;
            for (var i = 1; i <= maxIndex; i++) {
                if (elasticIpMap[i] === undefined) {
                    elasticIpMap[i] = {
                        serverIndex: i,
                        elasticIp: '0.0.0.0'
                    };
                }
            }
        }
        elasticIpMap = Ext.Object.getValues(elasticIpMap);
        Ext.Array.sort(elasticIpMap, function(a, b){return Ext.Array.numericSortFn(a.serverIndex, b.serverIndex)});
        if (settings['scaling.enabled'] == 1) {
            var maxInstances = settings['scaling.max_instances'] || 1;
            if (elasticIpMap.length > maxInstances) {
                var removeIndex = maxInstances;
                for (var i=elasticIpMap.length-1; i>=0; i--) {
                    removeIndex = i + 1;
                    if (elasticIpMap[i]['serverId'] || removeIndex == maxInstances) {
                        break;
                    }
                }
                elasticIpMap.splice(removeIndex, elasticIpMap.length - removeIndex);
            } else if (elasticIpMap.length < maxInstances) {
                for (var i = elasticIpMap.length; i < maxInstances; i++)
                    elasticIpMap.push({ serverIndex: i + 1 });
            }
        }

        eipsFieldset.down('[name="aws.use_elastic_ips"]').setValue(settings['aws.use_elastic_ips'] == 1);
        field = this.down('[name="aws.elastic_ips.map"]');
        field.view['isVpcEnabled'] = me.vpc !== false;
        field['ipAddressEditorIps'] = elasticIps;
        field.store.load({ data: elasticIpMap });
        this.down('[name="aws.elastic_ips.warning"]').hide();

        //elb
        var elbIdField = this.down('[name="aws.elb.id"]'),
            farmRoleId = record.get('farm_role_id'),
            elbFieldset = this.down('[name="aws.elb"]');
        if (!me.isVpcRouter) {
            if (settings['lb.use_elb'] == 1) {//load settings from old balancing tab
                settings['aws.elb.id'] = settings['lb.name'];
                settings['aws.elb.enabled'] = settings['lb.use_elb'];
                settings['lb.use_elb'] = 0;
                record.set('settings', settings);
            }
            //store original elb id
            var originalElbIds = this.up('#network').originalElbIds;
            if (farmRoleId && originalElbIds[farmRoleId] === undefined) {
                originalElbIds[farmRoleId] = settings['aws.elb.id'] || null;
            }

            elbFieldset[settings['aws.elb.enabled'] == 1 ? 'expand' : 'collapse']();
            elbFieldset.show();
            elbIdField.store.loadData(this.tabData['elb'] || []);
            elbIdField.setValue(settings['aws.elb.id'] || '');
            elbIdField.scalrParams = {
                farmId: this.moduleTabParams['farmId'],
                roleId: record.get('role_id')
            };
            elbIdField.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + record.get('cloud_location') + (this.vpc ? '&vpcId=' + this.vpc.id : '');
            elbIdField.getPlugin('comboaddnew').setDisabled(!Scalr.isAllowed('AWS_ELB', 'manage'));
        } else {
            elbFieldset.hide();
        }

        this.isLoading = false;

        if (!me.isVpcRouter) {
            this.toggleElbRemoveWarning(settings['aws.elb.remove']);
        }

        this.resumeLayouts(true);
    },

    hideTab: function (record) {
        var me = this,
            settings = record.get('settings'),
            eipsFieldset = me.down('#elasticIps'),
            elbFieldset = me.down('[name="aws.elb"]'),
            field, value;

        if (me.vpc !== false) {
            value = me.down('[name="aws.vpc_subnet_id"]').getValue();
            settings['aws.vpc_subnet_id'] = Ext.encode(value);
            field = me.down('[name="router.scalr.farm_role_id"]');
            settings['router.scalr.farm_role_id'] = field.isVisible() ? field.getValue() : null;

            field = me.down('[name="router.vpc.networkInterfaceId"]');
            settings['router.vpc.networkInterfaceId'] = field.isVisible() ? field.getValue() : null;
        }

        //elastic IPs
        if (eipsFieldset.isVisible()) {
            var privateIpsMap = {};
            settings['aws.use_elastic_ips'] = eipsFieldset.down('[name="aws.use_elastic_ips"]').getValue() ? 1 : 0;
            settings['aws.elastic_ips.map'] = '';
            settings['aws.private_ips.map'] = '';
            field = me.down('[name="aws.elastic_ips.map"]');
            field.store.getUnfiltered().each(function(record) {
                var elasticIp = record.get('elasticIp') || '';
                elasticIp = elasticIp === '0.0.0.0' ? '' : elasticIp;
                if (settings['aws.use_elastic_ips'] == 1 && (!field.view.isVpcEnabled || !field.view.isPrivateSubnet)) {
                    settings['aws.elastic_ips.map'] += record.get('serverIndex') + '=' + elasticIp + ';';
                }
                if (field.view.isVpcEnabled && record.get('privateIp')) {
                    privateIpsMap[record.get('serverIndex')*1] = record.get('privateIp');
                }
            });
            if (Ext.Object.getSize(privateIpsMap)) {
                settings['aws.private_ips.map'] = Ext.encode(privateIpsMap);
            }
        } else {
            //vpc router
            settings['aws.use_elastic_ips'] = 0;
        }

        //elb
        settings['aws.elb.id'] = '';
        settings['aws.elb.enabled'] = 0;
        settings['aws.elb.remove'] = 0;

        if (elbFieldset.isVisible()) {
            if (!elbFieldset.collapsed) {
                var value = this.down('[name="aws.elb.id"]').getValue();
                settings['aws.elb.enabled'] = 1;
                settings['aws.elb.id'] = value;
            }
            if (this.down('#removeElb').isVisible()) {
                settings['aws.elb.remove'] = this.down('[name="aws.elb.remove"]').getValue() ? 1 : 0;
            }
        }
        this.down('[name="aws.elb"]').hide().collapse();
        this.down('[name="aws.elb.remove"]').setValue(false);
        this.down('#removeElb').hide();
        this.down('[name="aws.elb.id"]').reset();

        if (me.vpc !== false) {
            this.down('[name="aws.vpc_subnet_id"]').reset();
        }

        record.set('settings', settings);
    },

    toggleElbRemoveWarning: function(remove) {
        if (this.isLoading) return;
        var tab = this.up('#network'),
            farmRoleId = tab.currentRole.get('farm_role_id'),
            elbId = farmRoleId ? tab.originalElbIds[farmRoleId] : null,
            elbIdField = this.down('[name="aws.elb.id"]'),
            elbRemoveField = this.down('[name="aws.elb.remove"]'),
            rec;

        if (!Ext.isEmpty(elbId) && (elbIdField.getValue() != elbId || this.down('[name="aws.elb"]').collapsed)) {
            rec = elbIdField.findRecordByValue(elbId);
            this.down('#removeElb').show();
            if (remove !== undefined) {
                elbRemoveField.setValue(remove);
            }
            elbRemoveField.setElbHostname(rec ? rec.get('dnsName') : elbId);
        } else {
            this.down('#removeElb').hide();
            this.down('[name="aws.elb.remove"]').setValue(0);
        }
    },


    defaults: {
        anchor: '100%',
        defaults: {
            maxWidth: 820,
            labelWidth: 180,
            anchor: '100%'
        }
    },
    items: [{
        xtype: 'fieldset',
        title: '&nbsp',
        baseTitle: 'VPC subnet',
        itemId: 'vpcoptions',
        hidden: true,
        items:[{
            xtype: 'vpcsubnetfield',
            name: 'aws.vpc_subnet_id',
            flex: 1,
            maxWidth: 690,
            emptyText: 'Please select subnet(s)',
            requireSameSubnetType: true,
            listeners: {
                change: function(comp, value) {
                    var subnets = comp.getValue(),
                        networkType,
                        form = this.up('#ec2'),
                        field,
                        warnings = [];

                    Ext.Array.each(subnets, function(subnet) {
                        var record = comp.findRecordByValue(subnet);
                        if (record) {
                            networkType = record.get('type');
                        }
                        return !networkType;
                    });
                    form.down('#subnetsWarning').setVisible(subnets && subnets.length > 1);

                    var networkTypeField = form.down('#networkType');
                    networkTypeField.setValue(networkType ? Ext.String.capitalize(networkType) : '');
                    networkTypeField.setVisible(!!networkType);
                    networkTypeField.updateIconTooltip('info', networkType === 'public' ? Scalr.strings['vpc.public_subnet.info'] : Scalr.strings['vpc.private_subnet.info']);

                    field = form.down('[name="aws.use_elastic_ips"]');
                    field.setReadOnly(networkType === 'private');
                    if (networkType === 'private') {
                        field.setValue(false);
                    }

                    field = form.down('[name="aws.elastic_ips.map"]');
                    field.view['isPrivateSubnet'] = networkType === 'private';
                    field.view.refresh();

                    field = form.down('[name="router.scalr.farm_role_id"]');
                    if (field.isFieldAvailable) {
                        field.setVisible(networkType === 'private');
                        field.allowBlank = networkType !== 'private';
                        field.validate();
                    }

                    var interfaceIdField = field.next('[name="router.vpc.networkInterfaceId"]');
                    if (interfaceIdField.isVisible() && !form.isLoading) {
                        interfaceIdField.reset();
                        interfaceIdField.updateEmptyText(true);
                        if (subnets.length > 0) {
                            interfaceIdField.store.proxy.params['subnetId'] = subnets[0];
                            interfaceIdField.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(interfaceIdField.store.proxy.params);
                        }
                    }
                }
            }
        },{
            xtype: 'displayfield',
            itemId: 'networkType',
            fieldLabel: 'Network type',
            labelWidth: 140,
            hidden: true,
            anchor: null,
            plugins: [{
                ptype: 'fieldicons',
                align: 'right',
                position: 'outer',
                icons: {
                    id: 'info',
                    tooltip: ''
                }
            }]
        },{
            xtype: 'combo',
            flex: 1,
            maxWidth: 690,
            name: 'router.scalr.farm_role_id',
            fieldLabel: 'Scalr VPC Router',
            editable: false,
            labelWidth: 140,
            hidden: true,

            queryCaching: false,
            clearDataBeforeQuery: true,
            store: {
                fields: ['farm_role_id', 'nid', 'ip', 'farm_name', 'role_name', {name: 'description', convert: function(v, record){
                    return record.data.farm_name + ' -> ' + record.data.role_name + ' -> ' + record.data.ip + ' (' + record.data.nid + ')';
                }} ],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'farmDesigner',
                    url: '/tools/aws/vpc/xListScalrRouters'
                }
            },
            valueField: 'farm_role_id',
            displayField: 'description',
            updateEmptyText: function(type){
                this.emptyText =  type ? 'Please select a VPC Router' : 'No VPC Router found in this VPC';
                this.applyEmptyText();
            },
            listeners: {
                afterrender: function(){
                    var me = this;
                    me.updateEmptyText(true);
                    me.store.on('load', function(store, records, result){
                        me.updateEmptyText(records && records.length > 0);
                    });
                }
            }
        },{
            xtype: 'vpcnetworkinterfacefield',
            name: 'router.vpc.networkInterfaceId',
            flex: 1,
            maxWidth: 690,
            labelWidth: 140,
            hidden: true,
            allowBlank: false
        },{
            xtype: 'displayfield',
            itemId: 'subnetsWarning',
            margin: 0,
            flex: 1,
            maxWidth: 690,
            hidden: true,
            cls: 'x-form-field-info',
            value: 'If more than one subnet is selected Scalr will evenly distribute instances across each subnet'
        }]
    },{
        xtype: 'fieldset',
        title: 'Use <a target="_blank" href="http://aws.amazon.com/elasticloadbalancing/">Amazon Elastic Load Balancer</a> to balance load between instances of this role',
        name: 'aws.elb',
        checkboxToggle: true,
        collapsed: true,
        defaults: {
            anchor: '100%',
            labelWidth: 130
        },
        items: [{
            xtype: 'combo',
            store: {
                fields: [ 'name', 'dnsName', {name: 'used', defaultValue: null}, 'farmId', 'farmName', 'roleId', 'roleName', 'vpcId', 'availZones', 'subnets' ],
                proxy: 'object'
            },
            flex: 1,
            maxWidth: 690,
            valueField: 'name',
            displayField: 'dnsName',
            restoreValueOnBlur: true,
            name: 'aws.elb.id',
            allowBlank: false,
            emptyText: 'Please select ELB',
            queryMode: 'local',
            anyMatch: true,
            listConfig: {
                cls: 'x-boundlist-alt',
                style: 'white-space:nowrap',
                tpl:
                    '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto;line-height:20px"><b>{dnsName}</b> <i>('+
                    '<tpl if="!used">'+
                        '<span style="color: #138913">Not used in Scalr</span>'+
                    '<tpl else>'+
                        '<span style="color:orange">Used by {farmName} -> {roleName}</span>' +
                    '</tpl></i>)' +
                    '<tpl if="availZones && availZones.length&gt;0"><div>Availability zones: <b>{[values.availZones.join(\', \')]}</b></div></tpl>' +
                    '<tpl if="subnets && subnets.length&gt;0"><div>Subnets: <b>{[values.subnets.join(\', \')]}</b></div></tpl>' +
                    '</div></tpl>'
            },
            plugins: [{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/aws/ec2/elb/create'
            }],
            listeners: {
                addnew: function(item) {
                    var tab = this.up('#ec2');
                    Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                        url: '/tools/aws/ec2/elb/xListElasticLoadBalancers',
                        params: {
                            cloudLocation: tab.ownerCt.currentRole.get('cloud_location'),
                            placement: tab.vpc ? tab.vpc.id : 'ec2'
                        }
                    });
                },
                change: function() {
                    var r = this.findRecord(this.valueField, this.getValue()),
                        zones, subnets;
                    if (r && r.get('farmId') && r.get('roleId') && r.get('farmId') == this.scalrParams.farmId && r.get('roleId') == this.scalrParams.roleId) {
                        this.up().next().show();
                    } else {
                        this.up().next().hide();
                    }
                    if (r) {
                        zones = r.get('availZones');
                        subnets = r.get('subnets');
                    }
                    if (zones && zones.length) {
                        this.next('#zones').show().setValue(zones.join(', '));
                    } else {
                        this.next('#zones').hide();
                    }
                    if (subnets && subnets.length) {
                        this.next('#subnets').show().setValue(subnets.join(', '));
                    } else {
                        this.next('#subnets').hide();
                    }
                    this.up('#ec2').toggleElbRemoveWarning();
                }
            }
        },{
            xtype: 'displayfield',
            fieldLabel: 'Availability zones',
            itemId: 'zones',
            hidden: true
        },{
            xtype: 'displayfield',
            fieldLabel: 'Subnets',
            itemId: 'subnets',
            hidden: true
        }],
        listeners: {
            collapse: function() {
                this.up('#ec2').toggleElbRemoveWarning();
            },

            expand: function() {
                this.up('#ec2').toggleElbRemoveWarning();
            }
        }
    }, {
        xtype: 'fieldset',
        itemId: 'removeElb',
        hidden: true,
        items: [{
            xtype: 'checkbox',
            name: 'aws.elb.remove',
            setElbHostname: function(hostname) {
                this.setBoxLabel('Check to remove <b>'+hostname+'</b> ELB from cloud after saving farm');
            },
            boxLabel: 'remove'
        }]
    }, {
        xtype: 'container',
        itemId: 'elasticIps',
        cls: 'x-container-fieldset',
        style: 'padding-top:12px',
        items: [{
            xtype: 'checkbox',
            name: 'aws.use_elastic_ips',
            boxLabel: 'Assign one <b>ElasticIP</b> per instance',
            plugins: [{
                ptype: 'fieldicons',
                icons: [{
                    id: 'info',
                    tooltip: 'Enable to have Scalr automatically assign an ElasticIP to each instance of this role (this requires a few minutes during which the instance is unreachable from the public internet) after HostInit but before HostUp. If out of allocated IPs, Scalr will request more, but never remove any.'
                },{
                    id: 'question',
                    hidden: true,
                    tooltip: 'ElasticIPs are not available with private subnets.'
                }]
            }],
            listeners: {
                writeablechange: function(comp, readOnly) {
                    if (this.rendered) {
                        this.toggleIcon('question', readOnly);
                        this.toggleIcon('info', !readOnly);
                    }
                },
                change: function(comp, value) {
                    var grid = this.up('#elasticIps').down('grid');
                    grid.view.useElasticIps = value;
                    grid.view.refresh();
                }
            }
        },{
            xtype: 'displayfield',
            cls: 'x-form-field-warning',
            anchor: '100%',
            name: 'aws.elastic_ips.warning',
            hidden: true,
            value: ''
        }, {
            xtype: 'grid',
            cls: 'x-grid-no-highlighting',
            name: 'aws.elastic_ips.map',
            features: {
                ftype: 'addbutton',
                text: 'Add server index',
                hidden: true,
                handler: function(view) {
                    var grid = view.up();
                    grid.store.add(grid.getStore().createModel({serverIndex: grid.store.getMaxServerIndex()+1, ipAddress: '0.0.0.0'}));
                }
            },
            plugins: [{
                ptype: 'cellediting',
                clicksToEdit: 1,
                listeners: {
                    beforeedit: function(comp, e) {
                        var editor = this.getEditor(e.record, e.column);
                        if (editor.field.xtype === 'combobox') {
                            if (!e.grid.view.useElasticIps) return false;
                            for (var i = 0, len = e.grid['ipAddressEditorIps'].length; i < len; i++) {
                                e.grid['ipAddressEditorIps'][i]['fieldInstanceId'] = e.record.get('instanceId') && (e.grid['ipAddressEditorIps'][i]['instanceId'] == e.record.get('instanceId'));
                            }
                            editor.field.store.load({ data: e.grid['ipAddressEditorIps'] });
                        } else {
                            if (!e.grid.view.isVpcEnabled) return false;

                        }
                    },
                    edit: function(comp, e) {
                        var editor = this.getEditor(e.record, e.column);
                        if (editor.field.xtype === 'combobox') {
                            if (e.record.get('elasticIp') !== '0.0.0.0') {
                                var r = editor.field.store.findRecord('ipAddress', e.record.get('elasticIp'));
                                if (r && r.get('instanceId') && r.get('instanceId') != e.record.get('instanceId') && r.get('ipAddress') != e.record.get('remoteIp'))
                                    e.grid.up('[tab="tab"]').down('[name="aws.elastic_ips.warning"]').setValue(
                                        'IP address \'' + e.record.get('elasticIp') + '\' is already in use, and will be re-associated with selected server. IP address on old server will revert to dynamic IP.'
                                    ).show();
                                else
                                    e.grid.up('[tab="tab"]').down('[name="aws.elastic_ips.warning"]').hide();
                            }
                        }
                    }
                }
            }],
            viewConfig: {
                disableSelection: true
            },
            onScalingEnabledChange: function(scalingEnabled) {
                this.view.findFeature('addbutton').setVisible(scalingEnabled != 1);
                this.columns[4].setVisible(scalingEnabled != 1);
            },
            store: {
                proxy: 'object',
                fields: [ {name: 'elasticIp', defaultValue: '0.0.0.0'}, 'instanceId', 'serverId', {name: 'serverIndex', type: 'int'}, 'remoteIp', 'warningInstanceIdDoesntMatch', 'privateIp' ],
                getMaxServerIndex: function() {
                    var maxServerIndex = 0;
                    this.getUnfiltered().each(function(record){
                        var serverIndex = record.get('serverIndex');
                        maxServerIndex = maxServerIndex > serverIndex ? maxServerIndex : serverIndex;
                    });
                    return maxServerIndex;
                }
            },
            columns: [
                { header: 'Server Index', width: 125, sortable: false, dataIndex: 'serverIndex' },
                { header: 'Server ID', flex: 1, sortable: false, dataIndex: 'serverId', xtype: 'templatecolumn', tpl:
                    '<tpl if="serverId"><a href="#/servers/{serverId}/dashboard">{serverId}</a> <tpl if="instanceId">({instanceId})</tpl><tpl else>Not running</tpl>'
                }, {
                    header: 'Public IP', width: 180, sortable: false, dataIndex: 'elasticIp', editable: true, tdCls: 'x-grid-cell-editable',
                    renderer: function(value, metadata, record, rowIndex, colIndex, store, view) {
                        metadata.style = 'padding-left:7px;';
                        if (view.useElasticIps) {
                            metadata.tdAttr = 'title="Click here to change"';

                            if (value === '0.0.0.0' || !value)
                                value = 'Allocate new';

                            if (record.get('warningInstanceIdDoesntMatch'))
                                value += ' <img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-warning" data-qtip="This IP address is out of sync and associated with another instance on EC2"/>'
                        } else {
                            value = view.isVpcEnabled && view.isPrivateSubnet ? '&mdash;' : 'Ephemeral IP';
                            metadata.style += 'background:transparent;';
                        }
                        return value;
                    },
                    editor: {
                        xtype: 'combobox',
                        forceSelection: true,
                        editable: false,
                        displayField: 'ipAddress',
                        valueField: 'ipAddress',
                        matchFieldWidth: false,
                        store: {
                            proxy: 'object',
                            fields: ['ipAddress', {name: 'instanceId', defaultValue: null}, {name: 'farmName', defaultValue: null} , 'roleName', 'serverIndex', 'fieldInstanceId']
                        },
                        listeners: {
                            beforeselect: function(field, record) {
                                var value = record.get('ipAddress');
                                if (field.getPicker().isVisible() && value !== '0.0.0.0') {
                                    var rec;
                                    field.ownerCt.grid.store.getUnfiltered().each(function(record){
                                        if (record.get('elasticIp') === value) {
                                            rec = record;
                                            return false;
                                        }
                                    });
                                    if (rec) Scalr.message.InfoTip('ElasticIP ' + value + ' is already assigned to Server #' + rec.get('serverIndex'), field.inputEl, {anchor: 'bottom'});
                                    return !rec;
                                }
                            },
                        },
                        displayTpl: '<tpl for="."><tpl if="ipAddress==\'0.0.0.0\'">Allocate new<tpl else>{[values.ipAddress]}</tpl></tpl>',
                        listConfig: {
                            minWidth: 250,
                            cls: 'x-boundlist-alt',
                            tpl: '<tpl for="."><div class="x-boundlist-item" style="height: auto;">' +
                                    '<tpl if="ipAddress==\'0.0.0.0\'">' +
                                        'Allocate new' +
                                    '<tpl else>' +
                                        '<tpl if="!fieldInstanceId">' +
                                            '<tpl if="farmName || instanceId"><span style="color: #F90000">{ipAddress}</span>' +
                                            '<tpl else><span style="color: #138913">{ipAddress}</span> (free)</tpl>' +
                                        '<tpl else>' + '\
                                            {ipAddress}' +
                                        '</tpl>' +
                                    '</tpl>' +
                                    '<tpl if="ipAddress && farmName"><div style="font-size:90%;margin-top:6px;">used by: {farmName} &rarr; {roleName} # {serverIndex}</div></tpl>' +
                                    '<tpl if="ipAddress && !farmName && instanceId"><div style="font-size:90%;margin-top:6px;">used by: {instanceId}</div></tpl>' +
                                '</div></tpl>'
                        }
                    }
                }, {
                    header: 'Private IP', width: 180, sortable: false, dataIndex: 'privateIp', editable: true, tdCls: 'x-grid-cell-editable',
                    renderer: function(value, metadata, record, rowIndex, colIndex, store, view) {
                        metadata.style = 'padding-left:6px;';
                        if (view.isVpcEnabled) {
                            metadata.tdAttr = 'title="Click here to change"';
                        } else {
                            metadata.tdAttr = 'data-qtip="You can assign custom private IPs only for instances inside VPC"';
                            metadata.style += 'background:transparent;';
                        }
                        return view.isVpcEnabled ? value || 'Auto-assign' : 'Auto-assign';
                    },
                    editor: {
                        xtype: 'textfield',
                        emptyText: 'Auto-assign',
                        vtype: 'ip',
                        validator: function(value) {
                            if (value) {
                                var rec,
                                    currentRecord = this.ownerCt.grid.findPlugin('cellediting').getActiveRecord();
                                this.ownerCt.grid.store.getUnfiltered().each(function(record){
                                    if (record !== currentRecord && record.get('privateIp') === value) {
                                        rec = record;
                                        return false;
                                    }
                                });
                                if (rec) return 'Private IP ' + value + ' is already assigned to Server #' + rec.get('serverIndex');
                            }
                            return true;
                        },
                        listeners: {
                            focus: function() {
                                if (this.getValue() === 'Auto-assign') {
                                    this.setValue('');
                                }
                            }
                        }
                    }
                }, {
                    width: 42,
                    sortable: false,
                    hidden: true,
                    align:'left',
                    renderer: function(value, meta, record, rowIndex, colIndex, store, grid) {
                        var serverIndex = record.get('serverIndex'),
                            ipAddress = record.get('ipAddress');
                        return  store.getMaxServerIndex() == serverIndex && !record.get('serverId') && !record.get('serverId') && (ipAddress == '0.0.0.0' || !ipAddress)
                                ? '<img class="x-grid-icon x-grid-icon-delete" title="Delete server index" src="'+Ext.BLANK_IMAGE_URL+'"/>'
                                : '';
                    }

                }
            ],
            listeners: {
                viewready: function() {
                    var me = this;
                    me.store.on('remove', me.view.refresh, me.view);
                    me.store.on('add', me.view.refresh, me.view);
                },
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('img.x-grid-icon-delete')) {
                        view.store.remove(record);
                        return false;
                    }
                }
            }
        }]
    }]
});

Ext.define('Scalr.ui.FarmRoleNetworkAzure', {
	extend: 'Ext.container.Container',
    alias: 'widget.farmrolenetworkazure',
    itemId: 'azure',

    beforeShowTab: function (record, handler) {
        var settings = record.get('settings', true);
        this.proxyParams = {
            resourceGroup: settings['azure.resource-group'],
            cloudLocation: record.get('cloud_location')
        };
        Scalr.cachedRequest.load(
            {
                url: '/platforms/azure/xGetOptions',
                params: this.proxyParams
            },
            function(data, status){
                if (status) {
                    this.tabData = data;
                    handler();
                } else {
                    this.up().deactivateTab();
                }
            },
            this
        );
    },

    showTab: function (record) {
        var settings = record.get('settings', true),
            data = this.tabData,
            field;
        //azure network
        field = this.down('[name="azure.virtual-network"]');
        field.store.load({data: data['virtualNetworks'] || []});
        field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(this.proxyParams);
        if (settings['azure.virtual-network']) {
            field.setValue(settings['azure.virtual-network']);
        } else {
            field.reset();
        }
        field = this.down('[name="azure.subnet"]');
        field.setValue(settings['azure.subnet']||'');

        this.down('[name="azure.use_public_ips"]').setValue(settings['azure.use_public_ips'] == 1);

    },

    hideTab: function (record) {
        var settings = record.get('settings');
        settings['azure.virtual-network'] = this.down('[name="azure.virtual-network"]').getValue();
        settings['azure.subnet'] = this.down('[name="azure.subnet"]').getValue();
        settings['azure.use_public_ips'] = this.down('[name="azure.use_public_ips"]').getValue() ? 1 : 0;
        record.set('settings', settings);
    },

    items: [{
        xtype: 'fieldset',
        cls: 'x-fieldset-separator-none',
        layout: 'anchor',
        defaults: {
            anchor: '100%',
            maxWidth: 600,
            labelWidth: 120
        },
        items: [{
            xtype: 'combo',
            name: 'azure.virtual-network',
            fieldLabel: 'Virtual network',
            editable: false,
            queryMode: 'local',
            allowBlank: false,
            store: {
                model: Scalr.getModel({fields: [ 'id', 'name', 'subnets' ]}),
                proxy: 'object'
            },
            valueField: 'id',
            displayField: 'name',
            plugins: [{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/azure/virtualNetworks/create'
            }],
            listeners: {
                addnew: function(item) {
                    Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                        url: '/platforms/azure/xGetOptions',
                        params: this.up('#azure').proxyParams
                    });
                },
                change: function(comp, value){
                    var rec = comp.findRecordByValue(value),
                        field = comp.next('[name="azure.subnet"]');
                    field.reset();
                    if (value && rec) {
                        field.show();
                        field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(comp.up('#azure').proxyParams) + '&virtualNetwork=' + value;
                        field.store.load({data: rec.get('subnets')});
                    } else {
                        field.hide();
                    }
                }
            }
        },{
            xtype: 'combo',
            name: 'azure.subnet',
            fieldLabel: 'Subnet',
            editable: false,
            hidden: true,
            queryMode: 'local',
            allowBlank: false,
            store: {
                model: Scalr.getModel({fields: [ 'id', 'name' ]}),
                proxy: 'object'
            },
            valueField: 'id',
            displayField: 'name',
            plugins: [{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/azure/virtualNetworks/createSubnet'
            }],
            updateEmptyText: function(isEmpty){
                this.emptyText =  isEmpty ? ' ' : 'No existing Subnets found';
                this.applyEmptyText();
            },
            listeners: {
                afterrender: function(){
                    var me = this;
                    me.store.on('load', function(store, records, result){
                        me.updateEmptyText(records && records.length > 0);
                    });
                },
                addnew: function(item) {
                    Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                        url: '/platforms/azure/xGetOptions',
                        params: this.up('#azure').proxyParams
                    });
                }
            }
        },{
            xtype: 'checkbox',
            name: 'azure.use_public_ips',
            boxLabel: 'Attach dynamic public IP to every instance of this Farm Role',
        }]
    }]
});
