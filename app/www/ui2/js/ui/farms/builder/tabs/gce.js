Ext.define('Scalr.ui.FarmRoleEditorTab.Gce', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'GCE settings',
    itemId: 'gce',
    layout: 'anchor',
    cls: 'x-panel-column-left-with-tabs x-container-fieldset',

    settings: {
        'gce.on-host-maintenance': 'MIGRATE'
    },

    isEnabled: function (record) {
        return this.callParent(arguments) && record.get('platform') == 'gce';
    },

    showTab: function (record) {
        var settings = record.get('settings', true);
        this.down('[name="gce.on-host-maintenance"]').setValue(settings['gce.on-host-maintenance'] || 'MIGRATE');
    },

    hideTab: function (record) {
        var settings = record.get('settings');
        settings['gce.on-host-maintenance'] = this.down('[name="gce.on-host-maintenance"]').getValue();
        record.set('settings', settings);
    },

    __items: [{
        xtype: 'combo',
        store: [['TERMINATE', 'TERMINATE'], ['MIGRATE', 'MIGRATE']],
        valueField: 'name',
        displayField: 'description',
        fieldLabel: 'Maintenance behavior',
        editable: false,
        queryMode: 'local',
        name: 'gce.on-host-maintenance',
        labelWidth: 170,
        width: 400
    }]
});
