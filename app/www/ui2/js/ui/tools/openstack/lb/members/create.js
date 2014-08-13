Scalr.regPage('Scalr.ui.tools.openstack.lb.members.create', function (loadParams, moduleParams) {

    var panel = Ext.create('Ext.form.Panel', {
        width: 500,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Load balancers &raquo; Members &raquo; Create member',
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },

        items: [{
            xtype: 'fieldset',
            title: 'Member details',
            defaults: {
                labelWidth: 150
            },
            items: [{
                fieldLabel:'Pool',
                xtype: 'combo',
                emptyText: 'Select a pool',
                editable: false,
                allowBlank: false,
                store: {
                    fields: ['id', 'name'],
                    data: moduleParams['pools'],
                    proxy: 'object'
                },
                displayField: 'name',
                valueField: 'id',
                name: 'pool_id'
            }, {
                fieldLabel:'Member(s)',
                xtype: 'checkboxgroup',
                columns: 1,
                itemId: 'membersCheckboxgroup'
            }, {
                fieldLabel:'Weight',
                xtype: 'textfield',
                value: 1,
                validator: function (value) {
                    return value >= 0 && value <= 256 ? true : 'Weight should have a value from 0 to 256';
                },
                name: 'weight'
            }, {
                fieldLabel:'Protocol port',
                xtype: 'textfield',
                allowBlank: false,
                validator: function (value) {
                    return value >= 0 && value <= 65535 ? true : 'Protocol port should have a value from 0 to 65535';
                },
                name: 'protocol_port'
            }, {
                fieldLabel:'Admin state',
                xtype: 'checkboxfield',
                inputValue: true,
                uncheckedValue: false,
                value: true,
                name: 'admin_state_up'
            }]
        }],

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
                text: 'Create',
                handler: function() {
                    var form = panel.getForm();
                    if (form.isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: form,
                            scope: this,
                            params: loadParams,
                            url: '/tools/openstack/lb/members/xSave',
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                    }
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    Scalr.event.fireEvent('close');
                }
            }]
        }]
    });

    var fillMembersCheckboxgroup = function() {
        var membersCheckboxgroup = panel.down('#membersCheckboxgroup'),
            membersData = moduleParams['instances'];
        for (var i = 0; i < membersData.length; i++) {
            var member = {boxLabel: membersData[i].name, inputValue: membersData[i].id, name: 'members[]'};
            membersCheckboxgroup.add(member);
        }
    };

    fillMembersCheckboxgroup();
    return panel;
});
