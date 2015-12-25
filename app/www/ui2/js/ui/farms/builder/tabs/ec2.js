Ext.define('Scalr.ui.FarmRoleEditorTab.Ec2', {
    extend: 'Scalr.ui.FarmRoleEditorTab',
    tabTitle: 'EC2',
    itemId: 'ec2',
    layout: 'anchor',
    minWidth: 700,

    cls: 'x-panel-column-left-with-tabs',

    settings: {
        'aws.additional_security_groups.append': 0,
        'aws.additional_security_groups': function(record){return (Scalr.getGovernance('ec2', 'aws.additional_security_groups') || {})['value'] || ''},
        'aws.aki_id': '',
        'aws.ari_id': '',
        'aws.cluster_pg': '',
        'aws.ebs_optimized': undefined,
        'aws.enable_cw_monitoring': 0,
        'aws.instance_name_format': '',
        'aws.additional_tags': undefined,
        'aws.iam_instance_profile_arn': undefined,
        'aws.instance_initiated_shutdown_behavior': function() {return Scalr.getDefaultValue('AWS_INSTANCE_INITIATED_SHUTDOWN_BEHAVIOR')}
    },

    tabData: null,

    isEnabled: function (record) {
        return this.callParent(arguments) && record.get('platform') == 'ec2';
    },

    onRoleUpdate: function(record, name, value, oldValue) {
        var me = this;
        if (!me.isEnabled(record)) return;
        var fullname = name.join('.');
        if (fullname === 'settings.aws.instance_type') {
            var settings = record.get('settings', true),
                field;

            record.loadPlacementGroupsSupport(function(pgSupported){
                if (!pgSupported) {
                    settings['aws.cluster_pg'] = 0;
                }
                if (me.isVisible()) {
                    field = me.down('[name="aws.cluster_pg"]');
                    if (field) {
                        field.setReadOnly(!pgSupported);
                        if (!pgSupported) {
                            field.setValue(0);
                        }
                    }
                }
            }, value);

            record.loadEBSOptimizedSupport(function(ebsOptSupported){
                settings['aws.ebs_optimized'] = ebsOptSupported !== false && settings['aws.ebs_optimized'] == 1 ? 1 : 0;
                if (me.isVisible()) {
                    field = me.down('[name="aws.ebs_optimized"]');
                    if (field) {
                        field.setReadOnly(!ebsOptSupported);
                        field.setValue(settings['aws.ebs_optimized'] == 1);
                    }
                }
            }, value);
        }
    },

    beforeShowTab: function (record, handler) {
        this.vpc = this.up('#farmDesigner').getVpcSettings();
        Scalr.CachedRequestManager.get('farmDesigner').load(
            {
                url: '/platforms/ec2/xGetPlatformData',
                params: {
                    cloudLocation: record.get('cloud_location'),
                    farmRoleId: record.get('new') ? '' : record.get('farm_role_id'),
                    vpcId: this.vpc ? this.vpc.id : null
                }
            },
            function(data, status){
                if (!status) {
                    this.deactivateTab();
                } else {
                    this.tabData = data;
                    var ebsOptField = this.down('[name="aws.ebs_optimized"]');
                    record.loadEBSOptimizedSupport(function(ensOptSupported){
                        ebsOptField.setReadOnly(!ensOptSupported);
                    });
                    var pgField = this.down('[name="aws.cluster_pg"]');
                    record.loadPlacementGroupsSupport(function(pgSupported){
                        pgField.setReadOnly(!pgSupported);
                    });
                    handler();
                }
            },
            this,
            0
        );
    },

    showTab: function (record) {
        var settings = record.get('settings', true),
            limits = Scalr.getGovernance('ec2'),
            field, field2;
        this.suspendLayouts();

        //advanced ec2 settings
        this.down('#securityGroups').setVisible(!settings['aws.security_groups.list']);
        field2 = this.down('[name="aws.additional_security_groups.append"]');
        field2.setValue(settings['aws.additional_security_groups.append'] == 1 ? 1 : 0);

        field = this.down('[name="aws.additional_security_groups"]');
        field.setValue(settings['aws.additional_security_groups']);

        if (limits['aws.additional_security_groups'] !== undefined) {
            if (limits['aws.additional_security_groups']['allow_additional_sec_groups'] == 1) {
                field2.setValue(1);
                field.setReadOnly(false);
            } else {
                field2.setValue(0);
                field.setReadOnly(true);
                field.setValue(limits['aws.additional_security_groups']['value']);
            }
            field2.setReadOnly(true);
            field2.toggleIcon('governance', true);
        } else {
            field.setReadOnly(false);
            field2.setReadOnly(false, false);
            field2.toggleIcon('governance', false);
        }

        this.down('[name="aws.aki_id"]').setValue(settings['aws.aki_id']);
        this.down('[name="aws.ari_id"]').setValue(settings['aws.ari_id']);

        field = this.down('[name="aws.instance_name_format"]');
        if (limits['aws.instance_name_format'] !== undefined) {
            field.setValueWithGovernance(settings['aws.instance_name_format'], limits['aws.instance_name_format']['value']);
        } else {
            field.setValue(settings['aws.instance_name_format']);
        }

        field = this.down('[name="aws.cluster_pg"]');
        field.store.proxy.params = {cloudLocation: record.get('cloud_location')};
        field.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + record.get('cloud_location');
        var clusterPgData = [{id: 0, groupName: 'Do not use placement group'}];
        if (!Ext.isEmpty(settings['aws.cluster_pg'])) {
            clusterPgData.push({id: settings['aws.cluster_pg'], groupName: settings['aws.cluster_pg']});
        }
        field.store.loadData(clusterPgData);

        field.setValue(field.readOnly ? 0 : (settings['aws.cluster_pg'] || 0));

        field = this.down('[name="aws.instance_initiated_shutdown_behavior"]');
        field.setValue(settings['aws.instance_initiated_shutdown_behavior'] || 'terminate');
        field.setReadOnly(record.isDbMsr());

        field = this.down('[name="aws.ebs_optimized"]');
        field.setValue(!field.readOnly && settings['aws.ebs_optimized'] == 1);

        this.down('[name="aws.enable_cw_monitoring"]').setDisabled(record.get('behaviors', true).match("cf_")).setValue(settings['aws.enable_cw_monitoring'] == 1);

        //additional tags
        var tags = {};
        Ext.Array.each((settings['aws.additional_tags']||'').replace(/(\r\n|\n|\r)/gm,'\n').split('\n'), function(tag){
            var pos = tag.indexOf('='),
                name = tag.substring(0, pos);
            if (name) tags[name] = tag.substring(pos+1);
        });

        field = this.down('[name="aws.additional_tags"]');
        if (limits['aws.tags'] !== undefined) {
            if (limits['aws.tags'].allow_additional_tags == 1) {
                field.setReadOnly(false);
                field.setValue(tags, limits['aws.tags'].value);
            } else {
                field.setReadOnly(true);
                field.setValue(null, limits['aws.tags'].value);
            }
        } else {
            field.setReadOnly(false);
            field.setValue(tags);
        }
        this.resumeLayouts(true);

        var iamProfilesData = Ext.clone(this.tabData['iamProfiles']);
        if (iamProfilesData) {
            if (limits['aws.iam'] !== undefined) {
                var iamProfiles = [{arn: '', name: ''}],
                    allowedProfiles = (limits['aws.iam']['iam_instance_profile_arn'] || '').split(',');
                Ext.Array.each(iamProfilesData, function(profile) {
                    if (Ext.Array.contains(allowedProfiles, profile.arn) || Ext.Array.contains(allowedProfiles, profile.name)) {
                        iamProfiles.push(profile);
                    }
                });
                iamProfilesData = iamProfiles;
            } else {
                iamProfilesData.unshift({arn: '', name: ''});
            }
        }

        //toggle governance icon before resumeLayouts causes wrong width calculation
        field = this.down('[name="aws.iam_instance_profile_arn"]');
        field.store.load({data: iamProfilesData});
        field.setValue(settings['aws.iam_instance_profile_arn']);
        field.toggleIcon('governance', !!limits['aws.iam']);

    },

    hideTab: function (record) {
        var me = this,
            settings = record.get('settings'),
            field;
        //advanced ec2 settings
        settings['aws.additional_security_groups.append'] = me.down('[name="aws.additional_security_groups.append"]').getValue();

        field = me.down('[name="aws.additional_security_groups"]');
        if (!field.readOnly) {
            settings['aws.additional_security_groups'] = field.getValue();
        }

        settings['aws.aki_id'] = me.down('[name="aws.aki_id"]').getValue();
        settings['aws.ari_id'] = me.down('[name="aws.ari_id"]').getValue();
        field = me.down('[name="aws.cluster_pg"]');

        if (field.getValue()) {
            settings['aws.cluster_pg'] = field.getValue();
        } else {
            delete settings['aws.cluster_pg'];
        }

        settings['aws.instance_initiated_shutdown_behavior'] = me.down('[name="aws.instance_initiated_shutdown_behavior"]').getValue();
        settings['aws.ebs_optimized'] = me.down('[name="aws.ebs_optimized"]').getValue() ? 1 : 0;
        settings['aws.enable_cw_monitoring'] = me.down('[name="aws.enable_cw_monitoring"]').getValue() ? 1 : 0;

        field = me.down('[name="aws.instance_name_format"]');
        if (!field.readOnly) {
            settings['aws.instance_name_format'] = field.getValue();
        }

        field = me.down('[name="aws.iam_instance_profile_arn"]');
        if (!field.readOnly) {
            settings['aws.iam_instance_profile_arn'] = field.getValue();
        }

        //additional tags
        field = me.down('[name="aws.additional_tags"]');
        if (!field.readOnly) {
            var tags = [];
            Ext.Object.each(field.getValue(), function(key, value){
                tags.push(key + '=' + value);
            });
            settings['aws.additional_tags'] = tags.join('\n');
        }
        record.set('settings', settings);
    },
    defaults: {
        anchor: '100%',
        defaults: {
            maxWidth: 820,
            labelWidth: 200,
            anchor: '100%'
        }
    },

    __items: [{
        xtype: 'fieldset',
        itemId: 'perks',
        title: 'Advanced EC2 settings',
        collapsible: true,
        toggleOnTitleClick: true,
        items: [{
            xtype: 'textfield',
            name: 'aws.instance_name_format',
            fieldLabel: 'Instance name',
            emptyText: '{SCALR_FARM_NAME} -> {SCALR_FARM_ROLE_ALIAS} #{SCALR_INSTANCE_INDEX}',
            flex: 1,
            plugins: {
                ptype: 'fieldicons',
                position: 'outer',
                icons: ['globalvars', 'governance']
            }
        },{
            xtype: 'combo',
            name: 'aws.iam_instance_profile_arn',
            plugins: {
                ptype: 'fieldicons',
                position: 'outer',
                icons: ['governance']
            },
            fieldLabel: 'IAM instance profile',
            editable: false,
            valueField: 'arn',
            displayField: 'name',
            store: {
                fields: ['arn', 'name'],
                proxy: 'object'
            }
        }, {
            xtype: 'fieldcontainer',
            itemId: 'securityGroups',
            flex: 1,
            layout: 'hbox',
            items: [{
                xtype: 'combo',
                name: 'aws.additional_security_groups.append',
                fieldLabel: 'Security groups',
                plugins: {
                    ptype: 'fieldicons',
                    position: 'outer',
                    icons: ['governance']
                },
                width: 375,
                labelWidth: 200,
                editable: false,
                store: [
                    [0, 'Override system SGs by:'],
                    [1, 'Append to system SGs:']
                ]
            },{
                xtype: 'textfield',
                name: 'aws.additional_security_groups',
                flex: 1,
                margin: '0 0 0 6'
            }]
        }, {
            xtype: 'textfield',
            fieldLabel: 'AKI id',
            name: 'aws.aki_id'
        }, {
            xtype: 'textfield',
            fieldLabel: 'ARI id',
            name: 'aws.ari_id'
        }, {
            xtype: 'combo',
            name: 'aws.cluster_pg',
            fieldLabel: 'Cluster placement group',
            editable: false,

            queryCaching: false,
            clearDataBeforeQuery: true,
            store: {
                fields: [ 'id', 'groupName' ],
                sorters: {
                    property: 'groupName'
                },
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'farmDesigner',
                    url: '/tools/aws/ec2/xListPlacementGroups',
                    prependData: [{id: 0, groupName: 'Do not use placement group'}]
                }
            },
            emptyText: 'Do not use placement group',
            valueField: 'id',
            displayField: 'groupName',
            plugins: [{
                ptype: 'fieldicons',
                position: 'outer',
                icons: [{id: 'question', tooltip: 'Cluster placement group is not available with current instance type'}]
            },{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/aws/ec2/createPlacementGroup'
            }],
            listeners: {
                addnew: function(item) {
                    Scalr.CachedRequestManager.get('farmDesigner').setExpired({
                        url: '/tools/aws/ec2/xListPlacementGroups',
                        params: this.store.proxy.params
                    });
                },
                writeablechange: function(comp, readOnly) {
                    if (this.rendered) {
                        this.toggleIcon('question', readOnly);
                    }
                }
            }
        }, {
            xtype: 'buttongroupfield',
            name: 'aws.instance_initiated_shutdown_behavior',
            fieldLabel: 'Instance Initiated<br/> Shutdown Behavior',
            labelStyle: 'padding-top:4px',//2 line fieldLabel fix
            defaults: {
                width: 120
            },
            items: [{
                text: 'Suspend',
                value: 'stop'
            },{
                text: 'Terminate',
                value: 'terminate'
            }],
            plugins: [{
                ptype: 'fieldicons',
                position: 'outer',
                icons: [{id: 'question', tooltip: 'Database roles don\'t support Suspend / Resume'}]
            }],
            listeners: {
                writeablechange: function(comp, readOnly) {
                    if (this.rendered) {
                        this.toggleIcon('question', readOnly);
                    }
                }
            }
        },{
            xtype: 'checkbox',
            name: 'aws.ebs_optimized',
            boxLabel: 'Launch instances as <a target="_blank" href="http://aws.typepad.com/aws/2012/08/fast-forward-provisioned-iops-ebs.html">EBS-Optimized</a>',
            plugins: {
                ptype: 'fieldicons',
                icons: [{id: 'question', tooltip: 'EBS-Optimized flag is not available with non-EBS roles and with some instance types'}]
            },
            listeners:{
                writeablechange: function(comp, readOnly) {
                    this.toggleIcon('question', readOnly);
                }
            }
        },{
            xtype: 'checkbox',
            name: 'aws.enable_cw_monitoring',
            boxLabel: 'Enable Detailed <a href="http://aws.amazon.com/cloudwatch/" target="_blank">CloudWatch</a> monitoring for instances of this role (1 min interval)'
        }]
    },{
        xtype: 'fieldset',
        cls: 'x-fieldset-separator-none',
        title: 'Tags',
        items: [{
            xtype: 'ec2tagsfield',
            itemId: 'additionaltags',
            name: 'aws.additional_tags'
        }]
    }]
});
