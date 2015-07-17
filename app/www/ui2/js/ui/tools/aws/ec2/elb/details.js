Scalr.regPage('Scalr.ui.tools.aws.ec2.elb.details', function (loadParams, moduleParams) {

    var loadBalancer = moduleParams['elb'];
    var healthCheck = loadBalancer['healthCheck'];
    var cloudLocation = loadParams['cloudLocation'];

    var availableZones = function (loadBalancer) {
        var availabilityZones = loadBalancer['availabilityZones'];

        return !Ext.isEmpty(availabilityZones)
            ? availabilityZones.join(', ')
            : '<i>There are no availability zones registered on this load balancer</i>';

    }(loadBalancer);


    var subnets = function (loadBalancer) {
        var subnets = loadBalancer['subnets'];

        return !Ext.isEmpty(subnets)
            ? subnets.join(', ')
            : '<i>There are no subnets registered on this load balancer</i>';

    }(loadBalancer);


    var instanceString = function (loadBalancer, cloudLocation) {
        var loadBalancerName = loadBalancer['loadBalancerName'];
        var instances = loadBalancer['instances'];

        return !Ext.isEmpty(instances)

            ? Ext.Array.map(instances, function (instance) {
                var instanceId = instance['instanceId'];

                return '<a href="#/tools/aws/ec2/elb/' + loadBalancerName
                    + '/instanceHealth?awsInstanceId=' + instanceId
                    + '&cloudLocation=' + cloudLocation + '" '
                    + 'style="cursor: pointer; text-decoration: none">'
                    + instanceId + '</a>';
            }).join(', ')

            : '<i>There are no instances registered on this load balancer</i>';

    }(loadBalancer, cloudLocation);


    var sgString = function (loadBalancer, cloudLocation) {
        var securityGroups = loadBalancer['securityGroups'];

        return !Ext.isEmpty(securityGroups)

            ? Ext.Array.map(securityGroups, function (group) {
                return '<a href="#/security/groups/'
                    + group +'/edit?cloudLocation=us-east-1&platform=ec2'
                    + '&cloudLocation=' + cloudLocation + '"'
                    + 'style="cursor: pointer; text-decoration: none">'
                    + group + '</a>';
            }).join(', ')

            : '<i>There are no security groups associated with this load balancer</i>';

    }(loadBalancer, cloudLocation);


    var listenerStore = Ext.create('Ext.data.JsonStore', {
        fields: [
            { name: 'protocol' },
            { name: 'loadBalancerPort' },
            { name: 'instancePort' },
            { name: 'policyNames' }
        ]
    });

    var listenerDescription = loadBalancer['listenerDescriptions'];

    var policyFlag = true;

    Ext.each(listenerDescription, function( item ){

        item[ 'listener' ].policyNames = item.policyNames;
        listenerStore.add( item['listener'] );

        if (item['listener'].protocol == 'HTTP' || item['listener'].protocol == 'HTTPS')
            policyFlag = false;
    });


    var policyStore = Ext.create('Ext.data.JsonStore', {
        fields: [
            { name: 'policyType' },
            { name: 'policyName' },
            { name: 'cookieSettings' }
        ],
        data: loadBalancer['policies']
    });


    var comboStore = Ext.create('Ext.data.JsonStore', {
        fields: [ 'policyName', 'description' ],
        data: [ { policyName: '', description : 'Do not use session stickiness on this ELB port' } ]
    });

    Ext.each( policyStore.getRange(), function(item){
        comboStore.add( { policyName: item.get('policyName'), description: item.get('policyName') } );
    });


    var listenerGrid = Ext.create('Ext.grid.Panel', {
        xtype: 'grid',
        itemId: 'listenerGrid',
        store: listenerStore,
        plugins: {
            ptype: 'gridstore'
        },
        viewConfig: {
            deferEmptyText: false,
            emptyText: 'No Listeners found'
        },
        columns: [{
            text: 'Protocol',
            dataIndex: 'protocol'
        },{
            flex: 1,
            text: 'LoadBalancer Port',
            sortable: false,
            dataIndex: 'loadBalancerPort'
        },{
            flex: 1,
            text: 'Instance Port',
            sortable: false,
            dataIndex: 'instancePort'
        },{
            text: 'Stickiness Policy',
            sortable: false,
            width: 140,
            dataIndex: 'policyNames'
        },{
            xtype: 'optionscolumn',
            menu: [{
                iconCls: 'x-menu-icon-edit',
                text:'Settings',
                showAsQuickAction: true,
                getVisibility: function(data) {
                    return data['protocol'] !== 'TCP' && data['protocol'] !== 'SSL';
                },
                menuHandler: function (data) {
                    Scalr.Request({
                        confirmBox: {
                            title: 'Create new parameter group',
                            formSimple: true,
                            formWidth: 500,
                            form: [{
                                xtype: 'combo',
                                name: 'policyName',
                                store: comboStore,
                                editable: false,
                                fieldLabel: 'Location',
                                queryMode: 'local',
                                valueField: 'policyName',
                                displayField: 'description',
                                value: listenerGrid.getSelectionModel().getLastSelected().get('policyNames') || ''
                            }]
                        },
                        processBox: {
                            type: 'save'
                        },
                        scope: this,
                        url: '/tools/aws/ec2/elb/'+ loadBalancer['loadBalancerName'] +'/xAssociateSp/',
                        params: {
                            cloudLocation: cloudLocation,
                            elbPort: data['loadBalancerPort']
                        },
                        success: function (data, response, options){
                            var rowIndex = listenerStore.find('loadBalancerPort', options.params.elbPort);
                            listenerStore.getAt(rowIndex).set('policyNames', options.params.policyName || '');
                        }
                    });
                }
            },{
                iconCls: 'x-menu-icon-delete',
                text:'Delete',
                showAsQuickAction: true,
                request: {
                    confirmBox: {
                        msg: 'Remove Listener?',
                        type: 'delete'
                    },
                    processBox: {
                        type: 'delete',
                        msg: 'Removing Listener ...'
                    },
                    dataHandler: function (data) {
                        this.recordId = data.id;
                        this.url = '/tools/aws/ec2/elb/'+ loadBalancer['loadBalancerName'] +'/xDeleteListeners/';
                        return {
                            lbPort: data['loadBalancerPort'],
                            cloudLocation: cloudLocation
                        };
                    },
                    success: function (data, response, options) {
                        listenerStore.remove(listenerStore.getById(this.recordId));
                        if (!policyFlag) {
                            var flag = true;
                            for(var i = 0; i < listenerStore.data.length; i++){
                                if(listenerStore.getAt(i).get('protocol') == "HTTP" || listenerStore.getAt(i).get('protocol') == "HTTPS") {
                                    flag = false;
                                    break;
                                }
                            }
                            if (flag) {
                                policyFlag = true;
                                panel.down('#policy').setPolicyVisible(false);
                            }
                        }
                    }
                }
            }]
        }],
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'inline',
            layout: {
                type: 'hbox',
                pack: 'start'
            },
            items: [{
                xtype: 'component',
                html:
                    '<div style="padding: 0 0 0 32px; margin-bottom: 0" class="x-fieldset-subheader">'
                    + '<span>Listeners</span>'
                    + '</div>'
            }, {
                xtype: 'tbfill'
            }, {
                text: 'Add listener',
                cls: 'x-btn-green',
                handler: function() {
                    Scalr.Request({
                        confirmBox: {
                            title: 'Add new Listener',
                            formWidth: 460,
                            formSimple: true,
                            form: [{
                                xtype: 'hiddenfield',
                                name: 'cloudLocation',
                                value: cloudLocation
                            },{
                                xtype: 'hiddenfield',
                                name: 'elbName',
                                value: loadBalancer['loadBalancerName']
                            },{
                                xtype: 'combo',
                                itemId: 'test',
                                name: 'protocol',
                                fieldLabel: 'Protocol',
                                labelWidth: 150,
                                editable: false,
                                store: [ 'TCP', 'HTTP', 'SSL', 'HTTPS' ],
                                queryMode: 'local',
                                allowBlank: false,
                                listeners: {
                                    change: function (field, value) {
                                        if (value == 'SSL' || value == 'HTTPS')
                                            this.next('[name="certificateId"]').show().enable();
                                        else
                                            this.next('[name="certificateId"]').hide().disable();
                                    }
                                }
                            },{
                                xtype: 'textfield',
                                name: 'lbPort',
                                fieldLabel: 'Load balancer port',
                                labelWidth: 150,
                                allowBlank: false,
                                validator: function (value) {
                                    if (value < 1024 || value > 65535) {
                                        if (value != 80 && value != 443)
                                            return 'Valid LoadBalancer ports are - 80, 443 and 1024 through 65535';
                                    }
                                    return true;
                                }
                            },{
                                xtype: 'textfield',
                                name: 'instancePort',
                                fieldLabel: 'Instance port',
                                labelWidth: 150,
                                allowBlank: false,
                                validator: function (value) {
                                    if (value < 1 || value > 65535)
                                        return 'Valid instance ports are one (1) through 65535';
                                    else
                                        return true;
                                }
                            },{
                                xtype: 'combo',
                                name: 'certificateId',
                                fieldLabel: 'SSL Certificate',
                                labelWidth: 120,
                                hidden: true,
                                disabled: true,
                                editable: false,
                                allowBlank: false,
                                store: {
                                    fields: [ 'name','path','arn','id','upload_date' ],
                                    proxy: {
                                        type: 'ajax',
                                        reader: {
                                            type: 'json',
                                            rootProperty: 'data'
                                        },
                                        url: '/tools/aws/iam/servercertificates/xListCertificates/'
                                    }
                                },
                                valueField: 'arn',
                                displayField: 'name'
                            }],
                            ok: 'Add'
                        },
                        processBox: {
                            msg: 'Adding new Listener ...',
                            type: 'save'
                        },
                        url: '/tools/aws/ec2/elb/'+ loadBalancer['loadBalancerName'] +'/xCreateListeners/',
                        scope: this,
                        success: function (data, response, options){
                            listenerStore.add({
                                protocol: options.params.protocol,
                                loadBalancerPort: options.params.lbPort,
                                instancePort: options.params.instancePort
                            });
                            if (policyFlag) {
                                if(options.params.protocol == "HTTP" || options.params.protocol == "HTTPS"){
                                    policyFlag = false;
                                    //todo
                                    panel.down('#policy').setPolicyVisible(true);
                                }
                            }
                        }
                    });
                }
            }]
        }]
    });

    var policyGrid = Ext.create('Ext.grid.Panel', {
        xtype: 'grid',
        itemId: 'policyGrid',
        plugins: {
            ptype: 'gridstore'
        },
        viewConfig: {
            deferEmptyText: false,
            emptyText: 'No Stickiness Policies found'
        },
        columns: [{
            text: 'Type',
            width: 120,
            dataIndex: 'policyType'
        },{
            flex: 1,
            text: 'Name',
            dataIndex: 'policyName'
        },{
            flex: 1,
            text: 'Cookie name / Exp. period',
            sortable: false,
            dataIndex: 'cookieSettings'
        },{
            xtype: 'optionscolumn',
            menu: [{
                iconCls: 'x-menu-icon-delete',
                text:'Delete',
                showAsQuickAction: true,
                request: {
                    confirmBox: {
                        msg: 'Remove Stickiness Policy?',
                        type: 'delete'
                    },
                    processBox: {
                        type: 'delete',
                        msg: 'Removing Stickiness Policy ...'
                    },
                    dataHandler: function (data) {
                        this.recordId = data.id;
                        this.url = '/tools/aws/ec2/elb/'+ loadBalancer['loadBalancerName'] +'/xDeleteSp/';
                        return {
                            policyName: data['policyName'],
                            cloudLocation: cloudLocation,
                            elbName: loadBalancer['loadBalancerName']
                        };
                    },
                    success: function (data, response, options ) {
                        policyStore.remove(policyStore.getById(this.recordId));
                        comboStore.remove(comboStore.getAt(comboStore.find('policyName', options.params['policyName'])));
                    }
                }
            }]
        }],
        hidden: policyFlag,
        store: policyStore,
        dockedItems: [{
            xtype: 'toolbar',
            dock: 'top',
            ui: 'inline',
            layout: {
                type: 'hbox',
                pack: 'start'
            },
            items: [{
                xtype: 'component',
                html:
                    '<div style="padding: 0 0 0 32px; margin-bottom: 0" class="x-fieldset-subheader">'
                    + '<span>Stickiness policies</span>'
                    + '<img style="margin-left: 6px" src="'
                    + Ext.BLANK_IMAGE_URL
                    + '" class="x-icon-info" data-qclickable="1" data-qtip=\''
                    + ''
                    + '\' />'
                    + '</div>'
            }, {
                xtype: 'tbfill'
            }, {
                text: 'Add policy',
                cls: 'x-btn-green',
                handler: function() {
                    Scalr.Request({
                        confirmBox: {
                            title: 'Create Stickiness Policies',
                            formSimple: true,
                            form: [{
                                xtype: 'hiddenfield',
                                name: 'cloudLocation',
                                value: cloudLocation
                            },{
                                xtype: 'hiddenfield',
                                name: 'elbName',
                                value: loadBalancer['loadBalancerName']
                            },{
                                xtype: 'combo',
                                itemId: 'polis',
                                name: 'policyType',
                                editable: false,
                                fieldLabel: 'Cookie Type',
                                queryMode: 'local',
                                store: [ ['AppCookie','App cookie'], ['LbCookie','Lb cookie'] ],
                                value: 'AppCookie',
                                listeners: {
                                    change: function (field, value){
                                        var nextContainer = this.next('container');
                                        if(value == "LbCookie"){
                                            nextContainer.down('[name="cookieSettings"]').labelEl.update("Exp. period:");
                                            nextContainer.down('[name="Sec"]').show();
                                        }
                                        else{
                                            nextContainer.down('[name="cookieSettings"]').labelEl.update("Cookie Name:");
                                            nextContainer.down('[name="Sec"]').hide();
                                        }
                                    }
                                }
                            },{
                                xtype: 'textfield',
                                name: 'policyName',
                                fieldLabel: 'Name',
                                allowBlank: false
                            },{
                                xtype: 'container',
                                layout: {
                                    type: 'hbox'
                                },
                                items:[{
                                    xtype: 'textfield',
                                    name: 'cookieSettings',
                                    fieldLabel: 'Cookie Name',
                                    allowBlank: false,
                                    labelWidth: 100,
                                    width: 365
                                },{
                                    margin: '0 0 0 2',
                                    xtype: 'displayfield',
                                    name: 'Sec',
                                    value: 'sec',
                                    hidden: true
                                }]
                            }],
                            formValidate: true
                        },
                        scope: this,
                        processBox: {
                            type: 'save'
                        },
                        url: '/tools/aws/ec2/elb/'+ loadBalancer['loadBalancerName'] +'/xCreateSp/',
                        success: function (data, response, options) {
                            policyStore.add({
                                policyType: options.params.policyType,
                                policyName: options.params.policyName,
                                cookieSettings: options.params.cookieSettings
                            });
                            comboStore.add({
                                policyName: options.params.policyName,
                                description: options.params.policyName
                            });
                        }
                    });
                }
            }]
        }]
    });


    var panel = Ext.create('Ext.Panel', {

        title: 'Tools &raquo; Amazon Web Services &raquo; Elastic Load Balancer &raquo; Details',
        width: 800,

        scalrOptions: {
            modal: true,
            menuTitle: 'AWS ELB',
            menuHref: '#/tools/aws/ec2/elb',
            menuParentStateId: 'grid-tools-aws-ec2-elb-view'
        },

        tools: [{
            type: 'close',
            handler: function () {
                Scalr.event.fireEvent('close');
            }
        }],

        items: [{
            xtype: 'fieldset',
            title: 'Elastic Load Balancer details',
            defaults: {
                labelWidth: 150,
                xtype: 'displayfield'
            },
            items: [{
                fieldLabel: 'Name',
                value: loadBalancer['loadBalancerName']
            }, {
                fieldLabel: 'Placement',
                value: loadBalancer['vpcId'] ? loadBalancer['vpcId'] : 'EC2'
            }, {
                fieldLabel: 'DNS name',
                value: loadBalancer['dnsName']
            }, {
                fieldLabel: 'Created At',
                value: loadBalancer['createdTime']['date']
            },{
                fieldLabel: 'Availability Zones',
                value: availableZones
            },{
                fieldLabel: 'Subnets',
                hidden: !loadBalancer['vpcId'],
                value: subnets
            },{
                fieldLabel: 'Instances',
                value: instanceString
            },{
                fieldLabel: 'Security groups',
                value: sgString
            }]
        }, {
            xtype: 'fieldset',
            title: 'HealthCheck settings',
            defaults: {
                labelWidth: 160,
                xtype: 'displayfield'
            },
            items: [{
                fieldLabel: 'Interval',
                value: healthCheck['interval']
            },{
                fieldLabel: 'Target',
                value: healthCheck['target']
            },{
                fieldLabel: 'Healthy Threshold',
                value: healthCheck['healthyThreshold']
            },{
                fieldLabel: 'Timeout',
                value: healthCheck['timeout'] + ' seconds'
            },{
                fieldLabel: 'UnHealthy Threshold',
                value: healthCheck['unhealthyThreshold']
            }]
        }, {
            xtype: 'fieldset',
            items: [ listenerGrid ]

        }, {
            xtype: 'fieldset',
            itemId: 'policy',
            cls: 'x-fieldset-separator-none',

            items: [{
                xtype: 'component',
                itemId: 'policyTitle',
                hidden: !policyFlag,
                html:
                    '<div style="padding: 0 0 0 32px; margin-bottom: 0" class="x-fieldset-subheader">'
                    + '<span>Stickiness policies</span>'
                    + '<img style="margin-left: 6px" src="'
                    + Ext.BLANK_IMAGE_URL
                    + '" class="x-icon-info" data-qclickable="1" data-qtip=\''
                    + ''
                    + '\' />'
                    + '</div>'
            }, policyGrid ],

            setPolicyVisible: function (visible) {
                var me = this;

                me.down('#policyTitle').setVisible(!visible);
                me.down('#policyGrid').setVisible(visible);

                return me;
            }

        }]
    });

    return panel;
});