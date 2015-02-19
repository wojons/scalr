Scalr.regPage('Scalr.ui.tools.aws.rds.instances.createSubnetGroup', function (loadParams, moduleParams) {

    var form = Ext.create('Ext.form.Panel', {
        title: 'Create subnet group',
        fieldDefaults: {
            anchor: '100%'
        },
        scalrOptions: {
            modal: true
        },
        width: 600,
        defaults: {
            labelWidth: 120
        },
        bodyCls: 'x-container-fieldset x-fieldset-no-bottom-padding',

        items: [{
            xtype: 'textfield',
            name: 'dbSubnetGroupName',
            fieldLabel: 'Name',
            allowBlank: false
        },{
            xtype: 'textfield',
            name: 'dbSubnetGroupDescription',
            fieldLabel: 'Description',
            allowBlank: false
        },{
            xtype: 'comboboxselect',
            name: 'subnets',
            fieldLabel: 'Subnets',
            displayField: 'description',
            valueField: 'id',
            //emptyText: '',
            flex: 1,
            queryCaching: false,
            clearDataBeforeQuery: true,
            allowBlank: false,
            minChars: 0,
            queryDelay: 10,
            store: {
                fields: ['id', 'name', 'description', 'ips_left', 'type', 'availability_zone', 'cidr'],
                proxy: {
                    type: 'cachedrequest',
                    crscope: 'rdsInstances',
                    url: '/tools/aws/vpc/xListSubnets',
                    filterFields: ['description']
                }
            },
            plugins: [{
                ptype: 'comboaddnew',
                pluginId: 'comboaddnew',
                url: '/tools/aws/vpc/createSubnet',
                applyNewValue: false
            }],
            getSubmitValue: function () {
                var me = this;

                var value = me.getValue();

                if (Ext.isEmpty(value)) {
                    value = '';
                }

                return Ext.encode(value);
            },
            listeners: {
                afterrender: function (me) {
                    var cloudLocation = moduleParams.cloudLocation;
                    var vpcId = moduleParams.vpcId;

                    me.getStore().getProxy().params = {
                        cloudLocation: cloudLocation,
                        vpcId: vpcId,
                        extended: 1
                    };

                    me.getPlugin('comboaddnew').postUrl = '?' + Ext.Object.toQueryString({
                        cloudLocation: cloudLocation,
                        vpcId: vpcId
                    });
                },
                addnew: function(item) {
                    Scalr.CachedRequestManager.get('rdsInstances').setExpired({
                        url: '/tools/aws/vpc/xListSubnets',
                        params: this.store.proxy.params
                    });
                }
            },
            listConfig: {
                style: 'white-space:nowrap',
                cls: 'x-boundlist-alt',
                tpl:
                '<tpl for="."><div class="x-boundlist-item" style="height: auto; width: auto;line-height:20px">' +
                '<div><span style="font-weight: bold">{[values.name || \'<i>No name</i>\' ]} - {id}</span> <span style="font-style: italic;font-size:90%">(Type: <b>{type:capitalize}</b>)</span></div>' +
                '<div>{cidr} in {availability_zone} [IPs left: {ips_left}]</div>' +
                '</div></tpl>'
            }
        }],

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
                text: 'Create',
                handler: function() {
                    if (form.getForm().isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'save'
                            },
                            form: form.getForm(),
                            params: {
                                cloudLocation: moduleParams.cloudLocation
                            },
                            scope: this,
                            url: '/tools/aws/rds/instances/xCreateSubnetGroup',
                            success: function (response) {
                                var subnetGroup = response['subnetGroup'];

                                if (subnetGroup) {
                                    Scalr.event.fireEvent(
                                        'update',
                                        '/tools/aws/rds/instances/createSubnetGroup',
                                        subnetGroup
                                    );
                                }

                                Scalr.event.fireEvent('close');
                            }
                        });
                    }
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

    return form;
});
