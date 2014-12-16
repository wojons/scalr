Scalr.regPage('Scalr.ui.webhooks.endpoints.view', function (loadParams, moduleParams) {
    var scalrOptions;
    if (moduleParams['levelMap'][moduleParams['level']] == 'account') {
		scalrOptions = {
			title: 'Account management &raquo; Webhooks &raquo; Endpoints',
			maximize: 'all',
			leftMenu: {
				menuId: 'settings',
				itemId: 'webhooks',
                showPageTitle: true
			}
		};
    } else {
		scalrOptions = {
			title: Ext.String.capitalize(moduleParams['levelMap'][moduleParams['level']]) + ' webhooks &raquo; Endpoints',
			maximize: 'all',
			leftMenu: {
				menuId: 'webhooks',
				itemId: 'endpoints',
                showPageTitle: true
			}
		};
    }
	var store = Ext.create('store.store', {
        fields: [
            {name: 'endpointId', type: 'string'},
            'level',
            'url',
            'isValid',
            'validationToken',
            'securityKey',
            'webhooks',
            {name: 'id', convert: function(v, record){return record.data.endpointId;}}
        ],
        data: moduleParams['endpoints'],
		proxy: {
			type: 'ajax',
			url: '/webhooks/endpoints/xList/',
            extraParams: {
                level: moduleParams['level']
            },
            reader: {
                type: 'json',
                root: 'endpoints',
                successProperty: 'success'
            }
		},
		sorters: [{
			property: 'url',
			transform: function(value){
				return value.toLowerCase();
			}
		}]
	});
	
	var reconfigurePage = function(params) {
        if (params.endpointId) {
            cb = function() {
                var selModel = grid.getSelectionModel();
                selModel.deselectAll();
                if (params.endpointId === 'new') {
                    panel.down('#add').handler();
                } else {
                    panel.down('#liveSearch').reset();
                    var record = store.getById(params.endpointId);
                    if (record) {
                        selModel.select(record);
                    }
                }
            };
            if (grid.view.viewReady) {
                cb();
            } else {
                grid.view.on('viewready', cb, grid.view, {single: true});
            }
        }
    };
	
    var grid = Ext.create('Scalr.ui.WebhooksGrid', {
        cls: 'x-grid-shadow x-panel-column-left',
        store: store,
        type: 'endpoint',
        filterFields: ['url'],
        columns: [
            {
                header: '<img style="cursor: help" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-info" data-qclass="x-tip-light" data-qtip="' +
                    Ext.String.htmlEncode('<div>Scopes:</div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-scalr">&nbsp;&nbsp;Scalr</div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-account">&nbsp;&nbsp;Account</div>' +
                    '<div><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-environment">&nbsp;&nbsp;Environment</div>') +
                    '" />&nbsp;URL',
                text: 'URL',
                flex: 1,
                sortable: true,
                resizable: false,
                dataIndex: 'url',
                xtype: 'templatecolumn',
                tpl: new Ext.XTemplate('{[this.getLevel(values.level)]}&nbsp;&nbsp;{url}',
                   {
                       getLevel: function(level){
                           var scope = moduleParams['levelMap'][level];
                           return '<img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-'+scope+'" data-qtip="'+Ext.String.capitalize(scope)+' scope"/>';
                       }
                   }
               )
            },
            //temporarily disable url validation per Igor`s request(see also Scalr_UI_Controller_Webhooks_Endpoints->xSaveAction)
            //{text: 'Status', minWidth: 120, dataIndex: 'isValid', sortable: true, resizeable: false, xtype: 'statuscolumn', statustype: 'webhookendpoint', qtipConfig: {width: 300}}
        ],
        level: moduleParams['level'],
        listeners: {
            btnnewclick: function(newRecord) {
                form.loadRecord(newRecord);
            },
            btnrefreshclick: function() {
                store.load();
            }
        }
    });

	var form = 	Ext.create('Scalr.ui.WebhooksEndpointForm', {
        level: moduleParams['level'],
        levelName: moduleParams['levelMap'][moduleParams['level']],
		listeners: {
			hide: function() {
    			grid.down('#add').setDisabled(false);
			},
            afterrender: function() {
                var me = this;
                grid.getSelectionModel().on('focuschange', function(gridSelModel){
                    var warning = me.up('#rightcol').down('#warning');
                    if (gridSelModel.lastFocused) {
                        if (moduleParams['level'] == gridSelModel.lastFocused.get('level')) {
                            warning.hide();
                            me.loadRecord(gridSelModel.lastFocused);
                        } else {
                            me.hide();
                            warning.down('displayfield').setValue('<b>'+gridSelModel.lastFocused.get('url')+'</b><br/>'+Ext.String.capitalize(''+moduleParams['levelMap'][gridSelModel.lastFocused.get('level')]) + ' endpoint info can\'t be viewed in the current scope.');
                            warning.show();
                        }
                    } else {
                        me.hide();
                        me.up('#rightcol').down('#warning').hide();
                    }
                });
            },
            loadrecord: function() {
                if (!this.isVisible()) {
                    this.show();
                }
            },
			beforeloadrecord: function(record) {
                grid.down('#add').setDisabled(!record.get('endpointId'));
			},
            validateendpoint: function(record){
                grid.getSelectionModel().setLastFocused(null);
                grid.getSelectionModel().setLastFocused(record);
            },
            saveendpoint: function(record, data) {
                var isNewRecord = !record.get('endpointId');
                grid.getSelectionModel().deselectAll();
                grid.getSelectionModel().setLastFocused(null);
                this.setVisible(false);
                if (isNewRecord) {
                    record = store.add(data.endpoint)[0];
                    grid.getSelectionModel().select(record);
                } else {
                    record.set(data.endpoint);
                    form.loadRecord(record);
                }
                if (isNewRecord) {
                    grid.getSelectionModel().select(record);
                } else {
                    grid.getSelectionModel().setLastFocused(record);
                }
            },
            cancelendpoint: function() {
                grid.getSelectionModel().setLastFocused(null);
                this.setVisible(false);
            }
		}
	});
	
	var panel = Ext.create('Ext.panel.Panel', {
		cls: 'scalr-ui-panel-webhooks-ebndpoints',
		layout: {
			type: 'hbox',
			align: 'stretch'
		},
		scalrOptions: scalrOptions,
        listeners: {
            applyparams: reconfigurePage
        },
        items: [
            grid
        ,{
            xtype: 'container',
            itemId: 'rightcol',
            flex: 1,
            maxWidth: 640,
            minWidth: 400,
            layout: 'fit',
            items: [form, {
                xtype: 'container',
                cls: 'x-container-fieldset',
                itemId: 'warning',
                layout: 'anchor',
                hidden: true,
                items: {
                    xtype: 'displayfield',
                    anchor: '100%',
                    cls: 'x-form-field-info'
                }
            }]
        }]
	});
	return panel;
});
