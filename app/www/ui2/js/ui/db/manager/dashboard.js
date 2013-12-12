Scalr.regPage('Scalr.ui.db.manager.dashboard', function (loadParams, moduleParams) {
	var panel = Ext.create('Ext.form.Panel', {
		width: 1140,
		title: 'Database status',
		bodyCls: 'scalr-ui-dbmsrstatus-panel',
        layout: 'auto',
        items: [{
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            cls: 'x-fieldset-separator-bottom',
            items: [{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                minWidth: 400,
                flex: 1.1,
                title: 'General',
                items: [{
                    xtype: 'displayfield',
                    name: 'general_dbname',
                    fieldLabel: 'Database type',
                    labelWidth: 130
                },{
                    xtype: 'container',
                    itemId: 'generalExtras',
                    height: 58,
                    defaults: {
                        labelWidth: 130
                    },
                    overflow: 'hidden'
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    defaults: {
                        flex: 1,
                        maxWidth: 180
                    },
                    items: [{
                        xtype: 'button',
                        itemId: 'manageConfigurationBtn',
                        text: 'Manage configuration',
                        margin: '0 5 0 0',
                        handler: function(){
                            var data = this.up('form').moduleParams;
                            Scalr.event.fireEvent('redirect', '#/services/configurations/manage?farmRoleId=' + data['farmRoleId'] + '&behavior=' + data['dbType']);
                        }
                    },{
                        xtype: 'button',
                        text: 'Connection details',
                        margin: '0 0 0 5',
                        handler: function() {
                            Scalr.utils.Window({
                                title: 'Connection details',
                                width: 640,
                                //padding: '0 24',
                                items: this.up('form').getConnectionDetails(),
                                dockedItems: [{
                                    xtype: 'container',
                                    cls: 'x-docked-buttons',
                                    dock: 'bottom',
                                    layout: {
                                        type: 'hbox',
                                        pack: 'center'
                                    },
                                    items: [{
                                        xtype: 'button',
                                        text: 'Close',
                                        handler: function() {
                                            this.up('#box').close();
                                        }
                                    }]
                                }]
                            });
                        }
                    }]
                }]
            },{
                xtype: 'fieldset',
                itemId: 'phpMyAdminAccess',
                cls: 'x-fieldset-separator-left',
                flex: 1,
                //width: null,
                items: [{
                    xtype: 'image',
                    src: '/ui2/images/ui/db/phpmyadmin_logo.png',
                    width: 146,
                    height: 88,
                    margin: '20 0 21 50'
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    defaults: {
                        width: 120
                    },
                    items: [{
                        xtype: 'button',
                        itemId: 'setupPMA',
                        text: 'Setup access',
                        hidden: true,
                        disabled: !Scalr.isAllowed('DB_DATABASE_STATUS', 'phpmyadmin'),
                        handler: function(){
                            var form = this.up('form'),
                                data = form.moduleParams;
                            Scalr.Request({
                                processBox: {
                                    type: 'action'
                                },
                                url: '/db/manager/xSetupPmaAccess/',
                                params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                success: function(){
                                    form.down('#setupPMA').hide();
                                    form.down('#PMAinProgress').show();
                                }
                            });
                        }
                    }, {
                        xtype: 'button',
                        itemId: 'launchPMA',
                        margin: '0 10 0 0',
                        text: 'Launch',
                        hidden: true,
                        disabled: !Scalr.isAllowed('DB_DATABASE_STATUS', 'phpmyadmin'),
                        handler: function() {
                            var data = this.up('form').moduleParams,
                                link = document.location.href.split('#');
                            window.open(link[0] + '#/services/mysql/pma?farmId=' + data['farmId']);
                            //Scalr.event.fireEvent('redirect', '#/services/mysql/pma?farmId=' + data['farmId']);
                        }
                    }, {
                        xtype: 'button',
                        cls: 'x-button-text-large',
                        itemId: 'resetPMA',
                        hidden: true,
                        disabled: !Scalr.isAllowed('DB_DATABASE_STATUS', 'phpmyadmin'),
                        text: 'Reset access',
                        handler: function(){
                            var form = this.up('form'),
                                data = form.moduleParams;
                            Scalr.Request({
                                confirmBox: {
                                    type: 'action',
                                    msg: 'Are you sure want to reset PMA access?'
                                },
                                processBox: {
                                    type: 'action'
                                },
                                url: '/db/manager/xSetupPmaAccess/',
                                params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                success: function(){
                                    form.down('#setupPMA').hide();
                                    form.down('#launchPMA').hide();
                                    form.down('#resetPMA').hide();
                                    form.down('#PMAinProgress').show();
                                }
                            });
                        }
                    },{
                        xtype: 'displayfield',
                        hidden: true,
                        itemId: 'PMAinProgress',
                        margin: '-8 0 0 0',
                        width: 280,
                        value: 'MySQL access details for PMA requested. Please refresh this page in a couple minutes...'
                    }]
                }]
            },{
                xtype: 'fieldset',
                flex: 1.1,
                minWidth: 400,
                title: 'Master storage',
                cls: 'x-fieldset-separator-left',
                defaults: {
                    labelWidth: 80
                },
                items: [{
                    xtype: 'displayfield',
                    name: 'storage_id',
                    fieldLabel: 'ID'
                },{
                    xtype: 'displayfield',
                    name: 'storage_engine_name',
                    fieldLabel: 'Type'
                },{
                    xtype: 'displayfield',
                    name: 'storage_fs',
                    fieldLabel: 'File system'
                },{
                    xtype: 'fieldcontainer',
                    fieldLabel: 'Usage',
                    layout: 'column',
                    items: [{
                        xtype: 'progressfield',
                        width: 180,
                        name: 'storage_size',
                        valueField: 'used',
                        units: 'Gb'
                    },{
                        xtype: 'button',
                        itemId: 'increaseStorageSizeBtn',
                        ui: 'flag',
                        cls: 'x-btn-flag-increase',
                        margin: '0 0 0 5',
                        tooltip: 'Increase storage size',
                        handler: function(){
                            var data = this.up('form').moduleParams;
                            Scalr.Request({
                                confirmBox: {
                                    type: 'action',
                                    formWidth: 700,
                                    msg: 'Are you sure want to increase storage size?',
                                    form: [{
                                        xtype: 'container',
                                        cls: 'x-container-fieldset',
                                        layout: 'anchor',
                                        items: [{
                                            xtype: 'displayfield',
                                            cls: 'x-form-field-warning',
                                            value: '<span style="color:red;">Attention! This operation will cause downtime on master server.</span><br />During creation and replacement of new storage, master server won\'t be able to accept any connections.'
                                        }, {
                                            xtype: 'fieldcontainer',
                                            hideLabel: true,
                                            layout: 'hbox',
                                            items: [{
                                                xtype: 'displayfield',
                                                value: 'New size (GB):',
                                                hideLabel: true
                                            }, {
                                                xtype: 'textfield',
                                                width: 50,
                                                hideLabel: true,
                                                margin: '0 0 0 5',
                                                name: 'newSize',
                                                value: (parseInt(data['storage']['size']['total'])+1)*2
                                            }, {
                                                xtype: 'displayfield',
                                                margin: '0 0 0 5',
                                                value: ' GB',
                                                hideLabel: true
                                            }]
                                        }]
                                    }]
                                },
                                processBox: {
                                    type: 'action',
                                    msg: 'Updating scalarizr ...'
                                },
                                url: '/db/manager/xGrowStorage/',
                                params: {farmRoleId: data['farmRoleId']},
                                success: function(data){
                                    Scalr.message.Success("Storage grow successfully initiated");
                                    Scalr.event.fireEvent('redirect', '#/operations/' + data['operationId'] + '/details');
                                }
                            });
                        }
                    }]
                }]
            }]
        },{
            xtype: 'fieldset',
            title: 'Cluster map',
            collapsible: true,
            items: [{
                xtype: 'dbmsclustermapfield',
                name: 'clustermap'
            }]
        },{
            xtype: 'container',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-right',
                hideOn: 'backupsNotSupported',
                flex: 1.04,
                title: 'Database dumps <img data-qtip="Database dumps description" class="tipHelp" src="/ui2/images/icons/info_icon_16x16.png" style="cursor: help; height: 16px;">',
                items: [{
                    xtype: 'displayfield',
                    showOn: 'backupsDisabled',
                    hidden: true,
                    value: 'Database dumps disabled.'
                },{
                    xtype: 'displayfield',
                    name: 'backup_schedule',
                    fieldLabel: 'Schedule',
                    hideOn: 'backupsDisabled'
                },{
                    xtype: 'displayfield',
                    name: 'backup_next',
                    fieldLabel: 'Next backup',
                    hideOn: 'backupsDisabled'
                },{
                    xtype: 'fieldcontainer',
                    fieldLabel: 'Last backup',
                    hideOn: 'backupsDisabled',
                    layout: 'column',
                    items: [{
                        xtype: 'displayfield',
                        name: 'backup_last'
                    },{
                        xtype: 'displayfield',
                        name: 'backup_last_result',
                        margin: '0 0 0 20',
                        style: 'font-weight:bold;',
                        valueToRaw: function(value) {
                            return value;
                        },
                        renderer: function(rawValue) {
                            var html = '';
                            if (rawValue.status) {
                                if (rawValue.status != 'ok') {
                                    html = '<span style="position:relative;top:-3px;color:#C00000;text-transform:capitalize">Failed';
                                    if (rawValue.error) {
                                        html += ' <img data-qtip="'+Ext.String.htmlEncode(rawValue.error)+'" src="/ui2/images/icons/question.png" style="cursor: help; height: 16px;position:relative;top:2px">';
                                    }
                                    html += '</span>';
                                } else {
                                    html = '<span style="color:#008000;text-transform:capitalize">Success</span>';
                                }
                            }
                            return html;
                        }
                    }]
                },{
                    xtype: 'dbmshistoryfield',
                    name: 'backup_history',
                    fieldLabel: 'History',
                    hideOn: 'backupsDisabled'
                },{
                    xtype: 'container',
                    hideOn: 'backupsDisabled',
                    layout: {
                        type: 'hbox'
                    },
                    margin: '24 0 12 0',
                    padding: '0 0 0 90',
                    defaults: {
                        margin: '0 16 0 0'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'Manage',
                        width: 140,
                        handler: function(){
                            var data = this.up('form').moduleParams;
                            Scalr.event.fireEvent('redirect', '#/db/backups?farmId='+data['farmId']);
                        }
                    },{
                        xtype: 'button',
                        text: 'Create now',
                        hideOn: 'backupInProgress',
                        margin: 0,
                        hidden: true,
                        width: 140,
                        handler: function(){
                            var data = this.up('form').moduleParams;
                            Scalr.Request({
                                confirmBox: {
                                    type: 'action',
                                    msg: 'Are you sure want to create backup?'
                                },
                                processBox: {
                                    type: 'action',
                                    msg: 'Sending backup request ...'
                                },
                                url: '/db/manager/xCreateBackup/',
                                params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                success: function(){
                                    Scalr.event.fireEvent('refresh');
                                }
                            });
                        }
                    },{
                        xtype: 'container',
                        showOn: 'backupInProgress',
                        hidden: true,
                        layout: {
                            type: 'hbox'
                        },
                        padding: '1 0 0 0',
                        items: [{
                            xtype: 'component',
                            cls: 'scalr-ui-dbmsr-status-inprogress',
                            html: 'In progress...'
                        },{
                            xtype: 'buttonfield',
                            itemId: 'cancelDataBackupBtn',
                            ui: 'custom',
                            cls: 'scalr-ui-dbmsr-status-stop',
                            margin: '0 0 0 12',
                            submitValue: false,
                            handler: function(){
                                var data = this.up('form').moduleParams;
                                Scalr.Request({
                                    confirmBox: {
                                        type: 'action',
                                        msg: 'Are you sure want to cancel running backup?'
                                    },
                                    processBox: {
                                        type: 'action',
                                        msg: 'Sending backup cancel request ...'
                                    },
                                    url: '/db/manager/xCancelBackup/',
                                    params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                    success: function(){
                                        Scalr.event.fireEvent('refresh');
                                    }
                                });
                            }
                        }]
                    }]
                }]
            },{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                flex: 1,
                title: 'Binary storage snapshots <img data-qtip="Binary storage snapshots" class="tipHelp" src="/ui2/images/icons/info_icon_16x16.png" style="cursor: help; height: 16px;">',
                defaults: {
                    labelWidth: 110
                },
                items: [{
                    xtype: 'displayfield',
                    showOn: 'bundleDisabled',
                    hidden: true,
                    value: 'Binary storage snapshots disabled.'
                },{
                    xtype: 'displayfield',
                    name: 'bundles_schedule',
                    fieldLabel: 'Schedule',
                    hideOn: 'bundleDisabled'
                },{
                    xtype: 'displayfield',
                    name: 'bundles_next',
                    fieldLabel: 'Next data bundle',
                    hideOn: 'bundleDisabled'
                },{
                    xtype: 'fieldcontainer',
                    fieldLabel: 'Last data bundle',
                    hideOn: 'bundleDisabled',
                    layout: 'column',
                    items: [{
                        xtype: 'displayfield',
                        name: 'bundles_last'
                    },{
                        xtype: 'displayfield',
                        name: 'bundles_last_result',
                        margin: '0 0 0 20',
                        style: 'font-weight:bold;',
                        valueToRaw: function(value) {
                            return value;
                        },
                        renderer: function(rawValue) {
                            var html = '';
                            if (rawValue.status) {
                                if (rawValue.status != 'ok') {
                                    html = '<span style="position:relative;top:-3px;color:#C00000;text-transform:capitalize">Failed';
                                    if (rawValue.error) {
                                        html += ' <img data-qtip="'+Ext.String.htmlEncode(rawValue.error)+'" src="/ui2/images/icons/question.png" style="cursor: help; height: 16px;position:relative;top:2px">';
                                    }
                                    html += '</span>';
                                } else {
                                    html = '<span style="color:#008000;text-transform:capitalize">Success</span>';
                                }
                            }
                            return html;
                        }
                    }]
                },{
                    xtype: 'dbmshistoryfield',
                    name: 'bundles_history',
                    fieldLabel: 'History',
                    hideOn: 'bundleDisabled'
                },{
                    xtype: 'container',
                    hideOn: 'bundleDisabled',
                    layout: {
                        type: 'hbox'
                    },
                    margin: '24 0 12 0',
                    padding: '0 0 0 160',
                    defaults: {
                        margin: '0 16 0 0'
                    },
                    items: [{
                        xtype: 'button',
                        text: 'Manage',
                        hidden: true,
                        width: 140,
                        handler: function(){
                            Scalr.message.Success('Under construction...');
                        }
                    },{
                        xtype: 'button',
                        hideOn: 'bundleInProgress',
                        text: 'Create now',
                        width: 140,
                        hidden: true,
                        handler: function(){
                            var form = this.up('form'),
                                data = form.moduleParams;
                            Scalr.Request({
                                confirmBox: {
                                    type: 'action',
                                    msg: 'Create data bundle?',
                                    formWidth: 600,
                                    form: form.getConfirmationDataBundleOptions()
                                },
                                processBox: {
                                    type: 'action',
                                    msg: 'Sending data bundle request ...'
                                },
                                url: '/db/manager/xCreateDataBundle/',
                                params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                success: function(){
                                    Scalr.event.fireEvent('refresh');
                                }
                            });
                        }

                    },{
                        xtype: 'container',
                        showOn: 'bundleInProgress',
                        hidden: true,
                        padding: '1 0 0 0',
                        layout: {
                            type: 'hbox'
                        },
                        items: [{
                            xtype: 'component',
                            cls: 'scalr-ui-dbmsr-status-inprogress',
                            html: 'In progress...'
                        },{
                            xtype: 'buttonfield',
                            itemId: 'cancelDataBundleBtn',
                            ui: 'custom',
                            cls: 'scalr-ui-dbmsr-status-stop',
                            margin: '0 0 0 12',
                            submitValue: false,
                            handler: function(){
                                var data = this.up('form').moduleParams;
                                Scalr.Request({
                                    confirmBox: {
                                        type: 'action',
                                        msg: 'Are you sure want to cancel data bundle?'
                                    },
                                    processBox: {
                                        type: 'action',
                                        msg: 'Sending data bundle cancel request ...'
                                    },
                                    url: '/db/manager/xCancelDataBundle/',
                                    params: {farmId: data['farmId'], farmRoleId: data['farmRoleId']},
                                    success: function(){
                                        Scalr.event.fireEvent('refresh');
                                    }
                                });
                            }

                        }]
                    }]
                }]
            }]
        }],
		listeners: {
			afterrender: function() {//todo: replace with something better
				this.loadData(moduleParams);
			}
		},
		
		toggleElementsByFeature: function(feature, visible) {
			var c = this.query('component[hideOn='+feature+'], component[showOn='+feature+']');
			for (var i=0, len=c.length; i<len; i++) {
				c[i].setVisible(!!(c[i].showOn && c[i].showOn == feature) === !!visible);
			}
		},
		
		loadData: function(data) {
			//console.log(data);
			this.moduleParams = data;
			var formatBackupValues = function(data, prefix) {
				prefix = prefix || 'backup';
				data = data || {};
				var history = data.history || [],
					values = {};
				values[prefix + '_schedule'] = data['schedule'] || '';
				values[prefix + '_next'] = data['next'] || 'Never';
				if (history.length) {
					values[prefix + '_last'] = history[history.length-1].date;
					values[prefix + '_last_result'] = {
						status: history[history.length-1].status,
						error: history[history.length-1].error
					}
					values[prefix + '_history'] = data.history;
				} else {
					values[prefix + '_last'] = 'Never';
				}
				return values;
			};
			
            data['storage'] = data['storage'] || {};
			var formValues = {
				general_dbname: data['name'],
				
				storage_id: data['storage']['id'] || '',
				storage_engine_name: data['storage']['engineName'] || '',
				storage_fs: data['storage']['fs'] || '',
				storage_size: data['storage']['size'] || 'not available'
			};
			
			//general extras
			var generalExtrasPanel = this.down('#generalExtras');
			generalExtrasPanel.removeAll();
			if (data['extras']) {
				Ext.Array.each(data['extras'], function(item){
					generalExtrasPanel.add({
						xtype: 'displayfield',
						fieldLabel: item.name,
						value: item.value
					});
				});
			}
			
			if (data['backups']) {
                if (data['backups']['supported']) {
                    Ext.apply(formValues, formatBackupValues(data['backups']));
                }
            }
			
            if (data['bundles']) {
                Ext.apply(formValues, formatBackupValues(data['bundles'], 'bundles'));
            }
			
			formValues.clustermap = data['servers'];
			
			this.refreshElements();
			this.getForm().setValues(formValues);
		},
		
		refreshElements: function() {
			var data = this.moduleParams;

            this.toggleElementsByFeature('bundleDisabled', !data['bundles']);
			if (data['bundles']) {
                this.toggleElementsByFeature('bundleInProgress', data['bundles']['inProgress']['status'] != '0');
            }
            
            this.toggleElementsByFeature('backupsDisabled', !data['backups']);
            if (data['backups']) {
                this.toggleElementsByFeature('backupsNotSupported', !data['backups']['supported']);
                if (data['backups']['supported']) {
                    this.toggleElementsByFeature('backupInProgress', data['backups']['inProgress']['status'] != '0');
                }
            }
			this.down('#manageConfigurationBtn').setVisible(data['dbType'] != 'mysql');
			this.down('#increaseStorageSizeBtn').setVisible(data['storage'] && (data['storage']['growSupported']));
			this.down('#cancelDataBundleBtn').setVisible(data['storage'] && data['storage']['engine'] == 'lvm');
			
			this.down('#cancelDataBackupBtn').setVisible(data['dbType'] == 'mysql2' || data['dbType'] == 'percona');
			
			if (data['pma']) {
				this.down('#phpMyAdminAccess').setVisible(true);
                this.down('#setupPMA').setVisible(!(data['pma']['accessSetupInProgress'] || data['pma']['configured']));
                this.down('#launchPMA').setVisible(data['pma']['configured']);
                this.down('#resetPMA').setVisible(data['pma']['accessError'] || data['pma']['configured']);
                this.down('#PMAinProgress').setVisible(data['pma']['accessSetupInProgress'] && !data['pma']['configured'] ? true : false);
			} else {
				this.down('#phpMyAdminAccess').setVisible(false);
			}

			//this.down('#phpMyAdminAccess').setVisible(data['dbType'] != 'mysql' || data['dbType'] != 'mysql2' || data['dbType'] != 'percona');
		},
		
		getConfirmationDataBundleOptions: function() {
			var data = this.moduleParams,
				confirmationDataBundleOptions = {};
			if ((data['dbType'] == 'percona' || data['dbType'] == 'mysql2') && (data['storage'] && data['storage']['engine'] == 'lvm')) {
				confirmationDataBundleOptions = {
					xtype: 'fieldset',
					title: 'Data bundle settings',
					items: [{
						xtype: 'combo',
						fieldLabel: 'Type',
						store: [['incremental', 'Incremental'], ['full', 'Full']],
						valueField: 'id',
						displayField: 'name',
						editable: false,
						queryMode: 'local',
						value: 'incremental',
						name: 'bundleType',
						labelWidth: 80,
						width: 500
					}, {
						xtype: 'combo',
						fieldLabel: 'Compression',
						store: [['', 'No compression (Recommended on small instances)'], ['gzip', 'gzip (Recommended on large instances)']],
						valueField: 'id',
						displayField: 'name',
						editable: false,
						queryMode: 'local',
						value: 'gzip',
						name: 'compressor',
						labelWidth: 80,
						width: 500
					}, {
						xtype: 'checkbox',
						hideLabel: true,
						name: 'useSlave',
						boxLabel: 'Use SLAVE server for data bundle'
					}]
				};
			} else if (data['dbType'] == 'percona' || data['dbType'] == 'mysql2') {
				confirmationDataBundleOptions = {
					xtype: 'fieldset',
					title: 'Data bundle settings',
					items: [{
						xtype: 'checkbox',
						hideLabel: true,
						name: 'useSlave',
						boxLabel: 'Use SLAVE server for data bundle'
					}]
				};
			}
			return confirmationDataBundleOptions;
		},
		
		getConnectionDetails: function() {
			var data = this.moduleParams,
				items = [];
			items.push({
				xtype: 'fieldset',
				title: 'Credentials',
				defaults: {
					labelWidth: 170
				},
				items: [{
					xtype: 'displayfield',
					fieldLabel: 'Master username',
					value: data['accessDetails']['username']
				},{
					xtype: 'displayfield',
					fieldLabel: 'Master password',
					value: data['accessDetails']['password']
				}]
			});
			
			if (data['accessDetails']['dns']) {
				items.push({
					xtype: 'fieldset',
					title: 'Endpoints',
					defaults: {
						labelWidth: 170,
						width: '100%'
					},
					items: [{
						xtype: 'displayfield',
						cls: 'x-form-field-info',
						value: 'Public - To connect to the service from the Internet<br / >Private - To connect to the service from another instance'
					}, {
						xtype: 'displayfield',
						fieldLabel: 'Writes endpoint (Public)',
						value: data['accessDetails']['dns']['master']['public']
					}, {
						xtype: 'displayfield',
						fieldLabel: 'Reads endpoint (Public)',
						value: data['accessDetails']['dns']['slave']['public']
					}, {
						xtype: 'displayfield',
						fieldLabel: 'Writes endpoint (Private)',
						value: data['accessDetails']['dns']['master']['private']
					}, {
						xtype: 'displayfield',
						fieldLabel: 'Reads endpoint (Private)',
						value: data['accessDetails']['dns']['slave']['private']
					}]
				});
			}
			return items;
		},
		
		tools: [{
			type: 'refresh',
			handler: function () {
				Scalr.event.fireEvent('refresh');
			}
		}, {
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}]
	});
	return panel;
});

