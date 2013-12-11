Scalr.regPage('Scalr.ui.account2.roles.usage', function (loadParams, moduleParams) {
    var storeUsers = Ext.create('Ext.data.Store', {
        fields: ['name', 'email', 'teams'],
        data: moduleParams['users'],
        queryMode: 'local'
    });

	var form = Ext.create('Ext.form.Panel', {
		scalrOptions: {
			'modal': true
		},
		width: 700,
        bodyCls: 'x-container-fieldset',
		title: 'Acl\'s &raquo; ' + moduleParams['role']['name'] + '&raquo; Usage summary',
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 130
		},
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
		items: [{
            xtype: 'component',
            html: 'The list of the users which are dependent on ACL &laquo;' + moduleParams['role']['name'] + '&raquo; '
        },{
            xtype: 'grid',
            padding: '0 0 12',
            margin: '6 0 0',
            itemId: 'resources',
            cls: 'x-grid-shadow x-grid-no-highlighting',
            flex: 1,
            store: storeUsers,
            viewConfig: {
                deferEmptyText: false,
                emptyText: 'ACL &laquo;' + moduleParams['role']['name'] + '&raquo; is not assigned to users'
            },
            columns: [{
                text: 'User',
                dataIndex: 'name',
                flex: 1,
                xtype: 'templatecolumn',
                tpl: '{name} &lt;{email}&gt;'
            },{
                text: 'Teams',
                sortable: false,
                flex: 1,
                xtype: 'templatecolumn',
                tpl: '{[Ext.Object.getValues(values.teams).join(\', \')]}'
            }]
        }],
		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}]
	});

	return form;
});
