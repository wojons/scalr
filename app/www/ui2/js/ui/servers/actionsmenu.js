Ext.define('Scalr.ui.ServerMenu', {
	extend: 'Scalr.ui.ActionsMenu',
	alias: 'widget.servermenu',

    hideOptionInfo: false,

    items: [{
        itemId: 'option.cancel',
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
        itemId: 'option.importstatus',
        iconCls: 'x-menu-icon-info',
        text: 'Check import status',
        href: '#/roles/import?serverId={server_id}',
        getVisibility: function(data) {
            return data['status'] === 'Importing';
        }
    }, {
        itemId: 'option.info',
        iconCls: 'x-menu-icon-info',
        text: 'Extended instance information',
        href: '#/servers/{server_id}/dashboard',
        getVisibility: function(data) {
            return !this.ownerCt.hideOptionInfo && !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary'], data['status']);
        }
    }, {
        itemId: 'option.windowspassword',
        iconCls: 'x-menu-icon-key',
        text: 'Get administrator password',
        getVisibility: function(data) {
            return data['os_family'] === 'windows' && (data['status'] === 'Running' || data['status'] === 'Initializing');
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
    }, {
        itemId: 'option.loadStats',
        iconCls: 'x-menu-icon-statsload',
        text: 'Load statistics',
        href: '#/monitoring/view?farmId={farm_id}&farmRoleId={farm_roleid}&index={index}',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated', 'Suspended'], data['status']);
        }
    },{
        itemId: 'option.cloudWatch',
        iconCls: 'x-menu-icon-statsload',
        text: 'CloudWatch statistics',
        getVisibility: function(data) {
            return data['platform'] === 'ec2' && data['status'] === 'Running';
        },
        menuHandler: function (data) {
            var location = data['cloud_location'].substring(0, (data['cloud_location'].length-2));
            document.location.href = '#/tools/aws/ec2/cloudwatch/view?objectId=' + data['cloud_server_id'] + '&object=InstanceId&namespace=AWS/EC2&region=' + location;
        }
    }, {
        xtype: 'menuseparator'
    }, {
        itemId: 'option.sync',
        text: 'Create server snapshot',
        iconCls: 'x-menu-icon-createserversnapshot',
        href: '#/servers/{server_id}/createSnapshot',
        getVisibility: function(data) {
        	if (Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated'], data['status']))
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
        itemId: 'option.editRole',
        iconCls: 'x-menu-icon-configure',
        text: 'Configure role in farm',
        href: '#/farms/{farm_id}/edit?farmRoleId={farm_roleid}',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated'], data['status']);
        }
    }, {
        xtype: 'menuseparator'
    }, {
        itemId: 'option.dnsEx',
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
        itemId: 'option.dnsIn',
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
        itemId: 'option.console',
        text: 'View console output',
        iconCls: 'x-menu-icon-console',
        href: '#/servers/{server_id}/consoleoutput',
        getVisibility: function(data) {
            return (data['platform'] === 'ec2' || data['platform'] === 'gce') && !Ext.Array.contains(['Terminated', 'Pending launch', 'Troubleshooting', 'Suspended'], data['status']);
        }
    },
    {
        itemId: 'option.messaging',
        text: 'Scalr internal messaging',
        iconCls: 'x-menu-icon-internalmessage',
        href: '#/servers/{server_id}/messages',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Terminated', 'Troubleshooting'], data['status']);
        }
    },
    {
        text: 'Download Private key',
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
            return !Ext.Array.contains(['Terminated', 'Pending launch', 'Troubleshooting', 'Suspended'], data['status']);
        }
    },
    {
        xtype: 'menuseparator'
    }, {
        itemId: 'option.exec',
        iconCls: 'x-menu-icon-execute',
        text: 'Execute script',
        href: '#/scripts/execute?serverId={server_id}',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated', 'Suspended'], data['status']);
        }
    }, {
        xtype: 'menuseparator'
    }, {
        itemId: 'option.disableapitermflag',
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
        itemId: 'option.reboot',
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
        itemId: 'option.term',
        iconCls: 'x-menu-icon-terminate',
        text: 'Terminate',
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
        itemId: 'option.resume',
        text: 'Resume',
        iconCls: 'x-menu-icon-launch',
        getVisibility: function(data) {
            return data['status'] === 'Suspended' && (data['platform'] === 'ec2' || Scalr.isOpenstack(data['platform'], true));
        },
        menuHandler: function(data) {
            var me = this;
            Scalr.cache['Scalr.ui.servers.resume']([data['server_id']], function() {
                me.ownerCt.fireEvent('actioncomplete');
            });
        }
    }, {
        itemId: 'option.suspend',
        text: 'Suspend',
        iconCls: 'x-menu-icon-suspend',
        getVisibility: function(data) {
            return data['status'] === 'Running' && (data['platform'] === 'ec2' || Scalr.isOpenstack(data['platform'], true));
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
        itemId: 'option.logs',
        iconCls: 'x-menu-icon-logs',
        text: 'System logs',
        href: '#/logs/system?serverId={server_id}',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Troubleshooting', 'Suspended'], data['status']);
        }
    }, {
        itemId: 'option.scripting_logs',
        iconCls: 'x-menu-icon-logs',
        text: 'Scripting logs',
        href: '#/logs/scripting?serverId={server_id}',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Troubleshooting', 'Suspended'], data['status']);
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
            type: 'reboot',
            msg: 'Reboot server' + (serverIds.length > 1 ? '(s)' : '') + ' %s ?',
            objects: serverIds,
            form: [{
                xtype: 'container',
                margin: '0 0 6 76',
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