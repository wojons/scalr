Ext.define('Scalr.ui.ServerMenu', {
	extend: 'Scalr.ui.ActionsMenu',
	alias: 'widget.servermenu',

    hideOptionInfo: false,

    items: [{
        iconCls: 'x-menu-icon-cancel',
        text: 'Cancel',
        request: {
            processBox: {
                type: 'action'
            },
            url: '/servers/xServerCancelOperation/',
            dataHandler: function (data) {
                return { serverId: data['server_id'] };
            }
        },
        getVisibility: function(data) {
            return Ext.Array.contains(['Importing', 'Pending launch', 'Temporary'], data['status']);
        }
    }, {
        iconCls: 'x-menu-icon-information',
        text: 'Check import status',
        href: '#/roles/import?serverId={server_id}',
        getVisibility: function(data) {
            return data['status'] === 'Importing';
        }
    }, {
        iconCls: 'x-menu-icon-information',
        showAsQuickAction: true,
        text: 'Extended instance information',
        href: '#/servers/{server_id}/dashboard',
        getVisibility: function(data) {
            return !this.ownerCt.hideOptionInfo && !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary'], data['status']);
        }
    }, {
        iconCls: 'x-menu-icon-statsload',
        text: 'Load statistics',
        href: '#/monitoring?farmId={farm_id}&farmRoleId={farm_roleid}&index={index}',
        getVisibility: function(data) {
            return data['isScalarized'] == 1 && !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated', 'Suspended'], data['status']);
        }
    },{
        iconCls: 'x-menu-icon-statsload',
        text: 'CloudWatch statistics',
        getVisibility: function(data) {
            return data['platform'] === 'ec2' && data['status'] === 'Running';
        },
        menuHandler: function (data) {
            var location = data['cloud_location'].substring(0, (data['cloud_location'].length-2));
            Scalr.event.fireEvent('redirect', '#/tools/aws/ec2/cloudwatch?objectId=' + data['cloud_server_id'] + '&object=InstanceId&namespace=AWS/EC2&region=' + location);
        }
    }, {
        xtype: 'menuseparator'
    }, {
        text: 'Create server snapshot',
        iconCls: 'x-menu-icon-createserversnapshot',
        href: '#/servers/{server_id}/createSnapshot',
        getVisibility: function(data) {
        	if (data['isScalarized'] != 1 || Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated'], data['status']))
        		return false;
        	else {
        		if (data['status'] == 'Suspended') {
        			return (data['os_family'] === 'windows');
        		} else {
        			return true;
        		}
        	}

            return true;
        }
    }, {
        xtype: 'menuseparator'
    }, {
        iconCls: 'x-menu-icon-configure',
        text: 'Configure role in farm',
        href: '#/farms/designer?farmId={farm_id}&farmRoleId={farm_roleid}',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated'], data['status']);
        }
    }, {
        xtype: 'menuseparator'
    }, {
        text: 'Exclude from DNS zone',
        iconCls: 'x-menu-icon-excludedns',
        getVisibility: function(data) {
            return !data['excluded_from_dns'] && !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated', 'Suspended'], data['status']);
        },
        request: {
            processBox: {
                type: 'action'
            },
            url: '/servers/xServerExcludeFromDns/',
            dataHandler: function (data) {
                return { serverId: data['server_id'] };
            }
        }
    }, {
        text: 'Include in DNS zone',
        iconCls: 'x-menu-icon-includedns',
        getVisibility: function(data) {
            return data['excluded_from_dns'] && !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated', 'Suspended'], data['status']);
        },
        request: {
            processBox: {
                type: 'action'
            },
            url: '/servers/xServerIncludeInDns/',
            dataHandler: function (data) {
                return { serverId: data['server_id'] };
            }
        }
    }, {
        xtype: 'menuseparator'
    }, {
        text: 'View console output',
        iconCls: 'x-menu-icon-console',
        href: '#/servers/{server_id}/consoleoutput',
        getVisibility: function(data) {
            return (data['platform'] === 'ec2' || data['platform'] === 'gce') && !Ext.Array.contains(['Terminated', 'Pending launch', 'Troubleshooting', 'Suspended'], data['status']);
        }
    },
    {
        text: 'SSH console',
        iconCls: 'x-menu-icon-console',
        showAsQuickAction: true,
        menuHandler: function (data) {
            //var loadParams = this.up('servermenu').loadParams || {};
            var userSettings = Scalr.user['settings'] || {},
                sshLauncher = userSettings['ssh.console.launcher'] || 'applet';
            if (sshLauncher === 'application'){
                Scalr.cache['Scalr.ui.servers.sshconsole2'](data['server_id']);
            } else {
                Scalr.event.fireEvent('redirect', '#/servers/'+data['server_id']+'/sshConsole');
            }
        },
        getVisibility: function(data) {
            var moduleParams = this.up('servermenu').moduleParams || {};
            return (Ext.Array.contains(['Running', 'Initializing', 'Pending'], data['status']) && (data['local_ip'] || data['remote_ip'])) && moduleParams['mindtermEnabled'] && data['os_family'] != 'windows';
        }
    },
    {
        text: 'Scalr internal messaging',
        iconCls: 'x-menu-icon-internalmessage',
        href: '#/servers/messages?serverId={server_id}',
        getVisibility: function(data) {
            return data['isScalarized'] == 1 && !Ext.Array.contains(['Terminated', 'Troubleshooting'], data['status']);
        }
    },
    {
        text: 'Download SSH Private key in PEM format',
        iconCls: 'x-menu-icon-downloadprivatekey',
        menuHandler: function (data) {
            var cloudLocation = data['cloud_location'];
            if (data['platform'] == 'ec2') {
                cloudLocation = cloudLocation.split('/');
                cloudLocation = cloudLocation[0];
            }

            Scalr.utils.UserLoadFile('/sshkeys/downloadPrivate?' + Ext.Object.toQueryString({
                platform: data['platform'],
                cloudLocation: cloudLocation,
                farmId: data['farm_id']
            }));
        },
        getVisibility: function(data) {
            return !Ext.Array.contains(['Terminated', 'Pending launch', 'Troubleshooting', 'Suspended'], data['status']) && !Ext.isWindows && data['os_family'] !== 'windows';
        }
    }, {
        text: 'Download SSH Private key in PPK format',
        iconCls: 'x-menu-icon-downloadprivatekey',
        menuHandler: function (data) {
            var cloudLocation = data['cloud_location'];
            if (data['platform'] == 'ec2') {
                cloudLocation = cloudLocation.split('/');
                cloudLocation = cloudLocation[0];
            }

            Scalr.utils.UserLoadFile('/sshkeys/downloadPrivate?' + Ext.Object.toQueryString({
                platform: data['platform'],
                cloudLocation: cloudLocation,
                farmId: data['farm_id'],
                formatPpk: true
            }));
        },
        getVisibility: function(data) {
            return !Ext.Array.contains(['Terminated', 'Pending launch', 'Troubleshooting', 'Suspended'], data['status']) && Ext.isWindows && data['os_family'] !== 'windows';
        }
    }, {
        iconCls: 'x-menu-icon-key',
        text: 'Get administrator password',
        getVisibility: function(data) {
            // show get password in Pending status for development purposes
            return data['os_family'] === 'windows' && (data['status'] === 'Running' || data['status'] === 'Initializing' || (data['status'] === 'Pending' && Scalr.flags['betaMode']));
        },
        request: {
            processBox: {
                type: 'action'
            },
            url: '/servers/xGetWindowsPassword',
            dataHandler: function (data) {
                return { serverId: data['server_id'] };
            },
            success: function (data) {
                Scalr.utils.Window({
                    title: 'Administrator password',
                    xtype: 'form',
                    width: 460,
                    items: [{
                        xtype: 'fieldset',
                        cls: 'x-fieldset-separator-none',
                        items: [data.password ? {
                            xtype: 'textfield',
                            anchor: '100%',
                            readOnly: true,
                            value: data.password,
                            listeners: {
                                afterrender: function() {
                                    this.focus();
                                    this.inputEl.dom.select();
                                }
                            }
                        } : {
                            xtype: 'displayfield',
                			cls: 'x-form-field-warning',
                            value: data.encodedPassword ? 'Because you are using governance and we don\'t have the private key we can\'t decrypt the password.' : 'Empty password has been returned by AWS'
                        }]
                    }],
                    dockedItems: [{
                        xtype: 'container',
                        dock: 'bottom',
                        layout: {
                            type: 'hbox',
                            pack: 'center'
                        },
                        items: [{
                            xtype: 'button',
                            text: 'Close',
                            margin: '0 0 18 0',
                            handler: function() {
                                this.up('form').close();
                            }
                        }]
                    }]
                });
            }
        }
    },
    {
        xtype: 'menuseparator'
    }, {
        iconCls: 'x-menu-icon-execute',
        text: 'Execute script',
        href: '#/scripts/execute?serverId={server_id}',
        getVisibility: function(data) {
            return data['isScalarized'] == 1 && !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated', 'Suspended'], data['status']);
        }
    }, {
        iconCls: 'x-menu-icon-execute',
        text: 'Fire event',
        href: '#/scripts/events/fire?serverId={server_id}',
        getVisibility: function(data) {
            return Scalr.isAllowed('GENERAL_CUSTOM_EVENTS', 'fire') && !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated', 'Suspended'], data['status']);
        }
    }, {
        xtype: 'menuseparator'
    }, {
        text: 'Set disableAPITermination flag',
        iconCls: 'x-menu-icon-setflag',
        getVisibility: function(data) {
            return data['platform'] === 'ec2' && data['is_locked'] === 0 && (data['status'] === 'Running' || data['status'] === 'Initializing');
        },
        request: {
            confirmBox: {
                type: 'action',
                msg: 'Set disableAPITermination flag on server "{server_id}" ?'
            },
            processBox: {
                type: 'action',
                msg: 'Setting disableAPITermination flag ...'
            },
            url: '/servers/xLock/',
            dataHandler: function (data) {
                return { serverId: data['server_id'] };
            }
        }
    }, {
        text: 'Reboot',
        iconCls: 'x-menu-icon-reboot',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated', 'Suspended'], data['status']);
        },
        menuHandler: function(data) {
            var me = this;
            Scalr.cache['Scalr.ui.servers.reboot']([data['server_id']], function() {
                me.ownerCt.fireEvent('actioncomplete');
            });
        }
    }, {
        iconCls: 'x-menu-icon-terminate',
        text: 'Terminate',
        showAsQuickAction: true,
        getVisibility: function(data) {
            return !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Terminated'], data['status']);
        },
        menuHandler: function(data) {
            var me = this,
                forcefulDisabled = Scalr.isOpenstack(data['platform'], true) || data['platform'] === 'cloudstack';
            Scalr.cache['Scalr.ui.servers.terminate']([data['server_id']], forcefulDisabled, function() {
                me.ownerCt.fireEvent('actioncomplete');
            });
        }
    }, {
        text: 'Resume',
        iconCls: 'x-menu-icon-launch',
        getVisibility: function(data) {
            var osFamily = data['os_family'];
            if (!osFamily && Ext.isObject(data['os'])) {
                osFamily = data['os']['family'];
            }
            return osFamily !== 'windows' && data['status'] === 'Suspended' && (data['platform'] === 'gce' || data['platform'] === 'ec2' || data['platform'] === 'cloudstack' || Scalr.isOpenstack(data['platform'], true));
        },
        menuHandler: function(data) {
            var me = this;
            Scalr.cache['Scalr.ui.servers.resume']([data['server_id']], function() {
                me.ownerCt.fireEvent('actioncomplete');
            });
        }
    }, {
        text: 'Suspend',
        iconCls: 'x-menu-icon-suspend',
        getVisibility: function(data) {
            var osFamily = data['os_family'];
            if (!osFamily && Ext.isObject(data['os'])) {
                osFamily = data['os']['family'];
            }
            return osFamily !== 'windows' && data['status'] === 'Running' && (data['platform'] === 'gce' || data['platform'] === 'ec2' || data['platform'] === 'cloudstack' || Scalr.isOpenstack(data['platform'], true));
        },
        menuHandler: function(data) {
            var me = this;
            Scalr.cache['Scalr.ui.servers.suspend']([data['server_id']], function() {
                me.ownerCt.fireEvent('actioncomplete');
            });
        }
    }, {
        xtype: 'menuseparator'
    }, {
        text: 'Event Log',
        iconCls: 'x-menu-icon-events',
        href: '#/logs/events?eventServerId={server_id}'
    }, {
        iconCls: 'x-menu-icon-logs',
        text: 'System Log',
        href: '#/logs/system?serverId={server_id}',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Troubleshooting', 'Suspended'], data['status']);
        }
    }, {
        iconCls: 'x-menu-icon-logs',
        text: 'Scripting Log',
        href: '#/logs/scripting?serverId={server_id}',
        getVisibility: function(data) {
            return data['isScalarized'] == 1 && !Ext.Array.contains(['Troubleshooting', 'Suspended'], data['status']);
        }
    }, {
        text: 'Delete server',
        iconCls: 'x-menu-icon-delete',
        getVisibility: function(data) {
            return data['termination_error'] && (data['status'] === 'Pending terminate' || data['status'] === 'Terminated');
        },
        request: {
            confirmBox: {
                type: 'action',
                msg: 'Are you sure you want to remove server "{server_id}" ?'
            },
            processBox: {
                type: 'action',
                msg: 'Deleting server ...'
            },
            url: '/servers/xServerDelete/',
            dataHandler: function (data) {
                return { serverId: data['server_id'] };
            }
        }
    }]
});


