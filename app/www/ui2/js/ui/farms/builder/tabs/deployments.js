Ext.define('Scalr.ui.FarmRoleEditorTab.Deployments', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Deployments',
    isDeprecated: true,
    itemId: 'deployments',

    cls: 'x-panel-column-left-with-tabs',

    settings: {
        'dm.remote_path': '/var/www',
        'dm.application_id': undefined
    },

    tabData: null,

    isEnabled: function (record) {
        return this.callParent(arguments) && record.get('platform') != 'rds';
    },

    addApp: function (target, app, type) {
        if (type == 'create') {
            this.down('#appList').store.add(app);
            this.tabData = this.tabData || [];
            this.tabData.push(app);
        }
    },

    beforeShowTab: function (record, handler) {
        Scalr.CachedRequestManager.get('farmDesigner').load(
            {
                url: '/dm/applications/xGetApplications/'
            },
            function(data, status){
                this.tabData = data;
                status ? handler() : this.deactivateTab();
            },
            this,
            0
        );
        Scalr.event.on('update', this.addApp, this);
    },


    showTab: function (record) {
        var settings = record.get('settings');
        this.down('[name="dm.application_id"]').store.load({ data: this.tabData || []});
        if(this.down('[name="dm.application_id"]').store.data.length && this.down('[name="dm.application_id"]').store.getAt(0)['id'] != 0)
            this.down('[name="dm.application_id"]').store.insert(0, {id: 0, name: ''});

        this.down('[name="dm.application_id"]').setValue(settings['dm.application_id']);
        this.down('[name="dm.remote_path"]').setValue(settings['dm.remote_path']);
    },

    hideTab: function (record) {
        Scalr.event.un('update', this.addApp, this);
        var settings = record.get('settings');
        settings['dm.application_id'] = this.down('[name="dm.application_id"]').getValue();
        settings['dm.remote_path'] = this.down('[name="dm.remote_path"]').getValue();
        record.set('settings', settings);
    },

    __items: [{
        xtype: 'displayfield',
        cls: 'x-form-field-warning x-form-field-warning-fit',
        anchor: '100%',
        value: Scalr.strings['deprecated_warning']
    },{
        xtype: 'fieldset',
        title: 'Deployment options',
        itemId: 'options',
        cls: 'x-fieldset-separator-none',
        labelWidth: 150,
        items: [{
            xtype: 'fieldcontainer',
            layout: 'hbox',
            items: [{
                fieldLabel: 'Application',
                itemId: 'appList',
                xtype: 'combo',
                store: {
                    fields: [ 'id', 'name' ],
                    proxy: 'object'
                },
                valueField: 'id',
                displayField: 'name',
                editable: false,
                width: 400,
                queryMode: 'local',
                name: 'dm.application_id'
            }, {
                xtype: 'button',
                ui: 'action',
                cls: 'x-btn-action-add',
                tooltip: 'Add new Application',
                margin: '0 0 0 8',
                listeners: {
                    click: function() {
                        Scalr.event.fireEvent('redirect','#/dm/applications/create');
                    }
                }
            }]
        }, {
            xtype:'textfield',
            itemId: 'remotePath',
            width:400,
            fieldLabel: 'Remote path',
            name: 'dm.remote_path'
        }]
    }]
});
