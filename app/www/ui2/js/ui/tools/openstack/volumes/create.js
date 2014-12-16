Scalr.regPage('Scalr.ui.tools.openstack.volumes.create', function (loadParams, moduleParams) {
	loadParams['size'] = loadParams['size'] || 1;

	return Ext.create('Ext.form.Panel', {
		width: 700,
		title: 'Tools &raquo; Openstack &raquo; Volumes &raquo; Create',
		fieldDefaults: {
			anchor: '100%'
		},

		items: [{
			xtype: 'fieldset',
			title: 'Placement information',
			labelWidth: 130,
			items: [{
				fieldLabel: 'Cloud location',
				xtype: 'combo',
				allowBlank: false,
				editable: false,
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.locations,
					proxy: 'object'
				},
				displayField: 'name',
				valueField: 'id',
				queryMode: 'local',
				name: 'cloudLocation',
				width: 200,
				listeners: {
					render: function () {
						this.setValue(loadParams['cloudLocation'] || this.store.getAt(0).get('id'));
					}
				}
			}]
		}, {
			xtype: 'fieldset',
			title: 'Volume information',
			labelWidth: 130,
			items: [{
				xtype:'fieldcontainer',
				fieldLabel: 'Size',
				layout: 'hbox',
				items:[{
					xtype: 'textfield',
					name: 'size',
					value: loadParams['size'],
					validator: function (value) {
						if (loadParams['snapshotId'] && value < loadParams['size'])
							return "Volume size should be equal or greater than snapshot size";
						else
							return true;
					},
					width: 100
				}, {
					xtype: 'displayfield',
					value: 'GB',
					padding: '0 0 0 5'
				}]
			}, {
				xtype: 'textfield',
				fieldLabel: 'Snapshot',
				readOnly: true,
				hidden: !(loadParams['snapshotId']),
				name: 'snapshotId',
				value: loadParams['snapshotId'] || ''
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
						url: '/tools/openstack/volumes/xCreate',
						success: function (data) {
							Scalr.event.fireEvent('redirect',
								'#/tools/openstack/volumes/' + data.data.volumeId + '/view?platform='+ loadParams['platform'] +'&cloudLocation=' +
								this.up('form').down('[name="cloudLocation"]').getValue()
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
