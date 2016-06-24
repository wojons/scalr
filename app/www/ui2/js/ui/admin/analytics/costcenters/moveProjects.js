Scalr.regPage('Scalr.ui.admin.analytics.costcenters.moveProjects', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.panel.Panel', {
		scalrOptions: {
			modal: true
		},
		width: 760,
        bodyCls: 'x-container-fieldset',
        layout: 'fit',
        items: [{
            xtype: 'grid',
            cls: 'x-grid-no-highlighting x-costanalytics-notifications-grid x-grid-with-formfields',
            store: {
                fields: ['projectId', 'projectName', 'ccId', {name: 'currentCcId', convert: function(v, record) {return record.data.ccId;}}],
                proxy: 'object'
            },
            viewConfig: {
                emptyText: 'No Projects in this Cost Center',
                deferEmptyText: false
            },
            getValues: function() {
                var result = [];
                this.store.getUnfiltered().each(function(record){
                    result.push({
                        projectId: record.get('projectId'),
                        ccId: record.get('ccId')
                    });
                });
                return result;
            },
            columns: [{
                text: 'Project',
                dataIndex: 'projectName',
                flex: .6
            },{
                text: 'Cost Center',
                dataIndex: 'ccId',
                flex: 1,
                xtype: 'widgetcolumn',
                widget: {
                    xtype: 'combo',
                    store: {
                        fields: ['ccId', 'name', 'billingCode', 'current'],
                        proxy: 'object',
                        data: Ext.Array.map(moduleParams['ccs'] || [], function(item){
                            item.current = item['ccId'] == loadParams['ccId'];
                            return item;
                        }),
                        sorters: [{
                            property: 'name'
                        }]
                    },
                    valueField: 'ccId',
                    displayField: 'name',
                    editable: false,
                    queryMode: 'local',
                    listConfig: {
                        cls: 'x-boundlist-alt',
                        tpl:
                            '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto">' +
                                '<div style="white-space:nowrap;">{name}<tpl if="current"> <i>(current)</i></tpl></div>' +
                            '</div></tpl>'
                    },
                    listeners: {
                        change: function(comp, value){
                            var record = comp.getWidgetRecord();
                            if (record) {
                                record.set('ccId', value);
                            }
                        }
                    }
                }
            }],
        }],
        dockedItems: [{
            xtype: 'container',
            cls: 'x-container-fieldset',
            style: 'padding-bottom:0;background:#f1f5fa',
            dock: 'top',
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-subheader',
                html: 'Move projects to another Cost Center',

            }]
        },{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
                disabled: !moduleParams['projects'].length,
				handler: function() {
                    Scalr.Request({
                        processBox: {
                            type: 'save'
                        },
                        url: '/admin/analytics/costcenters/xMoveProjects/',
                        params: {
                            projects: Ext.encode(form.down('grid').getValues())
                        },
                        success: function (data) {
                            Scalr.event.fireEvent('close');
                        }
                    });
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
            }]
		}]
	});
    form.down('grid').store.load({data: moduleParams['projects']});
	return form;
});
