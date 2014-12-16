Ext.define('Scalr.ui.RoleDesignerTabOverview', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditoverview',
    cls: 'x-panel-column-left',
    layout: {
        type: 'vbox',
        align: 'stretch'
    },
    autoScroll: true,
    items: [{
        xtype: 'form',
        itemId: 'roleSettings',
        cls: 'x-panel-column-left',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        defaults: {
            flex: 1,
            maxWidth: 450
        },
        items: [{
            xtype: 'container',
            cls: 'x-container-fieldset x-fieldset-separator-right',
            layout: 'anchor',
            maxWidth: 540,
            defaults: {
                anchor: '100%',
                labelWidth: 80
            },
            items: [{
                xtype: 'component',
                itemId: 'nameReadonly',
                cls: 'x-fieldset-subheader',
                hidden: true
            },{
                xtype: 'textfield',
                name: 'name',
                itemId: 'name',
                fieldLabel: 'Name',
                vtype: 'rolename',
                hideInputOnReadOnly: true,
                allowBlank: false
            },{
                xtype: 'fieldcontainer',
                itemId: 'os',
                layout: 'hbox',
                fieldLabel: 'OS',
                items: [{
                    xtype: 'combo',
                    name: 'osFamily',
                    width: 130,
                    displayField: 'title',
                    valueField: 'osFamily',
                    editable: false,
                    store: Ext.create('Ext.data.ArrayStore', {
                        fields: ['osFamily', 'versions', {name: 'title', convert: function(v, record){return Scalr.utils.beautifyOsFamily(record.data.osFamily);}}],
                        data: Scalr.constants.osFamily
                    }),
                    listeners: {
                        change: function(comp, value) {
                            var record = comp.findRecordByValue(value);
                            if (record) {
                                var version = comp.next(),
                                    store = version.store;
                                store.getProxy().data = record.get('versions');
                                store.load();
                                version.setValue(store.last());
                            }
                        }
                    }
                },{
                    xtype: 'combo',
                    name: 'osVersion',
                    displayField: 'title',
                    valueField: 'osVersion',
                    editable: false,
                    flex: 1,
                    store: Ext.create('Ext.data.ArrayStore', {
                        fields: ['osVersion', 'osGeneration', 'suffix', {name: 'title', convert: function(v, record){return record.data.osVersion + ' ' + record.data.suffix;}}]
                    }),
                    margin: '0 0 0 12'
                }]
            },{
                xtype: 'combo',
                name: 'catId',
                valueField: 'id',
                displayField: 'name',
                editable: false,
                hideInputOnReadOnly: true,
                store: {
                    fields: ['id', 'name'],
                    proxy: 'object'
                },
                fieldLabel: 'Category',
                allowBlank: false
            },{
                xtype: 'displayfield',
                name: 'created',
                fieldLabel: 'Created by'
            },{
                xtype: 'textarea',
                name: 'description',
                fieldLabel: 'Description',
                height: 70
            }]
        },{
            xtype: 'container',
            cls: 'x-container-fieldset x-fieldset-separator-right',
            items: [{
                xtype: 'container',
                layout: 'anchor',
                defaults: {
                    anchor: '100%',
                    labelWidth: 120
                },
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    items: [{
                        xtype: 'component',
                        itemId: 'topRightcol',
                        //hidden: true,
                        cls: 'x-fieldset-subheader',
                        html: 'Software and built-in automation'
                    },{
                        xtype: 'button',
                        itemId: 'configureAutomation',
                        text: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-configure" />&nbsp;Configure',
                        maxWidth: 120,
                        flex: 1,
                        margin: '0 0 0 10',
                        handler: function() {
                            var behaviors = [
                                    'chef', /*'mysql',*/'mariadb','mysql2','percona','postgresql'/*,'cassandra'*/,'redis','mongodb',
                                    'app','www','haproxy','tomcat','memcached','rabbitmq' /* ,
                                    'cf_router','cf_cloud_controller','cf_health_manager','cf_dea','cf_service'*/
                                ],
                                store = this.up('roleeditoverview').down('#automation').store;
                            Scalr.Confirm({
                                formWidth: 850,
                                form: [{
                                    xtype: 'fieldset',
                                    title: 'Configure Scalr automation<br><span style="font-size: 12px">' +
                                        'Please ensure the selected software is built into every image used by role</span>',
                                    cls: 'x-fieldset-separator-none',
                                    height: 440,
                                    defaults: {
                                        xtype: 'button',
                                        ui: 'simple',
                                        enableToggle: true,
                                        cls: 'x-btn-simple-large',
                                        iconAlign: 'above',
                                        margin: '10 10 0 0'
                                    },
                                    items: Ext.Array.map(behaviors, function(behavior){
                                        return {
                                            iconCls: 'x-icon-behavior-large x-icon-behavior-large-' + behavior,
                                            text: Scalr.utils.beautifyBehavior(behavior),
                                            behavior: behavior,
                                            pressed: store.query('name', behavior).length > 0
                                        };
                                    })
                                }],
                                closeOnSuccess: true,
                                scope: this,
                                success: function (formValues, form) {
                                    var behaviors = [];
                                    Ext.Array.each(form.query('[xtype="button"]'), function(btn){
                                        if (btn.pressed) {
                                            behaviors.push(btn.behavior);
                                        }
                                    });
                                    if (behaviors.length === 1 && behaviors[0] === 'chef') {
                                        behaviors.push('base');
                                    }
                                    this.up('roleeditoverview').fireEvent('behaviorschange', behaviors);

                                    store.loadData(Ext.Array.map(behaviors, function(behavior){ return {name: behavior}}));
                                    return true;
                                }
                            });
                        }
                    }]
                },{
                    xtype: 'displayfield',
                    itemId: 'osReadonly',
                    fieldLabel: 'Operating system',
                    hidden: true
                },{
                    xtype: 'fieldcontainer',
                    fieldLabel: 'Built-in automation',
                    items: {
                        xtype: 'dataview',
                        itemId: 'automation',
                        itemSelector: '.x-item',
                        store: Ext.create('Ext.data.ArrayStore', {
                            fields: ['name']
                        }),
                        tpl  : new Ext.XTemplate(
                            '<tpl for=".">',
                                '<tpl if="values.name!=\'base\'">',
                                    '<span style="white-space:nowrap;color:#666;line-height:26px;" data-qtip="{[Scalr.utils.beautifyBehavior(values.name, true)]}">',
                                        '<img style="margin:4px 0 0 0;" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-role-small x-icon-role-small-{name}"/>&nbsp;{[Scalr.utils.beautifyBehavior(values.name)]}',
                                    '</span>&nbsp;&nbsp; ',
                                '</tpl>',
                            '</tpl>'
                        )
                    }
                }]
            }]
        },{
            xtype: 'container',
            cls: 'x-container-fieldset',
            layout: 'anchor',
            defaults: {
                anchor: '100%'
            },
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-subheader',
                html: 'Role usage'
            },{
                xtype: 'cloudlocationmap',
                itemId: 'locationmap',
                size: 'large',
                autoSelect: true,
                //hidden: true,
                listeners: {
                    selectlocation: function(location){
                        var grid = this.next(),
                            res = grid.store.query('location', location, false, false, true),
                            selModel;
                        if (res.length) {
                            selModel = grid.view.getSelectionModel();
                            selModel.deselectAll();
                            selModel.setLastFocused(res.first());
                        }
                    }
                }
            },{
                xtype: 'displayfield',
                name: 'usage',
                fieldStyle: 'text-align: center',
                margin: '10 0 0 0'
                //fieldLabel: 'Role usage'

            }]
        }]
    },{
        xtype: 'container',
        flex: 1,
        cls: 'x-fieldset-separator-top',
        minHeight: 240,
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        defaults: {
            flex: 1
        },
        items: [{
            xtype: 'fieldset',
            title: 'Images',
            cls: 'x-fieldset-separator-right',
            maxWidth: 760,
            layout: {
                type: 'vbox',
                align: 'stretch'
            },
            items: [{
                xtype: 'grid',
                itemId: 'images',
                cls: 'x-grid-shadow x-grid-no-highlighting',
                flex: 1,
                store: {
                    fields: [ 'platform', 'cloudLocation', 'imageId', 'name'],
                    proxy: 'object'
                },
                features: {
                    ftype: 'addbutton',
                    text: 'Add image',
                    handler: function(view) {
                        view.up('roleeditoverview').fireEvent('addimage');
                    }
                },
                plugins: [{
                    ptype: 'focusedrowpointer',
                    thresholdOffset: 26
                }],

                viewConfig: {
                    getRowClass: function(record) {
                        var cls = '';
                        if (!record.get('imageId')) {
                            cls += ' x-grid-row-disabled';
                        }
                        return cls;
                    }
                },
                columns: [
                    { header: "Cloud Location", flex: 0.6, dataIndex: 'platform', sortable: true, renderer:
                        function(value, meta, record) {
                            var platform = record.get('platform'),
                                cloudLocation = record.get('cloudLocation'),
                                res = '';

                            res = '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                            if (record.get('imageId')) {
                                if (platform === 'gce' || platform === 'ecs') {
                                    res += 'All regions';
                                } else if (location) {
                                    if (Scalr.platforms[platform] && Scalr.platforms[platform]['locations'] && Scalr.platforms[platform]['locations'][cloudLocation]) {
                                        res += Scalr.platforms[platform]['locations'][cloudLocation];
                                    } else {
                                        res += cloudLocation;
                                    }
                                }
                            } else {
                                res += Scalr.utils.getPlatformName(platform) + '&nbsp;';
                            }
                            return res;
                        }
                    },
                    { header: "Image", flex: 1, dataIndex: 'cloudLocation', sortable: true, renderer:
                        function(value, meta, record) {
                            var res = '';
                            if (record.get('imageId')) {
                                res = '<a href="#/images/view?platform=' + record.get('platform') + '&cloudLocation=' +
                                record.get('cloudLocation') + '&id=' + record.get('imageId') + '">' + record.get('name') + '</a>';
                            } else {
                                res = '<i>No image has been added for this cloud</i>';
                            }

                            return res;
                        }
                    }
                ],
                listeners: {
                    viewready: {
                        fn: function() {
                            var me = this;
                            this.store.on('load', function(){
                                var store = this,
                                    map = me.prev(),
                                    locations = [];
                                //map.reset();

                                (store.snapshot || store.data).each(function(record){
                                    locations.push({
                                        platform: record.get('platform'),
                                        cloudLocation: record.get('cloudLocation')
                                    });
                                });
                                //map.addLocations(locations);
                            });

                            me.getSelectionModel().on('focuschange', function(gridSelModel, oldFocused, newFocused){
                                if (newFocused) {
                                    //me.prev().setLocation(newFocused.get('location'));
                                }
                            });

                        },
                        single: true
                    }
                }
            }]
        },{
            xtype: 'container',
            autoScroll: true,
            items: [{
                xtype: 'fieldset',
                cls: 'x-fieldset-separator-none',
                flex: 1,
                title: 'Orchestration',
                //cls: 'x-fieldset-separator-none',
                minHeight: 130,
                items: [{
                    xtype: 'scriptinggrid',
                    itemId: 'scripts',
                    maxWidth: 600,
                    groupingStartCollapsed: true,
                    groupingShowTotal: true,
                    cls: 'x-grid-shadow x-grid-role-scripting x-grid-no-highlighting',
                    hideDeleteButton: true,
                    addButtonHandler: function(view) {
                        view.up('roleeditoverview').fireEvent('addscript');
                    },
                    refreshScripts: function(params) {
                        var scripts = [];
                        if (params['role']['scripts'].length) {
                            scripts.push.apply(scripts, params['role']['scripts']);
                        }

                        if (params['accountScripts'].length) {
                            Ext.each(params['accountScripts'], function(script){
                                var addScript = true;
                                if (script['script_type'] === 'scalr') {
                                    addScript = script['os'] == params['role']['osFamily'] || script['os'] == 'linux' && params['role']['osFamily'] != 'windows';
                                }
                                if (addScript) {
                                    scripts.push(script);
                                }
                            });
                        }

                        this.store.load({data: Ext.Array.map(scripts, function(script){
                            script['event'] = script['event'] || script['event_name'];
                            script['script'] = script['script'] || script['script_name'];
                            return script;
                        })});

                    }
                }]
            },{
                xtype: 'container',
                cls: 'x-container-fieldset x-fieldset-separator-top',
                itemId: 'chefPanel',
                refreshChefSettings: function(params) {
                    var chefEnabled = Ext.Array.contains(params['role']['behaviors'], 'chef');
                    if (chefEnabled) {
                        var chef, chefCt = this.down('#chef'), noChefCt = this.down('#nochef');
                        if (Ext.isObject(params['role']['chef'])) {
                            chef = params['role']['chef'];
                            if (chef['chef.bootstrap'] == 1) {
                                chefCt.setFieldValues(chef);
                                Ext.Array.each(chefCt.query('[hideOn="solo"]'), function(item){
                                    item.setVisible(chef['chef.cookbook_url'] === undefined);
                                });
                                Ext.Array.each(chefCt.query('[hideOn="server"]'), function(item){
                                    item.setVisible(chef['chef.cookbook_url'] !== undefined);
                                });
                                chefCt.down('[name="chef.runlist"]').setVisible(chef['chef.role_name'] === undefined);
                                chefCt.down('[name="chef.role_name"]').setVisible(chef['chef.role_name'] !== undefined);
                                chefCt.show();
                                noChefCt.hide();
                            } else {
                                chefCt.hide();
                                noChefCt.show();
                            }
                        } else {
                            chefCt.hide();
                            noChefCt.show();
                        }
                    }
                    this.setVisible(chefEnabled);
                },
                items: [{
                    xtype: 'container',
                    layout: 'hbox',
                    items: [{
                        xtype: 'component',
                        cls: 'x-fieldset-subheader',
                        html: 'Bootstrap role with Chef'
                    },{
                        xtype: 'button',
                        maxWidth: 120,
                        flex: 1,
                        margin: '0 0 0 10',
                        text: '<img style="vertical-align:top;" width="16" height="16" src="/ui2/images/ui/roles/edit/chef_config.png" />&nbsp;Configure',
                        handler: function() {
                            this.up('roleeditoverview').fireEvent('editchef');
                        }
                    }]
                },{
                    xtype: 'displayfield',
                    itemId: 'nochef',
                    value: '<i>Chef is currently not used by this role.</i>'
                },{
                    xtype: 'container',
                    layout: 'anchor',
                    itemId: 'chef',
                    hidden: true,
                    defaults: {
                        labelWidth: 110,
                        anchor: '100%',
                        maxWidth: 600
                    },
                    items: [{
                        xtype: 'displayfield',
                        hideOn: 'solo',
                        name: 'chef.server_id',
                        fieldLabel: 'Chef server',
                        renderer: function(value, comp) {
                            if (value) {
                                Scalr.cachedRequest.load({url: '/services/chef/servers/xListServers/'},function(data, status) {
                                    if (comp.rendered && !comp.isDestroyed && status) {
                                        Ext.Object.each(data, function(key, chefServer){
                                            if (chefServer.id == value) {
                                                value = chefServer.url;
                                                comp.inputEl.dom.innerHTML = chefServer.url;
                                                comp.updateLayout();
                                                return false;
                                            }
                                        });
                                    }
                                });
                            }
                            return value;
                        }
                    },{
                        xtype: 'displayfield',
                        name: 'chef.environment',
                        hideOn: 'solo',
                        fieldLabel: 'Chef environment'
                    },{
                        xtype: 'displayfield',
                        name: 'chef.role_name',
                        hideOn: 'solo',
                        fieldLabel: 'Chef role'
                    },{
                        xtype: 'displayfield',
                        name: 'chef.cookbook_url',
                        hideOn: 'server',
                        fieldLabel: 'Cookbook URL'
                    },{
                        xtype: 'textarea',
                        name: 'chef.runlist',
                        readOnly: true,
                        submitValue: false,
                        labelAlign: 'top',
                        icons: {
                            globalvars: true
                        },
                        fieldLabel: 'Runlist'
                    },{
                        xtype: 'textarea',
                        name: 'chef.attributes',
                        readOnly: true,
                        submitValue: false,
                        labelAlign: 'top',
                        icons: {
                            globalvars: true
                        },
                        fieldLabel: 'Attributes'
                    }]
                }]
            }]
        }]
    }],
    initComponent: function() {
        this.callParent(arguments);
        this.addListener({
            showtab: {
                fn: function(params){
                    var isNewRole = !params['role']['roleId'];
                    this.down('[name="catId"]').store.load({data: params.categories});
                    if (!isNewRole) {
                        this.down('#nameReadonly').show().update(params['role']['name']);
                        //this.down('#topRightcol').show();
                        this.down('#name').hide().disable();
                        this.down('#osReadonly').show().setValue('<img src="'+Ext.BLANK_IMAGE_URL+'" title="" class="x-icon-osfamily-small x-icon-osfamily-small-'+params['role']['osFamily']+'" />&nbsp;' + params['role']['os']);
                        this.down('#os').hide();
                        this.setFieldValues({
                            name: params['role']['name'],
                            catId: params['role']['catId'],
                            osFamily: params['role']['osFamily'],
                            osVersion: params['role']['osVersion'],
                            description: params['role']['description'],
                            created: params['role']['addedByEmail'] ? '<i>' + params['role']['addedByEmail'] + '</i>' + (params['role']['dtadded'] ? ' on <i>' + params['role']['dtadded'] + '</i>' : '') : '-',
                            usage: '<span style="color:green;font-size:140%">' + params['roleUsage']['farms'] + '</span> farm(s) with ' + '<span style="color:green;font-size:140%">' + params['roleUsage']['instances'] + '</span> running instance(s) of this role'
                        });
                    } else {
                        if (params['role']['osFamily']) {
                            this.down('[name="osFamily"]').setValue(params['role']['osFamily']);
                            this.down('[name="osVersion"]').setValue(params['role']['osVersion']);
                        } else {
                            this.down('[name="osFamily"]').setValue('ubuntu');
                        }

                        this.down('[name="created"]').hide();
                        this.down('[name="usage"]').hide();
                    }

                    var fields = this.down('#roleSettings').query('[isFormField]');
                    Ext.Array.each(fields, function(item){
                        if (item.name !== 'description') {
                            item.setReadOnly(!isNewRole, false);
                        }
                        if (item.name === 'catId') {
                            item.allowBlank = !isNewRole;
                        }
                    });

                    this.down('#configureAutomation').setVisible(isNewRole);
                    this.down('#automation').store.loadData(isNewRole ? [['base']] : Ext.Array.map(params['role']['behaviors'], function(item){return [item];}));
                    this.down('#scripts').getStore().loadEvents(params['scriptData']['events']);
                    
                    this.down('[name="osFamily"]').on('change', function(comp, value){
                        this.up('roleeditoverview').fireEvent('osfamilychange', value);
                    });
                },
                single: true
            },
            hidetab: function(params) {
                var automationStore = this.down('#automation').store;
                params['role']['behaviors'] = [];
                (automationStore.snapshot || automationStore.data).each(function(record){
                    params['role']['behaviors'].push(record.get('name'));
                });
                if (!params['role']['roleId']) {
                    params['role']['osFamily'] = this.down('[name="osFamily"]').getValue();
                    params['role']['osVersion'] = this.down('[name="osVersion"]').getValue();
                }
            }
        });
        this.addListener({
            showtab: {
                fn: function(params){
                    var images = [],
                        platformsAdded = {};
                    Ext.Array.each(params['role']['images'], function(value) {
                        images.push(value);
                        platformsAdded[value['platform']] = true;
                    });
                    if (Scalr.user.type !== 'ScalrAdmin') {
                        Ext.Object.each(Scalr.platforms, function(key, value) {
                            if (platformsAdded[key] === undefined && value.enabled) {
                                images.push({platform: key});
                            }
                        });
                    }
                    this.down('#images').store.load({data: images});

                    this.down('#scripts').refreshScripts(params);
                    this.down('#chefPanel').refreshChefSettings(params);
                }
            }
        });
    },
    getSubmitValues: function() {
        var values = this.getFieldValues(true),
            osField = this.down('[name="osVersion"]'),
            automationStore = this.down('#automation').store,
            os;
        os = osField.findRecordByValue(values['osVersion']);
        if (os) {
            os = os.getData();
            values.osGeneration = os.osGeneration;
            values.os = Scalr.utils.beautifyOsFamily(values.osFamily) + ' ' + os.title;
        }

        values['behaviors'] = [];
        (automationStore.snapshot || automationStore.data).each(function(record){
            values['behaviors'].push(record.get('name'));
        });
        return values;
    },

    isValid: function(params) {
        var valid = true;
        if (!params['id']) {
            valid = this.down('#roleSettings').isValid();
        }
        return valid;
    }

});
