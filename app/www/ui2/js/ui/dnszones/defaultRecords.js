Scalr.regPage('Scalr.ui.dnszones.defaultRecords', function (loadParams, moduleParams) {
    var records = moduleParams.records;
    var storeRecords = Ext.create('store.store', {
        filterOnLoad: true,
        sortOnLoad: true,
        fields: [
            'issystem', 'name', 'port', 'priority', 'server_id', {name: 'ttl', type: 'string', defaultValue: 14400}, {name: 'type', defaultValue: 'A'}, 'value', 'weight', 'zone_id', 'isnew', 'editing'
        ],
        data: records
    });
    var form = Ext.create('Ext.form.Panel', {
        scalrOptions: {
            reload: true,
            maximize: 'all',
            menuTitle: 'DNS Zones',
            menuHref: '#/dnszones',
            menuParentStateId: 'grid-dnszones-view',
            menuFavorite: true
        },
        cls: 'scalr-ui-dnszone-form',
        layout: {
            type: 'vbox',
            pack: 'start',
            align: 'stretch'
        },
        items: [{
            xtype: 'displayfield',
            cls: 'x-form-field-info x-form-field-info-fit',
            anchor: '100%',
            value: 'Default DNS records will be automatically added to all of your <b>new</b> DNS Zones. To edit existing zones within an Account, go to DNS Zones and choose the Edit DNS Zone option. You can use the <b>%zonename%</b> tag, which will be replaced with the full zone name.'
        },{
            xtype: 'dnsrecords',
            itemId: 'dnsrecords',
            store: storeRecords,
            flex: 1,
            dockedToolbarUi: 'simple',
            stores: {own: storeRecords, system: null}
        }],
        dockedItems: [{
            xtype: 'container',
            dock: 'bottom',
            cls: 'x-docked-buttons-mini',
            layout: {
                type: 'hbox',
                pack: 'center'
            },
            items: [{
                xtype: 'button',
                text: 'Save',
                handler: function() {
                    if (form.getForm().isValid()) {
                        if (!form.down('#dnsrecords').fireEvent('closeeditor')) {
                            return;
                        }
                        var results = {};
                        storeRecords.getUnfiltered().each(function(item, index){
                            results['record-'+index] = item.getData();
                        });
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: form.getForm(),
                            url: '/dnszones/xSaveDefaultRecords/',
                            scope: this,
                            params: {
                                records: Ext.encode(results)
                            },
                            success: function () {
                                Scalr.event.fireEvent('close');
                            },
                            failure: function(data) {
                                if (data.errors) {
                                    Ext.Object.each(data.errors, function(index, item){
                                        storeRecords.getUnfiltered().each(function(record, recIndex){
                                            if (index.replace('record-', '') == recIndex) {
                                                form.down('#dnsrecords').getPlugin('rowediting').startEdit(record, 0);
                                            }
                                        });
                                        Scalr.message.Error(item);
                                        return false;
                                    });
                                }
                            }
                        });
                    }
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
    return form;
});
