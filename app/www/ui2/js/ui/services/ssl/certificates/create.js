Scalr.regPage('Scalr.ui.services.ssl.certificates.create', function () {

    return Scalr.utils.Window({
        xtype: 'certificate',
        isModal: true,

        title: 'SSL certificates &raquo; Create',
        width: 600,

        scalrOptions: {
            modalWindow: true
        },

        onCertificateSave: function (form, certificate) {
            if (certificate) {
                Scalr.event.fireEvent(
                    'update', '/services/ssl/certificates/create', certificate
                );
            }

            form.close();
        },

        onCancel: function (form) {
            form.close();
        }
    });

});


Ext.define('Scalr.ui.CertificateForm', {
    extend: 'Ext.form.Panel',

    alias: 'widget.certificate',

    autoScroll: true,

    isModal: false,

    onCertificateSave: Ext.emptyFn,

    onCertificateRemove: Ext.emptyFn,

    onCancel: Ext.emptyFn,

    setHeader: function (headerText) {
        var me = this;

        me.down('fieldset').setTitle(headerText);

        return me;
    },

    setReadOnly: function (readOnly) {
        var me = this;

        me.getForm().getFields().each(function (field) {
            if (field.xtype !== 'filefield') {
                field.setReadOnly(readOnly);
            } else {
                field.button.setDisabled(readOnly);
            }
        });

        me.down('#save').setDisabled(readOnly);
        me.down('#delete').setDisabled(readOnly);

        if (readOnly) {
            me.disableButtons(null, null, null);
        }

        return me;
    },

    disableButtons: function (certificate, chain, key) {
        var me = this;

        var isCertificateEmpty = Ext.isEmpty(certificate);

        me.down('#deleteCertificateButton')
            .setDisabled(isCertificateEmpty);

        var isChainEmpty = Ext.isEmpty(chain);

        me.down('#deleteChainButton')
            .setDisabled(isChainEmpty);

        var isKeyEmpty = Ext.isEmpty(key);

        me.down('#deleteKeyButton')
            .setDisabled(isKeyEmpty);

        return me;
    },

    fieldDefaults: {
        anchor: '100%'
    },

    initEvents: function () {
        var me = this;

        me.callParent();

        me.on({
            certificatesave: me.onCertificateSave,
            certificateremove: me.onCertificateRemove,
            cancel: me.onCancel
        });
    },

    initComponent: function () {
        var me = this;

        me.callParent();

        me.add([{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            labelWidth: 120,
            items: [{
                xtype: 'textfield',
                fieldLabel: 'Name',
                labelAlign: 'top',
                name: 'name',
                allowBlank: false,
                margin: !me.isModal
                    ? '0 162 0 0'
                    : '0 108 0 0'
            }, {
                xtype: 'hidden',
                name: 'id'
            }, {
                xtype: 'fieldcontainer',
                fieldLabel: 'Certificate',
                labelAlign: 'top',
                layout: 'hbox',
                items: [{
                    xtype: 'filefield',
                    name: 'certificate',
                    flex: 1
                }, {
                    xtype: 'button',
                    itemId: 'deleteCertificateButton',
                    cls: 'x-btn-red',
                    iconCls: 'x-btn-icon-delete',
                    margin: '0 0 0 12',
                    hidden: me.isModal,
                    tooltip: 'Click here to delete previously uploaded certificate',
                    handler: function (button) {
                        button.prev().setValue();
                        button.next().setValue(1);
                        button.disable();
                    }
                }, {
                    xtype: 'hidden',
                    name: 'certificateClear'
                }, {
                    itemId: 'certificateVoid',
                    margin: '0 0 0 12',
                    width: 42
                }]
            }, {
                xtype: 'fieldcontainer',
                fieldLabel: 'Certificate chain',
                labelAlign: 'top',
                layout: 'hbox',
                items: [{
                    xtype: 'filefield',
                    name: 'caBundle',
                    flex: 1
                }, {
                    xtype: 'button',
                    itemId: 'deleteChainButton',
                    cls: 'x-btn-red',
                    iconCls: 'x-btn-icon-delete',
                    margin: '0 0 0 12',
                    hidden: me.isModal,
                    tooltip: 'Click here to delete previously uploaded certificate chain',
                    handler: function (button) {
                        button.prev().setValue();
                        button.next().setValue(1);
                        button.disable();
                    }
                }, {
                    xtype: 'hidden',
                    name: 'caBundleClear'
                }, {
                    itemId: 'chainVoid',
                    margin: '0 0 0 12',
                    width: 42
                }]
            }, {
                xtype: 'fieldcontainer',
                fieldLabel: 'Private key',
                labelAlign: 'top',
                layout: 'hbox',
                items: [{
                    xtype: 'filefield',
                    name: 'privateKey',
                    flex: 1
                }, {
                    xtype: 'button',
                    itemId: 'deleteKeyButton',
                    cls: 'x-btn-red',
                    iconCls: 'x-btn-icon-delete',
                    margin: '0 0 0 12',
                    hidden: me.isModal,
                    tooltip: 'Click here to delete previously uploaded private key',
                    handler: function (button) {
                        button.prev().setValue();
                        button.next().setValue(1);
                        button.disable();
                    }
                }, {
                    xtype: 'hidden',
                    name: 'privateKeyClear'
                }, {
                    itemId: 'keyVoid',
                    margin: '0 0 0 12',
                    width: 42
                }]
            }, {
                xtype: 'textfield',
                fieldLabel: 'Private key password',
                labelAlign: 'top',
                name: 'privateKeyPassword',
                margin: !me.isModal
                    ? '0 162 0 0'
                    : '0 108 0 0'
            }]
        }]);

        me.addDocked([{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons',
            hidden: !Scalr.isAllowed('SERVICES_SSL', 'manage'),
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            maxWidth: 1100,
            defaults: {
                xtype: 'button',
                flex: 1,
                maxWidth: 140
            },
            items: [{
                text: 'Create',
                itemId: 'save',
                handler: function (button) {
                    button.up('certificate').saveCertificate();
                }
            }, {
                text: 'Cancel',
                handler: function (button) {
                    var formPanel = button.up('certificate');
                    formPanel.fireEvent('cancel', formPanel);
                }
            }, {
                itemId: 'delete',
                cls: 'x-btn-red',
                text: 'Delete',
                hidden: me.isModal,
                handler: function (button) {
                    button.up('certificate').deleteCertificate();
                }
            }]
        }]);
    },

    saveCertificate: function () {
        var me = this;

        var form = me.getForm();

        if (form.isValid()) {
            Scalr.Request({
                processBox: {
                    type: 'save'
                },
                form: form,
                url: '/services/ssl/certificates/xSave',
                success: function (response) {
                    var certificate = response['cert'];

                    me.fireEvent('certificatesave', me,
                        !Ext.isEmpty(certificate) ? certificate : null
                    );
                }
            });
        }
    },

    deleteCertificate: function () {
        var me = this;

        Scalr.Request({
            confirmBox: {
                type: 'delete',
                msg: 'Delete certificate?'
            },
            processBox: {
                type: 'delete',
                msg: 'Deleting...'
            },
            url: '/services/ssl/certificates/xRemove/',
            params: {
                certs: Ext.encode(
                    [ me.getForm().getRecord().get('id') ]
                )
            },
            success: function (response) {
                var deletedCertificateId = response.processed;

                if (!Ext.isEmpty(deletedCertificateId)) {
                    me.fireEvent('certificateremove', me, deletedCertificateId);
                }
            }
        });
    }
});
