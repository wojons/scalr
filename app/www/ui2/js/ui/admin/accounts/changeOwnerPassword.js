Scalr.regPage('Scalr.ui.admin.accounts.changeOwnerPassword', function (loadParams, moduleParams) {
    var form = Ext.create('Ext.form.Panel', {
        width: 460,
        title: 'Accounts &raquo; ' + moduleParams['accountName'] + ' &raquo; Change owner password',
        fieldDefaults: {
            anchor: '100%',
            labelWidth: 130
        },

        items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            items: [{
                xtype: 'displayfield',
                fieldLabel: 'Account owner',
                value: moduleParams['email']
            }, {
                xtype: 'scalrpasswordfield',
                name: 'password',
                itemId: 'password',
                allowBlank: false,
                minPasswordLengthAdmin: true,
                vtype: 'password',
                otherPassField: 'cpassword',
                fieldLabel: 'New password',
                listeners: {
                    afterrender: function(){
                        Ext.defer(this.focus, 100, this);
                    }
                }
            }, {
                xtype: 'scalrpasswordfield',
                name: 'cpassword',
                itemId: 'cpassword',
                allowBlank: false,
                vtype: 'password',
                minPasswordLengthAdmin: true,
                otherPassField: 'password',
                fieldLabel: 'Confirm password'
            }, {
                xtype: 'hidden',
                name: 'accountId',
                value: loadParams['accountId']
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
                itemId: 'save',
                text: 'Save',
                //disabled: true,
                handler: function () {
                    var confirmBox,
                        frm = form.getForm();
                    sendRequest = function(currentPassword){
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            url: '/admin/accounts/xSaveOwnerPassword',
                            form: frm,
                            params: {
                                currentPassword: currentPassword
                            },
                            success: function (data) {
                                if (confirmBox) {
                                    confirmBox.close();
                                }
                                Scalr.event.fireEvent('close');
                            },
                            failure: function(data) {
                                if (confirmBox) {
                                    confirmBox.onFailure(data.errors);
                                }
                            }
                        });
                    };
                    if (frm.isValid()) {
                        confirmBox = Scalr.utils.ConfirmPassword(sendRequest);
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

    return form;
});
