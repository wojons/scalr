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
            items: [{
                xtype: 'checkbox',
                name: 'sql',
                boxLabel: 'Enable SQL debug'
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
                            if (data['js'])
                                Ext.Loader.loadScripts(data['js'], Ext.emptyFn);

                            Scalr.event.fireEvent('close');
                        }
                    });
                }
            }]
        }]
	});

    form.getForm().setValues(moduleParams || []);
    return form;
});
