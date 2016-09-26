Scalr.regPage('Scalr.ui.tools.aws.rds.sg.edit', function (loadParams, moduleParams) {
	var rulesStore = Ext.create('Ext.data.JsonStore', {
		fields: ['Type', 'CIDRIP', 'EC2SecurityGroupOwnerId', 'EC2SecurityGroupName', 'Status']
	});
	Ext.each(moduleParams.rules['groupRules'], function(item){
		rulesStore.add({Type: 'EC2 Security Group', EC2SecurityGroupOwnerId: item.EC2SecurityGroupOwnerId, EC2SecurityGroupName: item.EC2SecurityGroupName, Status: item.Status});
	});
	Ext.each(moduleParams.rules['ipRules'], function(item){
		rulesStore.add({Type: 'CIDR IP', CIDRIP: item.CIDRIP, Status: item.Status});
	});
	return Ext.create('Ext.panel.Panel', {
		title: 'Tools &raquo; Amazon Web Services &raquo; Amazon RDS &raquo; Security groups &raquo; ' + loadParams['dbSgName'] + ' &raquo; Edit',
		width: 625,
        scalrOptions: {
            modal: true
        },
        bodyCls: 'x-container-fieldset',
        items: [{
            xtype: 'textfield',
            width: 560,
            fieldLabel: 'Name',
            readOnly: true,
            value: loadParams['dbSgName']
        }, {
            xtype: 'textfield',
            width: 560,
            name: 'description',
            fieldLabel: 'Description',
            readOnly: true,
            value: moduleParams['description']
        }, {
            xtype: 'grid',
            store: rulesStore,
            disableSelection: true,
            plugins: {
                ptype: 'gridstore'
            },
            viewConfig: {
                focusedItemCls: 'no-focus',
                emptyText: 'No security rules defined',
                deferEmptyText: false
            },
            columns: [{
                text: "Type", flex: 1, dataIndex: 'Type', sortable: true
            },{
                text: "Parameters", flex: 1, dataIndex: 'Parameters', sortable: true, xtype: 'templatecolumn',
                tpl: '<tpl if="CIDRIP">{CIDRIP}</tpl><tpl if="EC2SecurityGroupOwnerId">{EC2SecurityGroupOwnerId}/{EC2SecurityGroupName}</tpl>'
            },{
                text: "Status", width: 160, dataIndex: 'Status', sortable: true
            },{
                xtype: 'templatecolumn',
                tpl: '<img class="x-grid-icon x-grid-icon-delete" title="Delete" src="'+Ext.BLANK_IMAGE_URL+'"/>',
                text: '&nbsp;',
                width: 45,
                sortable: false,
                resizable: false,
                border: false,
                align:'center'
            }],
            listeners: {
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('img.x-grid-icon-delete')) {
                        view.store.remove(record);
                    }
                }
            },
            dockedItems: [{
                xtype: 'container',
                dock: 'bottom',
                cls: 'x-docked-buttons',
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                items: [{
                    xtype: 'button',
                    text: 'Save',
                    handler: function() {
                        var data = [];
                        Ext.each (rulesStore.getRange(), function (item) {
                            data.push(item.data);
                        });
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            url: '/tools/aws/rds/sg/xSave/',
                            params: Ext.applyIf(loadParams, {'rules': Ext.encode(data)}),
                            success: function (data) {
                                Scalr.event.fireEvent('close');
                            }
                        });
                    }
                }, {
                    xtype: 'button',
                    text: 'Cancel',
                    handler: function() {
                        Scalr.event.fireEvent('close');
                    }
                }]
            },{
                xtype: 'toolbar',
                ui: 'inline',
                dock: 'top',
                padding: '8 0 7 0',
                layout: {
                    type: 'hbox',
                    pack: 'start'
                },
                items:[{
                    xtype: 'tbfill'
                }, {
                    text: 'Create security rule',
                    cls: 'x-btn-green',
                    handler: function() {
                        Scalr.Confirm({
                            title: 'New security rule',
                            form: [{
                                xtype: 'fieldset',
                                cls: 'x-fieldset-separator-none',
                                defaults: {
                                    anchor: '100%'
                                },
                                items: [{
                                    xtype: 'hiddenfield',
                                    name: 'Status',
                                    value: 'new'
                                },{
                                    xtype: 'combo',
                                    name: 'Type',
                                    editable: false,
                                    fieldLabel: 'Type',
                                    queryMode: 'local',
                                    store: [ ['CIDR IP','CIDR IP'], ['EC2 Security Group','EC2 Security Group'] ],
                                    listeners: {
                                        change: function (field, value) {
                                            var isCidr = value == 'CIDR IP';

                                            field.next('[name=ipRanges]').
                                                setVisible(isCidr).
                                                setDisabled(!isCidr);

                                            field.next('[name=UserId]').
                                                setVisible(!isCidr).
                                                setDisabled(isCidr);

                                            field.next('[name=Group]').
                                                setVisible(!isCidr).
                                                setDisabled(isCidr);

                                            field.up('form').updateLayout();
                                        }
                                    }
                                },{
                                    xtype: 'textfield',
                                    name: 'ipRanges',
                                    fieldLabel: 'IP Ranges',
                                    value: '0.0.0.0/0',
                                    hidden: true,
                                    allowBlank: false
                                },{
                                    xtype: 'textfield',
                                    name: 'UserId',
                                    fieldLabel: 'User ID',
                                    hidden: true,
                                    allowBlank: false,
                                    validator: function (value) {
                                        if (value < 100000000000 || value > 999999999999) {
                                            return 'User ID must be 12 digits length';
                                        }
                                        return true;
                                    }
                                },{
                                    xtype: 'textfield',
                                    name: 'Group',
                                    fieldLabel: 'Group',
                                    hidden: true,
                                    allowBlank: false
                                }]
                            }],
                            formValidate: true,
                            ok: 'Add',
                            scope: this,
                            success: function (formValues) {
                                if(formValues['ipRanges'])
                                    rulesStore.insert(rulesStore.data.length,{'Type': formValues.Type, 'CIDRIP': formValues['ipRanges'], 'Status': formValues.Status});
                                else rulesStore.insert(rulesStore.data.length,{'Type': formValues.Type, 'EC2SecurityGroupOwnerId': formValues['UserId'] , 'EC2SecurityGroupName': formValues['Group'], 'Status': formValues.Status});
                            }
                        });
                    }
                }]
            }]
        }]
	});
});
