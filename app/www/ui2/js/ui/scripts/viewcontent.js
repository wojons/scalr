Scalr.regPage('Scalr.ui.scripts.viewcontent', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		width: 900,
		scalrOptions: {
			'modal': true
		},
		title: 'Scripts &raquo; View &raquo; ' + moduleParams['script']['name'],
        bodyCls: 'x-container-fieldset',
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
		items: [{
            xtype: 'combobox',
            itemId: 'comboVers',
            fieldLabel: 'Revision versions',
            labelWidth: 120,
            maxWidth: 200,
            editable: false,
            queryMode: 'local',
            displayField: 'revision',
            hidden: ! (moduleParams['revision'].length > 1),
            value: moduleParams['latest'],
            listConfig: {
                cls: 'x-boundlist-alt',
                tpl:
                    '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                        '{revision} [{dtCreated}]' +
                        '</div></tpl>'
            },
            store: {
                fields: [ 'revision', 'dtCreated' ],
                data: moduleParams['revision'],
                proxy: 'object'
            },

            listeners: {
                change: function (field, newValue) {
                    form.down('#scriptContents').setValue(moduleParams['content'][newValue]);
                }
            }
        }, {
            xtype: 'codemirror',
			itemId: 'scriptContents',
			readOnly: true,
			hideLabel: true,
			minHeight: 400,
            flex: 1,
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
    return form;
});
