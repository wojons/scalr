Scalr.regPage('Scalr.ui.admin.utils.debug', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		width: 800,
		title: 'Admin &raquo; Utils &raquo; Debug',
        tools: [{
            type: 'close',
            handler: function () {
                Scalr.event.fireEvent('close');
            }
        }],
        items: [{
            xtype: 'fieldset',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            items: [{
                xtype: 'checkbox',
                name: 'enabled',
                boxLabel: 'Enable debug (sql, dump, exception)'
            }]
        }],
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
                text: 'Apply',
                handler: function () {
                    Scalr.Request({
                        processBox: {
                            type: 'action'
                        },
                        url: '/admin/utils/xSaveDebug',
                        form: this.up('form').getForm(),
                        success: function (data) {
                            Scalr.event.fireEvent('reload');
                        }
                    });
                }
            }]
        }]
	});

    form.getForm().setValues(moduleParams || []);
    return form;
});
