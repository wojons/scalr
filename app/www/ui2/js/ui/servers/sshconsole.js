Scalr.regPage('Scalr.ui.servers.sshconsole', function (loadParams, moduleParams) {
    var panel = Ext.create('Ext.form.Panel', {
		scalrOptions: {
			maximize: 'all',
            reload: false
		},
		title: 'Servers &raquo; ' + moduleParams['serverId'] + ' &raquo; SSH Launcher',
		tools: [{
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}],
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
            margin: '6 0 0 12',
            defaults: {
                margin: 0,
                labelWidth: 70
            },
            items: [{
                xtype: 'displayfield',
                fieldLabel: 'IP',
                value: moduleParams['remoteIp']
            },{
                xtype: 'displayfield',
                fieldLabel: 'Internal IP',
                value: moduleParams['localIp']
            },{
                xtype: 'displayfield',
                fieldLabel: 'Farm',
                value: '<a href="#/farms/view?farmId='+moduleParams['farmId']+'">' + moduleParams['farmName'] + '</a> (ID: ' + moduleParams['farmId'] + ')'
            },{
                xtype: 'displayfield',
                fieldLabel: 'Role',
                value: moduleParams['roleName'] + ' #'+ moduleParams['serverIndex']
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
                            '<param name="host" value="' + params['remoteIp'] + '">' +
                            '<param name="port" value="' + params['ssh.console.port'] + '">' +
                            '<param name="user" value="' + params['ssh.console.username'] + '">' +
                            '<param name="sshKeyName" value="' + params['ssh.console.key_name'] + '">' +
                            '<param name="sshPrivateKey" value="' + params['ssh.console.key'] + '">' +
                            '<param name="puttyPrivateKey" value="' +  params['ssh.console.putty_key'] + '">' +
                            '<param name="ignoreHostKeys" value="1">' +
                            '<param name="logLevel" value="' +  params['ssh.console.log_level'] + '">' +
                            '<param name="disableKeyAuth" value="' +  params['ssh.console.disable_key_auth'] + '">' +
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
			value: 'Safari users need to allow applet to work with filesystem. <a target="_blank" href="https://scalr-wiki.atlassian.net/wiki/x/WYB1">More info</a>'
        }]
	});

    return panel;
});
