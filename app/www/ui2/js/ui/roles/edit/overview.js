Ext.define('Scalr.ui.RoleDesignerTabOverview', {
    extend: 'Ext.container.Container',
    alias: 'widget.roleeditoverview',
    cls: 'x-panel-column-left x-panel-column-left-with-tabs',
    layout: {
        type: 'vbox',
        align: 'stretch'
    },
    autoScroll: true,
    items: [{
        xtype: 'form',
        itemId: 'roleSettings',
        //cls: 'x-panel-column-left',
        layout: {
            type: 'hbox',
            align: 'stretch'
        },
        defaults: {
            flex: 1,
            maxWidth: 480
        },
        items: [{
            xtype: 'container',
            cls: 'x-container-fieldset x-fieldset-separator-right',
            layout: 'anchor',
            maxWidth: 540,
            defaults: {
                anchor: '100%',
                labelWidth: 90
            },
            items: [{
                xtype: 'component',
                itemId: 'nameReadonly',
                cls: 'x-fieldset-subheader x-fieldset-subheader-no-text-transform',
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
                    itemId: 'osFamily',
                    name: 'osFamily',
                    flex: 1,
                    displayField: 'name',
                    valueField: 'id',
                    editable: false,
                    emptyText: 'Family',
                    plugins: {
                        ptype: 'fieldinnericon',
                        field: 'id',
                        iconClsPrefix: 'x-icon-osfamily-small x-icon-osfamily-small-'
                    },
                    store: {
                        fields: ['id', 'name'],
                        proxy: 'object',
                        data: Scalr.utils.getOsFamilyList()
                    },
                    listeners: {
                        change: function(comp, value) {
                            var osIdField = comp.next();
                            osIdField.store.load({data: value ? Scalr.utils.getOsList(value) : []});
                            osIdField.reset();
                        }
                    }
                },{
                    xtype: 'combo',
                    itemId: 'osId',
                    name: 'osId',
                    displayField: 'title',
                    valueField: 'id',
                    editable: false,
                    emptyText: 'Version',
                    flex: .6,
                    allowBlank: false,
                    autoSetSingleValue: true,
                    store: {
                        fields: [
                            'id',
                            {
                                name: 'title',
                                convert: function(v, record){return record.data.version || record.data.generation || record.data.id},
                                sortType: 'asFloat'
                            }
                        ],
                        sorters: [{
                            property: 'title'
                        }],
                        proxy: 'object'
                    },
                    margin: '0 0 0 12',
                    listeners: {
                        beforequery: function() {
                            var osFamilyField = this.prev();
                            if (!osFamilyField.getValue()) {
                                Scalr.message.InfoTip('Select OS family first.', osFamilyField.inputEl, {anchor: 'bottom'});
                            }
                        }
                    }
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
            }, {
                xtype: 'displayfield',
                name: 'created',
                fieldLabel: 'Created by'
            }, {
                xtype: 'textarea',
                name: 'description',
                fieldLabel: 'Description',
                height: 70
            }, {
                xtype: 'displayfield',
                fieldLabel: 'Permissions',
                itemId: 'environments'
            }, {
                xtype: 'buttongroupfield',
                fieldLabel: 'Quick Start',
                maxWidth: 245,
                name: 'isQuickStart',
                defaults: {
                    width: 60
                },
                items: [{
                    text: 'Yes',
                    value: 1
                }, {
                    text: 'No',
                    value: 0
                }],
                plugins: {
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: [{id: 'info', tooltip: 'Make this a Quick Start Role.'}]
                }
            }, {
                xtype: 'buttongroupfield',
                fieldLabel: 'Deprecated',
                maxWidth: 245,
                name: 'isDeprecated',
                defaults: {
                    width: 60
                },
                items: [{
                    text: 'Yes',
                    value: 1
                }, {
                    text: 'No',
                    value: 0
                }],
                plugins: {
                    ptype: 'fieldicons',
                    align: 'right',
                    icons: [{id: 'info', tooltip: 'Deprecate this Role to prevent further use.'}]
                }
            }]
        },{
            xtype: 'container',
            flex: 1.1,
            cls: 'x-container-fieldset x-fieldset-separator-right',
            items: [{
                xtype: 'container',
                layout: 'anchor',
                defaults: {
                    anchor: '100%',
                    labelWidth: 150
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
                        xtype: 'tbfill',
                    },{
                        xtype: 'button',
                        itemId: 'configureAutomation',
                        iconCls: 'x-btn-icon-settings',
                        margin: '0 0 0 10',
                        handler: function() {
                            var behaviors = [
                                    {name: 'mysql2', disable: {behavior: ['postgresql', 'redis', 'mongodb', 'percona','mariadb'], os:[{family: 'centos', version: /^7/i}]}},
                                    {name: 'mariadb', disable: {behavior: ['postgresql', 'redis', 'mongodb', 'percona','mysql2']}},
                                    {name: 'postgresql', disable: {platform: ['gce'], behavior: ['redis', 'mongodb', 'percona', 'mysql2', 'mariadb']}},
                                    {name: 'percona', disable: {behavior: ['postgresql', 'redis', 'mongodb', 'mysql2', 'mariadb']}},
                                    {name: 'app', disable: {behavior:['www', 'tomcat']}},
                                    {name: 'tomcat', disable: {behavior:['app'], os:['oel', {family: 'ubuntu', version: ['10.04']}]}},
                                    {name: 'haproxy', disable: {behavior:['www']}},
                                    {name: 'www', disable: {behavior:['app', 'haproxy']}},
                                    {name: 'memcached'},
                                    {name: 'redis', disable: {behavior: ['postgresql', 'mongodb', 'percona', 'mysql2', 'mariadb']}},
                                    {name: 'rabbitmq', disable: {os: ['rhel', 'oel']}},
                                    {name: 'mongodb', disable: {platform: ['gce', 'rackspacengus', 'rackspacenguk'], behavior: ['postgresql', 'redis', 'percona', 'mysql2', 'mariadb']}},
                                    {name: 'chef'}
                                ],
                                store = this.up('roleeditoverview').down('#automation').store;
                            Scalr.Confirm({
                                winConfig: {
                                    layout: 'fit',
                                },
                                alignTop: true,
                                formWidth: 780,
                                form: [{
                                    xtype: 'fieldset',
                                    title: 'Configure Scalr automation<span class="x-fieldset-header-description">' +
                                        'Please ensure the selected software is built into every image used by role</span>',
                                    cls: 'x-fieldset-separator-none',
                                    listeners: {
                                        afterrender: function(){
                                            this.refreshBehaviors();
                                        }
                                    },
                                    refreshBehaviors: function() {
                                        var me = this,
                                            params = {behavior: []};
                                        Ext.Array.each(me.query('[xtype="button"]'), function(button){
                                            if (button.pressed) {
                                                params.behavior.push(button.behavior);
                                            }
                                        });

                                        for (var i=0, len=behaviors.length; i<len; i++) {
                                            var item = behaviors[i],
                                                enabled = true,
                                                disableInfo;
                                            if (item.disable) {
                                                Ext.Object.each(params, function(key, value){
                                                    if (item.disable[key]) {
                                                        enabled = Ext.isArray(value) ? !Ext.Array.intersect(item.disable[key], value).length : !Ext.Array.contains(item.disable[key], value);
                                                    }
                                                    disableInfo = {
                                                        reason: key,
                                                        value: item.disable[key] || null
                                                    };
                                                    return enabled;
                                                });
                                            }
                                            var btn = me.down('[behavior="'+item.name+'"]');
                                            if (enabled) {
                                                btn.enable();
                                                btn.setTooltip('');
                                            }  else {
                                                btn.toggle(false).disable();
                                                var message = '';
                                                if (disableInfo.reason == 'behavior') {
                                                    message = '<b>' + Scalr.utils.beautifyBehavior(item.name) + '</b> cannot be used together with <b style="white-space:nowrap">' + (Ext.Array.map(Ext.Array.intersect(params.behavior, disableInfo.value), Scalr.utils.beautifyBehavior)).join(', ') + '</b>.';
                                                }
                                                btn.setTooltip(message);
                                            }
                                        }
                                    },
                                    defaults: {
                                        xtype: 'button',
                                        ui: 'simple',
                                        enableToggle: true,
                                        cls: 'x-btn-simple-large',
                                        iconAlign: 'top',
                                        margin: '0 10 10 0',
                                        listeners: {
                                            toggle: function() {
                                                this.up().refreshBehaviors();
                                            }
                                        }
                                    },
                                    items: Ext.Array.map(behaviors, function(behavior){
                                        return {
                                            iconCls: 'x-icon-behavior-large x-icon-behavior-large-' + behavior.name,
                                            text: Scalr.utils.beautifyBehavior(behavior.name),
                                            behavior: behavior.name,
                                            pressed: store.query('name', behavior.name, false, false, true).length > 0
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
                                    if (behaviors.length === 0 || behaviors.length === 1 && behaviors[0] === 'chef') {
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
                    hidden: true,
                    renderer: function(value) {
                        var os = Scalr.utils.getOsById(value);
                        if (os) {
                            return '<img src="'+Ext.BLANK_IMAGE_URL+'" title="" class="x-icon-osfamily-small x-icon-osfamily-small-'+os.family+'" />&nbsp;' + os.name
                        } else {
                            return value;
                        }
                    }
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
                        style: 'line-height:26px',
                        tpl  : new Ext.XTemplate(
                            '<tpl for=".">',
                                '<tpl if="values.name!=\'base\'">',
                                    '<img style="margin:0 8px 0 0" src="'+Ext.BLANK_IMAGE_URL+'" class="x-icon-role-small x-icon-role-small-{name}" data-qtip="{[Scalr.utils.beautifyBehavior(values.name, true)]}"/>',
                                '<tpl elseif="xcount==1">&mdash;',
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
                trackMouseOver: false,
                disableSelection: true,
                flex: 1,
                store: {
                    fields: [ 'platform', 'cloudLocation', 'imageId', 'name', 'hash',
                        {
                            name: 'ordering',
                            convert: function(v, record){
                                return record.data.imageId ? record.data.platform + record.data.cloudLocation : null;
                            }
                        }
                    ],
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
                    { header: "Location", flex: 0.6, dataIndex: 'ordering', sortable: true, renderer:
                        function(value, meta, record) {
                            var platform = record.get('platform'),
                                platformName = Scalr.utils.getPlatformName(platform),
                                cloudLocation = record.get('cloudLocation'),
                                res = '';

                            res = '<img class="x-icon-platform-small x-icon-platform-small-' + platform + '" data-qtip="' + platformName + '" src="' + Ext.BLANK_IMAGE_URL + '"/>&nbsp;&nbsp;';
                            if (record.get('imageId')) {
                                if (platform === 'gce' || platform === 'azure') {
                                    res += 'All locations';
                                } else if (location) {
                                    if (Scalr.platforms[platform] && Scalr.platforms[platform]['locations'] && Scalr.platforms[platform]['locations'][cloudLocation]) {
                                        res += Scalr.platforms[platform]['locations'][cloudLocation];
                                    } else {
                                        res += cloudLocation;
                                    }
                                }
                            } else {
                                res += platformName + '&nbsp;';
                            }
                            return res;
                        }
                    },
                    { header: "Image", flex: 1, dataIndex: 'cloudLocation', sortable: false, renderer:
                        function(value, meta, record) {
                            var res = '';
                            if (record.get('imageId')) {
                                res = '<a href="#' + Scalr.utils.getUrlPrefix() + '/images?hash=' + record.get('hash') + '">' + record.get('name') + '</a>';
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

                                store.getUnfiltered().each(function(record){
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
                minHeight: 130,
                items: [{
                    xtype: 'scriptinggrid',
                    itemId: 'scripts',
                    maxWidth: 600,
                    groupingStartCollapsed: true,
                    groupingShowTotal: true,
                    trackMouseOver: false,
                    disableSelection: true,
                    cls: 'x-grid-role-scripting',
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
                            var roleOsFamily = Scalr.utils.getOsById(params['role']['osId'], 'family');
                            Ext.each(params['accountScripts'], function(script){
                                var addScript = true;
                                if (script['script_type'] === 'scalr') {
                                    addScript = script['os'] == roleOsFamily || script['os'] == 'linux' && roleOsFamily != 'windows';
                                }
                                if (addScript) {
                                    scripts.push(script);
                                }
                            });
                        }
                        var groupingFeature = this.view.findFeature('grouping');
                        groupingFeature.restoreGroupsState = true;
                        groupingFeature.disable();
                        this.store.load({data: Ext.Array.map(scripts, function(script){
                            script['event'] = script['event'] || script['event_name'];
                            script['script'] = script['script'] || script['script_name'];
                            return script;
                        })});
                        groupingFeature.enable();
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
                    maxWidth: 600,
                    items: [{
                        xtype: 'component',
                        cls: 'x-fieldset-subheader',
                        html: 'Bootstrap role with Chef'
                    },{
                        xtype: 'tbfill'
                    },{
                        xtype: 'button',
                        margin: '0 0 0 10',
                        iconCls: 'x-btn-icon-settings',
                        tooltip: 'Configure Chef',
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
                        labelWidth: 130,
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
                        plugins: {
                            ptype: 'fieldicons',
                            position: 'label',
                            icons: ['globalvars']
                        },
                        fieldLabel: 'Runlist'
                    },{
                        xtype: 'textarea',
                        name: 'chef.attributes',
                        readOnly: true,
                        submitValue: false,
                        labelAlign: 'top',
                        plugins: {
                            ptype: 'fieldicons',
                            position: 'label',
                            icons: ['globalvars']
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
                        this.down('#name').hide().disable();
                        this.down('#osReadonly').show().setValue(params['role']['osId']);
                        this.down('#os').hide();
                        this.setFieldValues({
                            name: params['role']['name'],
                            catId: params['role']['catId'],
                            description: params['role']['description'],
                            isQuickStart: params['role']['isQuickStart'],
                            isDeprecated: params['role']['isDeprecated'],
                            created: params['role']['addedByEmail'] ? '<i>' + params['role']['addedByEmail'] + '</i>' + (params['role']['dtadded'] ? ' on <i>' + params['role']['dtadded'] + '</i>' : '') : '-',
                            usage: '<span style="color:green;font-size:140%">' + params['roleUsage']['farms'] + '</span> farm(s) with ' + '<span style="color:green;font-size:140%">' + params['roleUsage']['instances'] + '</span> running instance(s) of this role'
                        });
                    } else {
                        if (params['role']['osId']) {
                            this.down('#osFamily').setValue(Scalr.utils.getOsById(params['role']['osId'], 'family'));
                            this.down('#osId').setValue(params['role']['osId']);
                        }

                        this.down('[name="isQuickStart"]').setValue(0);
                        this.down('[name="isDeprecated"]').setValue(0);
                        this.down('[name="created"]').hide();
                        this.down('[name="usage"]').hide();
                    }

                    var fields = this.down('#roleSettings').query('[isFormField]');
                    Ext.Array.each(fields, function(item){
                        if (item.name !== 'description' && item.name !== 'isQuickStart' && item.name !== 'isDeprecated') {
                            item.setReadOnly(!isNewRole, false);
                        }
                        if (item.name === 'catId') {
                            item.allowBlank = !isNewRole;
                        }
                    });

                    this.down('#configureAutomation').setVisible(isNewRole);
                    this.down('#automation').store.loadData(isNewRole ? [['base']] : Ext.Array.map(params['role']['behaviors'], function(item){return [item];}));

                    this.down('#scripts').getStore().loadEvents(Ext.apply({'*': {name: '*', description: 'All events', scope: ''}}, params['scriptData']['events']));

                    this.down('#osId').on('change', function(comp, value){
                        this.up('roleeditoverview').fireEvent('osidchange', value);
                    });
                },
                single: true
            },
            hidetab: function(params) {
                var automationStore = this.down('#automation').store;
                params['role']['behaviors'] = [];
                automationStore.getUnfiltered().each(function(record){
                    params['role']['behaviors'].push(record.get('name'));
                });
                if (!params['role']['roleId']) {
                    params['role']['osId'] = this.down('#osId').getValue();
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

                    var environments = this.down('#environments'), cnt = 0;
                    Ext.each(params['role']['environments'], function(v) {
                        if (v['enabled'] == 0) {
                            cnt++;
                        }
                    });
                    environments.setVisible(cnt && cnt != params['role']['environments'].length);
                    cnt = params['role']['environments'].length - cnt;
                    environments.setValue("Available on " + cnt + " environment" + (cnt > 1 ? 's' : ''));

                    this.down('#scripts').refreshScripts(params);
                    this.down('#chefPanel').refreshChefSettings(params);
                }
            }
        });
    },
    getSubmitValues: function() {
        var values = this.getFieldValues(true),
            automationStore = this.down('#automation').store;

        values['behaviors'] = [];
        automationStore.getUnfiltered().each(function(record){
            values['behaviors'].push(record.get('name'));
        });
        return values;
    },

    isValid: function(params) {
        var valid = true;
        if (!params['roleId']) {
            valid = this.down('#roleSettings').isValid();
        }
        return valid;
    }
});
