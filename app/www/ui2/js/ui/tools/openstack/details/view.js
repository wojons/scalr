Scalr.regPage('Scalr.ui.tools.openstack.details.view', function (loadParams, moduleParams) {
	var store = Ext.create('store.store', {
		fields: [
			'service', 'alias', 'description', 'links', 'name', 'namespace', 'updated', 'count'
		],
        groupField: 'service',
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}],
		proxy: {
            type: 'cachedrequest',
            crscope: 'grid-openstack-details',
			url: '/tools/openstack/details/xListDetails',
            root: 'details'
		}
	});

	return Ext.create('Ext.grid.Panel', {
        plugins: [{
            ptype: 'localcachedrequest',
            crscope: 'grid-openstack-details'
        }, {
            ptype: 'applyparams',
            filterIgnoreParams: [ 'platform' ]
		}],
		scalrOptions: {
			reload: true,
			maximize: 'all',
            menuTitle: Scalr.utils.getPlatformName(loadParams['platform']) + ' details'
		},
		store: store,
		stateId: 'grid-openstack-details-view',
		stateful: true,
        features: [{
            id: 'grouping',
            ftype: 'grouping',
            startCollapsed: true,
            groupHeaderTpl: Ext.create('Ext.XTemplate',
                '{children:this.getGroupName}',
                {
                    getGroupName: function(children) {
                        if (children.length > 0) {
                            var name = children[0].get('service'),
                                count = children[0].get('count');
                            return Ext.String.capitalize(name) + (count ? ' (' + count + ')' : '');
                        }
                    }
                }
            )
        }],

		viewConfig: {
			emptyText: 'No details loaded',
			loadingText: 'Loading details ...',
            loadMask: false,
            plugins: {
                ptype: 'dynemptytext',
                emptyText: 'No records found to match your search',
                emptyTextNoItems: 'No details loaded'
            }
		},
        cls: 'x-grid-with-formfields',
        disableSelection: true,
        hideHeaders: true,
		columns: [
			{header: 'Name', width: 320, dataIndex: 'name'},
            {header: 'Alias', width: 220, dataIndex: 'alias'},
            {header: 'Description', flex: 1, dataIndex: 'description'},
		],

		dockedItems: [{
            xtype: 'toolbar',
            margin: '0 0 1 0',
			items: [{
                xtype: 'filterfield',
                store: store,
                filterFields: ['name', 'alias', 'service']
            }, ' ', {
                xtype: 'cloudlocationfield',
                platforms: [loadParams['platform']],
				gridStore: store
            }]
		}]
	});
});
