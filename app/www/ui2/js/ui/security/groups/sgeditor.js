Ext.define('Scalr.ui.SecurityGroupEditor', {
	extend: 'Ext.form.Panel',
	alias: 'widget.sgeditor',
    items: {
        xtype: 'fieldset',
        itemId: 'formtitle',
        cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
        title: '&nbsp;',
        defaults: {
            anchor: '100%',
            labelWidth: 120
        },
        items: [{
            xtype: 'hidden',
            name: 'platform'
        }, {
            xtype: 'hidden',
            name: 'cloudLocation'
        }, {
            xtype: 'hidden',
            name: 'vpcId'
        }, {
            xtype: 'textfield',
            name: 'id',
            fieldLabel: 'ID',
            readOnly: true
        }, {
            xtype: 'textfield',
            name: 'name',
            fieldLabel: 'Name',
            allowBlank: false
        }, {
            xtype: 'textfield',
            name: 'description',
            fieldLabel: 'Description',
            allowBlank: false
        }, {
            xtype: 'displayfield',
            name: 'cloudLocationName',
            fieldLabel: 'Cloud location'
        },{
            xtype: 'grid',
            itemId: 'view',
            cls: 'x-grid-shadow',
            maxHeight: 500,
            selModel: {
                selType: 'selectedmodel'
            },
            store: {
                proxy: 'object',
                fields: ['id', 'ipProtocol', 'fromPort', 'toPort', 'sourceType', 'sourceValue', 'comment']
            },
            plugins: {
                ptype: 'gridstore'
            },
            listeners: {
                selectionchange: function(selModel, selected) {
                    this.down('#delete').setDisabled(!selected.length);
                }
            },
            viewConfig: {
                focusedItemCls: 'no-focus',
                emptyText: 'No security rules defined',
                deferEmptyText: false
            },
            columns: [
                { header: 'Protocol', width: 100, sortable: true, dataIndex: 'ipProtocol' },
                { header: 'From port', width: 110, sortable: true, dataIndex: 'fromPort' },
                { header: 'To port', width: 100, sortable: true, dataIndex: 'toPort' },
                { header: 'Source', width: 220, sortable: true, dataIndex: 'sourceValue' },
                { header: 'Comment', flex: 1, sortable: true, dataIndex: 'comment' }
            ],
            dockedItems: [{
                xtype: 'toolbar',
                ui: 'simple',
                dock: 'top',
                defaults: {
                    margin: '0 0 0 10'
                },
                items: [{
                    xtype: 'tbfill'
                },{
                    itemId: 'add',
                    text: 'Add security rule',
                    cls: 'x-btn-green-bg',
                    tooltip: 'New security sule',
                    handler: function() {
                        var accountId = this.up('sgeditor').accountId;
                        Scalr.Confirm({
							form: [{
                                xtype: 'fieldset',
                                title: 'New security rule',
                                cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
                                defaults: {
                                    anchor: '100%',
                                    labelWidth: 75
                                },
                                items: [{
                                    xtype: 'buttongroupfield',
                                    name: 'ipProtocol',
                                    fieldLabel: 'Protocol',
                                    value: 'tcp',
                                    defaults: {
                                        width: 80
                                    },
                                    items: [{
                                        text: 'TCP',
                                        value: 'tcp'
                                    },{
                                        text: 'UDP',
                                        value: 'udp'
                                    },{
                                        text: 'ICMP',
                                        value: 'icmp'
                                    }]
                                }, {
                                    xtype: 'textfield',
                                    name: 'fromPort',
                                    fieldLabel: 'From port',
                                    allowBlank: false,
                                    validator: function (value) {
                                        if (value < -1 || value > 65535) {
                                            return 'Valid ports are - 1 through 65535';
                                        }
                                        return true;
                                    }
                                }, {
                                    xtype: 'textfield',
                                    name: 'toPort',
                                    fieldLabel: 'To port',
                                    allowBlank: false,
                                    validator: function (value) {
                                        if (value < -1 || value > 65535) {
                                            return 'Valid ports are - 1 through 65535';
                                        }
                                        return true;
                                    }
                                }, {
                                    xtype: 'container',
                                    layout: 'hbox',
                                    value: 'ip',
                                    items: [{
                                        xtype: 'buttongroupfield',
                                        fieldLabel: 'Source',
                                        name: 'sourceType',
                                        value: 'ip',
                                        width: 330,
                                        labelWidth: 75,
                                        defaults: {
                                            width: 120
                                        },
                                        items: [{
                                            text: 'CIDR IP',
                                            value: 'ip'
                                        },{
                                            text: 'Security group',
                                            value: 'sg'
                                        }],
                                        listeners: {
                                            change: function(comp, value) {
                                                comp.next().setValue(value === 'ip' ? '0.0.0.0/0' : accountId + '/default');
                                            }
                                        }
                                    },{
                                        xtype: 'textfield',
                                        name: 'sourceValue',
                                        value: '0.0.0.0/0',
                                        flex: 1,
                                        allowBlank: false
                                    }],
                                }, {
                                    xtype: 'textarea',
                                    name: 'comment',
                                    height: 60,
                                    fieldLabel: 'Comment',
                                    allowBlank: true
                                }]
							}],
                            formWidth: 600,
							ok: 'Add',
							formValidate: true,
							closeOnSuccess: true,
							scope: this,
							success: function (formValues) {
								var view = this.up('#view'), store = view.store;

								if (store.findBy(function (record) {
									if (
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
									return true;
								} else {
									return false;
								}
							}
						});
                    }
                },{
                    itemId: 'delete',
                    iconCls: 'x-tbar-delete',
                    ui: 'paging',
                    disabled: true,
                    tooltip: 'Delete selected rules',
                    handler: function() {
                        var grid = this.up('grid'),
                            selModel = grid.getSelectionModel(),
                            selection = selModel.getSelection();
                        selModel.setLastFocused(null);
                        grid.getStore().remove(selection);
                    }
                }]
            }]
        }]
    },
    setValues: function(data) {
        var isNewRecord,
            frm = this.getForm(),
            allRules = [];
        if (!data['id'] && data['securityGroupId']) {
            data['id'] = data['securityGroupId'];
        }
        if (!data['cloudLocationName']) {
            data['cloudLocationName'] = data['cloudLocation'];
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
        this.down('grid').store.loadData(allRules);
        frm.setValues(data);
        frm.findField('id').setVisible(!isNewRecord);
        frm.findField('name').setReadOnly(!isNewRecord);
        frm.findField('description').setReadOnly(!isNewRecord);
        this.down('#formtitle').setTitle((isNewRecord ? 'New' :'Edit') + ' security group', false);
    },

    getValues: function() {
        var frm = this.getForm(),
            store, values, rules = [], sgRules = [];
        if (!frm.isValid()) return false;
        values = this.callParent(arguments);
        store = this.down('grid').store;
        (store.snapshot || store.data).each(function() {
            var data = this.getData();
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

    initComponent: function() {
        var me = this;
        me.callParent(arguments);
        var store = Ext.create('store.store', {
            fields: [
                'name', 'description', 'id', 'vpcId',
                'farm_name', 'farm_id', 'role_name', 'farm_roleid'
            ],
            proxy: {
                type: 'scalr.paging',
                url: '/security/groups/xListGroups/',
                extraParams: me.storeExtraParams
            },
            listeners: {
                beforeload: function() {
                    me.down('grid').getView().getSelectionModel().deselectAll(true);
                },
                load: function(store) {
                    var records = [];
                    Ext.Array.each(me.selection, function(rec){
                        var record = store.getById(rec.get('id'));
                        if (record) {
                            records.push(record);
                        }
                    });
                    me.down('grid').getView().getSelectionModel().select(records);
                }
            },
            pageSize: 15,
            remoteSort: true
        });
        me.add([{
            xtype: 'grid',
            cls: 'x-grid-shadow',
            store: store,
            plugins: {
                ptype: 'gridstore'
            },
            viewConfig: {
                focusedItemCls: 'no-focus',
                emptyText: "No security groups found",
                deferEmptyText: false,
                loadingText: 'Loading security groups ...',
                listeners: {
                    viewready: function() {
                        this.up('grid').getDockedComponent('paging').setPageSizeAndLoad();
                    }
                }
            },

            columns: [
                { header: "ID", width: 120, dataIndex: 'id', sortable: true , xtype: 'templatecolumn', tpl: '<a class="edit-group" title="Edit security group" href="#xxx">{id}</a>'},
                { header: "Name", flex: 1, dataIndex: 'name', sortable: true },
                { header: "Description", flex: 2, dataIndex: 'description', sortable: true }
            ],

            multiSelect: true,
            selModel: {
                selType: 'selectedmodel',
                injectCheckbox: 'first',
                getVisibility: function(record) {
                    var visible = true;
                    if (me.excludeGroups['names'] !== undefined && Ext.Array.contains(me.excludeGroups['names'], record.get('name'))) {
                        visible = false;
                    }

                    if (visible && me.excludeGroups['names'] !== undefined && Ext.Array.contains(me.excludeGroups['ids'], record.get('id'))) {
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
                    Ext.Array.each(me.selection, function(rec) {
                        if (!store.getById(rec.get('id'))) {
                            newSelection.push(rec);
                        }
                    });
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
                style: 'box-shadow:none;padding-left:0;padding-right:0',
                store: store,
                dock: 'top',
                calculatePageSize: false,
                beforeItems: [{
                    text: 'Add group',
                    cls: 'x-btn-green-bg',
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
    },
    updateButtonState: function(count) {
        var button = this.up('#box').down('#buttonOk'),
            total;
        button.setDisabled(!count);
        button.setText(count > 0 ? 'Add ' + count + ' group(s)' : 'Add');
        if (this.limit > 0) {
            total = (this.excludeGroups['names'] || []).length + (this.excludeGroups['ids'] || []).length + count;
            if (total > this.limit) {
                button.setDisabled(true);
                Scalr.message.InfoTip('There is limit of ' + this.limit + ' security groups per farm role. Please reduce your selection.', button.getEl(), {anchor: 'bottom', dismissDelay: 0});
            }
        }
    },
    edit: function(record) {
        var me = this,
            groupsGrid = me.down('grid'),
            showEditor = function(data) {
                var win = me.up('#box');
                win.hide();
                Scalr.Confirm({
                    formWidth: 950,
                    alignTop: true,
                    winConfig: {
                        autoScroll: false
                    },
                    form: [{
                         xtype: 'sgeditor',
                         accountId: me.accountId,
                         listeners: {
                             afterrender: function() {
                                 this.setValues(data);
                             }
                         }
                    }],
                    ok: 'Save',
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
