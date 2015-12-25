Ext.define('Scalr.ui.FarmRoleEditorTab.Rabbitmq', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'RabbitMQ settings',
    itemId: 'rabbitmq',

    cls: 'x-panel-column-left-with-tabs',

    settings: {
        'rabbitmq.data_storage.engine': function(record){return record.getRabbitMQDefaultStorageEngine()},
        'rabbitmq.data_storage.ebs.size': 2,
        'rabbitmq.nodes_ratio': '10%'
    },

    isEnabled: function(record){
        return this.callParent(arguments) && record.get('behaviors').match('rabbitmq');
    },

    showTab: function(record){
        var settings = record.get('settings', true),
            platform = record.get('platform', true),
            storages = [],
            field;

        if (platform === 'ec2') {
            storages.push({name:'ebs', description:'Single EBS Volume'});
        } else if (Scalr.isOpenstack(platform)) {
            storages.push({name:'cinder', description:'Cinder volume'});
        } else if (Scalr.isCloudstack(platform)) {
            storages.push({name:'csvol', description:'CloudStack Block Volume'});
        } else if (platform === 'gce') {
            storages.push({name:'gce_persistent', description:'GCE Persistent disk'});
        }

        field = this.down('[name="rabbitmq.data_storage.engine"]')
        field.store.load({data: storages});
        field.setValue(settings['rabbitmq.data_storage.engine']);

        field = this.down('[name="rabbitmq.data_storage.ebs.size"]');
        field.setReadOnly(!record.get('new'));
        field.setValue(settings['rabbitmq.data_storage.ebs.size']);

        this.down('[name="rabbitmq.nodes_ratio"]').setValue(settings['rabbitmq.nodes_ratio']);
    },

    hideTab: function(record){
        var settings = record.get('settings');

        settings['rabbitmq.data_storage.engine'] = this.down('[name="rabbitmq.data_storage.engine"]').getValue();

        settings['rabbitmq.nodes_ratio'] = this.down('[name="rabbitmq.nodes_ratio"]').getValue();

        if (record.get('new'))  {
            settings['rabbitmq.data_storage.ebs.size'] = this.down('[name="rabbitmq.data_storage.ebs.size"]').getValue();
        }

        record.set('settings', settings);
    },

    __items: [{
        xtype: 'fieldset',
        title: 'General settings',
        items: [{
            xtype: 'textfield',
            fieldLabel: 'Disk nodes / RAM nodes ratio',
            name: 'rabbitmq.nodes_ratio',
            value: '10%',
            width: 400,
            labelWidth: 210
        }]
    }, {
        xtype: 'fieldset',
        title: 'Data storage settings',
        items: [{
            xtype: 'combo',
            name: 'rabbitmq.data_storage.engine',
            fieldLabel: 'Storage engine',
            editable: false,
            store: {
                fields: [ 'description', 'name' ],
                proxy: 'object'
            },
            valueField: 'name',
            displayField: 'description',
            width: 400,
            labelWidth: 210,
            queryMode: 'local',
            listeners: {
                change: function(){
                    this.up('#rabbitmq').down('[name="ebs_settings"]').show();
                }
            }
        }]
    }, {
        xtype: 'fieldset',
        name: 'ebs_settings',
        title: 'Block Storage settings',
        hidden: true,
        items: [{
            xtype: 'textfield',
            fieldLabel: 'Storage size (max. 1000 GB)',
            labelWidth: 210,
            width: 400,
            name: 'rabbitmq.data_storage.ebs.size',
            vtype: 'num',
            allowBlank: false
        }]
    }]
});
