Scalr.regPage('Scalr.ui.tools.aws.rds.instances.create', function (loadParams, moduleParams) {
	if(loadParams['instanceId'])
		var instance = moduleParams['instance'];
	function fillComboes(newValue) {
		Scalr.Request({
			processBox: {
				type: 'action'
			},
			url: '/tools/aws/rds/instances/xGetParameters/',
			params: {
				cloudLocation: newValue
			},
			scope: this,
			success: function (response) {
				form.down('checkboxgroup').removeAll();
				var flag = false;
				for(i = 0; i < response.sgroups.length; i++) {
					if(loadParams['instanceId']){
						if(moduleParams.instance.DBSecurityGroups.DBSecurityGroup.length){
							for(j = 0; j < moduleParams.instance.DBSecurityGroups.DBSecurityGroup.length; j++) {
								if(response.sgroups[i].DBSecurityGroupName == moduleParams.instance.DBSecurityGroups.DBSecurityGroup[j].DBSecurityGroupName){
									flag = true;
									break;
								}
							}
						}
						else{ 
							if(response.sgroups[i].DBSecurityGroupName == moduleParams.instance.DBSecurityGroups.DBSecurityGroup.DBSecurityGroupName)
								flag = true;
						}
					}
					form.down('checkboxgroup').add({boxLabel: response.sgroups[i].DBSecurityGroupName, inputValue: response.sgroups[i].DBSecurityGroupName, name: 'DBSecurityGroups[]', checked: flag});
					flag = false;
				}
				if(response.groups){
					form.down('#DBParameterGroup').enable();
					form.down('#DBParameterGroup').store.load({data: response.groups});
				}
				else {
					form.down('#DBParameterGroup').setValue('');
					form.down('#DBParameterGroup').disable();
				}
				form.down('#AvailabilityZone').store.load({data: response.zones});
			},
			failure: function(){
				form.disable();
			}
		});
	}
	var form = Ext.create('Ext.form.Panel', {
		title: (loadParams['instanceId']) ? 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Instances &raquo; ' + loadParams['instanceId'] + ' &raquo; Edit' : 'Tools &raquo; Amazon Web Services &raquo; RDS &raquo; DB Instances &raquo; Launch',
		bodyCls: 'x-panel-body-frame',
		width: 800,
		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-bottom-frame',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
					form.down('#PreferredMaintenanceWindow').setValue(form.down('#FirstDay').value + ':' + form.down('#fhour').value + ':' + form.down('#fminute').value + '-' + form.down('#LastDay').value + ':' + form.down('#lhour').value + ':' + form.down('#lminute').value);
					form.down('#PreferredBackupWindow').setValue(form.down('#bfhour').value + ':' + form.down('#bfminute').value + '-' + form.down('#blhour').value + ':' + form.down('#blminute').value)
					var data = {};
					Scalr.Request({
						processBox: {
							type: 'save'
						},
						url: (loadParams['instanceId']) ? '/tools/aws/rds/instances/xModifyInstance' : '/tools/aws/rds/instances/xLaunchInstance',
						form: form.getForm(),
						success: function (data) {
							Scalr.event.fireEvent('close');
						}
					});
				}
			},{
				xtype: 'button',
				margin: '0 0 0 5',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}],
		items:[{
			hidden: loadParams['instanceId'] ? true : false,
			xtype: 'fieldset',
			title: 'Location',
			items: [{
				padding: 5,
				xtype: 'combo',
				name: 'cloudLocation',
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.locations,
					proxy: 'object'
				},
				editable: false,
				width: 710,
				queryMode: 'local',
				displayField: 'name',
				valueField: 'id',
				listeners: {
					change: function(field, newValue, oldValue, options) {
						form.down('fieldset').next('fieldset').show().enable();
						fillComboes(newValue);
					}
				}
			}]
		},{
			xtype: 'fieldset',
			hidden: loadParams['instanceId'] ? false : true,
			title: 'General information',
			items:[{
				xtype: 'hiddenfield',
        		name: 'PreferredMaintenanceWindow',
        		itemId: 'PreferredMaintenanceWindow'
			},{
				xtype: 'hiddenfield',
        		name: 'PreferredBackupWindow',
        		itemId: 'PreferredBackupWindow'
			},{
				labelWidth: 200,
				xtype: 'textfield',
				name: 'DBInstanceIdentifier',
				fieldLabel: 'Identifier',
				allowBlank: false,
				readOnly: loadParams['instanceId'] ? true : false,
				value: loadParams['instanceId']
			},{
				xtype: 'container',
				layout: {
					type: 'hbox'
				},
				items:[{
					labelWidth: 200,
					width: 355,
					xtype: 'textfield',
					name: 'AllocatedStorage',
					fieldLabel: 'Allocated Storage(Gb)',
					allowBlank: false,
					value: loadParams['instanceId'] ? instance['AllocatedStorage'] : '5'
				},{
					margin: '0 0 0 5',
					xtype: 'displayfield',
					value: loadParams['instanceId'] ? (instance['PendingModifiedValues']['AllocatedStorage'] ? '<i><font color="red">New value (' + instance['PendingModifiedValues']['AllocatedStorage'] + ') is pending</font></i>' : '')  : ''
				}]
			},{
				xtype: 'container',
				layout: {
					type: 'hbox'
				},
				items:[{
					labelWidth: 200,
					width: 355,
					xtype: 'combo',
					name: 'DBInstanceClass',
					fieldLabel: 'Type',
					store: ['db.m1.small','db.m1.large','db.m1.xlarge','db.m2.2xlarge','db.m2.4xlarge'],
					queryMode: 'local',
					allowBlank: false,
					value: loadParams['instanceId'] ? instance['DBInstanceClass'] : 'db.m1.small',
					editable: false
				},{
					margin: '0 0 0 5',
					xtype: 'displayfield',
					value: loadParams['instanceId'] ? (instance['PendingModifiedValues']['DBInstanceClass'] ? '<i><font color="red">New value (' + instance['PendingModifiedValues']['DBInstanceClass'] + ') is pending</font></i>' : '') : ''
				}]
			},{
				labelWidth: 200,
				xtype: 'combo',
				name: 'Engine',
				fieldLabel: 'Engine',
				store: [['mysql5.1','MySQL 5.1']],
				queryMode: 'local',
				value: 'mysql5.1',
				editable: false,
				hidden: loadParams['instanceId'] ? true : false
			},{
				labelWidth: 200,
				xtype: 'textfield',
				name: 'MasterUsername',
				fieldLabel: 'Master Username',
				allowBlank: false,
				hidden: loadParams['instanceId'] ? true : false
			},{
				xtype:'container',
				layout: {
					type: 'hbox'
				},
				items: [{
					labelWidth: 200,
					width: 355,
					xtype: 'textfield',
					name: 'MasterUserPassword',
					fieldLabel: 'Master Password'
				},{
					margin: '0 0 0 5',
					xtype: 'displayfield',
					value: loadParams['instanceId'] ? (instance['PendingModifiedValues']['MasterUserPassword'] ? '<i><font color="red">New value is pending</font></i>' : '') : ''
				}]
			},{
				labelWidth: 200,
				xtype: 'textfield',
				name: 'Port',
				fieldLabel: 'Port',
				itemId: 'Port',
				value: '3306',
				allowBlank: false,
				hidden: loadParams['instanceId'] ? true : false
			},{
				labelWidth: 200,
				xtype: 'textfield',
				name: 'DBName',
				fieldLabel: 'DB Name',
				allowBlank: false,
				hidden: loadParams['instanceId'] ? true : false
			},{
				labelWidth: 200,
				xtype: 'combo',
				name: 'DBParameterGroup',
				fieldLabel: 'DB Parameter Group',
				itemId: 'DBParameterGroup',
				store: {
					fields: ['DBParameterGroupName'],
					proxy: 'object'
				},
				queryMode: 'local',
				valueField: 'DBParameterGroupName',
				displayField: 'DBParameterGroupName',
				editable: false,
				allowBlank: false,
				value: loadParams['instanceId'] ? instance['DBParameterGroups']['DBParameterGroup']['DBParameterGroupName'] : ''
			},{
				labelWidth: 200,
				width: 400,
				xtype: 'checkboxgroup',
        		fieldLabel: 'DB Security Group',
        		itemId: 'DBSecurityGroups',
      	  		columns: 2,
        		vertical: true,
        		allowBlank: false
			},{
				itemId: 'AvailabilityZone',
				labelWidth: 200,
				xtype: 'combo',
				name: 'AvailabilityZone',
				fieldLabel: 'Availability Zone',
				store: {
					fields: ['id', 'name'],
					proxy: 'object'
				},
				queryMode: 'local',
				editable: false,
				valueField: 'id',
				displayField: 'name',
				allowBlank: false,
				value: loadParams['instanceId'] ? instance['AvailabilityZone'] : ' ',
				disabled: loadParams['instanceId'] ? instance['MultiAZ'] : false,
				hidden: loadParams['instanceId'] ? true : false
			},{
				labelWidth: 200,
				xtype: 'fieldcontainer',
            	fieldLabel: 'Enable Multi Availability Zones',
            	defaultType: 'checkboxfield',
            	hidden: loadParams['instanceId'] ? true : false,
            	items: [{
                    name: 'MultiAZ',
                    checked: loadParams['instanceId'] ? instance['MultiAZ'] != 'false' : false,
                    listeners: {
                    	change: function(field, value, oldvalue, eOpts){
                    		if(value) field.up('panel').down('#AvailabilityZone').disable();
                    		else field.up('panel').down('#AvailabilityZone').enable();
                    	}
                    }
                }]
			},{
				xtype: 'container',
				layout: {
					type: 'hbox'
				},
				defaults: {
					margin: '0 3 0 0'
				},
				items: [{
					labelWidth: 200,
					width: 270,
					xtype: 'combo',
					itemId: 'FirstDay',
					fieldLabel: 'Preferred Maintenance Window',
					queryMode: 'local',
					editable: false,
					store: [['sun','Sun'], ['mon','Mon'],['tue','Tue'],['wed','Wed'],['thu','Thur'],['fri','Fri'],['sat','Sat']],
					value: loadParams['instanceId'] ? instance['PreferredMaintenanceWindow'].substr(0, 3) : 'mon'
				},{
					xtype: 'displayfield',
					value: ' : '
				},{
					width: 35,
					xtype: 'textfield',
					itemId: 'fhour',
					value: loadParams['instanceId'] ? instance['PreferredMaintenanceWindow'].substr(4, 2) : '05'
				},{
					xtype: 'displayfield',
					value: ' : '
				},{
					width: 35,
					xtype: 'textfield',
					itemId: 'fminute',
					value: loadParams['instanceId'] ? instance['PreferredMaintenanceWindow'].substr(7, 2) : '00'
				},{
					xtype: 'displayfield',
					value: ' - '
				},{
					width: 70,
					xtype: 'combo',
					itemId: 'LastDay',
					queryMode: 'local',
					editable: false,
					store: [['sun','Sun'], ['mon','Mon'],['tue','Tue'],['wed','Wed'],['thu','Thur'],['fri','Fr'],['sat','Sat']],
					value: loadParams['instanceId'] ? instance['PreferredMaintenanceWindow'].substr(10, 3) : 'mon'
				},{
					xtype: 'displayfield',
					value: ' : '
				},{
					width: 35,
					xtype: 'textfield',
					itemId: 'lhour',
					value: loadParams['instanceId'] ? instance['PreferredMaintenanceWindow'].substr(14, 2) : '09'
				},{
					xtype: 'displayfield',
					value: ' : '
				},{
					width: 35,
					xtype: 'textfield',
					itemId: 'lminute',
					value: loadParams['instanceId'] ? instance['PreferredMaintenanceWindow'].substr(17, 2) : '00'
				},{
					xtype: 'displayfield',
					value: '(Format: hh24:mi - hh24:mi)'
				}]
			},{
				xtype:'container',
				layout: {
					type: 'hbox'
				},
				items: [{
					labelWidth: 200,
					width: 240,
					xtype: 'textfield',
					name: 'BackupRetentionPeriod',
					fieldLabel: 'Backup Retention Period',
					value: loadParams['instanceId'] ? instance['BackupRetentionPeriod'] : '1'
				},{
					margin: '0 0 0 5',
					xtype: 'displayfield',
					value: loadParams['instanceId'] ? (instance['PendingModifiedValues']['BackupRetentionPeriod'] ? '<i><font color="red">New value (' + instance['PendingModifiedValues']['BackupRetentionPeriod'] + ') is pending</font></i>' : '') : ''
				}]
			},{
				xtype: 'container',
				layout: {
					type: 'hbox'
				},
				items: [{
					labelWidth: 200,
					width: 240,
					xtype: 'textfield',
					itemId: 'bfhour',
					fieldLabel: 'Preferred Backup Window',
					value: loadParams['instanceId'] ? instance['PreferredBackupWindow'].substr(0, 2) : '10'
				},{
					xtype: 'displayfield',
					value: ' : ',
					margin: '0 0 0 3'
				},{
					width: 35,
					xtype: 'textfield',
					itemId: 'bfminute',
					value: loadParams['instanceId'] ? instance['PreferredBackupWindow'].substr(3, 2) : '00',
					margin: '0 0 0 3'
				},{
					xtype: 'displayfield',
					value: ' - ',
					margin: '0 0 0 3'
				},{
					width: 35,
					xtype: 'textfield',
					itemId: 'blhour',
					value: loadParams['instanceId'] ? instance['PreferredBackupWindow'].substr(6, 2) : '12',
					margin: '0 0 0 3'
				},{
					xtype: 'displayfield',
					value: ' : ',
					margin: '0 0 0 3'
				},{
					width: 35,
					xtype: 'textfield',
					itemId: 'blminute',
					value: loadParams['instanceId'] ? instance['PreferredBackupWindow'].substr(9, 2) : '00',
					margin: '0 0 0 3'
				},{
					xtype: 'displayfield',
					value: '(Format: hh24:mi - hh24:mi)',
					margin: '0 0 0 3'
				}]
			}]
		}]
	});

	if(loadParams['instanceId']){
		fillComboes(loadParams['cloudLocation']);
	}

	return form;
});