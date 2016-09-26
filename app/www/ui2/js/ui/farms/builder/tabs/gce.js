Ext.define('Scalr.ui.FarmRoleEditorTab.Gce', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'GCE settings',
    itemId: 'gce',
    layout: 'anchor',
    cls: 'x-panel-column-left-with-tabs',

    settings: {
        'gce.on-host-maintenance': 'MIGRATE',
        'gce.instance_permissions': undefined
    },

    isEnabled: function (record) {
        return this.callParent(arguments) && record.get('platform') == 'gce';
    },

    showTab: function (record) {
        var settings = record.get('settings', true);
        this.down('[name="gce.on-host-maintenance"]').setValue(settings['gce.on-host-maintenance'] || 'MIGRATE');
        this.down('[name="gce.instance_permissions"]').setValue(settings['gce.instance_permissions']);
    },

    hideTab: function (record) {
        var settings = record.get('settings'),
            value;
        settings['gce.on-host-maintenance'] = this.down('[name="gce.on-host-maintenance"]').getValue();
        settings['gce.instance_permissions'] = this.down('[name="gce.instance_permissions"]').getValue();
        record.set('settings', settings);
    },

    __items: [{
        xtype:'container',
        cls: 'x-container-fieldset x-fieldset-separator-bottom',
        items: [{
            xtype: 'combo',
            store: [['TERMINATE', 'TERMINATE'], ['MIGRATE', 'MIGRATE']],
            valueField: 'name',
            displayField: 'description',
            fieldLabel: 'Maintenance behavior',
            editable: false,
            queryMode: 'local',
            name: 'gce.on-host-maintenance',
            labelWidth: 170,
            margin: 0,
            maxWidth: 400
        }]
    }, {
        xtype: 'fieldset',
        title: 'Service account permissions',
        cls: 'x-fieldset-separator-none',
        items: [{
            xtype: 'valuelistfield',
            name: 'gce.instance_permissions',
            itemName: 'permission',
            hideHeaders: true,
            maxWidth: 600,
            isValueValid: function(value) {
                return Ext.form.field.VTypes.url(value) || 'Invalid URL';
            },
            forcedItems: [
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/compute',
                'https://www.googleapis.com/auth/devstorage.full_control'
            ]
        }]
    }]
});
