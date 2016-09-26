Scalr.regPage('Scalr.ui.tools.openstack.snapshots.create', function (loadParams, moduleParams) {
	loadParams['size'] = loadParams['size'] || 1;

	return Ext.create('Ext.form.Panel', {
		width: 700,
		title: 'Tools &raquo; Openstack &raquo; Snapshots &raquo; Create',
		fieldDefaults: {
			anchor: '100%'
		},

		items: [{
			xtype: 'fieldset',
			title: 'Snapshot details',
			labelWidth: 130,
			items: [{
				xtype: 'textfield',
				fieldLabel: 'Volume ID',
				readOnly: true,
				name: 'volumeId',
				value: loadParams['volumeId']
			}, {
				xtype: 'textfield',
				fieldLabel: 'Name',
				name: 'name',
				value: ''
			}, {
				xtype: 'textfield',
				fieldLabel: 'Description',
				name: 'description',
				value: ''
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
				text: 'Create',
				handler: function() {
					Scalr.Request({
						processBox: {
							type: 'save'
						},
						form: this.up('form').getForm(),
						params: loadParams,
						scope: this,
						url: '/tools/openstack/snapshots/xCreate',
						success: function (data) {
							Scalr.event.fireEvent('redirect',
								'#/tools/openstack/snapshots/' + data.data.snapshotId + '/view?platform='+ loadParams['platform'] +'&cloudLocation=' + loadParams['cloudLocation']
							);
						}
					});
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});
});
