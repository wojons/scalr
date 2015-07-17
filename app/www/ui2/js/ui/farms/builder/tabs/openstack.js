Ext.define('Scalr.ui.FarmRoleEditorTab.Openstack', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'Openstack',
    itemId: 'openstack',
    layout: 'anchor',
    cls: 'x-panel-column-left-with-tabs',

    settings: {
        'base.custom_tags': undefined,
        'base.instance_name_format': undefined
    },

    isEnabled: function (record) {
        return this.callParent(arguments) && Scalr.utils.isOpenstack(record.get('platform'));
    },

    getTabTitle: function(record) {
        return Scalr.utils.getPlatformName(record.get('platform'));
    },

    showTab: function (record) {
        var settings = record.get('settings', true),
            limits = Scalr.getGovernance(record.get('platform')),
            field;

        var tags = {};
        Ext.Array.each((settings['base.custom_tags']||'').replace(/(\r\n|\n|\r)/gm,'\n').split('\n'), function(tag){
            var pos = tag.indexOf('='),
                name = tag.substring(0, pos);
            if (name) tags[name] = tag.substring(pos+1);
        });

        field = this.down('[name="base.custom_tags"]');
        if (limits['openstack.tags'] !== undefined) {
            if (limits['openstack.tags'].allow_additional_tags == 1) {
                field.setReadOnly(false);
                field.setValue(tags, limits['openstack.tags'].value);
            } else {
                field.setReadOnly(true);
                field.setValue(null, limits['openstack.tags'].value);
            }
        } else {
            field.setReadOnly(false);
            field.setValue(tags);
        }

        field = this.down('[name="base.instance_name_format"]');
        if (limits['openstack.instance_name_format']) {
            field.setValueWithGovernance(settings['base.instance_name_format'], limits['openstack.instance_name_format'].value);
        } else {
            field.setValue(settings['base.instance_name_format']);
        }
    },

    hideTab: function (record) {
        var me = this,
            settings = record.get('settings'),
            field;

        field = me.down('[name="base.custom_tags"]');
        if (!field.readOnly) {
            var tags = [];
            Ext.Object.each(field.getValue(), function(key, value){
                tags.push(key + '=' + value);
            });
            settings['base.custom_tags'] = tags.join('\n');
        }

        field = me.down('[name="base.instance_name_format"]');
        if (!field.readOnly) {
            settings['base.instance_name_format'] = field.getValue();
        }

        record.set('settings', settings);
    },

    items: [{
        xtype: 'fieldset',
        layout: 'anchor',
        defaults: {
            anchor: '100%',
            maxWidth: 820,
            labelWidth: 160
        },
        items: [{
            xtype: 'textfield',
            name: 'base.instance_name_format',
            fieldLabel: 'Instance name',
            emptyText: '{SCALR_SERVER_ID}',
            flex: 1,
            plugins: {
                ptype: 'fieldicons',
                position: 'outer',
                icons: ['globalvars', 'governance']
            }
        }]
    },{
        xtype: 'fieldset',
        title: 'Metadata',
        defaults: {
            anchor: '100%',
            maxWidth: 820,
            labelWidth: 140
        },
        items: [{
            xtype: 'displayfield',
            cls: 'x-form-field-info',
            value: 'Global Variable Interpolation is supported for metadata values <img src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-globalvars" style="vertical-align:top;position:relative;top:2px" />'+
                   '<br/><i>Scalr reserves some <a href="https://scalr-wiki.atlassian.net/wiki/x/MwAeAQ" target="_blank">metadata name-value pairs</a> to configure the Scalarizr agent.</i>'
        },{
            xtype: 'ec2tagsfield',
            name: 'base.custom_tags',
            tagsLimit: 0,
            cloud: 'openstack'
        }]
    }]
});