if (!Ext.ClassManager.isCreated('Scalr.ui.FormFieldDbmsHistory')) {
	Ext.define('Scalr.ui.FormFieldDbmsHistory', {
		extend: 'Ext.form.field.Display',
		alias: 'widget.dbmshistoryfield',

		fieldSubTpl: [
			'<div id="{id}"',
			'<tpl if="fieldStyle"> style="{fieldStyle}"</tpl>', 
			' class="{fieldCls}"></div>',
			{
				compiled: true,
				disableFormats: true
			}
		],

		fieldCls: Ext.baseCSSPrefix + 'form-dbmshistory-field',

		setRawValue: function(value) {
			var me = this;
			me.rawValue = value;
			if (me.rendered) {
				var html = [],
					list = value.slice(-8);
				for (var i=0, len=list.length; i<len; i++) {
					html.push('<div title="'+Ext.String.htmlEncode(list[i].date + (list[i].error ? ' - ' + list[i].error : ''))+'" class="item'+(list[i].status != 'ok' ? ' failed' : '')+'"></div>');
				}
				Ext.DomHelper.append(me.inputEl.dom, html.join(''), true);
				me.updateLayout();
			}
			return value;
		},

		valueToRaw: function(value) {
			return value;
		}
	});
}

if (!Ext.ClassManager.isCreated('Scalr.ui.FormFieldDbmsClusterMap')) {
	Ext.define('Scalr.ui.FormFieldDbmsClusterMap', {
		extend: 'Ext.form.FieldContainer',
		alias: 'widget.dbmsclustermapfield',

		mixins: {
			field: 'Ext.form.field.Field'
		},

		baseCls: 'x-container x-form-dbmsclustermapfield',
		allowBlank: false,
		
		layout: {
			type: 'vbox',
			align: 'center'
		},
		currentServerId: null,
		
		buttonConfig: {
			xtype: 'custombutton',
			cls: 'x-dbmsclustermapfield-btn',
			overCls: 'x-dbmsclustermapfield-btn-over',
			pressedCls: 'x-dbmsclustermapfield-btn-pressed',
			enableToggle: true,
			width: 192,
			height: 90,
			margin: 0,
			allowDepress: true,
			toggleGroup: 'dbmsclustermapfield',
			handler: function() {
				var comp = this.up('dbmsclustermapfield');
				if (this.pressed) {
					comp.showServerDetails(this.serverInfo);
				} else if (!Ext.ButtonToggleManager.getPressed('dbmsclustermapfield')){
					comp.hideServerDetails();
				}
			},
			renderTpl:
				'<div class="x-btn-el x-dbmsclustermapfield-inner x-dbmsclustermapfield-{type}" id="{id}-btnEl">'+
					'<div><span class="title">{title}:</span> {ip}</div>'+
					'<div>{location}</div>'+
					'<div class="status status-{status}">{status_title}</div>'+
				'</div>'
		},
		initComponent: function() {
			var me = this;
			me.callParent();
			me.initField();
			if (!me.name) {
				me.name = me.getInputId();
			}
		},

		getValue: function() {
			var me = this,
				val = me.getRawValue();
			me.value = val;
			return val;
		},

		setValue: function(value) {
			var me = this;
			me.setRawValue(value);
			return me.mixins.field.setValue.call(me, value);
		},

		getRawValue: function() {
			var me = this;
			return me.rawValue;
		},

		setRawValue: function(value) {
			var me = this;
			me.rawValue = me.valueToRaw(value);
			if (me.rendered) {
				me.renderButtons(me.rawValue);
			}
			return value;
		},
		
		valueToRaw: function(data) {
			var rawValue = {master: {}, slaves: []};
			if (data) {
				for (var i=0, len=data.length; i<len; i++) {
					if (data[i].serverRole == 'master') {
						rawValue.master = data[i];
					} else {
						rawValue.slaves.push(data[i]);
					}
				}
			}
			return rawValue;
		},
        
        getServerStatus: function(status){
            var result;
            switch (status) {
                case 'Pending':
                case 'Initializing':
                    result = '<span style="color:#f79501">' + status.toLowerCase() + '</span>';
                break;
                case 'Running':
                    result = '<span style="color:#008000">' + status.toLowerCase() + '</span>';
                break;
                default:
                    result = '<span style="color:#EA5535">down</span>';
                break;
            }
            return result;
        },
        
        getReplicationStatus: function(status) {
            var result;
            switch (status) {
                case 'up':
                    result = 'ok';
                break;
                case 'down':
                    result = 'broken';
                break;
                default:
                    result = status || 'error'
                break;
            }
            return result;
        },
        
		renderButtons: function(data) {
			this.suspendLayouts();
			this.removeAll();
            
            //render master button
            var master = {
                height: 85,
                serverInfo: Ext.clone(data.master),
                disabled: data.master.status !== 'Running',
                renderData: {
                    type: 'master',
                    title: 'Master',
                    location: data.master.cloudLocation || '',
                    ip: data.master.remoteIp || '',
                    serverid: data.master.serverId || '',
                    status_title: 'Server is ' + this.getServerStatus(data.master.status)
                }
            };
            master.serverInfo.title = master.renderData.title;
            this.add({
                xtype: 'container',
                cls: 'x-dbmsclustermapfield-container',
                padding: 12,
                items: Ext.applyIf(master, this.buttonConfig)
            });
            
            //render slaves buttons
            var slavesRowsCount = Math.ceil((data.slaves.length + 1)/5);//
            for (var row=0; row<slavesRowsCount; row++) {
                var slave, status, replication, statusTitle, slaves, limit; 

                slaves = this.add({
                    xtype: 'container',
                    cls: 'x-dbmsclustermapfield-container',
                    width: '100%',
                    layout: {
                        type: 'hbox',
                        pack: 'center'
                    },
                    padding: 12,
                    margin: '2 0 0 0'
                });
                
                limit = row*5 + 5 > data.slaves.length ? data.slaves.length : row*5 + 5;
                for (var i=row*5; i<limit; i++) {
                    replication = data.slaves[i].replication || {};

                    if (data.slaves[i].status == 'Running') {
                        status = this.getReplicationStatus(replication.status);
                        statusTitle = status == 'error' ? 'Can\'t get replication status' : 'Replication is ' + status;
                    } else {
                        status = 'down';
                        statusTitle = 'Server is ' + this.getServerStatus(data.slaves[i].status);
                    }

                    slave = {
                        serverInfo: Ext.clone(data.slaves[i]),
                        renderData: {
                            type: 'slave',
                            title: 'Slave #' + (i+1),
                            location: data.slaves[i].cloudLocation || '',
                            ip: data.slaves[i].remoteIp || 'Not available',
                            serverid: data.slaves[i].serverId || '',
                            status: status,
                            status_title: statusTitle
                        },
                        margin: '0 5'

                    };
                    slave.serverInfo.title = slave.renderData.title;
                    slaves.add(Ext.applyIf(slave, this.buttonConfig));
                }
            }
            
            //render launch new slave button
            var addBtn = {
                cls: 'x-dbmsclustermapfield-add',
                overCls: 'x-dbmsclustermapfield-btn-over',
                pressedCls: 'x-dbmsclustermapfield-add-pressed',
                toggleGroup: null,
                renderTpl:
                    '<div class="x-btn-el x-dbmsclustermapfield-inner" id="{id}-btnEl">'+
                        'Launch new slave'+
                    '</div>',
                margin: '0 5',
                handler: function(){
                    var data = this.up('form').moduleParams,
                        r = {
                            confirmBox: {
                                msg: 'Launch new slave?',
                                type: 'launch'
                            },
                            processBox: {
                                type: 'launch'
                            },
                            url: '/farms/' + data['farmId'] + '/roles/' + data['farmRoleId'] + '/xLaunchNewServer',
                            success: function (data) {
                                Scalr.event.fireEvent('refresh');
                            }
                        };
                    Scalr.Request(r);
                }
            };
            slaves.add(Ext.applyIf(addBtn, this.buttonConfig));
            
            //server details form
            this.detailsForm = this.add({
                xtype: 'form',
                cls: 'x-dbmsclustermapfield-container',
                width: '100%',
                padding: 0,
                margin: '2 0 0 0',
                hidden: true,
                items: [{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        align: 'stretch'
                    },
                    defaults: {
                        flex: 1
                    },
                    cls: 'x-fieldset-separator-bottom',
                    items: [{
                        xtype: 'fieldset',
                        title: 'Basic info',
                        cls: 'x-fieldset-separator-none',
                        defaults: {
                            labelWidth: 180
                        },
                        items: [{
                            xtype: 'displayfield',
                            fieldLabel: 'ID',
                            name: 'server_id'
                        },{
                            xtype: 'displayfield',
                            fieldLabel: 'Remote IP',
                            name: 'server_remote_ip'
                        },{
                            xtype: 'displayfield',
                            fieldLabel: 'Local IP',
                            name: 'server_local_ip'
                        }]
                    },{
                        xtype: 'toolfieldset',
                        title: 'General metrics',
                        cls: 'x-fieldset-separator-left',
                        items: [{
                            xtype: 'progressfield',
                            fieldLabel: 'Memory usage',
                            name: 'server_metrics_memory',
                            width: 360,
                            units: 'Gb',
                            emptyText: 'Loading...',
                            fieldCls: 'x-form-progress-field x-form-progress-field-small'
                        },{
                            xtype: 'progressfield',
                            fieldLabel: 'CPU load',
                            name: 'server_metrics_cpu',
                            width: 360,
                            emptyText: 'Loading...',
                            fieldCls: 'x-form-progress-field x-form-progress-field-small'
                        },{
                            xtype: 'displayfield',
                            fieldLabel: 'Load averages',
                            name: 'server_load_average'
                        }],
                        tools: [{
                            type: 'refresh',
                            //tooltip: 'Refresh general metrics',
                            handler: function () {
                                this.up('dbmsclustermapfield').loadGeneralMetrics();
                            }			
                        }]
                    }]
                },{
                    xtype: 'container',
                    layout: {
                        type: 'hbox',
                        align: 'stretch'
                    },
                    defaults: {
                        flex: 1
                    },
                    items: [{
                        xtype: 'fieldset',
                        title: 'Database metrics',
                        itemId: 'serverMetrics',
                        cls: 'x-fieldset-separator-none',
                        defaults: {
                            labelWidth: 180
                        }
                    },{
                        xtype: 'toolfieldset',
                        title: 'Statistics',
                        cls: 'x-fieldset-separator-left',
                        items: [{
                            xtype: 'container',
                            layout: 'column',
                            defaults: {
                                width: 220
                            },
                            items: [{
                                xtype: 'label',
                                text: 'Memory:'
                            },{
                                xtype: 'label',
                                text: 'CPU:'
                            }]
                        },{
                            xtype: 'container',
                            layout: 'column',
                            defaults: {
                                margin: '0 20 10 0'
                            },
                            items: [{
                                xtype: 'chartpreview',
                                itemId: 'memoryChart'
                            },{
                                xtype: 'chartpreview',
                                itemId: 'cpuChart'
                            }]
                        },{
                            xtype: 'container',
                            layout: 'column',
                            defaults: {
                                width: 220
                            },
                            items: [{
                                xtype: 'label',
                                text: 'Load averages:'
                            },{
                                xtype: 'label',
                                text: 'Network:'
                            }]
                        },{
                            xtype: 'container',
                            layout: 'column',
                            defaults: {
                                margin: '0 20 0 0'
                            },
                            items: [{
                                xtype: 'chartpreview',
                                itemId: 'laChart'
                            },{
                                xtype: 'chartpreview',
                                itemId: 'netChart'
                            }]
                        }],
                        tools: [{
                            type: 'refresh',
                            //tooltip: 'Refresh general metrics',
                            handler: function () {
                                this.up('dbmsclustermapfield').loadChartsData();
                            }			
                        }]
                    }]
                }]
            });
			this.resumeLayouts(true);
			
		},
		
		hideServerDetails: function() {
			var form = this.up('form');
			if (this.detailsForm) {
				var scrollTop = form.body.getScroll().top;
				form.suspendLayouts();
				this.detailsForm.hide();
				form.resumeLayouts(true);
				form.body.scrollTo('top', scrollTop);
				this.currentServerId = null;
			}
		},

		showServerDetails: function(data) {
			if (this.detailsForm) {
				var form = this.up('form'),
					scrollTop = form.body.getScroll().top,
					metricsPanel = this.detailsForm.down('#serverMetrics'),
                    replication = data['replication'] || {};
				form.suspendLayouts();
				this.detailsForm.getForm().setValues({
					server_id: '<a href="#/servers/' + data.serverId + '/dashboard">' + (data.serverId || '') + '</a>',
					server_remote_ip: data.remoteIp || '',
					server_local_ip: data.localIp || '',
					server_metrics_memory: null,
					server_metrics_cpu: null,
					server_load_average: ''
				});

				metricsPanel.removeAll();
                
				if (replication['status'] == 'error' || !replication['status']) {
					var message = replication['message'] ? replication['message'] : 'Can\'t get replication status'
					metricsPanel.add({
						xtype: 'displayfield',
						value: '<span style="color:#C00000">' + message + '</span>'
					});
				} else if (replication[form.moduleParams['dbType']]) {
                    Ext.Object.each(replication[form.moduleParams['dbType']], function(name, value){
                        if (form.moduleParams['dbType'] === 'redis' && Ext.isObject(value)) {
                            metricsPanel.add({
                                xtype: 'label',
                                html: '<b>' + name + ':</b>'
                            });
                            var c = metricsPanel.add({
                                xtype: 'container', 
                                margin: '12 0 20 0',
                                defaults: {
                                    labelWidth: 180
                                }
                            });
                            Ext.Object.each(value, function(key, val) {
                                c.add({
                                    xtype: 'displayfield',
                                    fieldLabel: Ext.String.capitalize(key),
                                    value: val,
                                    margin: '0 0 6 0'
                                });
                            });
                        } else if (!Ext.isEmpty(value)) {
                            metricsPanel.add({
                                xtype: 'displayfield',
                                fieldLabel: Ext.String.capitalize(name),
                                value: value
                            });
                        }
                    });
                }
				metricsPanel.show();

				this.detailsForm.show();
				form.resumeLayouts(true);
				delete this.currentServerInfo;
				this.currentServerInfo = data;
				this.loadGeneralMetrics();
				this.loadChartsData();
				form.body.scrollTo('top', scrollTop);
			}
		},

		loadChartsData: function() {
			var me = this,
				data = me.up('form').moduleParams;
			if (me.currentServerInfo) {
				me.down('#memoryChart').loadStatistics(data['farmId'], 'MEMSNMP', 'daily', data['farmRoleId'], me.currentServerInfo);	
				me.down('#cpuChart').loadStatistics(data['farmId'], 'CPUSNMP', 'daily', data['farmRoleId'], me.currentServerInfo);	
				me.down('#laChart').loadStatistics(data['farmId'], 'LASNMP', 'daily', data['farmRoleId'], me.currentServerInfo);	
				me.down('#netChart').loadStatistics(data['farmId'], 'NETSNMP', 'daily', data['farmRoleId'], me.currentServerInfo);	
			}
		},
		loadGeneralMetrics: function() {
			var me = this,
				serverId = me.currentServerInfo.serverId,
				form = me.up('form'),
				scrollTop = form.body.getScroll().top;
			if (serverId) {
				me.detailsForm.getForm().setValues({
					server_load_average: null,
					server_metrics_memory: null,
					server_metrics_cpu: null
				});
				form.body.scrollTo('top', scrollTop);
				Scalr.Request({
					url: '/servers/xGetHealthDetails',
					params: {
						serverId: serverId
					},
					success: function (res) {
						if (
                            !form.isDestroyed && !me.isDestroyed && serverId == me.currentServerInfo.serverId &&
                            res.data['memory'] && res.data['cpu']
                        ) {
							form.suspendLayouts();
							me.detailsForm.getForm().setValues({
								server_load_average: res.data['la'],
								server_metrics_memory: {
									total: res.data['memory']['total']*1,
									value: Ext.util.Format.round(res.data['memory']['total'] - res.data['memory']['free'], 2)
								},
								server_metrics_cpu: (100 - res.data['cpu']['idle'])/100
							});
							form.resumeLayouts(false);
						}
					},
                    failure: function() {
                        if (!form.isDestroyed && !me.isDestroyed ) {
                            form.suspendLayouts();
                            me.detailsForm.getForm().setValues({
                                server_load_average: 'not available',
                                server_metrics_memory: 'not available',
                                server_metrics_cpu: 'not available'
                            });
                            form.resumeLayouts(false);
                        }
                    }
				});
			}
		},

		getInputId: function() {
			return this.inputId || (this.inputId = this.id + '-inputEl');
		}
	});
}
