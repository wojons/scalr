Scalr.regPage('Scalr.ui.tools.aws.rds.instances.promoteReadReplica', function (loadParams, moduleParams) {
    var form = Ext.create('Ext.form.Panel', {
        scalrOptions: {
            'modal': true
        },
        width: 500,
        title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; Promote Read Replica',

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
                text: 'Promote',
                handler: function () {
                    form.down('#PreferredBackupWindow').setValue(form.down('#bfhour').value + ':' + form.down('#bfminute').value + '-' + form.down('#blhour').value + ':' + form.down('#blminute').value);

                    if (form.getForm().isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save',
                                msg: 'Promoting ...'
                            },
                            url: '/tools/aws/rds/instances/xPromoteReadReplica',
                            params: {
                                cloudLocation: loadParams.cloudLocation

                            },
                            form: form.getForm(),
                            success: function () {
                                Scalr.event.fireEvent('close');
                            }
                        });
                    }
                }
            },{
                xtype: 'button',
                text: 'Cancel',
                handler: function () {
                    Scalr.event.fireEvent('close');
                }
            }]
        }],

        items: [{
            xtype: 'fieldset',
            items: [{
                labelWidth: 200,
                xtype: 'displayfield',
                fieldLabel: 'Read Replica',
                name: 'DBInstanceIdentifier',
                value: loadParams['instanceId'],
                submitValue: true
            }, {
                xtype: 'hiddenfield',
                name: 'PreferredBackupWindow',
                itemId: 'PreferredBackupWindow'
            }, {
                xtype: 'container',
                layout: {
                    type: 'hbox'
                },
                items: [{
                    labelWidth: 200,
                    width: 240,
                    xtype: 'textfield',
                    itemId: 'bfhour',
                    fieldLabel: 'Preferred Backup Window',
                    value: '10'
                },{
                    xtype: 'displayfield',
                    value: ' : ',
                    margin: '0 0 0 3'
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'bfminute',
                    value: '00',
                    margin: '0 0 0 3'
                },{
                    xtype: 'displayfield',
                    value: ' - ',
                    margin: '0 0 0 3'
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'blhour',
                    value: '12',
                    margin: '0 0 0 3'
                },{
                    xtype: 'displayfield',
                    value: ' : ',
                    margin: '0 0 0 3'
                },{
                    width: 35,
                    xtype: 'textfield',
                    itemId: 'blminute',
                    value: '00',
                    margin: '0 0 0 3'
                },{
                    xtype: 'displayfield',
                    value: 'UTC',
                    margin: '0 0 0 6'
                },{
                    xtype: 'displayinfofield',
                    info: 'Format: hh24:mi - hh24:mi',
                    margin: '0 0 0 6'
                }]
            }, {
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    labelWidth: 200,
                    width: 280,
                    xtype: 'numberfield',
                    name: 'BackupRetentionPeriod',
                    fieldLabel: 'Backup Retention Period',
                    value: 1,
                    minValue: 0,
                    maxValue: 35
                }, {
                    xtype: 'displayfield',
                    margin: '0 0 0 9',
                    value: 'days'
                }]
            }]
        }]
    });

    return form;
});
