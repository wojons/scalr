Scalr.regPage('Scalr.ui.farms.builder.tabs.chef', function (tabParams) {
    return Ext.create('Scalr.ui.FarmsBuilderTab', {
        tabTitle: 'Bootstrap with Chef',
        tab: 'chef',

        settings: {
            'chef.bootstrap': undefined,
            'chef.cookbook_url': undefined,
            'chef.cookbook_url_type': undefined,
            'chef.relative_path': undefined,
            'chef.ssh_private_key': undefined,
            'chef.server_id': undefined,
            'chef.role_name': undefined,
            'chef.environment': undefined,
            'chef.runlist': '',
            'chef.attributes': undefined,
            'chef.node_name_tpl' : undefined,
            'chef.daemonize': undefined
        },

        isEnabled: function (record) {
            return record.get('behaviors').match('chef');
        },

        beforeShowTab: function(record, handler) {
            var me = this,
                settings = record.get('settings', true),
                chefSettings = {},
                field = this.down('chefsettings');
            Ext.Object.each(me.settings, function(key, value){
                chefSettings[key] = settings[key];
            });
            field.setValue(chefSettings, function(success){
                success ? handler() : me.deactivateTab();
            });
            field.clearInvalid();
        },

        showTab: function (record) {
        },

        hideTab: function (record) {
            var me = this,
                settings = record.get('settings');
            Ext.Object.each(me.settings, function(key, value){
                delete settings[key];
            });
            Ext.apply(settings, this.down('chefsettings').getValue());
            record.set('settings', settings);
        },
        items: [{
            xtype: 'chefsettings'
        }]
    });
});