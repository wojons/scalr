Scalr.regPage('Scalr.ui.tools.aws.rds.instances.createSubnetGroup', function (loadParams, moduleParams) {

    var vpcPolicy = Scalr.getGovernance('ec2', 'aws.vpc');

    var form = Scalr.utils.Window({
        xtype: 'form',
        title: 'Create subnet group',
        fieldDefaults: Ext.isEmpty(vpcPolicy) ? {
            anchor: '100%'
        } : {
            width: 530
        },
        scalrOptions: {
            modalWindow: true
        },
        width: 600,
        defaults: {
            labelWidth: 120
        },
        bodyCls: 'x-container-fieldset x-fieldset-no-bottom-padding',

        items: [{
            xtype: 'textfield',
            name: 'dbSubnetGroupName',
            fieldLabel: 'Name',
            allowBlank: false
        },{
            xtype: 'textfield',
            name: 'dbSubnetGroupDescription',
            fieldLabel: 'Description',
            allowBlank: false
        },{
            xtype: 'vpcsubnetfield',
            name: 'subnets',
            fieldLabel: 'Subnets',
            flex: 1,
            allowBlank: false,
            iconAlign: 'right',
            iconPosition: 'outer',
            getSubmitValue: function () {
                var me = this;

                var value = me.getValue();

                if (Ext.isEmpty(value)) {
                    value = '';
                }

                return Ext.encode(value);
            },
            listeners: {
                afterrender: function (me) {
                    var cloudLocation = moduleParams.cloudLocation;
                    var vpcId = moduleParams.vpcId;

                    me.getStore().getProxy().params = {
                        cloudLocation: cloudLocation,
                        vpcId: vpcId,
                        extended: 1
                    };

                    var addNewPlugin = me.getPlugin('comboaddnew');

                    //addNewPlugin.enable();

                    addNewPlugin.postUrl = '?' + Ext.Object.toQueryString({
                        cloudLocation: cloudLocation,
                        vpcId: vpcId
                    });

                    me.getPlugin('fieldicons').
                        toggleIcon('governance', !Ext.isEmpty(vpcPolicy));
                }
            }
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
                    if (form.getForm().isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: form.getForm(),
                            params: {
                                cloudLocation: moduleParams.cloudLocation
                            },
                            scope: this,
                            url: '/tools/aws/rds/instances/xCreateSubnetGroup',
                            success: function (response) {
                                var subnetGroup = response['subnetGroup'];

                                if (subnetGroup) {
                                    Scalr.event.fireEvent(
                                        'update',
                                        '/tools/aws/rds/instances/createSubnetGroup',
                                        subnetGroup
                                    );
                                }

                                form.close();
                            }
                        });
                    }
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                handler: function() {
                    form.close();
                }
            }]
        }]
    });

    return form;
});
