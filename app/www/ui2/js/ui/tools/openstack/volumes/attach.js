Scalr.regPage('Scalr.ui.tools.openstack.volumes.attach', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		width: 700,
		title: 'Tools &raquo; Openstack &raquo; Volumes &raquo; ' + loadParams['volumeId'] + ' &raquo;Attach',
		fieldDefaults: {
			anchor: '100%'
		},

		items: [{
			xtype: 'fieldset',
			title: 'Attach options',
			labelWidth: 130,
			items: [{
				fieldLabel: 'Server',
				xtype: 'combo',
				name:'serverId',
				allowBlank: false,
				editable: true,
				forceSelection: true,
				autoSearch: false,
                selectOnFocus: true,
				store: {
					fields: [ 'id', 'name' ],
					data: moduleParams.servers,
					proxy: 'object'
				},
				value: '',
				displayField: 'name',
				valueField: 'id',
				queryMode: 'local',
				listeners: {
					added: function() {
						this.setValue(this.store.getAt(0).get('id'));
					}
				}
			}]
		}/*, {
			xtype: 'fieldset',
			title: 'Always attach this volume to selected server',
			collapsed: true,
			checkboxName: 'attachOnBoot',
			checkboxToggle: true,
			labelWidth: 100,
			items: [{
				xtype: 'fieldcontainer',
				hideLabel: true,
				layout: 'hbox',
				items: [{
					xtype:'checkbox',
					name:'mount',
					inputValue: 1,
					checked: false
				}, {
					xtype:'displayfield',
					margin: '0 0 0 3',
					value:'Automatically mount this volume after attach to '
				}, {
					xtype:'textfield',
					name:'mountPoint',
					margin: '0 0 0 3',
					value:'/mnt/storage',
					cls: 'x-form-check-wrap'
				}]
			}]
		}*/],

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
				text: 'Attach',
				handler: function() {
					Scalr.Request({
						processBox: {
							type: 'action',
							msg: 'Attaching ...'
						},
						form: this.up('form').getForm(),
						url: '/tools/openstack/volumes/xAttach',
						params: loadParams,
						success: function () {
							Scalr.event.fireEvent('redirect',
								'#/tools/openstack/volumes/' + loadParams['volumeId'] +
								'/view?cloudLocation=' + loadParams['cloudLocation'] + '&platform='+loadParams['platform']
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
