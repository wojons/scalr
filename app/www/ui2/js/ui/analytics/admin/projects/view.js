Scalr.regPage('Scalr.ui.analytics.admin.projects.view', function (loadParams, moduleParams) {
    Scalr.utils.Quarters.days = moduleParams['quarters'];
	var store = Ext.create('store.store', {
        data: moduleParams['projects'],
		fields: [
            'projectId',
			'ccId',
            'ccName',
            'name',
			'billingCode',
            'description',
            'farmsCount',
            'growth',
            'growthPct',
            {name: 'periodTotal', type: 'float'},
            'budget',
            {name: 'budgetSpentPct', defaultValue: null},
            'budgetSpent',
            'archived',
            'shared',
            'accountId',
            'accountName',
            'envId',
            'envName',
            {name: 'id', convert: function(v, record){;return record.data.projectId;}}
		],
		sorters: [{
			property: 'name',
			transform: function(value){
				return value.toLowerCase();
			}
		}],
		proxy: {
			type: 'ajax',
			url: '/analytics/projects/xList',
            reader: {
                type: 'json',
                root: 'projects'
            }
		}
	});

	var panel = Ext.create('widget.costanalyticslistpanel', {
		scalrOptions: {
			title: 'Projects',
			reload: false,
			maximize: 'all',
            leftMenu: {
                menuId: 'analytics',
                itemId: 'projects'
            }
		},
        listeners: {
            applyparams: function(params) {
                this.applyParams({itemId: params.projectId});
            }
        },
        subject: 'projects',
        store: store
	});

	Scalr.event.on('update', function (type, project) {
        var dataview = panel.down('costanalyticslistview');
		if (type === '/analytics/projects/edit' || type === '/analytics/projects/add') {
            var record = dataview.store.getById(project.projectId);
            if (!record) {
                record = dataview.store.add(project)[0];
                dataview.getSelectionModel().select(record);
            } else {
                record.set(project);
                panel.down('#form').loadRecord(record);
            }
        } else if (type === '/analytics/projects/remove') {
            var record = dataview.store.getById(project.projectId);
            if (record) {
                if (project.removable || record.get('periodTotal') == 0) {
                    dataview.store.remove(record);
                } else {
                    record.set('archived', true);
                }
            }
        } else if (type === '/analytics/budgets/edit') {
            panel.refreshStoreOnReconfigure = true;
        }
    }, panel);

	return panel;
});