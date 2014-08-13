Scalr.regPage('Scalr.ui.tools.openstack.lb.pools.info', function (loadParams, moduleParams) {

    var panel = Ext.create('Ext.form.Panel', {
        width: 460,
        title: Scalr.utils.getPlatformName(loadParams['platform']) + ' &raquo; Load balancers &raquo; Pools &raquo; Pool details',
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },
        tools: [{
            type: 'close',
            handler: function () {
                Scalr.event.fireEvent( 'close' );
            }
        }],

        items: [
            {
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                title: 'Pool details',
                defaults: {
                    labelWidth: 150,
                    value: '-'
                },
                items: [{
                    fieldLabel: 'ID',
                    xtype: 'displayfield',
                    name: 'id'
                }, {
                    fieldLabel: 'Tenant ID',
                    xtype: 'displayfield',
                    name: 'tenant_id'
                }, {
                    fieldLabel: 'VIP ID',
                    xtype: 'displayfield',
                    name: 'vip_id'
                }, {
                    fieldLabel: 'Name',
                    xtype: 'displayfield',
                    name: 'name'
                }, {
                    fieldLabel: 'Description',
                    xtype: 'displayfield',
                    name: 'description'
                }, {
                    fieldLabel: 'Subnet name',
                    xtype: 'displayfield',
                    name: 'subnet'
                }, {
                    fieldLabel: 'Subnet ID',
                    xtype: 'displayfield',
                    name: 'subnet_id'
                }, {
                    fieldLabel: 'Protocol',
                    xtype: 'displayfield',
                    name: 'protocol'
                }, {
                    fieldLabel: 'Load balancing method',
                    xtype: 'displayfield',
                    name: 'lb_method'
                }, {
                    fieldLabel: 'Members',
                    xtype: 'displayfield',
                    name: 'members'
                }, {
                    fieldLabel: 'Health monitors',
                    xtype: 'displayfield',
                    name: 'health_monitors'
                }, {
                    fieldLabel: 'Admin state up',
                    xtype: 'displayfield',
                    name: 'admin_state_up'
                }, {
                    fieldLabel: 'Status',
                    xtype: 'displayfield',
                    name: 'status'
                }]
            }]
    });

    var createLinksForMonitors = function() {
        var platform = 'platform=' + loadParams.platform,
            cloudLocation = '&cloudLocation=' + loadParams.cloudLocation,
            params = platform + cloudLocation + '&monitorId=',
            monitors = moduleParams.pool['health_monitors'],
            monitorsNumber = monitors.length;

        for (var i = 0; i < monitorsNumber; i++) {
            var monitorId = monitors[i],
                link = '#/tools/openstack/lb/monitors/info?' + params + monitorId;

            monitorId = '<a href="' + link + '">' + monitorId + '</a>';
            monitorId = !i ? monitorId : '<br/>' + monitorId;
            monitors[i] = monitorId;
        }
    };

    var formatMembersValue = function() {
        var members = moduleParams['pool']['members'];
        if (members.length > 1) {
            members = members.join().replace(',', ',<br />');
            moduleParams['pool']['members'] = members;
        }
    };

    createLinksForMonitors();
    formatMembersValue();
    panel.getForm().setValues(moduleParams['pool']);
    return panel;
});
