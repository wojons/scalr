Scalr.regPage('Scalr.ui.servers.sshconsole', function (loadParams, moduleParams) {
    var panel = Ext.create('Ext.form.Panel', {
		scalrOptions: {
			maximize: 'all',
            reload: false,
            menuTitle: 'Servers',
            menuHref: '#/servers',
            menuParentStateId: 'grid-servers-view'
		},
        listeners: {
            hide: function() {
                this.close();
            },
            activate: function() {
                this.down('#sshConsoleWrap').show();
            },
            deactivate: function(){
                this.down('#sshConsoleWrap').hide();
            }
        },

		layout: {
			type: 'vbox',
			align: 'stretch',
			pack: 'start'
		},
		items: [{
            xtype: 'container',
            layout: 'anchor',
            margin: '6 0 0 12',
            defaults: {
                margin: 0,
                labelWidth: 100
            },
            items: [{
                xtype: 'fieldcontainer',
                layout: 'hbox',
                anchor: '100%',
                items: [{
                    xtype: 'displayfield',
                    fieldLabel: 'Server ID',
                    value: moduleParams['serverId']
                },{
                    xtype: 'tbfill'
                },{
                    xtype: 'button',
                    ui: '',
                    margin: '0 12 0 0',
                    iconCls: 'x-tool-img x-tool-close',
                    handler: function () {
                        Scalr.event.fireEvent('close');
                    }
                }]
            },{
                xtype: 'displayfield',
                fieldLabel: 'Farm',
                value: '<a href="#/farms?farmId='+moduleParams['farmId']+'">' + moduleParams['farmName'] + '</a> (ID: ' + moduleParams['farmId'] + ')'
            },{
                xtype: 'displayfield',
                fieldLabel: 'Farm Role',
                value: '<a href="#/farms/designer?farmId='+moduleParams['farmId']+'&farmRoleId='+moduleParams['farmRoleId']+'">' + moduleParams['farmRoleAlias'] + '</a> #'+ moduleParams['serverIndex']
            },{
                xtype: 'displayfield',
                value: 'Using this Server\'s ' + (moduleParams['ssh.console.ip'] === 'public' ? 'External' : 'Internal') + ' IP <b>' + moduleParams['ip'] +
                       '</b>, and logging in as <b>' + moduleParams['ssh.console.username'] + '</b>.' +
                       ' Access <a href="#/core/settings?ssh"> SSH Launcher Settings</a> to use another IP or username.'
            }]
		}, {
            xtype: 'component',
            itemId: 'sshConsoleWrap',
			flex: 1,
			layout: 'fit',
            margin: '12 0 0 0',
			html: '<span style="margin:12px">Loading, please wait ...</span>',
            refreshApplet: function(params) {
                this.show();
                this.el.update(
                    '<object ' +
                        'code="com.scalr.ssh.launcher.SSHLauncherApplet" ' +
                        'archive="/ui2/java/ssh-launcher.jar" ' +
                        'type="application/x-java-applet" ' +
                        'width="100%" height="100%">' +
                            '<param name="cache_archive" VALUE="/ui2/java/ssh-launcher.jar">' +
                            '<param name="cache_version" VALUE="0.13.1">' +
                            '<param name="host" value="' + params['ip'] + '">' +
                            '<param name="port" value="' + params['ssh.console.port'] + '">' +
                            '<param name="user" value="' + params['ssh.console.username'] + '">' +
                            '<param name="sshKeyName" value="' + params['ssh.console.key_name'] + '">' +
                            '<param name="sshPrivateKey" value="' + params['ssh.console.key'] + '">' +
                            '<param name="puttyPrivateKey" value="' +  params['ssh.console.putty_key'] + '">' +
                            '<param name="ignoreHostKeys" value="1">' +
                            '<param name="logLevel" value="' +  params['ssh.console.log_level'] + '">' +
                            '<param name="disableKeyAuth" value="' +  params['ssh.console.disable_key_auth'] + '">' +
                            '<param name="enableAgentForwarding" value="' +  params['ssh.console.enable_agent_forwarding'] + '">' +
                            (params['ssh.console.preferred_provider'] ? '<param name="preferredProvider" value="'+params['ssh.console.preferred_provider']+'">' : '')+
                    '</object>'
                );
            },
			listeners: {
				boxready: function() {
                    this.el.setVisibilityMode(Ext.Element.OFFSETS);
                    this.refreshApplet(moduleParams);
				}
			}

        }],
		dockedItems: [{
            dock: 'top',
			xtype: 'displayfield',
            hidden: !Ext.isSafari,
			cls: 'x-form-field-info x-form-field-info-fit',
			value: 'Safari users need to allow applet to work with filesystem. <a target="_blank" href="https://scalr-wiki.atlassian.net/wiki/x/WYB1">More information</a>'
        }, {
            dock: 'top',
            xtype: 'displayfield',
            hidden: !(Ext.isChrome && (parseInt(window.navigator.appVersion.match(/Chrome\/(\d+)\./)[1], 10) >= 42)),
            cls: 'x-form-field-info x-form-field-info-fit',
            value: 'Chrome 42 (and greater) users need to enable NPAPI Plugins for the SSH Launcher to function. <a target="_blank" href="https://scalr-wiki.atlassian.net/wiki/x/G4BPAQ">More information</a>'
        }]
	});

    return panel;
});
