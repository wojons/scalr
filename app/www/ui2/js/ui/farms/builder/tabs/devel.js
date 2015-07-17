Ext.define('Scalr.ui.FarmRoleEditorTab.Devel', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Development',
    itemId: 'devel',

    cls: 'x-panel-column-left-with-tabs',

    settings: {
        'user-data.scm_branch': '',
        'user-data.szr_version': '',
        'base.custom_user_data': '',
        'base.devel_repository': '',
        'openstack.boot_from_volume': 0,
        'openstack.keep_fip_on_suspend': 0
    },

    isEnabled: function (record) {
        return this.callParent(arguments) && Scalr.flags['betaMode'];
    },

    showTab: function (record) {
        var settings = record.get('settings'),
            tabParams = this.up('#farmDesigner').moduleParams.tabParams;

        this.down('[name="base.devel_repository"]').store.loadData(Ext.Array.map(tabParams['scalr.scalarizr_update.devel_repos']||[], function(item){return [item, item]}));

        this.down('[name="user-data.scm_branch"]').setValue(settings['user-data.scm_branch'] || 'master');
        this.down('[name="user-data.szr_version"]').setValue(settings['user-data.szr_version'] || '');
        this.down('[name="base.custom_user_data"]').setValue(settings['base.custom_user_data'] || '');
        this.down('[name="base.devel_repository"]').setValue(settings['base.devel_repository'] || '');
        this.down('[name="openstack.boot_from_volume"]').setValue(settings['openstack.boot_from_volume'] || 0);
        this.down('[name="openstack.keep_fip_on_suspend"]').setValue(settings['openstack.keep_fip_on_suspend'] || 0);

        this.down('[name="user-data.enabled"]')[settings['user-data.scm_branch'] || settings['user-data.szr_version'] ? 'expand' : 'collapse']();
    },

    hideTab: function (record) {
        var settings = record.get('settings'),
            userDataEnabled = !this.down('[name="user-data.enabled"]').collapsed;

        settings['user-data.scm_branch'] = userDataEnabled ? this.down('[name="user-data.scm_branch"]').getValue() : '';
        settings['user-data.szr_version'] = userDataEnabled ? this.down('[name="user-data.szr_version"]').getValue() : '';
        settings['base.devel_repository'] = userDataEnabled ? this.down('[name="base.devel_repository"]').getValue() : '';

        settings['base.custom_user_data'] = this.down('[name="base.custom_user_data"]').getValue();
        settings['openstack.boot_from_volume'] = this.down('[name="openstack.boot_from_volume"]').getValue();
        settings['openstack.keep_fip_on_suspend'] = this.down('[name="openstack.keep_fip_on_suspend"]').getValue();

        record.set('settings', settings);
    },

    __items: [{
        xtype: 'fieldset',
        title: ' Scalarizr branch & version',
        name: 'user-data.enabled',
        checkboxToggle: true,
        collapsible: true,
        toggleOnTitleClick: true,
        defaults: {
            maxWidth: 600,
            anchor: '100%',
            labelWidth: 130
        },
        items: [{
            xtype: 'textfield',
            fieldLabel: 'SCM Branch',
            name: 'user-data.scm_branch'
        }, {
            xtype: 'textfield',
            fieldLabel: 'Scalarizr version',
            name: 'user-data.szr_version'
        }, {
            xtype: 'combo',
            queryMode: 'local',
            valueField: 'value',
            displayField: 'text',
            store: Ext.create('Ext.data.ArrayStore', {fields: ['value', 'text']}),
            editable: false,
            fieldLabel: 'Devel repository',
            name: 'base.devel_repository'
        }]
    }, {
        xtype: 'fieldset',
        itemId: 'additionaltags',
        title: 'Custom user-data&nbsp;&nbsp;<img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-globalvars" data-qtip="Custom user-data supports Global Variable Interpolation" />',
        collapsible: true,
        collapsed: true,
        toggleOnTitleClick: true,
        items: [{
            xtype: 'textarea',
            width: 600,
            height: 200,
            name: 'base.custom_user_data'
        }]
    },{
        xtype: 'fieldset',
        cls: 'x-fieldset-separator-none',
        items: [{
            xtype: 'checkbox',
            name: 'openstack.boot_from_volume',
            boxLabel: 'Use cinder volume as root device'
        }, {
            xtype: 'checkbox',
            name: 'openstack.keep_fip_on_suspend',
            boxLabel: 'Keep floating IP on server suspend'
        }]
    }]
});
