Scalr.regPage('Scalr.ui.admin.settings.core', function (loadParams, moduleParams) {
	var form = Ext.create('Ext.form.Panel',{
		bodyCls: 'x-panel-body-frame',
		title: 'Settings &raquo; Core',
		items: [{
			xtype: 'fieldset',
			title: 'Admin account',
			defaults: {
				labelWidth: 300,
				anchor: '100%',
				xtype: 'textfield'
			},
			items: [{
				name: 'email_address',
				fieldLabel: 'E-mail',
				vtype: 'email'
			},{
				name: 'email_name',
				fieldLabel: 'Name'
			}]
		},{
			xtype: 'fieldset',
			title: 'eMail settings',
			items: [{
				xtype: 'container',
				layout: {
					type: 'hbox'
				},
				items: [{
					labelWidth: 300,
					flex: 1,
					xtype: 'textfield',
					name: 'email_dns',
					fieldLabel: 'SMTP connection'
				},{
					xtype: 'displayinfofield',
					margin: '0 0 0 5',
					info: 'user:password@host:port. Leave empty to use MTA'
				}]
			},{
				xtype: 'textarea',
				name: 'team_emails',
				fieldLabel: 'Scalr team emails (one per line)',
				labelWidth: 300,
				anchor: '100%'
			}]
		},{
			xtype: 'fieldset',
			title: 'AWS settings',
			defaults: {
				labelWidth: 300,
				anchor: '100%'
			},
			items: [{
				xtype: 'textfield',
				name: 'secgroup_prefix',
				fieldLabel: 'Security groups prefix'
			},{
				xtype: 'textarea',
				name: 's3cfg_template',
				fieldLabel: 'S3cfg template'
			}]
		},{
			xtype: 'fieldset',
			title: 'RRD statistics settings',
			defaults: {
				labelWidth: 300,
				anchor: '100%',
				xtype: 'textfield'
			},
			items: [{
				name: 'rrdtool_path',
				fieldLabel: 'Path to rrdtool binary'
			},{
				name: 'rrd_default_font_path',
				fieldLabel: 'Path to font (for rrdtool)'
			},{
				name: 'rrd_db_dir',
				fieldLabel: 'Path to RRD database dir'
			},{
				xtype: 'container',
				layout: {
					type: 'hbox'
				},
				items: [{
					labelWidth: 300,
					flex: 1,
					xtype: 'textfield',
					name: 'rrd_stats_url',
					fieldLabel: 'Statistics URL'
				},{
					xtype: 'displayinfofield',
					margin: '0 0 0 5',
					info: 'Allowed tags: %fid% - Farm ID, %rn% - role name, %wn% - watcher name'
				}]
			},{
				xtype: 'container',
				layout: {
					type: 'hbox'
				},
				items: [{
					labelWidth: 300,
					flex: 1,
					xtype: 'textfield',
					name: 'rrd_graph_storage_path',
					fieldLabel: 'Path to graphics'
				},{
					xtype: 'displayinfofield',
					margin: '0 0 0 5',
					info: 'Bucket name for Amazon S3 or path to folder for Local filesystem'
				}]
			}]
		},{
			xtype: 'fieldset',
			title: 'Application settings',
			defaults: {
				labelWidth: 300,
				anchor: '100%',
				xtype: 'textfield'
			},
			items: [{
				xtype: 'container',
				layout: {
					type: 'hbox'
				},
				items: [{
					xtype: 'combo',
					queryMode: 'local',
					store: [['http','http://'],['https','https://']],
					name: 'http_proto',
					fieldLabel: 'Event handler URL',
					labelWidth: 300,
					editable: false
				},{
					flex: 1,
					xtype: 'textfield',
					name: 'eventhandler_url'
				}]
			}]
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
					Scalr.Request({
						processBox: {
							type: 'save'
						},
						url: '/admin/settings/xSave/',
						form: form.getForm(),
						success: function (data) {
						}
					});
				}
			}]
		}]
	});
	if (moduleParams['config'])
		form.getForm().setValues(moduleParams['config']);
		
	return form;
});