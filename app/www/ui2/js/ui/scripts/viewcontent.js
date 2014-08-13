Scalr.regPage('Scalr.ui.scripts.viewcontent', function (loadParams, moduleParams) {
    var script = moduleParams['script'];
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
            fieldLabel: 'Versions',
            labelWidth: 70,
            maxWidth: 200,
            editable: false,
            queryMode: 'local',
            name: 'version',
            displayField: 'version',
            hidden: ! (script['versions'].length > 1),
            listConfig: {
                cls: 'x-boundlist-alt',
                tpl:
                    '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                        '{version} [{dtCreated}]' +
                        '</div></tpl>'
            },
            store: {
                fields: [ 'version', 'dtCreated', 'content' ],
                data: script['versions'],
                proxy: 'object'
            },

            listeners: {
                afterrender: function() {
                    this.setValue(this.store.last().get('version'));
                },
                change: function (field, newValue) {
                    var rec = this.findRecordByValue(newValue);
                    form.down('#scriptContents').setValue(rec.get('content'));
                }
            }
        }, {
            xtype: 'codemirror',
			itemId: 'scriptContents',
			readOnly: true,
			hideLabel: true,
			minHeight: 400,
            flex: 1
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
