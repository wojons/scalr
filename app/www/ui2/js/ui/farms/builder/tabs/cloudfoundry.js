Ext.define('Scalr.ui.FarmRoleEditorTab.Cloudfoundry', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'CloudFoundry settings',
    itemId: 'cloudfoundry',

    cls: 'x-panel-column-left-with-tabs',

    settings: {
        'cf.data_storage.engine': 'ebs',
        'cf.data_storage.ebs.size': 5
    },

    isEnabled: function (record) {
        return this.callParent(arguments) && (record.get('behaviors').match('cf_') && record.get('platform') == 'ec2');
    },

    showTab: function (record) {
        var settings = record.get('settings');

        if (record.get('new') && record.get('behaviors').match('cf_cloud_controller'))
            this.down('[name="cf.data_storage.ebs.size"]').setReadOnly(false);
        else
            this.down('[name="cf.data_storage.ebs.size"]').setReadOnly(true);

        this.down('[name="cf.data_storage.engine"]').setReadOnly(true);


        this.down('[name="cf.data_storage.ebs.size"]').setValue(settings['cf.data_storage.ebs.size']);
        this.down('[name="cf.data_storage.engine"]').setValue(settings['cf.data_storage.engine']);
    },

    hideTab: function (record) {
        var settings = record.get('settings');

        if (record.get('new'))
            settings['cf.data_storage.ebs.size'] = this.down('[name="cf.data_storage.ebs.size"]').getValue();

        settings['cf.data_storage.engine'] = this.down('[name="cf.data_storage.engine"]').getValue();

        record.set('settings', settings);
    },

    __items: [{
        xtype: 'fieldset',
        title: 'Cloud Controller data storage settings',
        items: [{
            xtype: 'displayfield',
            fieldLabel: 'Storage engine',
            name: 'cf.data_storage.engine',
            value: 'ebs',
            labelWidth: 100
        }, {
            xtype: 'textfield',
            fieldLabel: 'EBS size (max. 1000 GB)',
            labelWidth: 160,
            width: 200,
            name: 'cf.data_storage.ebs.size'
        }]
    }]
});
