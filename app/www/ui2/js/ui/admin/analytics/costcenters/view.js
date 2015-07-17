Scalr.regPage('Scalr.ui.admin.analytics.costcenters.view', function (loadParams, moduleParams) {
    Scalr.utils.Quarters.days = moduleParams['quarters'];

	var store = Ext.create('store.store', {
        data: moduleParams['ccs'],
        model: Ext.define(null, {
            extend: 'Ext.data.Model',
            idProperty: 'ccId',
            fields: [
                'ccId',
                'name',
                'billingCode',
                'description',
                'envCount',
                'projectsCount',
                'growth',
                'growthPct',
                {name: 'periodTotal', type: 'float'},
                'budget',
                'budgetSpentPct',
                'budgetRemainPct',
                'budgetSpent',
                'archived',
                //{name: 'id', convert: function(v, record){;return record.data.ccId;}}
            ],
        }),
        remoteFilter: true,
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}],
		proxy: {
			type: 'ajax',
			url: '/admin/analytics/costcenters/xList',
            reader: {
                type: 'json',
                rootProperty: 'ccs'
            }
		}
	});

	var panel = Ext.create('widget.costanalyticslistpanel', {
		scalrOptions: {
			reload: false,
            maximize: 'all',
            menuTitle: 'Cost analytics',
            menuSubTitle: 'Cost centers',
            menuHref: '#/admin/analytics/dashboard',
            menuParentStateId: 'panel-admin-analytics',
            leftMenu: {
                menuId: 'analytics',
                itemId: 'costcenters'
            }
		},
        listeners: {
            applyparams: function(params) {
                this.applyParams({itemId: params.ccId});
            }
        },
        subject: 'costcenters',
        store: store
	});

	Scalr.event.on('update', function (type, item) {
        var dataview = panel.down('costanalyticslistview');
		if (type === '/admin/analytics/costcenters/edit') {
            var record = dataview.store.getById(item.ccId);
            if (!record) {
                record = dataview.store.add(item)[0];
                dataview.getSelectionModel().select(record);
            } else {
                record.set(item);
                panel.down('#form').loadRecord(record);
            }
            dataview.getNode(record, true, false).scrollIntoView(dataview.el);

        } else if (type === '/admin/analytics/costcenters/remove') {
            var record = dataview.store.getById(item.ccId);
            if (record) {
                if (item.removable || record.get('periodTotal') == 0) {
                    dataview.store.remove(record);
                } else {
                    record.set('archived', true);
                }
            }
        } else if (type === '/admin/analytics/budgets/edit') {
            panel.refreshStoreOnReconfigure = true;
        } else if (type === '/analytics/projects/add') {
            var record = dataview.store.getById(item.ccId);
            if (record && record.get('projectsCount') == 0) {
                record.set('projectsCount', record.get('projectsCount') + 1);
                if (dataview.getSelectionModel().getLastSelected() === record) {
                    if (panel.isVisible()) {
                        panel.down('#form').loadRecord(record);
                    } else {
                        dataview.getSelectionModel().deselectAll();
                    }
                }
            }
        }
    }, panel);

	return panel;
});