Scalr.regPage('Scalr.ui.servers.terminate', function (serverIds, forcefulDisabled, callback){
    Scalr.Request({
        confirmBox: {
            type: 'terminate',
            msg: 'Terminate server' + (serverIds.length > 1 ? '(s)' : '') + ' %s ?',
            objects: serverIds,
            formWidth: forcefulDisabled ? 610 : (serverIds.length > 1 ? 560 : 450),
            form: {
                xtype: 'fieldset',
                title: 'Termination parameters',
                defaults: {
                    anchor: '100%'
                },
                items: [{
                    xtype: 'checkbox',
                    boxLabel: 'Decrease \'Minimum servers\' setting',
                    inputValue: 1,
                    name: 'descreaseMinInstancesSetting'
                },{
                    xtype: 'checkbox',
                    boxLabel: 'Forcefully terminate selected server(s)',
                    disabled: forcefulDisabled === true,
                    inputValue: 1,
                    name: 'forceTerminate'
                },{
                    xtype: 'displayfield',
                    cls: 'x-form-field-info',
                    margin: '12 0 0',
                    hidden: forcefulDisabled === false,
                    value: '<b>Forceful termination</b> '+(forcefulDisabled === 'partial' ? 'will not be applied to' : 'is not allowed for')+' <b>Cloudstack</b> and <b>Openstack</b> servers'
                }]
            }
        },
        processBox: {
            type: 'terminate',
            msg: 'Terminating server(s) ...'
        },
        url: '/servers/xServerTerminateServers/',
        params: {
            servers: Ext.encode(serverIds)
        },
        success: callback || Ext.emptyFn
    });
});

