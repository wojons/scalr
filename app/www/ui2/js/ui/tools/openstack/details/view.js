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
            crscope: 'tools-openstack-details',
			url: '/tools/openstack/details/xListDetails/',
            root: 'details'
		}
	});

	return Ext.create('Ext.grid.Panel', {
		title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Details',
        plugins: {
            ptype: 'localcachedrequest',
            crscope: 'farmbuilder'
		},
		scalrOptions: {
			'reload': true,
			'maximize': 'all'
		},
		store: store,
		//stateId: 'grid-tools-openstack-details-view',
		//stateful: true,
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


		tools: [{
			xtype: 'favoritetool',
			favorite: {
				text: 'Openstack details',
				href: '#/tools/openstack/details?platform=' + loadParams['platform']
			}
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
        cls: 'x-grid-with-formfields x-grid-no-highlighting',
        hideHeaders: true,
		columns: [
			{header: 'Name', width: 320, dataIndex: 'name'},
            {header: 'Alias', width: 220, dataIndex: 'alias'},
            {header: 'Description', flex: 1, dataIndex: 'description'},
		],

		dockedItems: [{
            xtype: 'toolbar',
			items: [{
                xtype: 'filterfield',
                store: store,
                filterFields: ['name', 'alias', 'service'],
                listeners: {
                    afterfilter1: function(){
                        //workaround of the extjs grouped store/grid bug
                        var grid = this.up('grid'),
                            grouping = grid.getView().getFeature('grouping');
                        if (grid.headerCt.rendered) {
                            grid.suspendLayouts();
                            grouping.disable();
                            grouping.enable();
                            grid.resumeLayouts(true);
                        }
                    }
                }
            }, {
				xtype: 'combo',
                margin: '0 0 0 12',
                fieldLabel: 'Location',
                labelWidth: 53,
                width: 358,
                matchFieldWidth: false,
                listConfig: {
                    width: 'auto',
                    minWidth: 300
                },
                iconCls: 'no-icon',
                displayField: 'name',
                valueField: 'id',
                editable: false,
                queryMode: 'local',
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.locations,
					proxy: 'object'
				},
                listeners: {
                    change: function(comp, value) {
                        Ext.apply(store.getProxy(), {
                            params: {
                                platform: loadParams['platform'],
                                cloudLocation: value
                            }
                        });
                        store.load();
                    },
                    added: function() {
                        if (this.store.getCount()) {
                            this.setValue(this.store.getAt(0).get('id'));
                        }
                    }
                }
			}]
		}]
	});
});
