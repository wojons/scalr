Ext.define('Scalr.ui.FarmRoleEditorTab.Azure', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Azure',
    itemId: 'azure',
    layout: 'anchor',
    cls: 'x-panel-column-left-with-tabs',

    settings: {
        'base.custom_tags': undefined
    },

    isEnabled: function (record) {
        return this.callParent(arguments) && record.get('platform') == 'azure';
    },

    getTabTitle: function(record) {
        return Scalr.utils.getPlatformName(record.get('platform'));
    },

    showTab: function (record) {
        var settings = record.get('settings', true),
            limits = Scalr.getGovernance(record.get('platform')),
            field,
            params = {
                resourceGroup: settings['azure.resource-group'],
                cloudLocation: record.get('cloud_location')
            };

        field = this.down('[name="azure.storage-account"]');
        field.store.proxy.params = params;
        field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(params);
        if (settings['azure.storage-account']) {
            field.store.loadData([{id: settings['azure.storage-account'], name: settings['azure.storage-account']}]);
        }
        field = this.down('[name="azure.availability-set"]');
        field.store.proxy.params = params;
        field.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString(params);
        if (settings['azure.availability-set']) {
            field.store.loadData([{id: settings['azure.availability-set'], name: settings['azure.availability-set']}]);
        }

        this.setFieldValues({
           'azure.resource-group': settings['azure.resource-group'],
           'azure.storage-account': settings['azure.storage-account'],
           'azure.availability-set': settings['azure.availability-set']
        });

        //tags
        var tags = {};
        Ext.Array.each((settings['base.custom_tags']||'').replace(/(\r\n|\n|\r)/gm,'\n').split('\n'), function(tag){
            var pos = tag.indexOf('='),
                name = tag.substring(0, pos);
            if (name) tags[name] = tag.substring(pos+1);
        });

        field = this.down('[name="base.custom_tags"]');
        if (limits['azure.tags'] !== undefined) {
            if (limits['azure.tags'].allow_additional_tags == 1) {
                field.setReadOnly(false);
                field.setValue(tags, limits['azure.tags'].value);
            } else {
                field.setReadOnly(true);
                field.setValue(null, limits['azure.tags'].value);
            }
        } else {
            field.setReadOnly(false);
            field.setValue(tags);
        }

    },

    hideTab: function (record) {
        var me = this,
            field,
            settings = record.get('settings');
        settings['azure.storage-account'] = me.down('[name="azure.storage-account"]').getValue();
        settings['azure.availability-set'] = me.down('[name="azure.availability-set"]').getValue();

        //tags
        field = me.down('[name="base.custom_tags"]');
        if (!field.readOnly) {
            var tags = [];
            Ext.Object.each(field.getValue(), function(key, value){
                tags.push(key + '=' + value);
            });
            settings['base.custom_tags'] = tags.join('\n');
        }

        record.set('settings', settings);
    },

    __items: [{
        xtype: 'fieldset',
        cls: 'x-fieldset-separator-none',
        layout: 'anchor',
        defaults: {
            anchor: '100%',
            maxWidth: 660,
            labelWidth: 130
        },
        items: [{
            xtype: 'displayfield',
            name: 'azure.resource-group',
            fieldLabel: 'Resource group'
        },{
            xtype: 'combo',
            name: 'azure.storage-account',
            fieldLabel: 'Storage account',
            editable: false,
            allowBlank: false,
            queryCaching: false,
            clearDataBeforeQuery: true,
            store: {
                fields: ['id', 'name'],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'farmDesigner',
                    url: '/platforms/azure/xGetOptions',
                    root: 'storageAccounts'
                }
            },
            valueField: 'id',
            displayField: 'name',
            plugins: [{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/azure/storageAccounts/create'
            }],
            listeners: {
                addnew: function(item) {
                    Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                        url: '/platforms/azure/xGetOptions',
                        params: this.store.proxy.params
                    });
                }
            }
        },{
            xtype: 'combo',
            name: 'azure.availability-set',
            fieldLabel: 'Availability set',
            editable: false,
            allowBlank: false,
            queryCaching: false,
            clearDataBeforeQuery: true,
            store: {
                fields: ['id', 'name'],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'farmDesigner',
                    url: '/platforms/azure/xGetOptions',
                    root: 'availabilitySets'
                }
            },
            valueField: 'id',
            displayField: 'name',
            plugins: [{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/azure/availabilitySets/create'
            }],
            listeners: {
                addnew: function(item) {
                    Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                        url: '/platforms/azure/xGetOptions',
                        params: this.store.proxy.params
                    });
                }
            }
        }]
    },{
        xtype: 'fieldset',
        title: 'Tags',
        cls: 'x-fieldset-separator-none',
        defaults: {
            anchor: '100%',
            maxWidth: 820,
            labelWidth: 140
        },
        items: [{
            xtype: 'ec2tagsfield',
            name: 'base.custom_tags',
            tagsLimit: 0,
            cloud: 'azure'
        }]
    }]
});

