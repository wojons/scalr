Scalr.regPage('Scalr.ui.farms.builder.tabs.ec2', function (moduleParams) {
	return Ext.create('Scalr.ui.FarmsBuilderTab', {
		tabTitle: 'EC2',
        itemId: 'ec2',
        layout: 'anchor',
        minWidth: 700,
        
        settings: {
            'aws.additional_security_groups.append': 0,
            'aws.additional_security_groups': function(record){return (this.up('#farmbuilder').getLimits('ec2',  'aws.additional_security_groups') || {})['value'] || ''},
            'aws.aki_id': '',
            'aws.ari_id': '',
            'aws.cluster_pg': '',
            'aws.ebs_optimized': undefined,
            'aws.enable_cw_monitoring': 0,
            'aws.instance_name_format': '',
            'aws.additional_tags': undefined,
            'aws.iam_instance_profile_arn': undefined
        },
        
        tabData: null,
        
		isEnabled: function (record) {
			return record.get('platform') == 'ec2';
		},
        
        onRoleUpdate: function(record, name, value, oldValue) {
            if (!this.isActive(record)) return;
            var fullname = name.join('.');
            if (fullname === 'settings.aws.instance_type') {
                var settings = record.get('settings', true),
                    field,
                    ebsOptimizedReadOnly = !record.isEc2EbsOptimizedFlagVisible(value),
                    clusterPlacementGroupReadOnly = !record.isEc2ClusterPlacementGroupVisible(value);
                settings['aws.ebs_optimized'] = !ebsOptimizedReadOnly && settings['aws.ebs_optimized'] == 1 ? 1 : 0;
                field = this.down('[name="aws.ebs_optimized"]');
                field.setReadOnly(ebsOptimizedReadOnly);
                field.setValue(settings['aws.ebs_optimized'] == 1);

                field = this.down('[name="aws.cluster_pg"]');
                field.setDisabled(clusterPlacementGroupReadOnly);
                if (clusterPlacementGroupReadOnly) field.setValue('');
            }
        },
        
		beforeShowTab: function (record, handler) {
            this.vpc = this.up('#fbcard').down('#farm').getVpcSettings();
            Scalr.CachedRequestManager.get('farmbuilder').load(
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
                        if (status === 'success') {
                            if (this.tabData['iamProfiles']) {
                                var iamLimits = this.up('#farmbuilder').getLimits('ec2', 'aws.iam');
                                if (iamLimits !== undefined) {
                                    var iamProfiles = [{arn: '', name: ''}],
                                        allowedProfiles = (iamLimits['iam_instance_profile_arn'] || '').split(',');
                                    Ext.Array.each(this.tabData['iamProfiles'], function(profile) {
                                        if (Ext.Array.contains(allowedProfiles, profile.arn) || Ext.Array.contains(allowedProfiles, profile.name)) {
                                            iamProfiles.push(profile);
                                        }
                                    });
                                    this.tabData['iamProfiles'] = iamProfiles;
                                } else {
                                    this.tabData['iamProfiles'].unshift({arn: '', name: ''});
                                }
                            }
                        }
                        handler();
                    }
                },
                this,
                0
            );
		},

		showTab: function (record) {
			var settings = record.get('settings', true),
                iamProfilesData = this.tabData['iamProfiles'],
                limits = this.up('#farmbuilder').getLimits('ec2'),
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
            if (limits['aws.tags'] && Ext.Object.getSize(limits['aws.tags'].value)) {
                field.setValueWithGovernance(settings['aws.instance_name_format'], limits['aws.tags'].value['Name']);
            } else {
                field.setValue(settings['aws.instance_name_format']);
            }

            var clusterPlacementGroupReadOnly = !record.isEc2ClusterPlacementGroupVisible();
            field = this.down('[name="aws.cluster_pg"]');
            field.store.proxy.params = {cloudLocation: record.get('cloud_location')};
            field.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + record.get('cloud_location');
            field.setDisabled(clusterPlacementGroupReadOnly);
            field.setValue(clusterPlacementGroupReadOnly ? '' : settings['aws.cluster_pg']);


            var ebsOptimizedReadOnly = !record.isEc2EbsOptimizedFlagVisible();
            field = this.down('[name="aws.ebs_optimized"]');
            field.setReadOnly(ebsOptimizedReadOnly);
            field.setValue(!ebsOptimizedReadOnly && settings['aws.ebs_optimized'] == 1);

            this.down('[name="aws.enable_cw_monitoring"]').setDisabled(record.get('behaviors', true).match("cf_")).setValue(settings['aws.enable_cw_monitoring'] == 1);

            //additional tags
            field = this.down('[name="aws.additional_tags"]');
            if (limits['aws.tags'] !== undefined) {
                field.setReadOnly(true);
                field.setValue(limits['aws.tags'].value);
            } else {
                field.setReadOnly(false);
                var tags = {};
                Ext.Array.each((settings['aws.additional_tags']||'').replace(/(\r\n|\n|\r)/gm,'\n').split('\n'), function(tag){
                    var pos = tag.indexOf('=');
                    tags[tag.substring(0, pos)] = tag.substring(pos+1);
                });
                field.setValue(tags);
            }
            this.resumeLayouts(true);

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
			settings['aws.cluster_pg'] = me.down('[name="aws.cluster_pg"]').getValue();
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
				labelWidth: 180,
                anchor: '100%'
			}
        },

		items: [{
			xtype: 'fieldset',
            itemId: 'perks',
            title: 'Advanced EC2 settings',
            collapsible: true,
            toggleOnTitleClick: true,
			items: [{
                xtype: 'textfield',
                name: 'aws.instance_name_format',
                fieldLabel: 'Instance name',
                emptyText: '{SCALR_FARM_NAME} -> {SCALR_ROLE_NAME} #{SCALR_INSTANCE_INDEX}',
                flex: 1,
                icons: {
                    globalvars: true,
                    governance: true
                },
                iconsPosition: 'outer'
            },{
                xtype: 'combo',
                name: 'aws.iam_instance_profile_arn',
                icons: {
                    governance: true
                },
                iconsPosition: 'outer',
                fieldLabel: 'IAM instance profile',
                editable: false,
                valueField: 'arn',
                displayField: 'name',
                store: {
                    fields: ['arn', 'name'],
                    proxy: 'object'
                }
			}, {
                xtype: 'container',
                itemId: 'securityGroups',
                flex: 1,
                layout: 'hbox',
                items: [{
                    xtype: 'combo',
                    name: 'aws.additional_security_groups.append',
                    fieldLabel: 'Security groups',
                    icons: {
                        governance: true
                    },
                    iconsPosition: 'outer',
                    width: 375,
                    labelWidth: 180,
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
                icons: {
                    question: true
                },
                iconsPosition: 'outer',
                questionTooltip: 'Cluster placement group is not available with current instance type',
                store: {
                    fields: ['id', 'groupName'],
                    sorters: {
                        property: 'groupName'
                    },
                    proxy: {
                        type: 'cachedrequest',
                        crscope: 'farmbuilder',
                        url: '/tools/aws/ec2/xListPlacementGroups',
                        prependData: [{id: '', groupName: 'Do not use placement group'}]
                    }
                },
                emptyText: 'Do not use placement group',
                valueField: 'id',
                displayField: 'groupName',
                plugins: [{
                    ptype: 'comboaddnew',
                    pluginId: 'comboaddnew',
                    url: '/tools/aws/ec2/createPlacementGroup'
                }],
                listeners: {
                    addnew: function(item) {
                        Scalr.CachedRequestManager.get('farmbuilder').setExpired({
                            url: '/tools/aws/ec2/xListPlacementGroups',
                            params: this.store.proxy.params
                        });
                    },
                    disable: function() {
                        this.toggleIcon('question', true);
                    },
                    enable: function() {
                        this.toggleIcon('question', false);
                    }
                }
			}, {
                xtype: 'checkbox',
                name: 'aws.ebs_optimized',
                boxLabel: 'Launch instances as <a target="_blank" href="http://aws.typepad.com/aws/2012/08/fast-forward-provisioned-iops-ebs.html">EBS-Optimized</a>',
                questionTooltip: 'EBS-Optimized flag is not available with non-EBS roles and with some instance types',
                icons: {
                    question: true
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
                name: 'aws.additional_tags',
                allowNameTag: false
            }]
        }]
	});
});