Scalr.regPage('Scalr.ui.servers.reboot', function (serverIds, callback){
    Scalr.Request({
        confirmBox: {
            formWidth: 440,
            type: 'reboot',
            msg: 'Reboot server' + (serverIds.length > 1 ? '(s)' : '') + ' %s ?',
            objects: serverIds,
            form: [{
                xtype: 'container',
                margin: '0 0 6 96',
                items: {
                    xtype: 'buttongroupfield',
                    fieldLabel: 'Type',
                    labelWidth: 40,
                    name: 'type',
                    value: 'soft',
                    width: 210,
                    defaults: {
                        width: 80
                    },
                    items: [{
                        text: 'Soft',
                        value: 'soft'
                    },{
                        text: 'Hard',
                        value: 'hard'
                    }]
                }
            }]
        },
        params: { servers: Ext.encode(serverIds) },
        processBox: {
            type: 'reboot',
            msg: 'Sending reboot command ...'
        },
        url: '/servers/xServerRebootServers/',
        success: function(result) {
            var ids = result.data;
            if (Ext.isArray(ids) && ids.length > 0) {
                Scalr.Request({
                    confirmBox: {
                        type: 'error',
                        msg: ' Soft reboot not available for the following server' + (ids.length > 1 ? '(s)' : '') + ':<br/> %s <br/>Do you want to perform hard reboot on ' + (ids.length > 1 ? 'those servers' : 'that server') + '?',
                        objects: ids,
                        ok: 'Reboot'
                    },
                    params: { servers: Ext.encode(ids), type: 'hard' },
                    processBox: {
                        type: 'reboot',
                        msg: 'Sending reboot command ...'
                    },
                    url: '/servers/xServerRebootServers/',
                    success: callback || Ext.emptyFn
                });
            } else {
                (callback || Ext.emptyFn)(result);
            }
        }
    });
});

