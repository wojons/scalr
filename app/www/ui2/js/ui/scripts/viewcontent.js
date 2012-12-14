Scalr.regPage('Scalr.ui.scripts.viewcontent', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		bodyCls: 'x-panel-body-frame',
		bodyStyle: 'padding: 12px',
		width: 900,
		scalrOptions: {
			'modal': true
		},
		title: 'Scripts &raquo; View &raquo; ' + moduleParams['script']['name'],
		layout: 'fit',
		items: [{
			xtype: 'codemirror',
			itemId: 'scriptContents',
			readOnly: true,
			hideLabel: true,
			minHeight: 400,
			value: moduleParams['content'][moduleParams['latest']]
		}],
		tools: [{
			type: 'maximize',
			handler: function () {
				Scalr.event.fireEvent('maximize');
			}
		}, {
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}]
	});
    if (moduleParams['revision'].length > 1) {
        form.addDocked({
			xtype: 'toolbar',
			dock: 'top',
			layout: {
				type: 'hbox',
				pack: 'start'
			},
            items: {
                xtype: 'combobox',
                itemId: 'comboVers',
                fieldLabel: 'Revision versions',
				labelWidth: 120,
                editable: false,
                queryMode: 'local',
                displayField: 'revision',
                store: moduleParams['revision'],
                listeners: {
                    change: function (field, newValue, oldValue) {
	                    form.down('#scriptContents').setValue(moduleParams['content'][newValue]);
                    }
                }
            }
        });
        form.down('#comboVers').setValue(moduleParams['latest']);
    }
    return form;
});
