Scalr.regPage('Scalr.ui.tools.aws.ec2.elb.view', function (loadParams, moduleParams) {

	var store = Ext.create('Scalr.ui.ContinuousStore', {
		fields: [
			'name',
			'dtcreated',
			'dnsName',
			'farmRoleId',
			'farmName',
			'roleName',
			'vpcId', {
				name: 'farmId',
				defaultValue: null
			}
		],

		proxy: {
            type: 'ajax',
            url: '/tools/aws/ec2/elb/xListElasticLoadBalancers/',
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success'
            }
        },

        listeners: {
        	beforeload: function () {
        		grid.disableNewButton(true);
        	},
        	load: function () {
        		grid.disableNewButton(false);
        	}
        },

        removeByName: function (loadBalancerNames) {
            this.remove(Ext.Array.map(
                loadBalancerNames, function (name) {
                    return store.findRecord('name', name);
                }
            ));
        }
	});

	var grid = Ext.create('Ext.grid.Panel', {
		cls: 'x-panel-column-left',
        flex: 1,
        scrollable: true,

		store: store,

		plugins: [ 'applyparams', 'focusedrowpointer', {
            ptype: 'selectedrecord',
            disableSelection: false,
            clearOnRefresh: true,
            selectSingleRecord: true
        }, {
            ptype: 'continuousrenderer'
        }],

		viewConfig: {
			emptyText: 'No Elastic Load Balancer found'
		},

        selModel: Scalr.isAllowed('AWS_ELB', 'manage') ? 'selectedmodel' : null,

        listeners: {
            selectionchange: function (selModel, selections) {
                var toolbar = this.down('toolbar');
                toolbar.down('#delete').setDisabled(!selections.length);
            }
        },

		getCloudLocation: function () {
			return this.down('#cloudLocation').getValue();
		},

		disableNewButton: function (disabled) {
			var me = this;

            var button = me.down('#add');

			button
				.toggle(false, true)
				.setDisabled(!button.disabledByGovernance ? disabled : true);

			return me;
		},

		applyLoadBalancer: function (loadBalancer) {
	        var me = this;

	        var record = me.getSelectedRecord();
	        var store = me.getStore();

	        if (Ext.isEmpty(record)) {
	            record = store.add(loadBalancer)[0];
	        } else {
	            record.set(loadBalancer);
	            me.clearSelectedRecord();
	        }

	        me.view.focusRow(record);

	        return me;
	    },

        deleteLoadBalancer: function (loadBalancerName) {
            var me = this;

            var isDeleteMultiple = Ext.typeOf(loadBalancerName) === 'array';

            Scalr.Request({
                confirmBox: {
                    type: 'delete',
                    msg: !isDeleteMultiple
                        ? 'Delete Load Balancer <b>' + loadBalancerName + '</b> ?'
                        : 'Delete selected Load Balancer(s): %s ?',
                    objects: isDeleteMultiple ? loadBalancerName : null
                },
                processBox: {
                    type: 'delete',
                    msg: !isDeleteMultiple
                        ? 'Deleting <b>' + loadBalancerName + '</b> ...'
                        : 'Deleting selected Load Balancer(s) ...'
                },
                url: '/tools/aws/ec2/elb/xDelete',
                params: {
                    cloudLocation: me.getCloudLocation(),
                    elbNames: Ext.encode(
                        !isDeleteMultiple ? [ loadBalancerName ] : loadBalancerName
                    )
                },
                success: function (response) {
                    var deletedloadBalancersNames = response.processed;

                    if (!Ext.isEmpty(deletedloadBalancersNames)) {
                        store.removeByName(deletedloadBalancersNames);
                    }

                }
            });

            me.down('#add').toggle(false, true);

            return me;
        },

        deleteSelectedLoadBalancer: function () {
            var me = this;

            me.deleteLoadBalancer(
                me.getSelectedRecord().get('name')
            );

            return me;
        },

        deleteSelectedLoadBalancers: function () {
            var me = this;

            var names = [];

            Ext.Array.each(
                me.getSelectionModel().getSelection(),

                function (record) {
                    names.push(record.get('name'));
                }
            );

            me.deleteLoadBalancer(names);

            return me;
        },

        setLockedElbCreation: function (cloudLocation) {
            var me = this;

            cloudLocation = !Ext.isEmpty(cloudLocation)
                ? cloudLocation
                : me.down('#cloudLocation').getValue();

            var isRegionAllowed = form.applyCloudLocation(cloudLocation);

            var addNewButton = me.down('#add');
            addNewButton.disabledByGovernance = !isRegionAllowed;
            addNewButton
                .setDisabled(!isRegionAllowed)
                .setTooltip(!isRegionAllowed
                    ? Ext.String.htmlEncode(Scalr.strings.awsLoadBalancerVpcEnforced)
                    : ''
                );

            return me;
        },

        columns: [{
            flex: 1,
            header: "Elastic Load Balancer",
            dataIndex: 'name',
            sortable: true
        }, {
            flex: 1,
            header: "Used on",
            dataIndex: 'farmName',
            sortable: true,
            xtype: 'templatecolumn',
            tpl: new Ext.XTemplate(
                '<tpl if="farmId">' +
                '<a href="#/farms?farmId={farmId}" title="Farm {farmName}">{farmName}</a>' +
                '<tpl if="roleName">' +
                '&nbsp;&rarr;&nbsp;<a href="#/farms/{farmId}/roles/{farmRoleId}/view" title="Role {roleName}">{roleName}</a>' +
                '</tpl>' +
                '</tpl>' +
                '<tpl if="!farmId">&mdash;</tpl>'
            )
        }, /*{
            flex: 2,
            header: "DNS name",
            dataIndex: 'dnsName',
            sortable: true
        },*/ {
            header: "Placement",
            //width: 150,
            flex: 0.7,
            dataIndex: 'vpcId',
            sortable: true,
            xtype: 'templatecolumn',
            tpl: '{[values.vpcId || \'EC2\']}'
        }, {
            header: "Created at",
            width: 170,
            dataIndex: 'dtcreated',
            sortable: true,
            xtype: 'templatecolumn',
            tpl: '<tpl if="dtcreated">{dtcreated}<tpl else>&mdash;</tpl>'
        }],

		dockedItems: [{
			xtype: 'toolbar',
			dock: 'top',
            ui: 'simple',

            defaults: {
                margin: '0 0 0 12'
            },

			items: [{
				xtype: 'filterfield',
				store: store,
				margin: 0,
                flex: 1,
                minWidth: 100,
                maxWidth: 200
			}, {
                xtype: 'cloudlocationfield',
                platforms: [ 'ec2' ],
				gridStore: store,
				listeners: {
					change: function (field, value) {
						if (!Ext.isEmpty(value) && !Ext.isEmpty(value.cloudLocation)) {
                            grid.setLockedElbCreation(value.cloudLocation);
						}
					}
				}
			}, {
                xtype: 'tbfill'
            }, {
                text: 'New Load Balancer',
                itemId: 'add',
                cls: 'x-btn-green',
                enableToggle: true,
                disabledByGovernance: false,
                hidden: !Scalr.isAllowed('AWS_ELB', 'manage'),
                toggleHandler: function (button, state) {
                    if (state) {
                        grid.clearSelectedRecord();

                        form
                        	.setReadOnly(false)
                        	.clearListenersStore()
                            .setGovernanceDisabled(false)
                        	.show();

                        return true;
                    }

                    form.hide();

                    return false;
                }
			}, {
                itemId: 'refresh',
                iconCls: 'x-btn-icon-refresh',
                tooltip: 'Refresh',
                handler: function () {
                    store.clearAndLoad();
                    grid.down('#add').toggle(false, true);
                }
            }, {
                itemId: 'delete',
                iconCls: 'x-btn-icon-delete',
                cls: 'x-btn-red',
                tooltip: 'Select one or more Elastic Load Balancers to delete them',
                disabled: true,
                hidden: !Scalr.isAllowed('AWS_ELB', 'manage'),
                handler: function () {
                    grid.deleteSelectedLoadBalancers();
                }
            }]
		}]
	});

	var form = Ext.create('Scalr.ui.ElasticLoadBalancerForm', {

        hidden: true,

        accountId: moduleParams.accountId,

        remoteAddress: moduleParams.remoteAddress,

        onCreate: function (form, response) {
        	var loadBalancer = response.elb;

        	if (!Ext.isEmpty(loadBalancer)) {
                grid.applyLoadBalancer(loadBalancer);
            }
        },

        onDelete: function () {
            grid.deleteSelectedLoadBalancer();
        },

        onCancel: function () {
            grid.clearSelectedRecord();
            grid.down('#add').toggle(false, true);
        },

        listeners: {
        	afterloadrecord: function (record) {
                var me = this;

        		me.setReadOnly(true);

        		var listeners = record.get('listeners');

        		if (Ext.isEmpty(listeners)) {
        			me.getLoadBalancerData(
	                	grid.getCloudLocation(),
	                	record.get('name'),
	                	me.applyLoadBalancerData
	            	);
        		} else {
        			me
                        .setFormLoading(true)
                        .applyInstances(
                            record.get('instances'),
                            record.get('name')
                        )
        				.applyListeners(listeners)
        				.applyStickinessPolicies(record.get('policies'))
                        .toggleStickinessPolicies()
                        .setSecurityGroups(record.get('securityGroups'))
                        .setGovernanceDisabled(true)
                        .setFormLoading(false);

                    var vpcId = record.get('vpcId');
                    var placementField = me.down('[name=vpcId]');
                    var placementStore = placementField.getStore();
                    var vpcRecord = placementStore.findRecord('id', vpcId);

                    if (!Ext.isEmpty(vpcRecord)) {
                        placementField.setValue(vpcRecord);
                    } else {
                        placementField.setRawValue(vpcId);
                        placementField.fireEvent('change', placementField, vpcId);
                    }
        		}

        		grid.down('#add').toggle(false, true);
        	}
        }
    });

	return Ext.create('Ext.panel.Panel', {

        isFirstLoad: true,

        stateful: true,
        stateId: 'grid-tools-aws-ec2-elb-view',

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        updateGovernanceHandler: function (type) {
            if (type === '/core/governance') {
                form.updateGovernanceHandler();
            }
        },

        listeners: {
            applyparams: function () {
                var me = this;

                if (!me.isFirstLoad) {
                    grid.setLockedElbCreation();
                    return true;
                }

                me.isFirstLoad = false;
                return true;
            },

            boxready: function (panel) {
                Scalr.event.on('update', panel.updateGovernanceHandler, panel);
            }
        },

        scalrOptions: {
			reload: false,
			maximize: 'all',
            menuTitle: 'EC2 ELB',
            menuHref: '#/tools/aws/ec2/elb',
            menuFavorite: true
		},

        items: [ grid, {
            xtype: 'container',
            itemId: 'rightcol',
            flex: 0.6,
            maxWidth: 750,
            minWidth: 500,
            layout: 'fit',
            items: [ form ]
        }]
    });
});
