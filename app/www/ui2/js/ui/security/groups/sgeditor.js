Ext.define('Scalr.ui.SecurityGroupEditor', {
	extend: 'Ext.form.Panel',
	alias: 'widget.sgeditor',
    vpcIdReadOnly: false,
    layout: 'fit',
    initComponent: function () {
        var me = this;

        me.callParent(arguments);

        me.down('#view').setVisible(me.platform !== 'rds');
    },

    items: {
        xtype: 'fieldset',
        itemId: 'formtitle',
        cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
        title: '&nbsp;',
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        defaults: {
            //anchor: '100%',
            labelWidth: 120
        },
        items: [{
            xtype: 'hidden',
            submitValue: false,
            name: 'advanced'
        }, {
            xtype: 'hidden',
            name: 'platform'
        }, {
            xtype: 'hidden',
            name: 'cloudLocation'
        }, {
            xtype: 'hidden',
            name: 'resourceGroup'//azure only
        }, {
            xtype: 'textfield',
            name: 'id',
            fieldLabel: 'ID',
            hideInputOnReadOnly: true,
            readOnly: true
        }, {
            xtype: 'textfield',
            name: 'name',
            fieldLabel: 'Name',
            hideInputOnReadOnly: true,
            allowBlank: false
        }, {
            xtype: 'textfield',
            name: 'description',
            fieldLabel: 'Description',
            hideInputOnReadOnly: true,
            allowBlank: false
        }, {
            xtype: 'combo',
            name: 'vpcId',
            fieldLabel: 'VPC ID',
            editable: false,
            hideInputOnReadOnly: true,

            queryCaching: false,
            clearDataBeforeQuery: true,
            store: {
                fields: [ 'id', 'name' ],
                proxy: {
                    type: 'cachedrequest',
                    url: '/platforms/ec2/xGetVpcList',
                    root: 'vpc',
                    ttl: 1
                }
            },
            valueField: 'id',
            displayField: 'name',
            plugins: [{
                ptype: 'fieldicons',
                position: 'outer',
                icons: ['governance']
            },{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/aws/vpc/create'
            }]
        }, {
            xtype: 'displayfield',
            name: 'cloudLocationName',
            fieldLabel: 'Cloud location'
        },{
            xtype: 'grid',
            itemId: 'view',
            trackMouseOver: false,
            disableSelection: !Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage'),
            selModel: Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage') ? 'selectedmodel' : null,
            flex: 1,
            store: {
                proxy: 'object',
                model: Scalr.getModel({fields: ['id', 'type', 'direction', 'ipProtocol', 'fromPort', 'toPort', 'sourceType', 'sourceValue', 'comment', 'priority']}),
                getMaxPriority: function() {
                    var maxPriority = 100;
                    this.getUnfiltered().each(function(record){
                        var priority = record.get('priority')*1;
                        maxPriority = priority > maxPriority ? priority : maxPriority;
                    });
                    maxPriority += 10;
                    return maxPriority;
                }
            },
            plugins: {
                ptype: 'gridstore'
            },
            features: [{
                ftype: 'grouping',
                id: 'groupingByType',
                hideGroupedHeader: true,
                disabled: true,
                groupHeaderTpl: '{[Ext.String.capitalize(values.name)]} ({rows.length})'
            }],
            listeners: {
                selectionchange: function(selModel, selected) {
                    this.down('#delete').setDisabled(!selected.length);
                },
                boxready: function (grid) {
                    if (grid.prev('[name=platform]').getValue() === 'ec2') {
                        grid.groupByType();
                    }
                }
            },
            viewConfig: {
                emptyText: 'No security rules defined',
                deferEmptyText: false
            },
            groupByType: function () {
                var me = this;

                me.getStore().setGroupField('type');

                me.getView().getFeature('groupingByType').enable();

                return me;
            },
            maybeRefreshGrouping: function () {
                var me = this;

                var grouping = me.getView().getFeature('groupingByType');

                if (!grouping.disabled) {
                    me.suspendLayouts();

                    grouping.disable();
                    grouping.enable();

                    me.resumeLayouts(true);
                }

                return me;
            },
            columns: [{
                header: 'Protocol',
                width: 100,
                sortable: true,
                dataIndex: 'ipProtocol',
                xtype: 'templatecolumn',
                tpl: '{[!values.ipProtocol||values.ipProtocol==\'*\'?\'ANY\':Ext.util.Format.uppercase(values.ipProtocol)]}'
            },{
                header: 'Port range',
                width: 160,
                sortable: true,
                dataIndex: 'fromPort',
                xtype: 'templatecolumn',
                tpl:
                    '<tpl if="ipProtocol==\'icmp\'">' +
                        '<tpl if="fromPort==-1&&fromPort==toPort">' +
                            'ANY' +
                        '<tpl else>' +
                            '{[values.fromPort==-1?\'ANY\':values.fromPort]}<tpl if="toPort"> - {[values.toPort==-1?\'ANY\':values.toPort]}</tpl>'+
                        '</tpl>' +
                    '<tpl else>' +
                        '<tpl if="!fromPort&&!toPort||fromPort==\'*\'&&toPort==\'*\'">' +
                            'ANY' +
                        '<tpl elseif="!toPort">' +
                            '{fromPort}' +
                        '<tpl else>' +
                            '{fromPort} - {toPort}' +
                        '</tpl>' +
                    '</tpl>'
            },{
                header: 'Direction',
                width: 100,
                sortable: true,
                hidden: true,
                dataIndex: 'direction',
                xtype: 'templatecolumn',
                tpl: '<span style="text-transform:capitalize">{[values.direction||\'ingress\']}</span>'
            },{
                header: 'Priority',
                width: 100,
                sortable: true,
                hidden: true,
                dataIndex: 'priority'
            },{
                header: 'Source',
                width: 220,
                sortable: true,
                dataIndex: 'sourceValue',
                xtype: 'templatecolumn',
                tpl: '{[values.sourceValue==\'*\'?\'ANY\':Ext.util.Format.uppercase(values.sourceValue)]}'
            },{
                header: 'Comment',
                flex: 1,
                sortable: true,
                dataIndex: 'comment',
                renderer: function (value) {
                    return Ext.String.htmlEncode(value);
                }
            }],
            dockedItems: [{
                xtype: 'toolbar',
                ui: 'inline',
                dock: 'top',
                defaults: {
                    margin: '0 0 0 10'
                },
                hidden: !Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage'),
                items: [{
                    xtype: 'tbfill'
                }, {
                    itemId: 'add',
                    text: 'Add security rule',
                    cls: 'x-btn-green',
                    tooltip: 'Add security rule',
                    handler: function() {
                        var editor = this.up('sgeditor'),
                            accountId = editor.accountId,
                            remoteAddress = editor.remoteAddress,
                            platform = editor.down('[name="platform"]').getValue(),
                            advanced = editor.down('[name="advanced"]').getValue(),
                            cloudLocation = editor.down('[name="cloudLocation"]').getValue(),
                            ipProtocols,
                            grid = this.up('#view'),
                            store = grid.store,
                            showType = platform === 'ec2' && !Ext.isEmpty(editor.down('[name="vpcId"]').getValue());

                        ipProtocols = [{
                            text: 'TCP',
                            value: 'tcp'
                        },{
                            text: 'UDP',
                            value: 'udp'
                        }];
                        if (platform === 'azure') {
                                ipProtocols.push({
                                    text: 'ANY',
                                    value: '*'
                                });
                        } else {
                            if (!Scalr.isCloudstack(platform)) {
                                ipProtocols.push({
                                    text: 'ICMP',
                                    value: 'icmp'
                                });
                            }
                            if (Scalr.isOpenstack(platform)) {
                                ipProtocols.push({
                                    text: 'Other',
                                    value: 'other'
                                });
                            }
                        }
                        Scalr.Confirm({
							form: [{
                                xtype: 'fieldset',
                                title: 'New security rule',
                                cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
                                defaults: {
                                    anchor: '100%',
                                    labelWidth: !showType ? 75 : 85
                                },
                                items: [{
                                    xtype: 'textfield',
                                    name: 'priority',
                                    allowBlank: false,
                                    maxWidth: 165,
                                    fieldLabel: 'Priority',
                                    hidden: platform !== 'azure',
                                    disabled: platform !== 'azure',
                                    value: platform === 'azure' ? store.getMaxPriority() : '',
                                    validator: function (value) {
                                        if (value < 100 || value > 4096) {
                                            return 'The priority must be between 100 and 4096';
                                        }
                                        return true;
                                    }
                                },{
                                    xtype: 'buttongroupfield',
                                    name: 'type',
                                    fieldLabel: 'Type',
                                    labelWidth: !showType ? 75 : 85,
                                    layout: 'hbox',
                                    maxWidth: !showType ? 340 : 350,
                                    hidden: !showType,
                                    disabled: platform !== 'ec2',
                                    defaults: {
                                        flex: 1
                                    },
                                    items: [{
                                        text: 'Inbound',
                                        value: 'inbound'
                                    }, {
                                        text: 'Outbound',
                                        value: 'outbound'
                                    }],
                                    value: 'inbound',
                                    listeners: {
                                        change: function (field, value) {
                                            var inbound = value === 'inbound';
                                            var sourceTypeContainer = field.next('#sourceType');
                                            var sourceTypeField = sourceTypeContainer.down('[name=sourceType]');

                                            sourceTypeField.setFieldLabel(
                                                inbound ? 'Source' : 'Destination'
                                            );
                                        }
                                    }
                                },{
                                    xtype: 'fieldcontainer',
                                    layout: 'hbox',
                                    items: [{
                                        xtype: 'buttongroupfield',
                                        name: 'ipProtocol',
                                        fieldLabel: 'Protocol',
                                        value: 'tcp',
                                        labelWidth: !showType ? 75 : 85,
                                        layout: 'hbox',
                                        width: !showType ? 340 : 350,
                                        defaults: {
                                            flex: 1
                                        },
                                        items: ipProtocols,
                                        listeners: {
                                            change: function(comp, value) {
                                                comp.up('form').down('#portCt').setVisible(value !== 'other');
                                                comp.up('form').down('[name="otherProtocol"]').setVisible(value === 'other').setDisabled(value !== 'other');
                                                comp.up('form').down('[name="fromPort"]').setDisabled(value === 'other');
                                                comp.up('form').down('[name="toPort"]').setDisabled(value === 'other');
                                            }
                                        }
                                    },{
                                        xtype: 'textfield',
                                        name: 'otherProtocol',
                                        margin: '0 0 0 10',
                                        width: 58,
                                        submitValue: false,
                                        allowBlank: false,
                                        hidden: true,
                                        disabled: true,
                                        maxLength: 3,
                                        validator: function (value) {
                                            if (value < 1 || value > 255) {
                                                return 'Valid protocols are 1 through 255';
                                            }
                                            return true;
                                        }
                                    }]
                                }, {
                                    xtype: 'fieldcontainer',
                                    itemId: 'portCt',
                                    layout: 'hbox',
                                    items: [{
                                        xtype: 'buttongroupfield',
                                        fieldLabel: 'Port',
                                        submitValue: false,
                                        value: 'single',
                                        width: !showType ? 350 : 360,
                                        labelWidth: !showType ? 75 : 85,
                                        defaults: {
                                            width: 130
                                        },
                                        items: [{
                                            text: 'Single port',
                                            value: 'single'
                                        },{
                                            text: 'Port range',
                                            value: 'range'
                                        }],
                                        listeners: {
                                            change: function(comp, value) {
                                                var fromPort = comp.next('[name="fromPort"]'),
                                                    toPort = comp.next('[name="toPort"]');
                                                toPort.setVisible(value === 'range');
                                                if (value === 'single') {
                                                    toPort.setValue(fromPort.getValue());
                                                }
                                                fromPort.focus(true);
                                            }
                                        }
                                    },{
                                        xtype: 'textfield',
                                        name: 'fromPort',
                                        width: 58,
                                        allowBlank: false,
                                        validator: function (value) {
                                            if (value < -1 || value > 65535) {
                                                return 'Valid ports are - 1 through 65535';
                                            }
                                            return true;
                                        },
                                        listeners: {
                                            change: function(comp, value) {
                                                var field = comp.next('[name="toPort"]');
                                                if (field.hidden) {
                                                    field.setValue(value);
                                                }
                                            }
                                        }
                                    },{
                                        xtype: 'textfield',
                                        name: 'toPort',
                                        fieldLabel: '&ndash;',
                                        labelSeparator: '',
                                        labelWidth: 12,
                                        width: 76,
                                        margin: '0 0 0 8',
                                        hidden: true,
                                        allowBlank: false,
                                        validator: function (value) {
                                            if (value < -1 || value > 65535) {
                                                return 'Valid ports are - 1 through 65535';
                                            }
                                            return true;
                                        }
                                    }]
                                }, {
                                    xtype: 'buttongroupfield',
                                    name: 'direction',
                                    fieldLabel: 'Direction',
                                    value: 'ingress',
                                    disabled: !Scalr.isOpenstack(platform) || !advanced,
                                    hidden: !Scalr.isOpenstack(platform) || !advanced,
                                    defaults: {
                                        width: 120
                                    },
                                    items: [{
                                        text: 'Ingress',
                                        value: 'ingress'
                                    },{
                                        text: 'Egress',
                                        value: 'egress'
                                    }]
                                }, {
                                    xtype: 'fieldcontainer',
                                    itemId: 'sourceType',
                                    layout: 'hbox',
                                    items: [{
                                        xtype: 'buttongroupfield',
                                        fieldLabel: 'Source',
                                        name: 'sourceType',
                                        value: 'ip',
                                        width: !showType ? 350 : 360,
                                        labelWidth: !showType ? 75 : 85,
                                        defaults: {
                                            width: 130
                                        },
                                        items: [{
                                            text: 'CIDR IP',
                                            value: 'ip'
                                        },{
                                            text: 'Security group',
                                            value: 'sg',
                                            disabled: Scalr.isCloudstack(platform) || platform === 'azure'
                                        }],
                                        listeners: {
                                            change: function(comp, value) {
                                                var sourceValueField = comp.next('#sourceValue');
                                                var ip = value === 'ip';
                                                var ruleType = comp.up('#sourceType').prev('[name=type]').getValue();

                                                sourceValueField.setValue(ip ? (platform === 'azure' ? '*' : '0.0.0.0/0') : accountId + (platform === 'ec2' ? '/' : '/default'));

                                                if (Scalr.isOpenstack(platform)) {
                                                    if (ip) {
                                                        sourceValueField.show().enable();
                                                        comp.next('#sgSourceValue').hide().disable();
                                                    } else {
                                                        comp.next('#sgSourceValue').show().enable();
                                                        sourceValueField.hide().disable();
                                                    }
                                                }

                                                if (remoteAddress) {
                                                    comp.next('#myIp').setVisible(ip);
                                                }

                                                sourceValueField.disableValidation(ip);
                                            }
                                        }
                                    },{
                                        xtype: 'combo',
                                        itemId: 'sgSourceValue',
                                        name: 'sourceValue',
                                        editable: false,

                                        queryCaching: false,
                                        clearDataBeforeQuery: true,
                                        store: {
                                            fields: [ 'id', 'name' ],
                                            proxy: {
                                                type: 'cachedrequest',
                                                url: '/security/groups/xListGroups',
                                                ttl: 1,
                                                params: {
                                                    platform: platform,
                                                    cloudLocation: cloudLocation
                                                }
                                            }
                                        },
                                        valueField: 'id',
                                        displayField: 'name',
                                        flex: 1,
                                        allowBlank: false,
                                        hidden: true,
                                        disabled: true
                                    },{
                                        xtype: 'textfield',
                                        itemId: 'sourceValue',
                                        name: 'sourceValue',
                                        value: platform === 'azure' ? '*' : '0.0.0.0/0',
                                        flex: 1,
                                        allowBlank: false,
                                        disableValidation: function (disabled) {
                                            var me = this;

                                            me.regex = disabled ? null : new RegExp('/sg-');
                                            me.regexText = disabled ? '' : 'Valid format: {AWS account ID}/{securityGroupId}';

                                            me.validate();

                                            return me;
                                        }
                                    },{
                                        xtype: 'button',
                                        itemId: 'myIp',
                                        text: 'My IP',
                                        margin: '0 0 0 10',
                                        hidden: !remoteAddress,
                                        handler: function() {
                                            this.prev('[name="sourceValue"]').setValue(remoteAddress + '/32').focus(10);
                                        }
                                    }]
                                }, {
                                    xtype: 'textarea',
                                    name: 'comment',
                                    height: 60,
                                    fieldLabel: 'Comment',
                                    allowBlank: true
                                }]
							}],
                            formWidth: 640,
                            winConfig: {
                                autoScroll: false,
                                alignTop: true
                            },
							ok: 'Add',
							formValidate: true,
							closeOnSuccess: true,
							scope: this,
							success: function (formValues, form) {
								var view = this.up('#view'), store = view.store;
                                if (formValues['ipProtocol'] === 'other') {
                                    formValues['ipProtocol'] = form.getForm().findField('otherProtocol').getValue();
                                }
                                if (platform === 'azure') {
                                    if (store.findBy(function (record) {
                                        if (record.get('priority') == formValues.priority) {
                                            Scalr.message.Error('Priority must de unique');
                                            return true;
                                        }
                                    }) != -1) {
                                        return false;
                                    }
                                }
								if (store.findBy(function (record) {
									if (
                                        (platform !== 'ec2' || record.get('type') === formValues.type) &&
										record.get('ipProtocol') == formValues.ipProtocol &&
											record.get('fromPort') == formValues.fromPort &&
											record.get('toPort') == formValues.toPort &&
											record.get('sourceType') == formValues.sourceType &&
                                            record.get('sourceValue') == formValues.sourceValue
										) {
										Scalr.message.Error('Rule already exists');
										return true;
									}
								}) == -1) {
									store.add(formValues);
                                    grid.maybeRefreshGrouping();
									return true;
								} else {
									return false;
								}
							}
						});
                    }
                },{
                    itemId: 'delete',
                    iconCls: 'x-btn-icon-delete',
                    cls: 'x-btn-red',
                    disabled: true,
                    tooltip: 'Delete selected rules',
                    handler: function() {
                        var grid = this.up('grid');
                        grid.getStore().remove(grid.getSelectionModel().getSelection());
                    }
                }]
            }]
        }]
    },
    setValues: function(data) {
        var isNewRecord,
            frm = this.getForm(),
            allRules = [],
            cloudLocation = data['cloudLocation'],
            grid = this.down('grid');
        if (!data['id'] && data['securityGroupId']) {
            data['id'] = data['securityGroupId'];
        }
        if (!data['cloudLocationName']) {
            data['cloudLocationName'] = cloudLocation;
        }
        isNewRecord = !data['id'];

        Ext.Array.each(['rules', 'sgRules'], function(field) {
            var rules = data[field];
            if (rules) {
                Ext.Object.each(rules, function(id, rule) {
                    var data = Ext.clone(rule);
                    data['id'] = id;
                    if (field === 'rules') {
                        data['sourceType'] = 'ip';
                        data['sourceValue'] = data['cidrIp'];
                        delete data['cidrIp'];
                    } else {
                        data['sourceType'] = 'sg';
                        data['sourceValue'] = data['sg'];
                        delete data['sg'];
                    }
                    allRules.push(data);
                });
            }
        });
        grid.store.loadData(allRules);
        grid.columns[2].setVisible(Scalr.isOpenstack(data['platform']) && data['advanced']);
        grid.columns[3].setVisible(data['platform'] === 'azure');
        frm.setValues(data);
        frm.findField('id').setVisible(!isNewRecord && data['platform'] !== 'azure');
        frm.findField('name').setReadOnly(!isNewRecord);
        frm.findField('description').setVisible(data['platform'] !== 'azure');
        frm.findField('description').setReadOnly(!isNewRecord);
        frm.findField('description').allowBlank = Scalr.isOpenstack(data['platform']) || data['platform'] === 'azure' ? true : false;
        frm.findField('cloudLocationName').setVisible(!Scalr.isCloudstack(data['platform']));

        var vpcIdField = frm.findField('vpcId');
        vpcIdField.getStore().getProxy().params = {cloudLocation: cloudLocation};
        vpcIdField.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + cloudLocation;
        vpcIdField.setVisible(data['platform'] === 'ec2' && (isNewRecord && !this.vpcIdReadOnly || data['vpcId']));
        vpcIdField.setReadOnly(!!data['vpcId'] || this.vpcIdReadOnly, false);

        var vpcLimits = Scalr.getGovernance('ec2', 'aws.vpc');
        if (data['vpcId']) {
            vpcIdField.store.load();//v5.1.0 extjs will not show value until store.load done
        } else if (isNewRecord && data['platform'] === 'ec2' && Ext.isObject(vpcLimits)) {
            vpcIdField.toggleIcon('governance', true);
            vpcIdField.allowBlank = vpcLimits['value'] == 0;
            if (vpcLimits['regions'] && vpcLimits['regions'][cloudLocation]) {
                if (vpcLimits['regions'][cloudLocation]['ids'] && vpcLimits['regions'][cloudLocation]['ids'].length > 0) {
                    var vpcList = Ext.Array.map(vpcLimits['regions'][cloudLocation]['ids'], function(vpcId){
                        return {id: vpcId, name: vpcId};
                    });
                    vpcIdField.getStore().getProxy().data = vpcList;
                    vpcIdField.store.load();
                    vpcIdField.getPlugin('comboaddnew').setDisabled(true);
                    if (vpcLimits['value'] == 1) {
                        vpcIdField.setValue(vpcIdField.store.first());
                    }
                }
            }
        }

        this.down('#formtitle').setTitle((Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage') ? (isNewRecord ? 'New' :'Edit') : 'View') + ' security group', false);
    },

    getValues: function() {
        var frm = this.getForm(),
            store, values, rules = [], sgRules = [];
        if (!frm.isValid()) return false;
        values = this.callParent(arguments);
        store = this.down('grid').store;
        store.getUnfiltered().each(function(record) {
            var data = record.getData();
            data[data['sourceType'] === 'ip' ? 'cidrIp' : 'sg'] = data['sourceValue'];
            (data['sourceType'] === 'ip' ? rules : sgRules).push(data);
            delete data['sourceValue'];
            delete data['sourceType'];
        });
        values['rules'] = Ext.encode(rules);
        values['sgRules'] = Ext.encode(sgRules);
        values['securityGroupId'] = values['id'];
        return values;
    }
});

Ext.define('Scalr.ui.SecurityGroupMultiSelect', {
	extend: 'Ext.form.FieldSet',
	alias: 'widget.sgmultiselect',

    cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
    excludeGroups: {},
    selection: [],//selected records
    limit: 0,
    layout: 'fit',
    titleAlignCenter: true,
    
    disableAddButton: false,

    selectOnLoad: function (store) {
        var me = this;

        var records = [];

        Ext.Array.each(me.selection, function(rec){
            var record = store.getById(rec.get('id'));
            if (record) {
                records.push(record);
            }
        });

        me.down('grid').getView().getSelectionModel().select(records);
    },

    initComponent: function() {
        var me = this;
        me.callParent(arguments);
        var store = Ext.create('store.store', {
            fields: [
                'name', 'description', 'id', 'vpcId', {
                    name: 'securityGroupId',
                    convert: function (value, model) {
                        return model.get('id');
                    }
                },
                'addedByGovernance'
            ],
            proxy: {
                type: 'scalr.paging',
                url: '/security/groups/xListGroups/',
                extraParams: me.storeExtraParams
            },
            listeners: {
                beforeload: function() {
                    me.down('grid').getView().getSelectionModel().deselectAll(true);
                }
            },
            pageSize: 15,
            remoteSort: true
        });

        store.on('load', me.selectOnLoad, me);
        
        var gridColumns = [
            { header: "Security group", flex: 1, dataIndex: 'name', sortable: true },
            { header: "Description", flex: 2, dataIndex: 'description', sortable: true }
        ];
        if (!me.isRdsSecurityGroupMultiSelect || me.storeExtraParams.platform === 'ec2') {
            gridColumns.unshift({ header: "ID", width: 120, dataIndex: 'id', sortable: true , xtype: 'templatecolumn', tpl: '<a class="edit-group" title="'+(Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage') ? 'Edit' : 'View')+' security group" href="#">{securityGroupId}</a>' });
        }

        me.add([{
            xtype: 'grid',
            store: store,
            plugins: {
                ptype: 'gridstore'
            },
            viewConfig: {
                focusedItemCls: 'no-focus',
                emptyText: 'No security groups found',
                deferEmptyText: false,
                loadingText: 'Loading security groups ...',
                listeners: {
                    viewready: function() {
                        store.applyProxyParams();
                    }
                }
            },

            columns: gridColumns,

            selModel: {
                selType: 'selectedmodel',
                injectCheckbox: 'first',
                getVisibility: function(record) {
                    var visible = true;
                    if (me.excludeGroups['names'] !== undefined && Ext.Array.contains(me.excludeGroups['names'], record.get('name'))) {
                        visible = false;
                    }

                    if (visible && me.excludeGroups['ids'] !== undefined && Ext.Array.contains(me.excludeGroups['ids'], record.get('id'))) {
                        visible = false;
                    }
                    return visible;
                }
            },

            listeners: {
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('a.edit-group')) {
                        view.up('sgmultiselect').edit(record);
                        e.preventDefault();
                    }
                },
                selectionchange: function(selModel, selections) {
                    var newSelection = [];

                    if (me.getXType() === 'rdssgmultiselect') {
                        Ext.Array.each(me.selection, function (record) {
                            if (!store.findRecord('name', record.get('name'), 0, false, false, true)) {
                                newSelection.push(record);
                            }
                        });
                    } else {
                        Ext.Array.each(me.selection, function(rec) {
                            if (!store.getById(rec.get('id'))) {
                                newSelection.push(rec);
                            }
                        });
                    }

                    Ext.Array.each(selections, function(record) {
                        newSelection.push(record);
                    });
                    me.selection = newSelection;

                    me.updateButtonState(me.selection.length);
                }
            },

            dockedItems: [{
                xtype: 'scalrpagingtoolbar',
                itemId: 'paging',
                style: 'padding-left:0;padding-right:0',
                store: store,
                dock: 'top',
                calculatePageSize: false,
                beforeItems: [{
                    text: 'Add group',
                    itemId: 'add',
                    cls: 'x-btn-green',
                    handler: function() {
                        this.up('sgmultiselect').edit();
                    }
                }],
                items: [{
                    xtype: 'filterfield',
                    store: store
                }]
            }]
        }]);
    
        if (me.disableAddButton) {
            me.down('#add')
                .setTooltip('You are not allowed to attach additional Security Groups.')
                .disable();
        }
    
    },
    updateButtonState: function(count) {
        var button = this.up('#box').down('#buttonOk'),
            total,
            gridOverflowEl = this.up('#box').down('grid').getView().getOverflowEl(),
            scrollPos;
        scrollPos = gridOverflowEl.getScroll();//changing button text causes grid scroll position reset
        button.setDisabled(!count);
        button.setText(count > 0 ? 'Add ' + count + ' group(s)' : 'Add');
        if (this.limit > 0) {
            total = (this.excludeGroups['names'] || []).length + (this.excludeGroups['ids'] || []).length + count;
            if (total > this.limit) {
                button.setDisabled(true);
                Scalr.message.InfoTip('There is limit of ' + this.limit + ' security groups per instance. Please reduce your selection.', button.getEl(), {anchor: 'bottom', dismissDelay: 0});
            }
        }
        gridOverflowEl.setScrollTop(scrollPos.top);
    },
    edit: function(record) {
        var me = this,
            groupsGrid = me.down('grid'),
            showEditor = function(data) {
                var win = me.up('#box');
                win.hide();
                Scalr.Confirm({
                    formWidth: 950,
                    formLayout: 'fit',
                    alignTop: true,
                    winConfig: {
                        autoScroll: false,
                        layout: 'fit'
                    },
                    form: [{
                        xtype: 'sgeditor',
                        vpcIdReadOnly: true,
                        accountId: me.accountId,
                        remoteAddress: me.remoteAddress,
                        platform: me.storeExtraParams['platform'],
                        listeners: {
                            afterrender: function() {
                                this.setValues(data);
                            }
                        }
                    }],
                    ok: 'Save',
                    hideOk: !Scalr.isAllowed('SECURITY_SECURITY_GROUPS', 'manage'),
                    closeOnSuccess: true,
                    scope: me,
                    listeners: {
                        destroy: function() {
                            win.show();
                        }
                    },
                    success: function (formValues, form) {
                        var win2 = form.up('#box'),
                            values = win2.down('sgeditor').getValues();
                        if (values !== false) {
                            values['returnData'] = true;
                            Scalr.Request({
                                processBox: {
                                    type: 'save'
                                },
                                url: '/security/groups/xSave',
                                params: values,
                                success: function (data) {
                                    if (!values['id']) {
                                        var newRec = groupsGrid.store.add(groupsGrid.store.createModel(data['group']));
                                        groupsGrid.getView().getSelectionModel().select(newRec);
                                    }
                                    win2.destroy();
                                }
                            });
                        }
                    }
                });
            }
        var params = {
                platform: me.storeExtraParams['platform'],
                cloudLocation: me.storeExtraParams['cloudLocation']
            };
        if (me.storeExtraParams['platform'] === 'azure') {
            params['resourceGroup'] = me.resourceGroup;
        }
        if (record) {
            params['securityGroupId'] = record.get('id');
            Scalr.Request({
                url: '/security/groups/xGetGroupInfo',
                params: params,
                processBox: {
                    type: 'load',
                    msg: 'Loading ...'
                },
                success: function(data) {
                    showEditor(Ext.applyIf(data, params));
                }
            });
        } else {
            if (me.vpc) {
                params['vpcId'] = me.vpc.id;
            }
            showEditor(params);
        }
    }
});

Ext.define('Scalr.ui.rds.SecurityGroupMultiSelect', {
    extend: 'Scalr.ui.SecurityGroupMultiSelect',
    alias: 'widget.rdssgmultiselect',

    isRdsSecurityGroupMultiSelect: true,

    governanceWarning: null,

    allowBlank: false,

    initComponent: function () {
        var me = this;

        me.callParent(arguments);

        var store = me.down('grid').getStore();

        me.selection = Ext.Array.map(me.selection, function (item) {
            if (Ext.isObject(item)) {
                return store.createModel(item);
            }

            return store.createModel({
                name: item.trim()
            });
        });

        store.on('load', function () {
            me.updateButtonState(me.selection.length);
        }, me, { single: true });

        var title = me.title;

        me.setTitle(!Ext.isString(me.governanceWarning)
            ? title
            : title + '&nbsp;&nbsp;<img src="' + Ext.BLANK_IMAGE_URL +
                         '" class="x-icon-governance" data-qtip="' +
                        me.governanceWarning +
                        '" />'
        );

        me.defaultVpcGroups = me.defaultVpcGroups || [];

    },

    initEvents: function () {
        var me = this;

        me.callParent(arguments);

        if (me.storeExtraParams.platform === 'ec2') {
            me.down('grid').
                on('beforedeselect', function (rowModel, record) {
                    return !Ext.Array.contains(me.defaultVpcGroups, record.get('name')) && !record.get('addedByGovernance');
                });
        }
    },

    selectOnLoad: function (store) {
        var me = this;

        var records = [];

        Ext.Array.each(me.selection, function (item) {
            var record = store.findRecord('name', item.get('name'), 0, false, false, true);

            if (!Ext.isEmpty(record)) {
                records.push(record);
            }
        });

        me.down('grid').getView().getSelectionModel().select(records);
    },

    updateButtonState: function (count) {
        var me = this;

        me.callParent(arguments);

        var button = me.up('#box').down('#buttonOk');
        if (!button.disabled) {
            button.setDisabled(!me.allowBlank ? !count : false);
        }
        button.setText('Select ' + count + ' group(s)');
    }
});
