Scalr.regPage('Scalr.ui.services.apache.vhosts.create', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel', {
		bodyCls: 'x-panel-body-frame',
		title: 'Services &raquo; Apache &raquo; Vhosts &raquo; Create',
		fieldDefaults: {
			anchor: '100%'
		},
		width: 900,

		items: [{
			xtype: 'fieldset',
			title: 'General',
			items: [{
				xtype: 'textfield',
				name: 'domainName',
				fieldLabel: 'Domain name',
				allowBlank: false,
				value: moduleParams['domainName']
			}]
		}, {
			xtype: 'farmroles',
			title: 'Create virtualhost on',
			itemId: 'vhostTarget',
			params: moduleParams['farmWidget']
		}, {
			xtype: 'fieldset',
			title: 'SSL',
			checkboxToggle:  true,
			collapsed: !moduleParams['isSslEnabled'],
			checkboxName: 'isSslEnabled',
			inputValue: 1,
			defaults: {
				anchor: '-200'
			},
			items: [{
				xtype: 'filefield',
				name: 'certificate',
				fieldLabel: 'Certificate',
				value: moduleParams['sslCertName']
			}, {
				xtype: 'filefield',
				name: 'privateKey',
				fieldLabel: 'Private key',
				value: moduleParams['sslKeyName']
			}, {
				xtype: 'filefield',
				name: 'certificateChain',
				fieldLabel: 'Certificate chain',
				value: moduleParams['caCertName']
			}],
			listeners: {
				boxready:function() {
					this.checkboxCmp.on('change', function(){
						if (this.getValue()) {
							form.down('[name="sslTemplate"]').show();
						} else {
							form.down('[name="sslTemplate"]').hide();
						}
						
						form.doLayout();
					});
				}
			}
		}, {
			xtype: 'fieldset',
			title: 'Settings',
			defaults:{
				labelWidth: 200
			},
			items: [{
				xtype: 'textfield',
				name: 'documentRoot',
				fieldLabel: 'Document root',
				allowBlank: false,
				value: moduleParams['documentRoot']
			}, {
				xtype: 'textfield',
				name: 'logsDir',
				fieldLabel: 'Logs directory',
				allowBlank: false,
				value: moduleParams['logsDir']
			}, {
				xtype: 'textfield',
				name: 'serverAdmin',
				allowBlank: false,
				vtype: 'email',
				fieldLabel: 'Server admin\'s email',
				value: moduleParams['serverAdmin']
			}, {
				xtype: 'textarea',
				grow: true,
				growMax: 400,
				name: 'serverAlias',
				fieldLabel: 'Server alias (space separated)',
				value: moduleParams['serverAlias']
			}, {
				xtype: 'textarea',
				name: 'nonSslTemplate',
				fieldLabel: 'Server non-SSL template',
				grow: true,
				growMax: 400,
				value: moduleParams['nonSslTemplate']
			}, {
				xtype: 'textarea',
				name: 'sslTemplate',
				hidden:!moduleParams['isSslEnabled'],
				fieldLabel: 'Server SSL template',
				value: moduleParams['sslTemplate'],
				grow: true,
				growMax: 400
			} ]
		}],

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
					if (form.getForm().isValid())
						Scalr.Request({
							processBox: {
								type: 'save'
							},
							form: form.getForm(),
							url: '/services/apache/vhosts/xSave/',
							params: { 'vhostId': moduleParams['vhostId'] },
							success: function () {
								Scalr.event.fireEvent('close');
							}
						});
				}
			}, {
				xtype: 'button',
				margin: '0 0 0 5',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});

	return form;
});
