Ext.define('Scalr.ui.FarmRoleEditorTab.Chef', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Bootstrap with Chef',
    //tab: 'chef',
    itemId: 'chef',

    cls: 'x-panel-column-left-with-tabs',

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
        'chef.log_level': undefined,
        'chef.client_rb_template': undefined,
        'chef.solo_rb_template': undefined
    },

    isEnabled: function (record) {
        return this.callParent(arguments) && record.hasBehavior('chef');
    },

    beforeShowTab: function(record, handler) {
        var me = this,
            field = me.down('chefsettings');

        field.clearInvalid();
        record.loadRoleChefSettings(function(data, status){
            if (status) {
                if (!data.roleChefSettings) {
                    field.disableDaemonize = false;
                    Ext.each(record.get('scripting', true) || [], function(script){
                        if (script['script_type'] === 'chef' && script['params'] && !script['params']['chef.cookbook_url']) {
                            field.disableDaemonize = true;
                            return false;
                        }
                    });
                }
                field.setRoleChefSettings(data.roleChefSettings);
                field.farmRoleChefSettings = data.farmRoleChefSettings;
                field.roleOsFamily = Scalr.utils.getOsById(record.get('osId'), 'family');
                field.setValue(data.chefSettings, function(success){
                    success ? handler() : me.deactivateTab();
                });

            } else {
                me.deactivateTab();
            }
        });
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
    __items: [{
        xtype: 'chefsettings',
        mode: 'farmrole'
    }]
});