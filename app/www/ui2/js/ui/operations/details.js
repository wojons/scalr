Scalr.regPage('Scalr.ui.operations.details', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		title: moduleParams['name'] + ' progress',
		scalrOptions: {
			'modal': true
		},
		width: 800,
        fieldDefaults: {
            labelWidth: 80
        },
		items:[{
			xtype: 'fieldset',
			items: [{
				xtype: 'displayfield',
				fieldLabel: 'Server ID',
				value: (moduleParams['serverId']) ? moduleParams['serverId'] : "*Server was terminated*" 
			},{
                xtype: 'container',
                layout: 'hbox',
                items: [{
                    xtype: 'displayfield',
                    fieldLabel: 'Status',
                    fieldStyle: moduleParams['status'] === 'Failed' ? 'color:red' : '',
                    value: moduleParams['status'] == 'ok' ? "Completed" : Ext.String.capitalize(moduleParams['status']),
                },{
	                xtype: 'button',
	                text: 'Download debug log',
                    iconCls: 'x-btn-icon-download',
	                hidden: !moduleParams['download'] || moduleParams['serverStatus'] !== 'Initializing',
	                handler: function() {
	                	Scalr.utils.UserLoadFile('/servers/downloadScalarizrDebugLog?serverId=' + moduleParams['serverId']);
	                },
                    margin: '0 0 0 36'
                }]
			}]
        },{
            xtype: 'container',
            items: [{
                xtype: 'component',
                tpl:
                    new Ext.XTemplate('<table style="margin: 12px 0;border-collapse:collapse;width: 100%;">' +
                        '<tpl foreach="details">' +
                            '<tr <tpl if="message">style="background:#FEFADE"</tpl>>' +
                                '<td style="vertical-align:top;padding:8px 0 8px 20px;width:102px;"><tpl if="timestamp">[{timestamp}]&nbsp;&nbsp;&nbsp;&nbsp;</tpl></td>' +
                                '<td style="vertical-align:top;padding:6px 4px;width:36px"><img style="width:20px" src="'+Ext.BLANK_IMAGE_URL+'" class="{[this.getIconCls(values.status)]}" /></td>' +
                                '<td style="vertical-align:top;padding:8px 20px 8px 0">' +
                                    '<tpl if="status!=\'pending\'">' +
                                        '<span class="x-semibold">{[xkey]}</span>' +
                                    '<tpl else>' +
                                        '<span>{[xkey]}</span>' +
                                    '</tpl>' +
                                    '{[this.getErrorMessage(values.message)]}',
                                '</td>' +
                            '</tr>' +
                        '</tpl>' +
                    '</table>', {
                        getErrorMessage: function(message) {
                            var html = [];
                            if (message) {
                                message = '' + message;
                                if (message.indexOf('<') !== -1) {
                                    message = Ext.htmlEncode(message);
                                }
                                html.push(
                                    '<div class="message-wrapper" style="max-height:80px;overflow:hidden;margin:12px 0 0"><div class="message" style="position:relative;overflow:hidden;padding:0 0 12px;color:red;width:630px;word-wrap:break-word;">'+message.replace(/\n/g, '<br/>') + '<br/>'+
                                        '<div class="expander" style="display:none;background:-moz-linear-gradient(top, rgba(254,250,222,0) 0%,rgba(254,250,222,1) 60%,rgba(254,250,222,1) 100%);background:-webkit-linear-gradient(top, rgba(254,250,222,0) 0%,rgba(254,250,222,1) 60%,rgba(254,250,222,1) 100%);position:absolute;bottom:0;width:100%;padding:32px 0 0;height:50px;cursor:pointer;text-align:center;">'+
                                            '<img src="'+Ext.BLANK_IMAGE_URL+'" class="showmore x-icon-show-more-red" />' +
                                        '</div>'+
                                    '</div></div>'
                                );
                        }
                            return html.join('');
                        },
                        getIconCls: function(status) {
                            var map = {complete: 'ok', error: 'fail'};
                            if (status === 'pending') {
                                return '';
                            } else if (map[status]) {
                                return 'x-grid-icon x-grid-icon-' + map[status];
                            } else {
                                return 'x-icon-colored-status-running';
                            }

                        }
                    }),
                data: moduleParams,
                listeners: {
                    boxready: function() {
                        var me = this,
                            messageEl = me.el.down('.message'),
                            messageWrapperEl;
                        if (messageEl) {
                            messageWrapperEl = me.el.down('.message-wrapper');
                            if (messageEl.getHeight() > 100) {
                                messageEl.setStyle('height', messageWrapperEl.getHeight()+'px');
                                messageEl.down('.expander').show();
                            }
                            messageWrapperEl.setStyle('max-height', null);
                            me.el.on('click', function(e) {
                                if (Ext.fly(e.getTarget()).hasCls('showmore')) {
                                    me.el.down('.message').setStyle('height', 'auto');
                                    me.el.down('.expander').hide();
                                    me.updateLayout();
                                }
                                e.stopEvent();

                            });
                        }
                    }
                }
            }]
		}],
		tools: [{
			type: 'refresh',
			handler: function () {
				Scalr.event.fireEvent('refresh');
			}
		}, {
			type: 'close',
			handler: function () {
				Scalr.event.fireEvent('close');
			}
		}]
	});
});
