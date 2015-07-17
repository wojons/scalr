Scalr.regPage('Scalr.ui.tools.aws.ec2.elb.create', function (loadParams, moduleParams) {
	var form = Scalr.utils.Window({
        xtype: 'form',
		title: 'AWS &raquo; Elastic Load Balancer &raquo; Create',
		fieldDefaults: {
			anchor: '100%'
		},
		scalrOptions: {
			modalWindow: true
		},
		width: 900,
		items: [{
			xtype: 'container',
            cls: 'x-fieldset-separator-bottom',
			layout: {
				type: 'hbox',
				align: 'stretch'
			},
			items: [{
				xtype: 'fieldset',
				title: 'Healthcheck',
                cls: 'x-fieldset-separator-none',
				flex: .7,
				//margin: '0 10 10 0',
				defaults: {
					labelWidth: 160
				},
				items: [{
                    xtype: 'textfield',
                    fieldLabel: 'Healthy Threshold',
                    name: 'healthythreshold',
                    width: 40,
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        icons: {
                            id: 'info',
                            tooltip: 'The number of consecutive health probe successes required ' +
                            'before moving the instance to the Healthy state.<br />The default is ' +
                            '3 and a valid value lies between 2 and 10.'
                        }
                    }]
                }, {
					xtype: 'fieldcontainer',
					layout: 'hbox',
					fieldLabel: 'Interval',
					items: [{
						xtype: 'textfield',
						name: 'interval',
						width: 40
					}, {
						xtype: 'displayfield',
						margin: '0 0 0 6',
						value: 'seconds',
                        plugins: [{
                            ptype: 'fieldicons',
                            align: 'right',
                            icons: {
                                id: 'info',
                                tooltip: 'The approximate interval (in seconds) between health checks of ' +
                                'an individual instance.<br />The default is 30 seconds and a valid interval ' +
                                'must be between 5 seconds and 600 seconds.' +
                                'Also, the interval value must be greater than the Timeout value'
                            }
                        }]
					}]
				}, {
					xtype: 'fieldcontainer',
					layout: 'hbox',
					fieldLabel: 'Timeout',
					items: [{
						xtype: 'textfield',
						name: 'timeout',
						width: 40
					}, {
						xtype: 'displayfield',
						margin: '0 0 0 6',
						value: 'seconds',
                        plugins: [{
                            ptype: 'fieldicons',
                            align: 'right',
                            position: 'outer',
                            icons: {
                                id: 'info',
                                tooltip: 'Amount of time (in seconds) during which no response means ' +
                                'a failed health probe. <br />The default is five seconds and a valid ' +
                                'value must be between 2 seconds and 60 seconds.' +
                                'Also, the timeout value must be less than the Interval value.'
                            }
                        }]
					}]
				}, {
                    xtype: 'textfield',
                    fieldLabel: 'Unhealthy Threshold',
                    name: 'unhealthythreshold',
                    width: 40,
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        icons: {
                            id: 'info',
                            tooltip: 'The number of consecutive health probe failures that move the instance to ' +
                            'the unhealthy state.<br />The default is 5 and a valid value lies between 2 and 10.'
                        }
                    }]
                }, {
                    xtype: 'textfield',
                    fieldLabel: 'Target',
                    name: 'target',
                    flex: 1,
                    plugins: [{
                        ptype: 'fieldicons',
                        align: 'right',
                        icons: {
                            id: 'info',
                            tooltip: 'The instance being checked. The protocol is either TCP or HTTP. The range of valid ports is one (1) through 65535.<br />' +
                            'Notes: TCP is the default, specified as a TCP: port pair, for example "TCP:5000".' +
                            'In this case a healthcheck simply attempts to open a TCP connection to the instance on the specified port.' +
                            'Failure to connect within the configured timeout is considered unhealthy.<br />' +
                            'For HTTP, the situation is different. HTTP is specified as a "HTTP:port/PathToPing" grouping, for example "HTTP:80/weather/us/wa/seattle". In this case, a HTTP GET request is issued to the instance on the given port and path. Any answer other than "200 OK" within the timeout period is considered unhealthy.<br />' +
                            'The total length of the HTTP ping target needs to be 1024 16-bit Unicode characters or less.'
                        }
                    }]
                }]
			}, {
				xtype: 'fieldset',
				flex: 1,
                cls: 'x-fieldset-separator-left',
				title: 'Placement',
                defaults: {
                    labelWidth: 110
                },
				items: [{
                    xtype: 'combo',
                    emptyText: 'EC2',
                    fieldLabel: 'Create LB inside',
                    name: 'vpcId',
                    editable: false,
                    hideInputOnReadOnly: true,
                    queryCaching: false,
                    clearDataBeforeQuery: true,
                    valueField: 'id',
                    displayField: 'name',
                    plugins: [{
                        ptype: 'fieldicons',
                        icons: ['governance']
                    },{
                        ptype: 'comboaddnew',
                        pluginId: 'comboaddnew',
                        url: '/tools/aws/vpc/create'
                    }],
                    store: {
                        fields: [ 'id', 'name', 'info' ],
                        proxy: {
                            type: 'cachedrequest',
                            url: '/platforms/ec2/xGetVpcList',
                            root: 'vpc',
                            prependData: [{id: 0, name: 'EC2'}]
                        }
                    },
                    listeners: {
                        addnew: function(item) {
                            Scalr.CachedRequestManager.get().setExpired({
                                url: this.store.proxy.url,
                                params: this.store.proxy.params
                            });
                        },
                        change: function(field, value) {
                            if (value) {
                                form.down('#zones').hide().disable();
                                form.down('[name="scheme"]').show().enable();

                                var field = form.down('#subnets'),
                                    vpcLimits = Scalr.getGovernance('ec2', 'aws.vpc');
                                field.reset();
                                field.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + loadParams['cloudLocation'] + '&vpcId=' + value;
                                field.getPlugin('comboaddnew').setDisabled(vpcLimits && vpcLimits['ids'] && Ext.isArray(vpcLimits['ids'][value]));
                                Ext.apply(field.store.getProxy(), {
                                    params: {cloudLocation: loadParams['cloudLocation'], vpcId: value, extended: 1},
                                    filterFn: vpcLimits && vpcLimits['ids'] && vpcLimits['ids'][value] ? field.subnetsFilterFn : null,
                                    filterFnScope: field
                                });
                                field.show().enable();
                                field.clearInvalid();
                            } else {
                                form.down('#zones').show().enable();
                                form.down('[name="scheme"]').hide().disable();
                                form.down('#subnets').hide().disable();
                            }
                        }
                    },
                    setCloudLocation: function(cloudLocation) {
                        var proxy = this.store.proxy,
                            disableAddNewPlugin = false,
                            vpcLimits = Scalr.getGovernance('ec2', 'aws.vpc');
                        this.reset();
                        this.getPlugin('comboaddnew').postUrl = '?cloudLocation=' + cloudLocation;
                        proxy.params = {cloudLocation: cloudLocation};
                        delete proxy.data;
                        this.setReadOnly(false, false);
                        if (Ext.isObject(vpcLimits)) {
                            this.toggleIcon('governance', true);
                            this.allowBlank = vpcLimits['value'] == 0;
                            if (vpcLimits['regions'] && vpcLimits['regions'][cloudLocation]) {
                                if (vpcLimits['regions'][cloudLocation]['ids'] && vpcLimits['regions'][cloudLocation]['ids'].length > 0) {
                                    var vpcList = Ext.Array.map(vpcLimits['regions'][cloudLocation]['ids'], function(vpcId){
                                        return {id: vpcId, name: vpcId};
                                    });
                                    if (vpcLimits['value'] == 0) {
                                        vpcList.unshift({id: 0, name: 'EC2'});
                                    }
                                    proxy.data = vpcList;
                                    this.store.load();
                                    disableAddNewPlugin = true;
                                    if (vpcLimits['value'] == 1) {
                                        this.setValue(this.store.first());
                                    }
                                }
                            }
                        }
                        this.getPlugin('comboaddnew').setDisabled(disableAddNewPlugin);
                    }

                },{
                    xtype: 'vpcsubnetfield',
                    itemId: 'subnets',
                    name: 'subnets[]',
                    fieldLabel: 'Subnets',
                    hidden: true,
                    disabled: true,
                    requireSameSubnetType: true
                },{
                    xtype: 'checkbox',
                    name: 'scheme',
                    inputValue: 'internal',
                    hidden: true,
                    disabled: true,
                    boxLabel: 'Create an internal load balancer'
                },{
                    // TODO: fix extjs5 remove multiSelect, use field.Tag
                    xtype: 'combobox',
                    fieldLabel: 'Avail. zones',
                    multiSelect: true,
                    name: 'zones[]',
                    itemId: 'zones',
                    valueField: 'id',
                    displayField: 'name',
                    allowBlank: false,
                    listConfig: {
                        cls: 'x-boundlist-with-icon',
                        tpl : '<tpl for=".">'+
                                '<tpl if="state != \'available\'">'+
                                    '<div class="x-boundlist-item x-boundlist-item-disabled" title="Zone is offline for maintenance"><img class="x-boundlist-icon" src="' + Ext.BLANK_IMAGE_URL + '"/>{name}&nbsp;<span class="warning"></span></div>'+
                                '<tpl else>'+
                                    '<div class="x-boundlist-item"><img class="x-boundlist-icon" src="' + Ext.BLANK_IMAGE_URL + '"/>{name}</div>'+
                                '</tpl>'+
                              '</tpl>'
                    },
                    store: {
                        fields: [ 'id', 'name', 'state' ],
                        proxy: 'object'
                    },
                    editable: false,
                    queryMode: 'local',
                    listeners: {
                        beforeselect: function(comp, record, index) {
                            if (comp.isExpanded) {
                                var result = true;
                                if (record.get('state') !== 'available') {
                                    result = false;
                                }
                                return result;
                            }
                        }
                    }
                }]
			}]
		}, {
            xtype: 'grid',
            itemId: 'listeners',
            cls: 'x-container-fieldset x-grid-no-hilighting',
            store: {
                proxy: 'object',
                fields: [ 'protocol', 'lb_port', 'instance_port' , 'ssl_certificate']
            },
            plugins: {
                ptype: 'gridstore'
            },

            viewConfig: {
                emptyText: 'No listeners defined',
                deferEmptyText: false
            },

            columns: [
                { header: 'Protocol', flex: 1, sortable: true, dataIndex: 'protocol' },
                { header: 'Load balancer port', flex: 1, sortable: true, dataIndex: 'lb_port' },
                { header: 'Instance port', flex: 1, sortable: true, dataIndex: 'instance_port' },
                { header: 'SSL certificate', flex: 1, sortable: true, dataIndex: 'ssl_certificate' },
                { header: '&nbsp;', width: 40, sortable: false, dataIndex: 'id', align:'center', xtype: 'templatecolumn',
                    tpl: '<img class="x-grid-icon x-grid-icon-delete" src="'+Ext.BLANK_IMAGE_URL+'">'
                }
            ],

            listeners: {
                itemclick: function (view, record, item, index, e) {
                    if (e.getTarget('img.x-grid-icon-delete'))
                        view.store.remove(record);
                }
            },

            dockedItems: [{
                xtype: 'toolbar',
                dock: 'top',
                ui: 'inline',
                layout: {
                    type: 'hbox',
                    pack: 'start'
                },
                style: 'padding-right:2px',
                items: [{
                    xtype: 'label',
                    cls: 'x-fieldset-subheader',
                    html: 'Listeners',
                    margin: 0
                },{
                    xtype: 'tbfill'
                },{
                    text: 'Add listener',
                    cls: 'x-btn-green',
                    handler: function() {
                        Scalr.Confirm({
                            form: [{
                                xtype: 'container',
                                cls: 'x-container-fieldset',
                                layout: 'anchor',
                                defaults: {
                                    anchor: '100%',
                                    labelWidth: 140,
                                },
                                items: [{
                                    xtype: 'combo',
                                    name: 'protocol',
                                    fieldLabel: 'Protocol',
                                    editable: false,
                                    store: [ 'TCP', 'HTTP', 'SSL', 'HTTPS' ],
                                    queryMode: 'local',
                                    allowBlank: false,
                                    listeners: {
                                        change: function (field, value) {
                                            if (value == 'SSL' || value == 'HTTPS')
                                                this.next('[name="ssl_certificate"]').show().enable();
                                            else
                                                this.next('[name="ssl_certificate"]').hide().disable();
                                        }
                                    }
                                }, {
                                    xtype: 'textfield',
                                    name: 'lb_port',
                                    fieldLabel: 'Load balancer port',
                                    allowBlank: false,
                                    validator: function (value) {
                                        if (value < 1024 || value > 65535) {
                                            if (value != 80 && value != 443)
                                                return 'Valid LoadBalancer ports are - 80, 443 and 1024 through 65535';
                                        }
                                        return true;
                                    }
                                }, {
                                    xtype: 'textfield',
                                    name: 'instance_port',
                                    fieldLabel: 'Instance port',
                                    allowBlank: false,
                                    validator: function (value) {
                                        if (value < 1 || value > 65535)
                                            return 'Valid instance ports are one (1) through 65535';
                                        else
                                            return true;
                                    }
                                }, {
                                    xtype: 'combo',
                                    name: 'ssl_certificate',
                                    fieldLabel: 'SSL Certificate',
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
                                            url: '/tools/aws/iam/servercertificates/xListCertificates'
                                        },
                                        listeners: {
                                            load: function () {
                                                //console.log(arguments);
                                            }}
                                    },
                                    valueField: 'arn',
                                    displayField: 'name'
                                }]
                            }],
                            ok: 'Add',
                            title: 'Add new listener',
                            formValidate: true,
                            closeOnSuccess: true,
                            scope: this,
                            success: function (formValues) {
                                var view = this.up('#listeners'), store = view.store;

                                if (store.findBy(function (record) {
                                    if (
                                        record.get('protocol') == formValues.protocol &&
                                            record.get('lb_port') == formValues.lb_port &&
                                            record.get('instance_port') == formValues.instance_port
                                        ) {
                                        Scalr.message.Error('Such listener already exists');
                                        return true;
                                    }
                                }) == -1) {
                                    store.add(formValues);
                                    return true;
                                } else {
                                    return false;
                                }
                            }
                        });
                    }
                }]
            }]
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
						var listeners = [];
						form.down('#listeners').store.each(function (rec) {
							listeners.push([ rec.get('protocol'), rec.get('lb_port'), rec.get('instance_port'), rec.get('ssl_certificate') ].join("#"));
						});

                        var healthcheck = {
							target: form.down("[name='target']").getValue(),
                            healthyThreshold: form.down("[name='healthythreshold']").getValue(),
                            interval: form.down("[name='interval']").getValue(),
                            timeout: form.down("[name='timeout']").getValue(),
                            unhealthyThreshold: form.down("[name='unhealthythreshold']").getValue()
						};

						Scalr.Request({
							processBox: {
								type: 'save'
							},
							params: {
								listeners: Ext.encode(listeners),
								healthcheck: Ext.encode(healthcheck),
								cloudLocation: loadParams['cloudLocation']
							},
							form: form.getForm(),
							url: '/tools/aws/ec2/elb/xCreate',
							success: function (data) {
								if (data['elb']) {
									Scalr.event.fireEvent('update', '/tools/aws/ec2/elb/create', data['elb']);
								}
								form.close();
							}
						});
					}
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
                    form.close();
				}
			}]
		}]
	});

	form.getForm().setValues({
		healthythreshold: 3,
		interval: 30,
		timeout: 5,
		unhealthythreshold: 5
	});

    form.down('#zones').store.load({data: moduleParams['zones']});

    var vpcId = form.down('[name="vpcId"]');
    if (loadParams['vpcId']) {
        vpcId.store.loadData([{id: loadParams['vpcId'], name: loadParams['vpcId']}]);
        vpcId.setValue(loadParams['vpcId']);
        vpcId.setReadOnly(true);
    } else {
        vpcId.setCloudLocation(loadParams['cloudLocation']);
    }

	return form;
});
