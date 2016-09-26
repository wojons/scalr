Scalr.regPage('Scalr.ui.tools.aws.s3.distribution', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		title: 'Create new Distribution',
		width: 750,
		scalrOptions: {
			'modal': true
		},
		items: [{
			xtype: 'hidden',
			name: 'bucketName',
			value: loadParams['bucketName']
		},{
			xtype: 'fieldset',
			title: 'Distribution information',
			defaults: {
				labelWidth: 165,
                anchor: '100%'
			},
			items: [{
				xtype: 'displayfield',
				fieldLabel: 'S3 Bucket',
				value: loadParams['bucketName']
			},{
				xtype: 'textarea',
				fieldLabel: 'Comment',
				name: 'comment'
			}]
		},{
			xtype: 'fieldset',
			title: 'Domain Name',
			items: [{
				xtype: 'fieldcontainer',
				layout: {
					type: 'hbox'
				},
				items: [{
					xtype: 'radiofield',
					labelWidth: 150,
					name: 'domain',
					fieldLabel: 'Local domain name',
					checked: true,
					margin: '0 5 0 0',
					listeners: {
						change: function(field, newValue, oldValue, opts){
							if(newValue)
							{
								field.next('#localDomain').enable();
								field.next('#comboZone').enable();
							}
							else{
								field.next('#localDomain').disable();
								field.next('#comboZone').disable();
							}
						}
					}
				},{
					xtype: 'textfield',
					itemId: 'localDomain',
					name: 'localDomain'
				},{
					xtype: 'displayfield',
					value: '.',
					margin: '0 2 0 2'
				},{
					xtype: 'combo',
					name: 'zone',
					itemId: 'comboZone',
					flex: 1,
					editable: false,
					allowBlank: false,
					store: {
						fields: ['zone_name'],
						proxy: {
							type: 'ajax',
							reader: {
								type: 'json',
								rootProperty: 'data'
							},
							url: '/tools/aws/s3/xListZones'
						}
					},
					valueField: 'zone_name',
					displayField: 'zone_name'
				}]
			},{
				xtype: 'fieldcontainer',
				layout: {
					type: 'hbox'
				},
				items: [{
					xtype: 'radiofield',
					name: 'domain',
					labelWidth: 150,
					fieldLabel: 'Remote domain name',
					margin: '0 5 0 0',
					listeners: {
						change: function(field, newValue, oldValue, opts){
							if(newValue)
								field.next('#remoteDomain').enable();
							else
								field.next('#remoteDomain').disable();
						}
					}
				},{
					xtype: 'textfield',
					itemId: 'remoteDomain',
					name: 'remoteDomain',
					disabled: true,
					flex: 1
				}]
			}]
		}],
		dockedItems: [{
			xtype: 'container',
			dock: 'bottom',
			cls: 'x-docked-buttons',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Save',
				handler: function() {
					Scalr.Request({
						processBox: {
							msg: 'Adding new distribution',
							type: 'save'
						},
						scope: this,
						url: '/tools/aws/s3/xCreateDistribution',
						form: form.getForm(),
						success: function (data) {
							Scalr.event.fireEvent('close');
						}
					});
				}
			},{
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});
	return form;
});