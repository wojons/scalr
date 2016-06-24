Ext.define('Scalr.ui.RoleDesignerTabEnvironments', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditenvironments',
    cls: 'x-panel-column-left x-panel-column-left-with-tabs',
    layout: 'fit',
    items: [{
        xtype: 'fieldset',
        layout: 'fit',//with layout fit empty text is not visible
        items: [{
            xtype: 'grid',
            trackMouseOver: false,
            disableSelection: true,
            //flex: 1,
            maxWidth: 700,
            hideHeaders: true,
            scrollable: 'y',
            store: {
                fields: ['id', 'name', 'enabled'],
                proxy: 'object',
                sorters: {
                    property: 'name'
                }
            },
            viewConfig: {
                emptyText: '<div class="x-semibold title">No environments were found to match your search.</div>Try modifying your search criteria.',
                deferEmptyText: false,
                preserveScrollOnRefresh: false,
                markDirty: false
            },
            columns: [{
                xtype: 'roleenvironmentscolumn',
                flex: 1
            }],
            dockedItems: [{
                xtype: 'toolbar',
                dock: 'top',
                ui: 'inline',
                items: [{
                    xtype: 'filterfield',
                    width: 200,
                    filterFields: ['name', 'group', function(record){
                        var permissions = record.get('permissions');
                        if (permissions) {
                            permissions = Ext.Object.getKeys(permissions).join(' ');
                        }
                        return permissions;
                    }],
                    submitValue: false,
                    excludeForm: true,
                    listeners: {
                        added: function() {
                            this.store = this.up('grid').store;
                        }
                    }
                }, {
                    xtype: 'buttongroupfield',
                    margin: '0 0 0 18',
                    maxWidth: 600,
                    isFormField: false,
                    value: 'all',
                    layout: 'hbox',
                    flex: 1,
                    defaults: {
                        flex: 1
                    },
                    items: [{
                        text: 'All',
                        value: 'all'
                    }, {
                        text: 'Available',
                        cls: 'x-full-access',
                        value: 1
                    }, {
                        text: 'Restricted',
                        cls: 'x-no-access',
                        value: 0
                    }],
                    listeners: {
                        change: function (comp, value) {
                            var filterId = 'granted',
                                filters = [],
                                store = comp.up('grid').getStore();
                            store.removeFilter(filterId);
                            if (value !== 'all') {
                                filters.push({
                                    id: filterId,
                                    filterFn: function (record) {
                                        return record.get('enabled') == value;
                                    }
                                });
                                store.addFilter(filters);
                            }
                        }
                    }
                }, {
                    xtype: 'button',
                    itemId: 'approve',
                    iconCls: 'x-btn-icon-approve',
                    cls: 'x-btn-green',
                    tooltip: 'Set Role as Available on all environments listed below',
                    margin: '0 0 0 78',
                    handler: function() {
                        var store = this.up('grid').getStore(), autoFilter;

                        autoFilter = store.getAutoFilter();
                        store.setAutoFilter(false);
                        store.each(function(r) {
                            r.set('enabled', 1);
                        });
                        store.setAutoFilter(autoFilter);
                    }
                }, {
                    xtype: 'button',
                    itemId: 'decline',
                    iconCls: 'x-btn-icon-decline',
                    cls: 'x-btn-red',
                    margin: '0 0 0 10',
                    tooltip: 'Set Role as Restricted on all environments listed below',
                    handler: function() {
                        var store = this.up('grid').getStore(), autoFilter;

                        autoFilter = store.getAutoFilter();
                        store.setAutoFilter(false);
                        store.each(function(r) {
                            r.set('enabled', 0);
                        });
                        store.setAutoFilter(autoFilter);
                    }
                }]
            }]
        }]
    }],

    initComponent: function() {
        this.callParent(arguments);
        this.addListener({
            showtab: {
                fn: function(params){
                    var role = params['role'] || {},
                        grid = this.down('grid');
                    grid.getStore().load({data: role['environments']});
                },
                single: true
            },
            hidetab: function(params) {
                var store = this.down('grid').getStore();
                params['role']['environments'].length = 0;
                store.getUnfiltered().each(function(record){
                    params['role']['environments'].push({
                        id: record.get('id'),
                        name: record.get('name'),
                        enabled: record.get('enabled')
                    });
                });
            }
        });
    },
    getSubmitValues: function() {
        var store = this.down('grid').getStore(),
            result = [];

        store.getUnfiltered().each(function(record){
            result.push({
                id: record.get('id'),
                name: record.get('name'),
                enabled: record.get('enabled')
            });
        });

        return { environments: result };
    },
    isValid: function(params) {
        var cnt = 0, all = 0;
        this.down('grid').getStore().getUnfiltered().each(function(r) {
            all++;
            if (r.get('enabled') == 0) {
                cnt++;
            }
        });

        return cnt == all ? 'You should allow at least one environment.' : true;
    }
});
