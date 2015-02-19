Scalr.regPage('Scalr.ui.farms.builder.tabs.openstack', function (moduleTabParams) {
    return Ext.create('Scalr.ui.FarmsBuilderTab', {
        tabTitle: 'Openstack',
        itemId: 'openstack',
        layout: 'anchor',

        settings: {
            'base.custom_tags': undefined,
            'base.instance_name_format': undefined
        },

        isEnabled: function (record) {
            return Scalr.utils.isOpenstack(record.get('platform'));
        },

        getTitle: function(record) {
            return Scalr.utils.getPlatformName(record.get('platform'));
        },

        showTab: function (record) {
            var settings = record.get('settings', true),
                limits = this.up('#farmbuilder').getLimits(record.get('platform')),
                field;

            field = this.down('[name="base.custom_tags"]');
            if (limits['openstack.tags'] !== undefined) {
                field.setReadOnly(true);
                field.setValue(limits['openstack.tags'].value);
            } else {
                field.setReadOnly(false);
                var tags = {};
                Ext.Array.each((settings['base.custom_tags']||'').replace(/(\r\n|\n|\r)/gm,'\n').split('\n'), function(tag){
                    var pos = tag.indexOf('=');
                    tags[tag.substring(0, pos)] = tag.substring(pos+1);
                });
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
                labelWidth: 140
            },
            items: [{
                xtype: 'textfield',
                name: 'base.instance_name_format',
                fieldLabel: 'Instance name',
                emptyText: '{SCALR_SERVER_ID}',
                flex: 1,
                icons: {
                    globalvars: true,
                    governance: true
                },
                iconsPosition: 'outer'
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
});
