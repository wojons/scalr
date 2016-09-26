Scalr.regPage('Scalr.ui.servers.import.view', function (loadParams, moduleParams) {
    var platformsTabs = [], stepFieldLabelWidth = 450, stepFieldMarginStyle = 'margin-left: 30px';

    Ext.each(moduleParams['allowedPlatforms'], function (platformId) {
        platformsTabs.push({
            text: Scalr.utils.getPlatformName(platformId, true),
            iconCls: 'x-icon-platform-large x-icon-platform-large-' + platformId,
            value: platformId,
            pressed: platformId == loadParams['platform']
        });
    });

    var confirmStop = function (handler) {
        Scalr.Confirm({
            type: 'action',
            msg: 'Are you sure want to stop import ? All processed servers will remain imported.',
            ok: 'Stop',
            success: function () {
                panel.finishImport();
                if (Ext.isFunction(handler)) {
                    handler();
                }
            }
        });
    };

    var panel = Ext.create('Ext.panel.Panel', {
        scalrOptions: {
            maximize: 'all',
            menuTitle: 'Servers import',
            beforeClose: function(handler, leavePageFlag) {
                if (panel.status == 'importing') {
                    if (leavePageFlag) {
                        return 'Import will be stopped';
                    } else {
                        confirmStop(handler);
                        return true;
                    }
                }

                return false;
            }
        },

        layout: {
            type: 'hbox',
            align: 'stretch'
        },

        items: [{
            xtype: 'form',
            itemId: 'leftColumn',
            cls: 'x-panel-column-left x-transparent-mask',
            autoScroll: true,
            width: 484,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'fieldset',
                layout: 'anchor',
                itemId: 'steps',
                defaults: {
                    applyStatus: Ext.emptyFn,
                    applyStepFlag: function (flag, href) {
                        var label = '<div class="x-grid-icon x-grid-icon-simple ' +
                            'x-grid-icon-' + (Ext.isBoolean(flag) ? (flag == true ? 'ok' : 'error') : 'gray-ok') +
                            '" style="margin-right: 8px"></div>';

                        if (flag === false && href) {
                            label += '<a href="' + href + '">' + this.fieldLabelText + '</a>';
                        } else {
                            label += this.fieldLabelText;
                        }

                        this.setFieldLabel(label);
                    }
                },
                items: [{
                    xtype: 'component',
                    hidden: true,
                    itemId: 'instances',
                    isStep: true,
                    applyStatus: function (data) {
                        panel.down('#servers').store.loadData(data || []);
                    }
                }, {
                    xtype: 'displayfield',
                    itemId: 'compatibility',
                    isStep: true,
                    labelWidth: stepFieldLabelWidth,
                    fieldLabelText: 'Check AMI, VPC, Subnet', //  compatibility between servers
                    applyStatus: function (data) {
                        if (data) {
                            this.applyStepFlag(data['success']);

                            if (data['message']) {
                                this.next().show().setValue('<span style="color:#b31904">' + data['message'] + '</span>');
                            } else {
                                var grid = panel.down('#servers'),
                                    required = {imageId: [], vpcId: [], subnetId: []},
                                    names = {imageId: 'AMI', vpcId: 'VPC', subnetId: 'VPC subnet'},
                                    optional = {instanceType: []},
                                    errors = [], i, value = [];

                                grid.getStore().each(function (record) {
                                    for (i in required) {
                                        required[i].push(record.get(i));
                                    }

                                    for (i in optional) {
                                        optional[i].push(record.get(i));
                                    }
                                });

                                for (i in required) {
                                    required[i] = Ext.Array.unique(required[i]);
                                    if (required[i].length > 1) {
                                        errors.push(i);
                                    }
                                }

                                for (i in optional) {
                                    optional[i] = Ext.Array.unique(optional[i]);
                                }

                                if (errors.length) {
                                    this.next().show().setValue('<span style="color:#b31904">Instances shoud have the same ' + errors.join(', ') + '</span>');
                                } else {
                                    for (i in names) {
                                        value.push(names[i] + ': ' + (required[i][0] || 'none'));
                                    }

                                    this.next().show().setValue(value.join(', '));
                                }
                            }
                        } else {
                            this.applyStepFlag();
                            this.next().hide();
                        }
                    }
                }, {
                    xtype: 'displayfield',
                    value: '',
                    hidden: true,
                    anchor: '100%',
                    style: stepFieldMarginStyle
                }, {
                    xtype: 'displayfield',
                    itemId: 'image',
                    isStep: true,
                    labelWidth: stepFieldLabelWidth,
                    fieldLabelText: 'Register image in Scalr',
                    applyStatus: function (data, info) {
                        if (data) {
                            if (data['success']) {
                                this.applyStepFlag(data['success']);
                                this.next().show().setValue(
                                    '<img src="' + Ext.BLANK_IMAGE_URL + '" data-qtip="' + Scalr.utils.getScopeLegend('image', '') +
                                    '" class="scalr-scope-' + data['data']['scope'] + '" style="margin:0 6px 0 0"/>' +
                                    data['data']['name'] + ' [' + data['data']['id'] + ']'
                                );
                            } else {
                                if (data['isScalarized']) {
                                    this.applyStepFlag(data['success']);
                                    this.next().show().setValue("<span style='color:#b31904'>Image " + data['data']['id'] + " is registered in Scalr as scalarized and cannot be used.</span>");
                                } else {
                                    if (Scalr.isAllowed('IMAGES_ENVIRONMENT', 'manage')) {
                                        var query = Ext.Object.toQueryString({
                                            platform: loadParams['platform'],
                                            cloudLocation: loadParams['cloudLocation'],
                                            imageId: data['data']['id']
                                        });

                                        this.applyStepFlag(data['success'], '#/images/register?' + query);
                                        this.next().hide();
                                    } else {
                                        this.applyStepFlag(data['success']);
                                        this.next().show().setValue("<span style='color:#b31904'>Image " + data['data']['id'] + " is not registered in Scalr and you don't have permissions to register it.</span>");
                                    }
                                }
                            }
                        } else {
                            this.applyStepFlag();
                            this.next().hide();
                        }
                    }
                }, {
                    xtype: 'displayfield',
                    value: '',
                    hidden: true,
                    anchor: '100%',
                    style: stepFieldMarginStyle
                }, {
                    xtype: 'displayfield',
                    itemId: 'role',
                    isStep: true,
                    labelWidth: stepFieldLabelWidth,
                    fieldLabelText: 'Select role with image above',
                    applyStatus: function (data) {
                        var c = this.next(), df = this.next('displayfield'), store = c.getStore();
                        if (data) {
                            c.show();
                            df.hide();
                            store.load({data: data['availableRoles']});
                            if (!loadParams['roleId'] && store.getCount() == 1) {
                                loadParams['roleId'] = store.getAt(0).get(c.valueField);
                            }

                            if ((store.getCount() > 0) && loadParams['roleId']) {
                                c.suspendEvents();
                                c.setValue(loadParams['roleId']);
                                c.getPlugin('innericon').updateFieldIcon(loadParams['roleId']);
                                c.resumeEvents();
                            } else if (store.getCount() == 0 && !Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage')) {
                                c.hide();
                                df.show().setValue("<span style='color:#b31904'>Role is not registered in scalr and you don't have permissions to register it.</span>");
                            }
                            this.applyStepFlag(data['success']);

                            c.getPlugin('comboaddnew').redirectParams['image'] = data['image'];
                        } else {
                            this.applyStepFlag();
                            c.hide();
                            df.hide();
                        }
                    }
                }, {
                    xtype: 'combobox',
                    hidden: true,
                    anchor: '100%',
                    style: stepFieldMarginStyle,
                    plugins: [{
                        ptype: 'fieldinnericonscope',
                        pluginId: 'innericon',
                        tooltipScopeType: 'role'
                    }, {
                        ptype: 'comboaddnew',
                        pluginId: 'comboaddnew',
                        disabled: !Scalr.isAllowed('ROLES_ENVIRONMENT', 'manage'),
                        isRedirect: true,
                        redirectParams: {
                            redirectToBack: true
                        },
                        url: '/roles/edit'
                    }],
                    store: {
                        fields: ['id', 'name', 'scope'],
                        proxy: 'object'
                    },
                    editable: false,
                    valueField: 'id',
                    displayField: 'name',
                    listeners: {
                        change: function (field, value, prev) {
                            if (field.isValid() && value) {
                                panel.updateStatus({roleId: value, farmId: '', farmRoleId: ''});
                                panel.down('#farmroles').reset();
                                panel.down('#farms').reset();
                            }
                        }
                    }
                }, {
                    xtype: 'displayfield',
                    value: '',
                    hidden: true,
                    anchor: '100%',
                    style: stepFieldMarginStyle
                }, {
                    xtype: 'displayfield',
                    itemId: 'farmrole',
                    isStep: true,
                    labelWidth: stepFieldLabelWidth,
                    fieldLabelText: 'Select running farm and farm role',
                    applyStatus: function (data) {
                        if (data) {
                            this.applyStepFlag(data['success']);

                            this.next('container').down('#farms').getPlugin('fieldicons').updateIconTooltip('info',
                                'Show only running Farms' +
                                (data['instance']['vpcId'] ? (' with VPC <b>' + data['instance']['vpcId'] + '</b>') : ' without VPC')
                            );

                            this.next('container').down('#farmroles').getPlugin('fieldicons').updateIconTooltip('info',
                                'Show only FarmRoles matching Role <b>' + data['instance']['roleName'] + '</b>' +
                                (data['instance']['subnetId'] ? (' and subnet <b>' + data['instance']['subnetId'] + '</b>') : '')
                            );

                            if (data['data']) {
                                var combobox = this.next('container').show().down('combobox');
                                combobox.getStore().load({data: data['data']});
                                if (loadParams['farmId']) {
                                    combobox.setValue(loadParams['farmId']);
                                }

                                var addnew = this.next('container').down('#farmroles').getPlugin('comboaddnew');
                                addnew.redirectParams['settings'] = {
                                    defaults: {
                                        'scaling.enabled': 0,
                                    },
                                    settings: {
                                        'instance_type': data['instance']['instanceType'],
                                        'aws.vpc_subnet_id': data['instance']['subnetId']
                                    }
                                };
                            }
                        } else {
                            this.applyStepFlag();
                            this.next('container').hide();
                        }
                    }
                }, {
                    xtype: 'container',
                    hidden: true,
                    style: stepFieldMarginStyle,
                    anchor: '100%',
                    layout: {
                        type: 'vbox',
                        align: 'stretch'
                    },
                    items: [{
                        xtype: 'combobox',
                        flex: 1,
                        store: {
                            fields: ['id', 'name', 'farmroles'],
                            proxy: 'object'
                        },
                        valueField: 'id',
                        displayField: 'name',
                        editable: false,
                        allowBlank: false,
                        itemId: 'farms',
                        name: 'farmId',
                        fieldLabel: 'Farm',
                        labelWidth: 80,
                        plugins: {
                            ptype: 'fieldicons',
                            align: 'left',
                            icons: [{id: 'info', tooltip: ''}]
                        },
                        listeners: {
                            change: function (field, value) {
                                var record = this.findRecordByValue(value),
                                    combobox = this.next('combobox'),
                                    store = combobox.getStore();

                                if (record) {
                                    panel.updateUrl({ farmId: value });
                                    combobox.enable().reset();
                                    store.load({data: record.get('farmroles')});
                                    if (store.getCount() == 1) {
                                        combobox.setValue(store.getAt(0));
                                    }

                                    combobox.getPlugin('comboaddnew').postUrl = Ext.Object.toQueryString({
                                        farmId: value,
                                        roleId: loadParams['roleId']
                                    });
                                } else {
                                    combobox.disable();
                                }
                            }
                        }
                    }, {
                        xtype: 'combobox',
                        valueField: 'id',
                        displayField: 'name',
                        editable: false,
                        allowBlank: false,
                        disabled: true,
                        fieldLabel: 'Farm Role',
                        labelWidth: 80,
                        flex: 1,
                        store: {
                            fields: ['id', 'name', 'tags'],
                            proxy: 'object'
                        },
                        itemId: 'farmroles',
                        name: 'farmRoleId',
                        plugins: [{
                            ptype: 'comboaddnew',
                            pluginId: 'comboaddnew',
                            isRedirect: true,
                            redirectParams: {},
                            url: '/farms/designer?'
                        }, {
                            ptype: 'fieldicons',
                            align: 'left',
                            icons: [{id: 'info', tooltip: ''}]
                        }],
                        listeners: {
                            change: function (field, value) {
                                var f = this.prev(),
                                    warn = panel.down('#warningPending'),
                                    recFarmRole = this.findRecordByValue(value),
                                    recFarm = f.findRecordByValue(f.getValue());

                                panel.updateUrl({ farmRoleId: value });
                                this.up('container').prev('[isStep=true]').applyStepFlag(this.isValid());

                                if (recFarm && recFarmRole) {
                                    warn.scalingMessage = '<br>For safety reasons Auto-scaling will be disabled for <b>' +
                                        recFarmRole.get('name') + '</b> on farm <b>' + recFarm.get('name') + '</b>.';

                                    panel.down('namevaluelistfield').farmRoleTags = recFarmRole.get('tags');
                                }
                            }
                        }
                    }]
                }]
            }],
            dockedItems: [{
                xtype: 'container',
                cls: 'x-docked-buttons',
                dock: 'bottom',
                itemId: 'optionsButtons',
                layout: {
                    type: 'hbox',
                    pack: 'center'
                },
                items: [{
                    xtype: 'button',
                    itemId: 'continue',
                    disabled: true,
                    text: 'Continue',
                    handler: function () {
                        panel.down('#leftColumn').setDisabled(true).next().show();
                        panel.down('#optionsButtons').hide();
                        var tagsField = panel.down('namevaluelistfield'), tagsArray = [], i;

                        for (i in tagsField.farmRoleTags) {
                            if (i == 'scalr-meta') {
                                tagsArray.unshift({
                                    name: i,
                                    value: tagsField.farmRoleTags[i],
                                    system: 1
                                });
                            } else {
                                tagsArray.push({
                                    name: i,
                                    value: tagsField.farmRoleTags[i],
                                });
                            }
                        }

                        tagsField.store.loadData(tagsArray);
                    }
                }, {
                    xtype: 'button',
                    text: 'Cancel',
                    handler: function () {
                        Scalr.event.fireEvent('close');
                    }
                }]
            }],
            listeners: {
                validitychange: function(form, valid) {
                    this.down('#continue').setDisabled(!valid);
                }
            }
        }, {
            xtype: 'container',
            cls: 'x-container-fieldset',
            style: {
                marginBottom: 0
            },
            flex: 1,
            hideMode: 'offsets',
            hidden: true,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'progressbarstepfield',
                itemId: 'progress',
                steps: [{
                    name: 'options',
                    title: 'Import options'
                }, {
                    name: 'tags',
                    title: 'Tags',
                    isWaiting: true
                }, {
                    name: 'confirmation',
                    title: 'Confirmation',
                    isWaiting: true
                }, {
                    name: 'import',
                    title: 'Import'
                }, {
                    name: 'complete',
                    title: 'Complete!'
                }],
                value: 'tags'
            }, {
                xtype: 'panel',
                flex: 1,
                layout: 'card',
                itemId: 'rightColumnCard',
                items: [{
                    xtype: 'panel',
                    itemId: 'tags',
                    layout: {
                        type: 'vbox',
                        align: 'stretch'
                    },
                    items: [{
                        xtype: 'displayfield',
                        cls: 'x-form-field-warning',
                        value: 'The following tags will be added to all imported instances.'
                    }, {
                        xtype: 'namevaluelistfield',
                        flex: 1,
                        autoScroll: true,
                        itemName: 'tag',
                        reservedNames: ['scalr-meta'],
                        systemTags: { 'scalr-meta': 1 },
                        isRowReadOnly: function(record) {
                            return record.get('system');
                        },
                        deleteColumnRenderer: function(record) {
                            var result = '<img style="cursor:pointer;margin-top:6px;';
                            if (record.get('system')) {
                                result += 'cursor:default;opacity:.4" class="x-grid-icon x-grid-icon-lock" data-qtip="System ' + this.itemName + 's cannot be modified or removed"';
                            } else {
                                result += '" class="x-grid-icon x-grid-icon-delete" data-qtip="Delete"';
                            }
                            result += ' src="'+Ext.BLANK_IMAGE_URL+'"/>';
                            return result;
                        },
                        isNameValid: function(name, record) {
                            name = Ext.String.trim(name);
                            return !Ext.Array.contains(this.reservedNames, name) || !!record.get('system') || (name === 'Name' ? 'Use the separate Instance Name Pattern option.' : 'Reserved name');
                        },
                        isValid: function(){
                            var me = this,
                                isValid = true;
                            me.store.getUnfiltered().each(function(record) {
                                var name = Ext.String.trim(record.get('name'));
                                if (!name || Ext.Array.contains(me.reservedNames, name) && !record.get('system')) {
                                    var widget = me.columns[0].getWidget(record);
                                    isValid = widget.validate();
                                    widget.focus();
                                    return false;
                                }
                            });
                            return isValid;
                        },
                        applyTagsLimit: function() {
                            var me = this,
                                view = me.view,
                                tagsLimit = 10;

                            view.findFeature('addbutton').setDisabled((view.store.snapshot || view.store.data).length >= tagsLimit, 'Tag limit of ' + tagsLimit + ' reached');
                        },
                        getValue: function(){
                            var me = this,
                                result  = {};
                            me.store.getUnfiltered().each(function(record){
                                var name = record.get('name'),
                                    value = record.get('value');
                                if (name && !me.systemTags[name] && !record.get('ignoreOnSave')) {
                                    result[name] = value;
                                }
                            });
                            return result;
                        },
                        listeners: {
                            viewready: function() {
                                this.store.on({
                                    refresh: this.applyTagsLimit,
                                    add: this.applyTagsLimit,
                                    remove: this.applyTagsLimit,
                                    scope: this
                                });
                                this.applyTagsLimit();
                            }
                        }
                    }],
                    dockedItems: [{
                        xtype: 'container',
                        cls: 'x-docked-buttons',
                        dock: 'bottom',
                        itemId: 'pendingButtons',
                        layout: {
                            type: 'hbox',
                            pack: 'center'
                        },
                        items: [{
                            xtype: 'button',
                            text: 'Next',
                            handler: function () {
                                if (panel.down('namevaluelistfield').isValid()) {
                                    panel.down('progressbarstepfield').setValue('confirmation');
                                    panel.down('#rightColumnCard').setActiveItem('confirmation');

                                    // check tags
                                    var store = panel.down('#servers').getStore(),
                                        tags = panel.down('namevaluelistfield').getValue(),
                                        warning = panel.down('#warningPending'),
                                        cntError = 0,
                                        cntWarning = 0,
                                        msg = "You are going to import selected instances to Scalr. You won't be able to undo this operation or re-import them again.";

                                    store.each(function(record) {
                                        var instanceTags = {}, i, override = [], len = 0;

                                        Ext.each(record.get('tags'), function(a) {
                                            instanceTags[a['key']] = a['value'];
                                        });

                                        for (i in tags) {
                                            if (instanceTags.hasOwnProperty(i)) {
                                                override.push(i);
                                            }
                                        }

                                        instanceTags = Ext.merge(instanceTags, tags);
                                        record.set('tagsError', Object.keys(instanceTags).length > 9); // 9 + scalr meta-tag
                                        record.set('tagsOverride', override);
                                        record.set('tagsExceeded', Object.keys(instanceTags).length - 9);
                                        cntError += record.get('tagsError') ? 1 : 0;
                                        cntWarning += !Ext.isEmpty(override) && !record.get('tagsError') ? 1 : 0;
                                    });

                                    if (cntError) {
                                        panel.down('#warningTagsImport').show().setValue(cntError + " servers have tag limit issue and won't be imported.");
                                    }

                                    msg += " The scalr-meta tag";
                                    if (Object.keys(tags).length) {
                                        msg += " and " + Object.keys(tags).length + " additional tags";
                                    }
                                    msg +=  " will be added to " + (store.getCount() - cntError);

                                    if (cntWarning) {
                                        msg += " and updated on " + cntWarning;
                                    }

                                    msg += " imported servers.";
                                    warning.setValue(msg + "\n" + warning.scalingMessage);

                                    panel.down("#confirmationButtons").down('#confirm').setDisabled(store.getCount() == cntError);
                                }
                            }
                        }, {
                            xtype: 'button',
                            text: 'Cancel',
                            handler: function () {
                                panel.down('#leftColumn').setDisabled(false).next().hide();
                                panel.down('#optionsButtons').show();
                            }
                        }]
                    }]
                }, {
                    xtype: 'panel',
                    itemId: 'confirmation',
                    layout: {
                        type: 'vbox',
                        align: 'stretch'
                    },
                    items: [{
                        xtype: 'displayfield',
                        cls: 'x-form-field-warning',
                        itemId: 'warningPending'
                    }, {
                        xtype: 'displayfield',
                        cls: 'x-form-field-warning',
                        hidden: true,
                        itemId: 'warningImport',
                        value: "Please do not close or reload this page."
                    }, {
                        xtype: 'displayfield',
                        cls: 'x-form-field-warning',
                        hidden: true,
                        itemId: 'warningTagsImport',
                        value: ''
                    }, {
                        flex: 1,
                        autoScroll: true,
                        itemId: 'servers',
                        xtype: 'grid',
                        disableSelection: true,
                        columns: [{
                            header: 'Cloud Server ID',
                            dataIndex: 'cloudServerId',
                            sortable: true,
                            flex: 1,
                            xtype: 'templatecolumn',
                            tpl: [
                                '<tpl if="importStatus">',
                                    '<div style="margin-right: 5px" class="x-grid-icon x-grid-icon-simple ',
                                        '<tpl if="importStatus == &quot;pending&quot;">x-grid-icon-gray-ok',
                                        '<tpl elseif="importStatus == &quot;importing&quot;">x-icon-colored-status-running white',
                                        '<tpl elseif="importStatus == &quot;imported&quot;">x-grid-icon-ok',
                                        '<tpl elseif="importStatus == &quot;failed&quot;">x-grid-icon-error',
                                        '</tpl>',
                                    '" <tpl if="importError">data-qtip="{importError}"</tpl> ></div>',
                                '<tpl elseif="tagsError">',
                                    '<div style="margin-right: 5px" class="x-grid-icon x-grid-icon-simple x-grid-icon-error" data-qtip="This instance cannot be imported. {tagsExceeded} tags must be removed to avoid exceeding maximum of 10 tags (AWS limit)."></div>',
                                '<tpl elseif="tagsOverride.length">',
                                    '<div style="margin-right: 5px" class="x-grid-icon x-grid-icon-simple x-grid-icon-warning" data-qtip="The following tags will be overridden during import: {[values.tagsOverride.join(", ")]}"></div>',
                                '<tpl else>',
                                    '<div style="margin-right: 5px" class="x-grid-icon x-grid-icon-simple x-grid-icon-gray-ok" data-qtip></div>',
                                '</tpl>',
                                '{cloudServerId}'
                            ]
                        }, {
                            header: 'Type',
                            dataIndex: 'instanceType',
                            sortable: true,
                            flex: 1
                        }, {
                            header: 'Public IP',
                            xtype: 'templatecolumn',
                            sortable: false,
                            flex: 1,
                            tpl: [
                                '<tpl if="!Ext.isEmpty(publicIp)">',
                                    '{publicIp}',
                                '<tpl else>',
                                    '&mdash;',
                                '</tpl>'
                            ]
                        }, {
                            header: 'Private IP',
                            xtype: 'templatecolumn',
                            sortable: false,
                            flex: 1,
                            tpl: [
                                '<tpl if="!Ext.isEmpty(privateIp)">',
                                    '{privateIp}',
                                '<tpl else>',
                                    '&mdash;',
                                '</tpl>'
                            ]
                        }, {
                            header: 'Image ID',
                            itemId: 'imageId',
                            dataIndex: 'imageId',
                            sortable: true,
                            flex: 1,
                            hidden: true
                        }],
                        store: {
                            fields: ['cloudServerId', 'instanceType', 'publicIp', 'privateIp', 'importStatus', 'importError', 'tagsError', 'tagsOverride', 'tagsExceeded']
                        }
                    }],
                    dockedItems: [{
                        xtype: 'container',
                        cls: 'x-docked-buttons',
                        itemId: 'confirmationButtons',
                        dock: 'bottom',
                        layout: {
                            type: 'hbox',
                            pack: 'center'
                        },
                        items: [{
                            xtype: 'button',
                            text: 'Confirm',
                            itemId: 'confirm',
                            handler: function () {
                                var grid = panel.down('#servers'), gridStore = grid.getStore();
                                panel.status = 'importing';
                                panel.down('progressbarstepfield').setValue('import');

                                panel.down('#warningPending').hide();
                                panel.down('#warningImport').show();

                                this.disable();

                                gridStore.each(function (record) {
                                    if (!record.get('tagsError')) {
                                        record.set('importStatus', 'pending');
                                    }
                                });

                                var handler = function () {
                                    var record = gridStore.findRecord('importStatus', 'pending');

                                    if (record && panel.status != 'finished') {
                                        record.set('importStatus', 'importing');
                                        grid.getView().focusRow(record);
                                        Scalr.Request({
                                            url: '/servers/import/xImport',
                                            hideErrorMessage: true,
                                            params: {
                                                platform: loadParams['platform'],
                                                farmRoleId: panel.down('[name="farmRoleId"]').getValue(),
                                                instanceId: record.get('cloudServerId'),
                                                tags: Ext.encode(panel.down('namevaluelistfield').getValue())
                                            },
                                            success: function (data) {
                                                if (panel.isDestroyed) {
                                                    return;
                                                }

                                                record.set('importStatus', 'imported');
                                                handler();
                                            },
                                            failure: function (data) {
                                                record.set('importStatus', 'failed');
                                                record.set('importError', data ? data.errorMessage : 'Connection failure');

                                                Scalr.utils.Window({
                                                    width: 500,
                                                    //cls: 'x-panel-shadow x-panel-confirm',
                                                    items: [{
                                                        xtype: 'component',
                                                        cls: 'x-panel-confirm-message x-panel-confirm-message-multiline',
                                                        data: {
                                                            type: 'error',
                                                            msg: data ? data.errorMessage : 'Connection failure'
                                                        },
                                                        tpl: '<div class="icon icon-{type}"></div><div class="message">{msg}</div>'
                                                    }, {
                                                        xtype: 'container',
                                                        layout: {
                                                            type: 'hbox',
                                                            pack: 'center'
                                                        },
                                                        cls: 'x-docked-buttons',
                                                        items: [{
                                                            xtype: 'button',
                                                            text: 'Retry',
                                                            handler: function () {
                                                                this.up('panel').close();
                                                                record.set('importStatus', 'pending');
                                                                handler();
                                                            }
                                                        }, {
                                                            xtype: 'button',
                                                            text: 'Continue',
                                                            handler: function () {
                                                                this.up('panel').close();
                                                                handler();
                                                            }
                                                        }, {
                                                            xtype: 'button',
                                                            text: 'Interrupt',
                                                            handler: function () {
                                                                this.up('panel').close();
                                                                panel.finishImport();
                                                            }
                                                        }]
                                                    }]
                                                });
                                            }
                                        });
                                    } else {
                                        panel.finishImport();
                                    }
                                };

                                handler();
                            }
                        }, {
                            xtype: 'button',
                            text: 'Cancel',
                            handler: function () {
                                if (panel.status == 'importing') {
                                    confirmStop();
                                } else {
                                    panel.down('progressbarstepfield').setValue('tags');
                                    panel.down('#rightColumnCard').setActiveItem('tags');
                                    panel.down('#warningTagsImport').hide();
                                }
                            }
                        }]
                    }, {
                        xtype: 'container',
                        cls: 'x-docked-buttons',
                        dock: 'bottom',
                        itemId: 'finishedButtons',
                        hidden: true,
                        layout: {
                            type: 'hbox',
                            pack: 'center'
                        },
                        items: [{
                            xtype: 'button',
                            text: 'View Servers',
                            handler: function () {
                                Scalr.event.fireEvent('redirect', '#/servers?farmRoleId=' + loadParams['farmRoleId']);
                            }
                        }, {
                            xtype: 'button',
                            text: 'Close',
                            handler: function () {
                                Scalr.event.fireEvent('close');
                            }
                        }]
                    }]
                }]
            }]
        }],

        dockedItems: [{
            xtype: 'container',
            itemId: 'platforms',
            dock: 'left',
            cls: 'x-docked-tabs',
            width: 110 + Ext.getScrollbarSize().width,
            overflowY: 'auto',
            defaults: {
                xtype: 'button',
                ui: 'tab',
                allowDepress: false,
                iconAlign: 'top',
                disableMouseDownPressed: true,
                disabled: true,
                toggleGroup: 'serversimport-tabs',
                cls: 'x-btn-tab-no-text-transform',
                toggleHandler: function (comp, state) {
                    if (state) {
                        panel.fireEvent('selectplatform', this.value);
                    }
                }
            },
            items: platformsTabs
        }],

        finishImport: function () {
            this.status = 'finished';
            this.down('progressbarstepfield').setValue('complete2');
            this.down('#confirmationButtons').hide();
            this.down('#finishedButtons').show();
        },

        updateUrl: function (params) {
            loadParams = Ext.apply(loadParams, params);
            Scalr.utils.replaceCurrentUrl(loadParams);
        },

        updateStatus: function(params) {
            this.updateUrl(params);
            Scalr.Request({
                processBox: {
                    type: 'action'
                },
                url: '/servers/import/xCheckStatus',
                params: loadParams,
                success: function(data) {
                    this.applyStatus(data['status']);
                },
                scope: this
            });
        },

        applyStatus: function(status) {
            Ext.each(this.down('#steps').query('[isStep=true]'), function(item) {
                item.applyStatus(status[item.itemId]);
            });
        },

        listeners: {
            boxready: function (panel) {
                this.applyStatus(moduleParams['status']);
            }
        }
    });

    return panel;
});
