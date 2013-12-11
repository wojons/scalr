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
                    items: [{
                        xtype: 'fieldset',
                        cls: 'x-fieldset-separator-none',
                        items: [{
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
        href: '#/monitoring/view?farmId={farm_id}&role={farm_roleid}&server_index={index}',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated'], data['status']);
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
            return !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated'], data['status']);
        }
    }, {
        xtype: 'menuseparator'
    }, {
        itemId: 'option.editRole',
        iconCls: 'x-menu-icon-configure',
        text: 'Configure role in farm',
        href: '#/farms/{farm_id}/edit?roleId={role_id}',
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
            return !data['excluded_from_dns'] && !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated'], data['status']);
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
            return data['excluded_from_dns'] && !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated'], data['status']);
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
            return (data['platform'] === 'ec2' || data['platform'] === 'gce') && !Ext.Array.contains(['Terminated', 'Pending launch', 'Troubleshooting'], data['status']);
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
        xtype: 'menuseparator'
    }, {
        itemId: 'option.exec',
        iconCls: 'x-menu-icon-execute',
        text: 'Execute script',
        href: '#/scripts/execute?serverId={server_id}',
        getVisibility: function(data) {
            return !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated'], data['status']);
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
            return data['platform'] !== 'gce' && !Ext.Array.contains(['Importing', 'Pending launch', 'Temporary', 'Troubleshooting', 'Terminated'], data['status']);
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
        xtype: 'menuseparator'
    }, {
        itemId: 'option.logs',
        iconCls: 'x-menu-icon-logs',
        text: 'System logs',
        href: '#/logs/system?serverId={server_id}',
        getVisibility: function(data) {
            return data['status'] !== 'Troubleshooting';
        }
    }, {
        itemId: 'option.scripting_logs',
        iconCls: 'x-menu-icon-logs',
        text: 'Scripting logs',
        href: '#/logs/scripting?serverId={server_id}',
        getVisibility: function(data) {
            return data['status'] !== 'Troubleshooting';
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
            objects: serverIds
        },
        params: { servers: Ext.encode(serverIds) },
        processBox: {
            type: 'reboot',
            msg: 'Sending reboot command ...'
        },
        url: '/servers/xServerRebootServers/',
        success: callback || Ext.emptyFn
    });
});