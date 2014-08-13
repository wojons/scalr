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
            'chef.daemonize': undefined,
            'chef.log_level': undefined
        },

        isEnabled: function (record) {
            return record.hasBehavior('chef');
        },

        beforeShowTab: function(record, handler) {
            var me = this,
                field = me.down('chefsettings');

            record.loadRoleChefSettings(function(data, status){
                if (status) {
                    if (!data.roleChefEnabled && Scalr.flags['betaMode']) {
                        field.disableDaemonize = false;
                        Ext.each(record.get('scripting', true) || [], function(script){
                            if (script['script_type'] === 'chef' && script['params'] && !script['params']['chef.cookbook_url']) {
                                field.disableDaemonize = true;
                                return false;
                            }
                        });
                    }
                    field.setReadOnly(data.roleChefEnabled);
                    field.setValue(data.chefSettings, function(success){
                        success ? handler() : me.deactivateTab();
                    });

                } else {
                    me.deactivateTab();
                }
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