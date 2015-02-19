Scalr.regPage('Scalr.ui.tools.aws.rds.instances.details', function (loadParams, moduleParams) {

	var instance = moduleParams['instance'];

	var form = Ext.create('Ext.form.Panel', {
		scalrOptions: {
			'modal': true
		},
		width: 650,
		title: 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Instance Details',
		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}],

		defaults: {
			xtype: 'fieldset',
			defaults: {
				xtype: 'displayfield',
				labelWidth: 200,
				width: '100%'
			}
		},

		items: [{
			title: 'Database Instance and Storage',
			items: [{
				name: 'DBInstanceIdentifier',
				fieldLabel: 'DB Instance Identifier'
			}, {
				name: 'Address',
				fieldLabel: 'DNS Name'
			}, {
				name: 'DBInstanceStatus',
				fieldLabel: 'Status'
			}, {
				name: 'InstanceCreateTime',
				fieldLabel: 'Created at'
			}, {
				name: 'isReplica',
				fieldLabel: 'Read Replica',
				renderer: function (value) {
					return !value ? 'No' : 'Yes';
				}
			}, {
                name: 'ReadReplicaSourceDBInstanceIdentifier',
                fieldLabel: 'Source Instance',
                hidden: !instance['isReplica']
            }, {
				name: 'DBInstanceClass',
				fieldLabel: 'Type'
			}, {
				name: 'MultiAZ',
				fieldLabel: 'Multi-AZ Deployment'
			}, {
				name: 'AvailabilityZone',
				fieldLabel: 'Availability Zone'
			}, {
				name: 'DBSecurityGroups',
				fieldLabel: 'Security Groups',
				renderer: function (value) {
					if (value) {
						var separator = ', ';
						var cloudLocation = loadParams.cloudLocation;
						var securityGroupNames = value.split(separator);
						var renderedValues = [];

						Ext.Array.each(securityGroupNames, function (name) {
							renderedValues.push(
								'<a href="#/tools/aws/rds/sg/edit?' + Ext.Object.toQueryString({
									dbSgName: name,
									cloudLocation: cloudLocation
								}) + '">' + name + '</a>'
							);
						});

						value = renderedValues.join(separator);
					}

					return value;
				}
			}, {
				name: 'VpcSecurityGroupIds',
				fieldLabel: 'Security Groups',
				hidden: true,
				renderer: function (value) {
					var me = this;

					var securityGroupIds = me.getSecurityGroupsIds();

					if (value && securityGroupIds.length) {
						var separator = ', ';
						var cloudLocation = loadParams['cloudLocation'];
						var securityGroupNames = value.split(separator);
						var renderedValues = [];

						Ext.Array.each(securityGroupNames, function (name, i) {
							renderedValues.push(
								'<a href="#/security/groups/'
								+ securityGroupIds[i]
								+ '/edit?' + Ext.Object.toQueryString({
									platform: 'ec2',
									cloudLocation: cloudLocation
								}) + '">' + name + '</a>'
							);
						});

						value = renderedValues.join(separator);
					}

					return value;
				},
				setSecurityGroupsIds: function (value) {
					var me = this;

					me.securityGroupIds = value;

					return me;
				},
				getSecurityGroupsIds: function () {
					return this.securityGroupIds || [];
				}
			}, {
				name: 'StorageType',
				fieldLabel: 'Storage type'
			}, {
				name: 'Iops',
				fieldLabel: 'IOPS',
				hidden: !instance['Iops']
			}, {
				name: 'AllocatedStorage',
				fieldLabel: 'Allocated Storage'
			}]
		}, {
			title: 'Database Engine',
			items: [{
				name: 'Engine',
				fieldLabel: 'Engine'
			}, {
				name: 'EngineVersion',
				fieldLabel: 'Version'
			}, {
				name: 'LicenseModel',
				fieldLabel: 'Licensing Model'
			}, {
				name: 'DBParameterGroup',
				fieldLabel: 'Parameter Group'
			}, {
				name: 'OptionGroupName',
				fieldLabel: 'Option Group'
			}, {
				name: 'Port',
				fieldLabel: 'Port'
			}]
		}, {
			title: 'Database',
			items: [{
				name: 'MasterUsername',
				fieldLabel: 'Master Username'
			}, {
				name: 'DBName',
				fieldLabel: 'Database Name'
			}]
		}, {
			title: 'Maintenance Windows and Backups',
			items: [{
				name: 'AutoMinorVersionUpgrade',
				fieldLabel: 'Auto Minor Version Upgrade',
				renderer: function (value) {
					return value ? 'Enabled' : 'Disabled';
				}
			}, {
				name: 'PreferredMaintenanceWindow',
				fieldLabel: 'Preferred Maintenance Window'
			}, {
				name: 'PreferredBackupWindow',
				fieldLabel: 'Preferred Backup Window'
			}, {
				name: 'BackupRetentionPeriod',
				fieldLabel: 'Backup Retention Period'
			}]
		}]
	});

	form.getForm().setValues(instance);

	var applySecurityGroups = function (groups) {
		var securityGroupNames = [];

		Ext.Array.each(groups, function (securityGroup) {
			securityGroupNames.push(
				securityGroup
			);
		});

		var field = form.down('[name=DBSecurityGroups]');
		field.reset();
		field.setValue(
			securityGroupNames.join(', ')
		);

		return true;
	};

	var updateSecurityGroups = function (selectedGroups, runOnVpc) {
		var securityGroupField = !runOnVpc
			? form.down('[name=DBSecurityGroups]')
			: form.down('[name=VpcSecurityGroupIds]');

		var selectedGroupNames = [];
		var selectedGroupIds = [];

		Ext.Array.each(selectedGroups, function (record) {
			selectedGroupNames.push(
				record.isModel
					? record.get('name')
					: record['vpcSecurityGroupName']
			);

			if (runOnVpc) {
				selectedGroupIds.push(
					record.isModel
						? record.get('id')
						: record['vpcSecurityGroupId']
				);
			}
		});

		if (runOnVpc) {
			securityGroupField.setSecurityGroupsIds(selectedGroupIds);
		}

		securityGroupField.setValue(
			selectedGroupNames.join(', ')
		);

		return true;
	};

	if (instance['VpcSecurityGroups'].length) {
		updateSecurityGroups(
			instance['VpcSecurityGroups'],
			true
		);

		form.down('[name=DBSecurityGroups]').hide();

		form.down('[name=VpcSecurityGroupIds]').show();

		return form;
	}

	applySecurityGroups(
		instance['DBSecurityGroups']
	);

	return form;
});
