Scalr.regPage('Scalr.ui.bundletasks.logs', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			{name: 'id', type: 'int'},
			'dtadded','message'
		],
		proxy: {
			type: 'scalr.paging',
			url: '/bundletasks/xListLogs/'
		},
		remoteSort: true
	});

	return Ext.create('Ext.grid.Panel', {
		scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'Bundle Tasks',
            menuHref: '#/bundletasks',
            menuFavorite: true,
            menuParentStateId: 'grid-bundletasks-view'
		},
		store: store,
		stateId: 'grid-bundletasks-logs-view',
		stateful: true,
        plugins: [ 'gridstore', {
            ptype: 'applyparams',
            hiddenParams: [ 'bundleTaskId' ],
            filterIgnoreParams: [ 'bundleTaskId' ]
        }, {
            ptype: 'rowexpander',
            rowBodyTpl: [
                '<p><b>Message:</b> <span style="white-space: pre">{message}</span></p>'
            ]
        }],

		/*tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}],*/

		viewConfig: {
			emptyText: 'Log is empty for selected bundle task',
			loadingText: 'Loading logs ...'
		},

		columns: [
			{ header: "Date", width: 165, dataIndex: 'dtadded', sortable: true },
			{ header: "Message", flex: 1, dataIndex: 'message', sortable: true }
		],

		dockedItems: [{
			xtype: 'scalrpagingtoolbar',
			store: store,
			dock: 'top',
            items: [{
                xtype: 'filterfield',
                store: store
            }]
		}]
	});
});