Scalr.regPage('Scalr.ui.servers.suspend', function (serverIds, callback){
    Scalr.Request({
        confirmBox: {
            type: 'suspend',
            msg: 'Suspend server' + (serverIds.length > 1 ? '(s)' : '') + ' %s ?',
            objects: serverIds,
            ok: 'Suspend',
            formWidth: 430
        },
        params: { servers: Ext.encode(serverIds) },
        processBox: {
            type: 'action',
            msg: 'Sending suspend command ...'
        },
        url: '/servers/xSuspendServers/',
        success: function(result) {
            (callback || Ext.emptyFn)(result);
        }
    });
});

Scalr.regPage('Scalr.ui.servers.resume', function (serverIds, callback){
    Scalr.Request({
        confirmBox: {
            type: 'resume',
            msg: 'Resume server' + (serverIds.length > 1 ? '(s)' : '') + ' %s ?',
            objects: serverIds,
            ok: 'Resume',
            formWidth: 430
        },
        params: { servers: Ext.encode(serverIds) },
        processBox: {
            type: 'action',
            msg: 'Sending resume command ...'
        },
        url: '/servers/xResumeServers/',
        success: function(result) {
            (callback || Ext.emptyFn)(result);
        }
    });
});

Scalr.regPage('Scalr.ui.servers.sshconsole2', function (serverId){
    Scalr.Request({
        params: { serverId: serverId },
        url: '/servers/getSshConsoleSettings/',
        success: function(data) {
            var id = Ext.id(),
                frame = document.createElement('iframe'),
                isSupported = false,
                params = {
                    host: data.settings['ip'],
                    port: data.settings['ssh.console.port'],
                    user: data.settings['ssh.console.username'],
                    sshKeyName: data.settings['ssh.console.key_name'],
                    sshPrivateKey: data.settings['ssh.console.key'],
                    puttyPrivateKey: data.settings['ssh.console.putty_key'],
                    ignoreHostKeys: 1,
                    logLevel: data.settings['ssh.console.log_level'],
                    disableKeyAuth: data.settings['ssh.console.disable_key_auth'],
                    enableAgentForwarding: data.settings['ssh.console.enable_agent_forwarding'],
                    preferredProvider: data.settings['ssh.console.preferred_provider'] || ''
                };

            onBlurEvent = function() {
                isSupported = true;
            }
            Ext.fly(frame).set({
                id: id,
                name: id,
                cls: Ext.baseCSSPrefix + 'hidden-display',
                src: 'scalr+bar://?' + Ext.Object.toQueryString(params),
                tabIndex: -1
            });

            Ext.fly(window).on('blur', onBlurEvent);
            document.body.appendChild(frame);

            Ext.defer(function(){
                Ext.fly(window).un('blur', onBlurEvent);
                Ext.defer(Ext.removeNode, 100, Ext, [frame]);
                if (!isSupported) {
                    Scalr.message.Warning('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut in mauris venenatis, elementum arcu egestas, fermentum urna. Donec sit amet metus nec sem tristique egestas. Nulla sit amet commodo sapien. In consectetur ante nunc, nec interdum risus luctus at. Cras sit amet tincidunt tellus, eget venenatis tellus. Etiam sit amet erat sed enim finibus venenatis. Nunc maximus imperdiet massa in facilisis.');
                }
            }, 500);

        }
    });
});

