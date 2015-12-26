Ext.ns('Scalr.utils');

Scalr.utils.CreateProcessBox = function (config) {
    var messages = {
        delete: 'Deleting ...',
        reboot: 'Rebooting ...',
        terminate: 'Terminating ...',
        launch: 'Launching ...',
        save: 'Saving ...'
    };

    config = config || {};

    config.msg = config.msg || messages[config.type] || 'Processing ...';
    config.msg = !config.text ? config.msg : config.msg +
    '<div class="x-fieldset-header-description" style="white-space: pre-wrap">' +
    config.text + '</div>';

    var progressBar = config.progressBar;

    if (progressBar) {
        if (!Ext.isObject(progressBar)) {
            progressBar = {};
        }

        Ext.applyIf(progressBar, {
            xtype: 'fieldprogressbar',
            margin: '0 0 24 24',
            width: 316
        });
    }

    return Scalr.utils.Window({
        title: config['msg'],
        width: 364,
        zIndexPriority: 10,
        items: progressBar || {
            xtype: 'component',
            cls: 'x-panel-confirm-loading'
        },
        itemId: 'proccessBox',
        closeOnEsc: false
    });
};

Scalr.utils.CloneObject = function (o) {
    if (o == null || typeof(o) != 'object')
        return o;

    if(o.constructor == Array)
        return [].concat(o);

    var t = {};
    for (var i in o)
        t[i] = Scalr.utils.CloneObject(o[i]);

    return t;
};

Scalr.utils.Confirm = function (config) {
    var a = '';
    switch (config['type']) {
        case 'delete':
            a = 'Delete'; break;
        case 'reboot':
            a = 'Reboot'; break;
        case 'terminate':
            a = 'Terminate'; break;
        case 'launch':
            a = 'Launch'; break;
        case 'error':
            a = 'Retry'; break;
    }

    if (config.objects) {
        config.objects.sort();
        var r = '<span style="font-family:OpenSansSemiBold">' + config.objects.shift() + '</span>';
        if (config.objects.length)
            r = r + ' and <span data-qtip="' + config.objects.join("<br/>") + '" style="font-family:OpenSansSemiBold; border-bottom: 1px dashed #000080;">' + config.objects.length + ' others</span>';

        config.msg = config.msg.replace('%s', r);
    }

    config['ok'] = config['ok'] || a;
    config['closeOnSuccess'] = config['closeOnSuccess'] || false;
    var items = [], winConfig = {
        width: config.formWidth || 400,
        title: config.title || null,
        alignTop: config.alignTop || null,
        items: items,
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
                text: config['ok'] || 'OK',
                minWidth: 150,
                itemId: 'buttonOk',
                hidden: config['hideOk'] || false,
                cls: 'x-btn-defaultfocus',
                disabled: config['disabled'] || false,
                // TODO: add ability to run handler manually
                handler: function () {
                    var values = this.up('#box').down('#form') ? this.up('#box').down('#form').getValues() : {};

                    if (! config.closeOnSuccess)
                        this.up('#box').close();

                    if (config.success.call(config.scope || this.up('#box'), values, this.up('#box') ? this.up('#box').down('#form') : this) && config.closeOnSuccess) {
                        this.up('#box').close();
                    }
                }
            }, {
                xtype: 'button',
                text: 'Cancel',
                width: 150,
                handler: function () {
                    this.up('#box').close();
                }
            }]
        }]
    };
    if (Ext.isDefined(config.listeners)) {
        winConfig.listeners = config.listeners;
    }

    if (Ext.isDefined(config.winConfig)) {
        Ext.apply(winConfig, config.winConfig);
    }

    if (Ext.isDefined(config.type)) {
        items.push({
            xtype: 'component',
            cls: 'x-panel-confirm-message' + (config.multiline ? ' x-fieldset-separator-bottom x-panel-confirm-message-multiline' : '') + (config.form ? ' x-panel-confirm-message-form' : ''),
            data: config,
            tpl: '<div class="icon icon-{type}"></div><div class="message">{msg}</div>'
        });
    }

    if (Ext.isDefined(config.form)) {
        var form = {
            layout: 'anchor',
            itemId: 'form',
            xtype: 'form',
            border: false,
            defaults: {
                anchor: '100%'
            },
            items: config.form
        };

        if (config.formSimple)
            form['bodyCls'] = 'x-container-fieldset';

        if (config.formLayout)
            form['layout'] = config.formLayout;

        if (Ext.isDefined(config.formValidate)) {
            form.listeners = {
                validitychange: function (form, valid) {
                    if (valid)
                        this.up('#box').down('#buttonOk').enable();
                    else
                        this.up('#box').down('#buttonOk').disable();
                },
                boxready: function () {
                    if (this.form.hasInvalidField())
                        this.up('#box').down('#buttonOk').disable();
                }
            };
        }

        items.push(form);
    }

    var c = Scalr.utils.Window(winConfig);

    if (! Ext.isDefined(config.form)) {
        c.keyMap.addBinding({
            key: Ext.event.Event.ENTER,
            fn: function () {
                var btn = this.down('#buttonOk');
                btn.handler.call(btn);
            },
            scope: c
        });
    }

    return c;
};

Scalr.utils.Window = function(config) {
    config = Ext.applyIf(config, {
        xtype: 'panel',
        itemId: 'box',
        floating: true,
        modal: true,
        shadow: false,
        border: false,
        cls: 'x-panel-shadow x-panel-confirm',
        width: 400,
        autoScroll: true,
        titleAlign: 'center',
        closeOnEsc: true
    });

    var c = Ext.widget(config);
    c.keyMap = new Ext.util.KeyMap(Ext.getBody(), [{
        key: Ext.event.Event.ESC,
        fn: function (key, e) {
            if (this.closeOnEsc && e.within(c.getEl()))
                this.close();
        },
        scope: c
    }]);

    var setSize = function () {
        if (! this.isDestroyed) {
            this.maxHeight = Ext.getBody().getHeight() - 55 - 5;
            this.updateLayout();
            setPosition.call(this);
        }
    };

    var setPosition = function() {
        if (!this.hidden && !this.isDestroyed) {
            var windowSize = Ext.getBody().getSize();
            var size = this.getSize();
            var xPosition = (windowSize.width - size.width) / 2;
            var yPosition = (windowSize.height - size.height) / 2.2;

            if (this.alignTop) {
                this.setY(55);
            } else {
                if (windowSize.height >= size.height + 55 + 5) {
                    this.setY(yPosition);
                } else {
                    this.setY(55);
                }
            }

            this.setX(xPosition);
        }
    };

    c.on('boxready', setSize);
    c.on('resize', setPosition);
    c.on('show', setPosition);
    Ext.on('resize', setSize, c);
    c.on('destroy', function () {
        this.keyMap.destroy();
        Ext.un('resize', setSize, this);
    });

    if (! c.hidden) {
        c.show(config.animationTarget || null);
        c.toFront();
    }

    return c;
};

Scalr.utils.Request = function (config) {
    var currentUrl = document.location.href;

    config = Ext.apply(config, {
        callback: function (options, success, response) {
            if (!options.disableAutoHideProcessBox && options.processBox)
                options.processBox.destroy();

            if (success == true && response.responseText && (Ext.isDefined(response.status) ? response.status == 200 : true)) {
                // only for HTTP Code = 200 (for fake ajax upload files doesn't exist response status)
                //try {

                if (typeof _hsqTrackEvent != 'undefined') {
                    _hsqTrackEvent(config.url);
                }

                var result = Ext.decode(response.responseText, config.disableHandleError);

                if (result && result.success == true) {
                    if (result.successMessage)
                        Scalr.message.Success(result.successMessage);

                    if (result.warningMessage)
                        Scalr.message.Warning(result.warningMessage);

                    options.successF.call(this, result, response, options);
                    /*try {
                     options.successF.call(this, result, response, options);
                     } catch (e) {
                     Scalr.message.Error('Success handler error:' + e);
                     }*/
                    return true;
                } else {
                    if (result && result.errorMessage && !config.hideErrorMessage)
                        Scalr.message.Error(result.errorMessage);

                    options.failureF.call(this, result, response, options);
                    /*try {
                     options.failureF.call(this, result, response, options);
                     } catch (e) {
                     Scalr.message.Error('Failure handler error:' + e);
                     }*/
                    return;
                }
            }

            // TODO: check if it still needs
            /*if ((response.status == 500 || (!response.responseText && response.status != 0 && response.status != 403)) && !options.disableHandleError) {
             Scalr.utils.PostError({
             message: 'responseText is null in ajax request\nRequest:\n' + Scalr.utils.VarDump(response.request.options.params || {}) +
             '\nresponse headers: \n' + response && Ext.isFunction(response.getAllResponseHeaders) ? Scalr.utils.VarDump(response.getAllResponseHeaders()) : '' +
             '\nresponse text: \n' + response.responseText,
             url: document.location.href
             });
             }*/

            //if (!response.responseText && Ext.isDefined(response.status) ? response.status == 200 : true)

            // else nothing, global error handler used (if status code != 200)
            options.failureF.call(this, null, response, options);
        }
    });

    //config.disableFlushMessages = !!config.disableFlushMessages;
    //if (! config.disableFlushMessages)
    //	Scalr.message.Flush();

    config.disableAutoHideProcessBox = !!config.disableAutoHideProcessBox;
    config.disableHandleError = !!config.disableHandleError;
    config.hideErrorMessage = !!config.hideErrorMessage;

    config.successF = config.success || function () {};
    config.failureF = config.failure || function () {};
    config.scope = config.scope || config;
    config.params = config.params || {};

    delete config.success;
    delete config.failure;

    var pf = function (config) {
        if (config.processBox) {
            config.processBox = Scalr.utils.CreateProcessBox(config.processBox);
        }

        if (config.form) {
            config['success'] = function (form, action) {
                action.callback.call(this, action, true, action.response);
            };

            config['failure'] = function (form, action) {
                // investigate later, in extjs 4
                action.callback.call(this, action, /*(action.response.status == 200) ? true : false*/ true, action.response);
            };
            config['clientValidation'] = false;

            if (config.form.hasUpload()) {
                config.params['X-Requested-With'] = 'XMLHttpRequest';
            }

            config.form.submit(config);
        } else {
            return Ext.Ajax.request(config);
        }
    };

    if (Ext.isObject(config.confirmBox)) {
        config.confirmBox['success'] = function (params) {
            delete config.confirmBox;

            if (Ext.isDefined(params))
                Ext.applyIf(config.params, params);

            pf(config);
        };

        Scalr.Confirm(config.confirmBox);
    } else {
        return pf(config);
    }
};

Scalr.utils.UserLoadFile = function(path) {
    path = Ext.String.urlAppend(path, Ext.Object.toQueryString(Scalr.application.getExtraParams()));
    var iframeEl = Ext.getBody().createChild({
        tag: 'iframe',
        src: path,
        width: 0,
        height: 0,
        frameborder: 0
    }).on('load', function(){
        var doc, contentNode, response;
        try {
            doc = this.el.dom.contentWindow.document || this.el.dom.contentDocument;
            if (doc && doc.body) {
                // Response sent as Content-Type: text/json or text/plain. Browser will embed in a <pre> element
                // Note: The statement below tests the result of an assignment.
                if ((contentNode = doc.body.firstChild) && /pre/i.test(contentNode.tagName)) {
                    response = contentNode.textContent || contentNode.innerText;
                }
            }
            response =  Ext.decode(response);
        } catch (e) {};
        if (Ext.isObject(response) && !response.success && response.errorMessage) {
            Scalr.message.Error(response.errorMessage);
        }
    });
    //load event is not firing in Chrome when content is an attachment
    Ext.Function.defer(function(){
        iframeEl.remove();
    }, 5000);
};

Scalr.utils.VarDump = function (c) {
    var d = [];
    for (var s in c) {
        if (Ext.isString(c[s]))
            d.push(s + ': ' + c[s]);
    }

    d = d.join("\n");

    return d;
};

Scalr.utils.ThrowDebug = function (c) {
    var d = [];
    for (var s in c) {
        if (Ext.isString(c[s]))
            d.push(s + ': ' + c[s]);
    }

    d = d.join("\n");
    throw d;
};

Scalr.utils.PostException = function(e) {
    Scalr.utils.PostError({
        message: 't4 ' + e.message + "\nstack: " + e.stack + "\ntype: " + e.type + "\nname: " + e.name
    });
};

Scalr.utils.PostError = function(params) {
    params = params || {};
    if (params['file'] && params['file'] == 'runtime')
        return;

    if (params['message']) {
        if (params['message'] == 'Script error.')
            return;
    } else
        return;

    var plugins = [];
    Ext.each(navigator.plugins, function(pl) {
        plugins.push(pl.name);
    });

    params['plugins'] = plugins.join(', ');
    params['url'] = document.location.href;

    Scalr.storage.set('debug-enable', true, true);
    Scalr.Request({
        url: '/guest/xPostError',
        hideErrorMessage: true,
        disableHandleError: true,
        params: params
    });

    //Scalr.message.Warning("Whoops! Something went wrong, and we have been notified. Try reloading the page if things don't work.");
};

Scalr.utils.IsEqualValues = function (obj1, obj2) {
    for (var i in obj1) {
        if (! Ext.isDefined(obj2[i]) && obj1[i] == obj2[i])
            return false;
    }

    return true;
}

Scalr.utils.getGravatarUrl = function (emailHash, size) {
    size = size || 'small';
    var sizes = {small: 48, large: 102},
        defaultIcon = window.location.protocol + '//' + window.location.hostname + '/ui2/js/extjs-5.0/theme/images/topmenu/avatar-default-' + size + '.png';
    return emailHash ? 'https://gravatar.com/avatar/' + emailHash + '?d=' + encodeURIComponent(defaultIcon) + '&s=' + sizes[size] : defaultIcon;
}

Scalr.utils.getStringHash = function(str){
    var res = 0,
        len = str.length;
    for (var i = 0; i < len; i++) {
        res = res * 31 + str.charCodeAt(i);
    }
    return res;
}

Scalr.utils.getColorById = function(id, colorSetName) {
    var colorSets = {
            default: ['D90000', '00B3D9', 'EC8200', '839A01', 'E916E3', '0000B9', 'B08500', '006C1C', '000000', '8B02F0'],
            clouds: ['FD8D11', 'D316CF', '0069D2', 'B56F04', '0C7509', '65A615', '05A4D8', 'DD0202', '3691F3', '555555', '6262E8', 'B2B200'],
            farms: ['FD8D11', 'D316CF', '0069D2', 'B56F04', '0C7509', '65A615', '05A4D8', 'DD0202', '3691F3', '555555', '6262E8', 'B2B200', '000000', '8F20FF', '00CC00', 'B25900', '816D01', 'FF4695', '009F9F', '0000FD', '6F0AC0', '00468C', '00C695', '016B7E', 'BF8BFE', 'D19B89', 'C4A702', 'A40045', 'AAAAAA', '8B8B8B']
        },
        cloudsColorMap = {
            ec2: 0,
            gce: 1,
            idcf: 2,
            rackspacengus: 3,
            rackspacenguk: 3,
            vio: 4,
            hpcloud: 5,
            cloudstack: 6,
            openstack: 7,
            ocs: 9,
            verizon: 9,
            nebula: 10,
            cisco: 10,
            mirantis: 11
        },
        colorSet = colorSets[colorSetName || 'default'],
        color;

    if (Ext.isString(id) && id.indexOf('virtual_') === 0) {
        id = id.substring(id.length - 6);
    }

    if (cloudsColorMap[id] !== undefined) {
        color = colorSet[cloudsColorMap[id]];
    } else {
        color = colorSet[(Ext.isNumeric(id) ? id: Scalr.utils.getStringHash(id+'')) % colorSet.length];
    }

    return color;
}

Scalr.utils.beautifyOsFamily = function(family) {
    var map = {
        unknown: 'Unknown',
        ubuntu: 'Ubuntu',
        centos: 'CentOS',
        gcel: 'GCEL',
        rhel: 'RHEL',
        redhat: 'RedHat',
        oel: 'OEL',
        debian: 'Debian',
        amazon: 'Amazon Linux',
        windows: 'Windows',
        scientific: 'Scientific'
    };

    return map[family] || family;
}

Scalr.utils.getOsList = function(familyFilter, includeDeprecated) {
    var list = [];
    Ext.each(Scalr.os, function(os){
        if ((!familyFilter || familyFilter === os.family) && (os.status == 'active' || includeDeprecated)) {
            list.push(os);
        }
    });
    return list;
}

Scalr.utils.getOsFamilyList = function(includeDisabled) {
    var list = [];
    Ext.each(Scalr.os, function(os){
        if (os.status == 'active' || includeDisabled)
            Ext.Array.include(list, os.family);
    });
    return Ext.Array.map(list, function(family){
        return {id: family, name: Scalr.utils.beautifyOsFamily(family)};
    });
}


Scalr.utils.getOsById = function(osId, field) {
    var result;
    Ext.each(Scalr.os, function(os){
        if (os.id === osId) {
            result = field ? os[field] : os;
            return false;
        }
    });
    return result;
}

Scalr.utils.beautifySoftware = function(name) {
    var map = {
        apache: 'Apache',
        base: 'Base',
        lamp: 'LAMP',
        memcached: 'Memcached',
        mongodb: 'MongoDB',
        nginx: 'Nginx',
        postgresql: 'PostgreSQL',
        rabbitmq: 'RabbitMQ',
        redis: 'Redis',
        tomcat: 'Tomcat',
        vpcrouter: 'VPC Router',
        percona: 'Percona',
        mariadb: 'MariaDB',
        mysql: 'MySQL',
        chef: 'Chef',
        haproxy: 'HAProxy'
    };

    return map[name] || name;
}

Scalr.utils.beautifyBehavior = function(name, full) {
    var map = {
        mysql: 'MySQL',
        mysql2: 'MySQL 5',
        postgresql: 'PostgreSQL',
        percona: 'Percona 5',
        app: 'Apache',
        tomcat: 'Tomcat',
        haproxy: 'HAProxy',
        www: 'Nginx',
        memcached: 'Memcached',
        redis: 'Redis',
        rabbitmq: 'RabbitMQ',
        mongodb: 'MongoDB',
        mysqlproxy: 'MySQL Proxy',
        mariadb: 'MariaDB',
        cassandra: 'Cassandra',
        cf_router: full ? 'CloudFoundry Router' : 'CF router',
        cf_cloud_controller: full ? 'CloudFoundry Controller' : 'CF controller',
        cf_health_manager: full ? 'CloudFoundry Health Manager' : 'CF health mngr',
        cf_dea: full ? 'CloudFoundry DEA' : 'CF DEA',
        cf_service: full ? 'CloudFoundry Service' : 'CF service',
        chef: 'Chef',
        base: 'Base'
    };

    return map[name] || name;
}

Scalr.utils.beautifyEngineName = function (engineName) {
    var map = {
        'mysql': 'MySQL',
        'oracle-se1': 'Oracle SE One',
        'oracle-se': 'Oracle SE',
        'oracle-ee': 'Oracle EE',
        'sqlserver-ee': 'Microsoft SQL Server EE',
        'sqlserver-se': 'Microsoft SQL Server SE',
        'sqlserver-ex': 'Microsoft SQL Server EX',
        'sqlserver-web': 'Microsoft SQL Server WEB',
        'postgres': 'PostgreSQL',
        'aurora': 'Amazon Aurora',
        'mariadb': 'MariaDB'
    };

    var fullEngineName = map[engineName];

    return !Ext.isEmpty(fullEngineName) ? fullEngineName : engineName;
};

//checks resource/permission against current context acl
Scalr.utils.isAllowed = function(resource, permission){
    if (Scalr.user['type'] === 'AccountOwner') return true;
    if (Scalr.user['type'] === 'ScalrAdmin') return true;
    var value = Scalr.acl[resource],
        access = value !== undefined;
    if (permission !== undefined && access) {
        access = Ext.isObject(value) && value.permissions !== undefined && value.permissions[permission] !== undefined;
    }
    return access;
}

Scalr.utils.getAclResourceMode = function(resource){
    var value = Scalr.acl[resource],
        mode;
    if (value !== undefined) {
        mode = value.mode;
    }
    return mode;

}

Scalr.utils.canManageAcl = function(){
    return Scalr.user['type'] === 'AccountOwner' || Scalr.user['type'] === 'AccountAdmin' || Scalr.user['type'] === 'AccountSuperAdmin';
}

Scalr.utils.isAdmin = function(){
    return Scalr.user['type'] === 'ScalrAdmin' || Scalr.user['type'] === 'FinAdmin';
}

Scalr.utils.isOpenstack = function(platform, pureOnly) {
    var list = ['openstack', 'ocs', 'nebula', 'mirantis', 'vio', 'verizon', 'cisco', 'hpcloud'];
    if (!pureOnly) {
        list.push('rackspacengus', 'rackspacenguk');
    }
    return Ext.Array.contains(list, platform);
}

Scalr.utils.isCloudstack = function(platform) {
    var list = ['cloudstack', 'idcf'];
    return Ext.Array.contains(list, platform);
}

Scalr.utils.isPlatformEnabled = function(platform) {
    return Scalr.platforms[platform] !== undefined && Scalr.platforms[platform].enabled;
}

Scalr.utils.getPlatformConfigValue = function(platform, key) {
    return Scalr.platforms[platform] !== undefined && Scalr.platforms[platform].config ? Scalr.platforms[platform].config[key] : undefined;
}

Scalr.utils.getPlatformName = function(platform, fix) {
    var name = Scalr.platforms[platform] !== undefined ? Scalr.platforms[platform].name : platform;

    if (fix === true) {
        if (platform.indexOf('rackspacenguk') === 0) {
            name = '<span class="small">' + name.replace(' ', '<br/>') + '</span>';
        } else if (platform === 'rackspace') {
            name = '<span class="small">' + name.replace(' ', '<br/>') + '</span>';
        } else if (platform === 'ocs') {
            name = '<span class="small">CloudScaling<br />OCS</span>';
        } else if (platform === 'nebula') {
            name = '<span class="small">Nebula<br />Openstack</span>';
        } else if (platform === 'mirantis') {
            name = '<span class="small">Mirantis<br />Openstack</span>';
        } else if (platform === 'vio') {
            name = '<span class="small">VMWare<br />VIO</span>';
        } else if (platform === 'cisco') {
            name = '<span class="small">Cisco<br />Metapod</span>';
        } else if (platform === 'hpcloud') {
            name = '<span class="small">HP Helion</span>';
        } else if (platform === 'verizon') {
            name = '<span class="small">Verizon Cloud</span>';
        } else if (platform === 'gce') {
            name = '<span class="small">' + name.replace(/(Google)/i, '$1<br/>') + '</span>';
        }
    }
    return name;
}

Scalr.utils.loadInstanceTypes = function(platform, cloudLocation, callback) {
    Scalr.cachedRequest.load(
        {
            url: '/platforms/xGetInstanceTypes',
            params: {
                platform: platform,
                cloudLocation: cloudLocation
            }
        },
        function(data, status){
            callback(data, status);
        },
        this,
        undefined,
        {type: 'action', msg: 'Loading instance types...'}
    );
}

Scalr.utils.loadCloudLocations = function(platform, callback, progressBox) {
    if (!Scalr.platforms[platform]) callback(false);

    Scalr.cachedRequest.load(
        {
            url: '/platforms/xGetLocations',
            params: {platforms: Ext.encode([platform])}
        },
        function(data, status, cacheId){
            if (status && data.locations[platform] !== undefined) {
                Scalr.platforms[platform].locations = data.locations[platform];
                callback(Ext.apply({}, Scalr.platforms[platform].locations));
            } else {
                callback(false);
            }
        },
        this,
        0,
        progressBox
    );
};

Scalr.utils.getMinStorageSizeByIops = function (iops) {
    // maximum IOPS:GB ratio has been increased from 10:1 to 30:1 for a volumes with storage size <= 133 GB
    var ebsMaxIopsSizeRatio = iops <= 4000
        ? Scalr.constants.ebsMaxIopsSizeRatioIncreased
        : Scalr.constants.ebsMaxIopsSizeRatio;
    var minSize = Math.ceil(iops/ebsMaxIopsSizeRatio);

    return Scalr.constants.ebsIo1MinStorageSize > minSize ? Scalr.constants.ebsIo1MinStorageSize : minSize;
}

Scalr.utils.getRoleCls = function(context) {
    var b = context['behaviors'] || [],
        behaviors = [
            "cf_cchm", "cf_dea", "cf_router", "cf_service",
            "rabbitmq", "www",
            "app", "tomcat", 'haproxy',
            "mysqlproxy",
            "memcached",
            "cassandra", "mysql", "mysql2", "percona", "postgresql", "redis", "mongodb", 'mariadb'
        ];

    if (b) {
        if (Ext.isString(b)) {
            b = b.split(',');
        }
        //Handle CF all-in-one role
        if (Ext.Array.difference(['cf_router', 'cf_cloud_controller', 'cf_health_manager', 'cf_dea'], b).length === 0) {
            return 'cf-all-in-one';
        }
        //Handle CF CCHM role
        if (Ext.Array.contains(b, 'cf_cloud_controller') || Ext.Array.contains(b, 'cf_health_manager')) {
            return 'cf-cchm';
        }

        for (var i=0, len=b.length; i < len; i++) {
            for (var k = 0; k < behaviors.length; k++ ) {
                if (behaviors[k] == b[i]) {
                    return b[i].replace('_', '-');
                }
            }
        }
    }
    return 'base';
}

Scalr.utils.getRandomString = function(len) {
    var text = "";
    var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

    for (var i = 0; i < len; i++)
        text += possible.charAt(Math.floor(Math.random() * possible.length));

    return text;
};

Scalr.utils.Quarters = {

    defaultDays: ['01-01', '04-01', '07-01', '10-01'],

    getDate: function(date, skipClearTime) {
        var res, h = 0, m = 0, s = 0;
        if (Ext.isArray(date)) {
            res = new Date(date[0], date[1], date[2], 0, 0, 0, 0);
        } else if (Ext.isDate(date)) {
            res = date;
        } else if (Ext.isString(date)) {
            var splitDate = date.split(' ');
            splitDate.unshift.apply(splitDate, splitDate[0].split('-'));
            splitDate.splice(3, 1);
            if (splitDate.length > 3) {
                splitDate.push.apply(splitDate, splitDate[3].split(':'));
                splitDate.splice(3, 1);
                h = splitDate[3]*1;
            }
            if (splitDate.length > 4) {
                m = splitDate[4]*1;
            }
            if (splitDate.length > 5) {
                s = splitDate[5]*1;
            }
            res = new Date(splitDate[0]*1, splitDate[1]*1-1, splitDate[2], h, m, s, 0);
        } else {
            res = new Date();
            res = Ext.Date.add(res, Ext.Date.MINUTE, res.getTimezoneOffset());
        }
        if (!skipClearTime) {
            res = Ext.Date.clearTime(res);
        }
        return res;
    },

    getNYSettings: function() {
        var days = this.days || this.defaultDays,
            result = [],
            ny = 0;
        for (var i=0; i<4; i++) {
            result[i] = ny;
            if (days[i].substr(0, 2) > days[(i + 1) % 4].substr(0, 2)) {
                ny = 1;
            }
        }
        return {
            q: result,
            sum: Ext.Array.sum(result)
        };
    },

    getPeriodForQuarter: function(quarter, year){
        var ny = this.getNYSettings(),
            days = this.days || this.defaultDays,
            add, result;

        add = ny['sum'] >= 2 ? -1 : 0;

        result = {
            startDate: this.getDate((year + add + ny.q[quarter - 1]) + '-' + days[quarter - 1]),
            endDate: this.getDate((year + add + (ny.q[quarter - 1] || ny.q[quarter % 4] || (days[quarter - 1] > days[quarter % 4] ? 1 : 0))) + '-' + days[quarter % 4]),
            quarter: quarter,
            year: year
        };
        result['endDate'] = Ext.Date.add(result['endDate'], Ext.Date.DAY, -1);
        result['shortTitle'] = 'Q' + quarter + ' ' + year;
        result['title'] = 'Q' + quarter + ' ' + year + ' (' + Ext.Date.format(result['startDate'], 'M j') + ' &ndash; ' + Ext.Date.format(result['endDate'], 'M j') + ')';

        return result;
    },

    getQuarterForDate: function(date) {
        var result = null,
            days = this.days || this.defaultDays,
            p, next, y;
        date = date || this.getDate();
        p = Ext.Date.format(date, 'm-d');

        for (var i=0; i<4; i++) {
            next = (i + 1) % 4;
            y = days[i] <= p && p <= '12-31' ? '0' : '1';
            if (days[i] < days[next]) {
                if (days[i] <= p && p < days[next]) {
                    result = i + 1;
                    break;
                }
            } else {
                if (('0' + days[i]) <= (y + p) && (y + p) < ('1' + days[next])) {
                    result = i + 1;
                    break;
                }
            }

        }
        return result;
    },

    getPeriodForDate: function(date, shift) {
        var quarter = this.getQuarterForDate(date),
            period = this.getPeriodForQuarter(quarter, Ext.Date.format(date, 'Y')*1);

        if (period['endDate'] < date) {
            period = this.getPeriodForQuarter(quarter, Ext.Date.format(date, 'Y')*1 + 1);
        } else if (period['startDate'] > date) {
            period = this.getPeriodForQuarter(quarter, Ext.Date.format(date, 'Y')*1 - 1);
        }

        if (shift < 0) {
            period = this.getPeriodForDate(Ext.Date.add(period['startDate'], Ext.Date.DAY, -1));
        } else if (shift > 0) {
            period = this.getPeriodForDate(Ext.Date.add(period['endDate'], Ext.Date.DAY, 1));
        }

        return period;
    }
};

Scalr.utils.saveSpecialToken = function(specialToken) {
    Ext.Ajax.setExtraParams(Ext.apply(Ext.Ajax.getExtraParams(), {'X-Requested-Token' : Scalr.flags.specialToken = specialToken }));
};

Scalr.utils.ConfirmPassword = function(cb) {
    return Scalr.Confirm({
        closeOnSuccess: true,
        formWidth: 400,
        formValidate: true,
        form: [{
            xtype: 'container',
            cls: 'x-container-fieldset x-fieldset-no-bottom-padding',
            layout: 'anchor',
            defaults: {
                anchor: '100%'
            },
            items: [{
                xtype: 'component',
                cls: 'x-fieldset-subheader',
                style: 'text-align:center',
                html: 'Please enter your current password',
            },{
                xtype: 'component',
                style: 'text-align:center',
                html: Scalr.user['userName'],
                margin: '0 0 12 0'
            },{
                xtype: 'textfield',
                inputType: 'password',
                name: 'currentPassword',
                emptyText: 'Password',
                allowBlank: false,
                listeners: {
                    afterrender: function() {
                        Ext.defer(this.focus, 100, this);
                    }
                }
            }]
        }],
        winConfig: {
            onFailure: function(errors) {
                if (Ext.Object.getSize(errors) == 1 && errors['currentPassword']) {
                    var field = this.down('[name="currentPassword"]');
                    field.markInvalid(errors['currentPassword']);
                    Ext.defer(field.focus, 100, field);
                } else {
                    this.close();
                }
            }
        },
        success: function(values, form) {
            cb(values['currentPassword']);
            return false;
        }
    });
};

Scalr.utils.getModel = function(config) {
    return Ext.define(null, Ext.apply({
        extend: 'Ext.data.ModelWithInternalId',
        proxy: 'object'
    }, config));
};

Scalr.utils.authWindow = function(flags) {
    var submitOnEnter = function(field, e) {
        if (e.getKey() == e.ENTER) {
            field.up('form').down('#buttonSubmit').handler();
        }
    };

    return Scalr.utils.Window({
        xtype: 'form',
        closeOnEsc: false,
        closeAction: 'hide',
        hidden: true,
        width: 500,
        layout: 'anchor',
        fieldDefaults: {
            anchor: '100%',
            labelWidth: 80
        },
        items: {
            xtype: 'fieldset',
            title: '<img src="' + Ext.BLANK_IMAGE_URL + '" class="x-icon-scalr" style="vertical-align: middle">&nbsp;&nbsp;Welcome',
            cls: 'x-fieldset-separator-none x-fieldset-no-bottom-padding',
            items: [{
                xtype: 'displayfield',
                value: 'Your session has timed out. Please log back in.',
                hidden: true,
                itemId: 'warningReLogin'
            }, {
                xtype: 'textfield',
                name: 'scalrLogin',
                fieldLabel: flags['authMode'] == 'ldap' ? 'Login' : 'Email',
                allowBlank: false,
                listeners: {
                    afterrender: function() {
                        this.inputEl.destroy();
                        this.inputEl = this.inputWrap.appendChild('textfield-user-login-inputEl');
                    },
                    specialkey: submitOnEnter
                }
            }, {
                xtype: 'textfield',
                inputType: 'password',
                name: 'scalrPass',
                fieldLabel: 'Password',
                allowBlank: false,
                listeners: {
                    afterrender: function() {
                        this.inputEl.destroy();
                        this.inputEl = this.inputWrap.appendChild('textfield-user-password-inputEl');
                    },
                    specialkey: submitOnEnter
                }
            }, {
                xtype: 'combobox',
                store: {
                    fields: [ 'id', 'name', 'org', 'dtadded', 'owner' ],
                    proxy: 'object'
                },
                queryMode: 'local',
                valueField: 'id',
                displayField: 'name',
                itemId: 'accountId',
                name: 'accountId',
                hidden: true,
                disabled: true,
                fieldLabel: 'Account',
                editable: false,
                allowBlank: false,
                listConfig: {
                    cls: 'x-boundlist-alt',
                    getInnerTpl: function () {
                        return '{name}<tpl if="org"> [{org}]</tpl><tpl if="owner"> [Owner: {owner}]</tpl> [Created on {dtadded}]'
                    }
                },
                listeners: {
                    specialkey: submitOnEnter
                }
            }, {
                xtype: 'container',
                layout: 'hbox',
                itemId: 'tfaGglCode',
                hidden: true,
                disabled: true,
                items: [{
                    xtype: 'textfield',
                    fieldLabel: '2FA Code',
                    name: 'tfaGglCode',
                    allowBlank: false,
                    flex: 1,
                    listeners: {
                        specialkey: submitOnEnter
                    }
                }, {
                    xtype: 'buttonfield',
                    margin: '0 0 0 6',
                    width: 100,
                    enableToggle: true,
                    text: 'Reset 2FA',
                    name: 'tfaGglReset',
                    toggleHandler: function(el, state) {
                        if (state)
                            Scalr.message.InfoTip('Please enter reset code to disable two-factor authentication', this.prev().el);

                        this.prev().setFieldLabel(state ? 'Reset code' : '2FA Code');
                    }
                }]
            }, {
                xtype: 'checkbox',
                name: 'scalrKeepSession',
                checked: true,
                hidden: flags['authMode'] == 'ldap',
                boxLabel: 'Remember me'
            }, {
                xtype: 'recaptchafield',
                name: 'scalrCaptcha',
                hidden: true,
                disabled: true,
                fieldLabel: '&nbsp;',
                listeners: {
                    specialkey: submitOnEnter
                }
            }, {
                xtype: 'hiddenfield',
                name: 'userTimezone',
                value: (new Date()).getTimezoneOffset()
            }]
        },

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
                text: 'Login',
                itemId: 'buttonSubmit',
                handler: function () {
                    var form = this.up('form');
                    if (form.getForm().isValid()) {
                        Scalr.Request({
                            processBox: {
                                type: 'action'
                            },
                            scope: this,
                            form: form.getForm(),
                            url: '/guest/xLogin',
                            success: function (data) {
                                form.hide();

                                if (data.specialToken) {
                                    Scalr.utils.saveSpecialToken(data.specialToken);
                                }

                                if (Scalr.user.userId) {
                                    if (data.userId == Scalr.user.userId) {
                                        if (Scalr.state.userNeedRefreshPageAfter) {
                                            window.onhashchange(true);
                                        } else if (Scalr.state.userNeedRefreshStoreAfter) {
                                            if (Scalr.application.layout.activeItem) {
                                                Scalr.application.layout.activeItem.fireEvent('reloadstore');
                                            }
                                        }
                                    } else {
                                        Scalr.application.updateContext(null, false, {
                                            'X-Scalr-Userid': data.userId
                                        });
                                    }
                                } else {
                                    // set default environment
                                    Scalr.application.updateContext(null, false, {
                                        'X-Scalr-Envid': Scalr.storage.get('system-environment-id')
                                    });
                                }

                                Scalr.state.userNeedLogin = false;
                                Scalr.state.userNeedRefreshPageAfter = false;
                                Scalr.state.userNeedRefreshStoreAfter = false;

                                form.down('#accountId').hide().disable().reset();
                                form.down('#tfaGglCode').hide().disable().child('[name="tfaGglCode"]').reset();
                                form.down('[name="scalrLogin"]').reset();
                                form.down('[name="scalrPass"]').reset();
                                form.down('[name="scalrCaptcha"]').hide().disable();
                                form.down('#warningReLogin').hide();
                            },
                            failure: function (data) {
                                if (data) {
                                    if (data['loginattempts'] && data['loginattempts'] > 2) {
                                        form.down('[name="scalrCaptcha"]').show().enable().reset();
                                    } else {
                                        form.down('[name="scalrCaptcha"]').hide().disable();
                                    }

                                    if (data['scalrCaptchaError']) {
                                        console.warn('Captcha error: ' + data['scalrCaptchaError']);
                                    }

                                    if (data['accounts']) {
                                        var field = this.up('form').down('#accountId');
                                        field.store.loadData(data['accounts']);
                                        field.reset();
                                        field.show();
                                        field.enable();
                                    }

                                    if (data['tfaGgl']) {
                                        form.down('#tfaGglCode').enable().show().child('[name="tfaGglCode"]').focus();
                                    }
                                }
                            }
                        });
                    }
                }
            }, {
                xtype: 'button',
                text: 'Forgot password?',
                hidden: flags['authMode'] == 'ldap',
                handler: function () {
                    Scalr.Request({
                        confirmBox: {
                            title: 'Recover password',
                            ok: 'Reset my password',
                            formWidth: 396,
                            formValidate: true,
                            form: [{
                                xtype: 'fieldset',
                                cls: 'x-fieldset-separator-none',
                                defaults: {
                                    anchor: '100%'
                                },
                                items: [{
                                    xtype: 'textfield',
                                    fieldLabel: 'E-mail',
                                    labelWidth: 45,
                                    anchor: '100%',
                                    vtype: 'email',
                                    name: 'email',
                                    value: this.up('form').down('[name=scalrLogin]').getValue(),
                                    allowBlank: false
                                }, {
                                    xtype: 'recaptchafield',
                                    labelWidth: 45,
                                    fieldLabel: '&nbsp;',
                                    name: 'scalrCaptcha'
                                }]
                            }]
                        },
                        processBox: {
                            type: 'action',
                            msg: 'Sending email ...'
                        },
                        url: '/guest/xResetPassword'
                    });
                }
            }]
        }],
        listeners: {
            boxready: function () {
                if (Ext.get('body-login-container'))
                    Ext.get('body-login-container').remove();
            },
            show: function () {
                var o, me = this;

                Scalr.state.pageSuspend = true;
                if (window.location.search && (o = Ext.Object.fromQueryString(window.location.search)) && Ext.isDefined(o['resetPasswordHash'])) {
                    Scalr.Request({
                        confirmBox: o['resetPasswordHash'] ? null : {
                            title: 'Password Reset',
                            ok: 'Continue',
                            formValidate: true,
                            form: [{
                                xtype: 'fieldset',
                                cls: 'x-fieldset-separator-none',
                                defaults: {
                                    labelWidth: 90,
                                    anchor: '100%'
                                },
                                items: [{
                                    xtype: 'textfield',
                                    fieldLabel: 'Reset code',
                                    name: 'hash',
                                    allowBlank: false
                                }]
                            }]
                        },
                        processBox: {
                            type: 'action'
                        },
                        params: o['resetPasswordHash'] ? {
                            hash: o['resetPasswordHash']
                        } : {},
                        url: '/guest/xUpdatePasswordValidate',
                        success: function(data) {
                            Scalr.Request({
                                confirmBox: {
                                    title: 'New password',
                                    ok: 'Update my password',
                                    formWidth: 450,
                                    formValidate: true,
                                    form: [{
                                        xtype: 'fieldset',
                                        cls: 'x-fieldset-separator-none',
                                        defaults: {
                                            labelWidth: 110,
                                            anchor: '100%'
                                        },
                                        items: [{
                                            xtype: 'displayfield',
                                            fieldLabel: 'Email',
                                            value: data['email']
                                        }, {
                                            xtype: 'scalrpasswordfield',
                                            fieldLabel: 'New password',
                                            name: 'password',
                                            itemId: 'password',
                                            vtype: 'password',
                                            minPasswordLengthAdmin: data['isAdmin'],
                                            otherPassField: 'cpassword',
                                            allowBlank: false
                                        }, {
                                            xtype: 'scalrpasswordfield',
                                            fieldLabel: 'Confirm',
                                            itemId: 'cpassword',
                                            submitValue: false,
                                            allowBlank: false,
                                            minPasswordLengthAdmin: data['isAdmin'],
                                            vtype: 'password',
                                            otherPassField: 'password'
                                        }]
                                    }]
                                },
                                processBox: {
                                    type: 'action',
                                    msg: 'Sending email ...'
                                },
                                url: '/guest/xUpdatePassword',
                                params: {
                                    hash: data['hash']
                                },
                                success: function(data) {
                                    var o = Ext.Object.fromQueryString(window.location.search), s;
                                    delete o['resetPasswordHash'];
                                    s = Ext.Object.toQueryString(o);
                                    s = s ? '?' + s : '';

                                    history.replaceState(null, null, document.location.pathname + s + document.location.hash);

                                    me.down('[name="scalrPass"]').setValue('');
                                    Scalr.message.InfoTip(data['message'], me.down('[name="scalrLogin"]').setValue(data['email']).focus());
                                }
                            });
                        }
                    });
                } else {
                    if (Scalr.state.userNeedLogin)
                        me.down('#warningReLogin').show();

                    me.down('[name="scalrLogin"]').focus();
                }
            },
            hide: function() {
                Scalr.state.pageSuspend = false;
            }
        }
    });
};

Scalr.utils.timeoutHandler = {
    defaultTimeout: 60000, // default delay between checks, default = 60000
    timeoutRun: 60000, // delay before next check (changeable), default = 60000
    timeoutRequest: 5000, // maximum time execution for ajax check request, default = 5000

    schedule: function (runNow) {
        var me = this;
        clearTimeout(me.timeoutId);
        if (runNow) {
            me.run();
        } else {
            me.timeoutId = Ext.Function.defer(me.run, me.timeoutRun, me);
        }
    },

    run: function() {
        var checkAjax = Scalr.user.userId && !Scalr.state.userNeedLogin, params = {}, post = false;

        if (checkAjax) {
            params = Scalr.utils.CloneObject(Scalr.user);

            var hash = Scalr.storage.hash(), time = ((new Date()).getTime() / 1000).toFixed(0);
            if (hash != Scalr.storage.get('system-hash')) {
                Scalr.debug.dump++;
                Scalr.storage.set('system-time', time);
                Scalr.storage.set('system-hash', hash);
                params['uiStorage'] = Ext.encode({
                    dump: Scalr.storage.dump(true),
                    time: time
                });
                post = true;
                console.log('update storage data on server');
            } else {
                Scalr.debug.pass++;
                delete params['uiStorage'];
            }
        }

        Ext.Ajax.request({
            url: checkAjax ? '/guest/xPerpetuumMobile' : '/ui2/js/connection.js',
            params: params,
            method: post ? 'POST' : 'GET',
            timeout: this.timeoutRequest,
            scope: this,
            hideErrorMessage: true,
            callback: function (options, success, response) {
                if (success) {
                    try {
                        response = Ext.decode(response.responseText);

                        if (response.success != true) {
                            if (response.success == false && response.errorMessage != '') {
                                Scalr.message.Error(response.errorMessage);
                            } else if (response && response.s == 'Connected') {
                                // connection.js
                            } else {
                                throw 'False';
                            }
                        }

                        this.hidePopup();
                        this.timeoutRun = this.defaultTimeout;

                        if (! response.isAuthenticated) {
                            Scalr.state.userNeedLogin = true;
                            Scalr.utils.authWindow.show();
                        } else if (! response.equal) {
                            document.location.reload();
                            return;
                        } else {
                            if (Scalr.state.pageSuspend) {
                                Scalr.state.pageSuspend = false;
                                Scalr.event.fireEvent('connectionup');

                                if (Scalr.state.userNeedRefreshPageAfter) {
                                    window.onhashchange(true);
                                } else if (Scalr.state.userNeedRefreshStoreAfter) {
                                    if (Scalr.application.layout.activeItem) {
                                        Scalr.application.layout.activeItem.fireEvent('reloadstore');
                                    }
                                }

                                Scalr.state.userNeedRefreshPageAfter = false;
                                Scalr.state.userNeedRefreshStoreAfter = false;
                            }

                            Scalr.event.fireEvent('update', 'lifeCycle', response);
                        }
                    } catch (e) {
                        console.log(e);
                    }
                }

                if (success === true || response.aborted === true || response.timedout === true) {
                    if (this.hasPopup())
                        this.showPopup(); // update timer

                } else {
                    // lock page
                    if (! Scalr.state.pageSuspend) {
                        Scalr.state.pageSuspend = true;
                        Scalr.event.fireEvent('connectiondown');
                    }

                    this.timeoutRun += 6000;
                    if (this.timeoutRun > 60000)
                        this.timeoutRun = 60000;

                    if (! this.hasPopup())
                        this.timeoutRun = 5000;

                    this.showPopup();
                }

                this.schedule();
            }
        });
    },

    hasPopup: function() {
        return !! this.popup;
    },

    showPopup: function() {
        var me = this;

        if (! me.popup) {
            me.popup = Scalr.utils.Window({
                xtype: 'form',
                closeOnEsc: false,
                width: 500,
                layout: 'anchor',
                alignTop: true,
                items: [{
                    xtype: 'fieldset',
                    items: [{
                        xtype: 'container',
                        layout: 'hbox',
                        items: [{
                            xtype: 'displayfield',
                            flex: 1,
                            itemId: 'timer',
                            value: this.timeoutRun/1000,
                            renderer: function(value) {
                                return value ? 'Not connected to Scalr. Connecting in ' + value + ' seconds' : 'Not connected. Trying now';
                            }
                        }, {
                            xtype: 'button',
                            text: 'Try now',
                            handler: function() {
                                this.prev().setValue(0);
                                me.schedule(true);
                            }
                        }]
                    }]
                }],
                createTimer: function (value) {
                    var me = this, field = me.down('#timer');
                    clearInterval(me.timerId);
                    field.setValue(value);

                    this.timerId = setInterval(function() {
                        var value = field.getValue() - 1;
                        if (value < 0)
                            value = 0;

                        field.setValue(value);
                    }, 1000);
                },
                listeners: {
                    destroy: function() {
                        clearInterval(this.timerId);
                    }
                }
            });
            me.popup.show();
        }

        me.popup.createTimer(me.timeoutRun / 1000);
    },

    hidePopup: function() {
        var me = this;
        if (me.popup) {
            me.popup.destroy();
            me.popup = null;
        }
    }
};

Scalr.utils.getUrlPrefix = function(scope, envId) {
    var prefix;
    switch (scope || Scalr.scope) {
        case 'scalr':
            prefix = '/admin';
            break;
        case 'account':
            prefix = '/account';
            break;
        default:
            if (envId) {
                prefix = '?environmentId=' + envId;
            } else {
                prefix = '';
            }
            break;
    }
    return prefix;
};

// added with ExtJS 5 release at March 2015. Remove after 6 months
Scalr.utils.fillFavorites = function() {
    if (Scalr.storage.get('system-favorites')) {
        // we have old favorites, convert them before use
        var result = [],
            cache = {
                '#/logs/api': { text: 'API Log', href:  '#/logs/api', stateId: 'grid-logs-api-view' },
                '#/logs/events': { text: 'Event Log', href: '#/logs/events', stateId: 'grid-logs-events-view' },
                '#/logs/scripting': { text: 'Scripting Log', href: '#/logs/scripting', stateId: 'grid-logs-scripting-view' },
                '#/logs/system': { text: 'System Log', href: '#/logs/system', stateId: 'grid-logs-system-view' },
                '#/roles/manager': { text: 'Roles', href: '#/roles', stateId: 'grid-roles-manager' },
                '#/farms/view': { text: 'Farms', href: '#/farms', stateId: 'grid-farms-view' },
                '#/dnszones/view': { text: 'DNS Zones', href: '#/dnszones', stateId: 'grid-dnszones-view' },
                '#/schedulertasks/view': { text: 'Tasks Scheduler', href: '#/schedulertasks', stateId: 'grid-schedulertasks-view' },
                '#/services/ssl/certificates/view': { text: 'SSL Certificates', href: '#/services/ssl/certificates', stateId: 'grid-services-ssl-certificates-view' },
                '#/scaling/metrics/view': { text: 'Custom Scaling Metrics', href: '#/scaling/metrics', stateId: 'grid-scaling-metrics-view' },
                '#/bundletasks/view': { text: 'Bundle Tasks', href: '#/bundletasks', stateId: 'grid-bundletasks-view' },
                '#/sshkeys/view': { text: 'SSH Keys', href: '#/sshkeys', stateId: 'grid-sshkeys-view' },
                '#/scripts/view': { text: 'Scripts', href: '#/scripts', stateId: 'grid-scripts-view' },
                '#/servers/view': { text: 'Servers', href: '#/servers', stateId: 'grid-servers-view' },
                '#/scripts/events/view': { text: 'Custom Events', href: '#/scripts/events', stateId: 'grid-scripts-events-view' },
                '#/images/view': { text: 'Images', href: '#/images', stateId: 'grid-images-view' },
                '#/db/backups': { text: 'DB Backups', href: '#/db/backups', stateId: 'grid-db-backups-view' },
                '#/services/configurations/presets/view': { text: 'Server Config Presets', href: '#/services/configurations/presets', stateId: 'grid-services-configurations-presets-view' },
                '#/services/apache/vhosts/view': { text: 'Apache Virtual Hosts', href: '#/services/apache/vhosts', stateId: 'grid-apache-vhosts-view' },

                '#/tools/aws/ec2/ebs/snapshots': { text: 'EBS Snapshots', href: '#/tools/aws/ec2/ebs/snapshots', stateId: 'grid-tools-aws-ec2-ebs-snapshots' },
                '#/tools/aws/ec2/ebs/volumes': { text: 'EBS volumes', href: '#/tools/aws/ec2/ebs/volumes', stateId: 'grid-tools-aws-ec2-ebs-volumes' },
                '#/tools/aws/ec2/elb': { text: 'AWS ELB', href: '#/tools/aws/ec2/elb', stateId: 'grid-tools-aws-ec2-elb-view' },
                '#/tools/aws/ec2/eips': { text: 'AWS EIPs', href: '#/tools/aws/ec2/eips', stateId: 'grid-tools-aws-ec2-eips-view' },
                '#/tools/aws/rds/instances': { text: 'RDS instances', href: '#/tools/aws/rds/instances', stateId: 'grid-tools-aws-rds-instances-view' },
                '#/tools/aws/route53': { text: 'Route 53', href: '#/tools/aws/route53', stateId: 'grid-tools-aws-route53-view' },
                '#/tools/rackspace/limits': { text: 'Rackspace Limits', href: '#/tools/rackspace/limits', stateId: 'grid-tools-rackspace-limits' },
                '#/tools/gce/disks': { text: 'GCE Persistent disks', href: '#/tools/gce/disks', stateId: 'grid-tools-gce-disks-view' },
                '#/tools/gce/addresses': { text: 'GCE Static IPs', href: '#/tools/gce/addresses', stateId: 'grid-tools-gce-addresses-view' },
                '#/tools/gce/snapshots': { text: 'GCE Snapshots', href: '#/tools/gce/snapshots', stateId: 'grid-tools-gce-snapshots-view' }
            };

        Ext.each(Scalr.storage.get('system-favorites'), function(item) {
            if (item.href && item.href in cache) {
                result.push(cache[item.href]);
            }
        });

        Scalr.storage.set('system-favorites-environment', result);
        Scalr.storage.clear('system-favorites');
        Scalr.storage.clear('system-favorites-created');
    }
};

Scalr.utils.getFavorites = function(scope) {
    if (Scalr.user.type == 'FinAdmin') {
        return [{
            href: '#/admin/analytics/dashboard',
            stateId: 'panel-admin-analytics',
            text: 'Cost Analytics'
        }];
    }

    var environment = [], account = [];
    if (Scalr.isAllowed('FARMS') || Scalr.isAllowed('TEAM_FARMS') || Scalr.isAllowed('OWN_FARMS'))
        environment.push({
            href: '#/farms',
            stateId: 'grid-farms-view',
            text: 'Farms'
        });

    if (Scalr.isAllowed('ROLES_ENVIRONMENT'))
        environment.push({
            href: '#/roles',
            stateId: 'grid-roles-manager',
            text: 'Roles'
        });

    if (Scalr.isAllowed('IMAGES_ENVIRONMENT'))
        environment.push({
            href: '#/images',
            stateId: 'grid-images-view',
            text: 'Images'
        });

    if (Scalr.isAllowed('FARMS') || Scalr.isAllowed('OWN_FARMS') || Scalr.isAllowed('TEAM_FARMS') || Scalr.isAllowed('IMAGES_ENVIRONMENT', 'build') || Scalr.isAllowed('IMAGES_ENVIRONMENT', 'import'))
        environment.push({
            href: '#/servers',
            stateId: 'grid-servers-view',
            text: 'Servers'
        });

    if (Scalr.isAllowed('SCRIPTS_ENVIRONMENT'))
        environment.push({
            href: '#/scripts',
            stateId: 'grid-scripts-view',
            text: 'Scripts'
        });

    if (Scalr.isAllowed('LOGS_SYSTEM_LOGS'))
        environment.push({
            href: '#/logs/system',
            stateId: 'grid-logs-system-view',
            text: 'System Log'
        });

    if (Scalr.isAllowed('LOGS_SCRIPTING_LOGS'))
        environment.push({
            href: '#/logs/scripting',
            stateId: 'grid-logs-scripting-view',
            text: 'Scripting Log'
        });

    // account scope
    if (Scalr.utils.canManageAcl() || Scalr.isAllowed('ENV_CLOUDS_ENVIRONMENT'))
        account.push({
            href: '#/account/environments',
            stateId: 'grid-account-environments',
            text: 'Environments'
        });

    if (Scalr.utils.canManageAcl())
        account.push({
            href: '#/account/teams',
            stateId: 'grid-account-teams',
            text: 'Teams'
        }, {
            href: '#/account/users',
            stateId: 'grid-account-users',
            text: 'Users'
        }, {
            href: '#/account/acl',
            stateId: 'grid-account-acl',
            text: 'ACL'
        });

    if (Scalr.isAllowed('SCRIPTS_ACCOUNT'))
        account.push({
            href: '#/account/scripts',
            stateId: 'grid-scripts-view',
            text: 'Scripts'
        });

    var predefined = {
        environment: environment,
        account: account,
        scalr: [{
            href: '#/admin/accounts',
            stateId: 'grid-admin-accounts-view',
            text: 'Accounts'
        }, {
            href: '#/admin/users',
            stateId: 'grid-admin-users-view',
            text: 'Admins'
        }, {
            href: '#/admin/roles',
            stateId: 'grid-roles-manager',
            text: 'Roles'
        }, {
            href: '#/admin/images',
            stateId: 'grid-images-view',
            text: 'Images'
        }, {
            href: '#/admin/scripts',
            stateId: 'grid-scripts-view',
            text: 'Scripts'
        }, {
            href: '#/admin/analytics/dashboard',
            stateId: 'panel-admin-analytics',
            text: 'Cost Analytics'
        }, {
            href: '#/admin/webhooks/endpoints',
            stateId: 'grid-webhooks-configs',
            text: 'Webhooks'
        }]
    };

    var result = Scalr.storage.get('system-favorites-' + scope);
    if (!result) {
        result = predefined[scope] || [];
        if (scope == 'scalr') {
            result.unshift({
                href: '#/admin/dashboard',
                stateId: 'panel-admin-dashboard',
                text: 'Admin Dashboard'
            });
        } else if (scope == 'account') {
            result.unshift({
                href: '#/account/dashboard',
                stateId: 'panel-account-dashboard',
                text: 'Account Dashboard'
            });
        } else {
            result.unshift({
                href: '#/dashboard',
                stateId: 'panel-dashboard',
                text: 'Dashboard'
            });
        }
    }
    return result;
};

Scalr.utils.capitalizeFirstLetter = function (string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
};

Scalr.utils.getForbiddenActionTip = function (entity, scope) {

    entity = Scalr.utils.capitalizeFirstLetter(entity);

    if (scope === 'scalr') {
        return 'This Scalr-Scope ' + entity + ' can only be edited by administrators';
    }

    scope = Scalr.utils.capitalizeFirstLetter(scope);

    return 'This ' + scope + '-Scope ' + entity
        + ' can be edited in the ' + scope + ' interface';
};

Scalr.utils.getScopeLegend = function(type, raw, scope) {
    var result = [],
        scalrEnv = ['scalr', 'environment'],
        scalrAccountEnv = ['scalr', 'account', 'environment'],
        typeScopes = {
            variable: Scalr.scope == 'account' ?
                ['scalr', 'account', 'role'] :
                (scope == 'farm' ?
                    ['scalr', 'account', 'environment', 'farm'] :
                    ['scalr', 'account', 'environment', 'role', 'farm', 'farmrole']
                ),
            orchestration: scope == 'account' ?
                ['account'] :
                (Scalr.scope == 'scalr' ?
                    ['role'] :
                    ['account', 'role', 'farmrole']
                ),
            role: scalrAccountEnv,
            image: scalrAccountEnv,
            metric: scalrEnv,
            event: scalrAccountEnv,
            script: scalrAccountEnv,
            chefserver: scalrAccountEnv,
            webhook: scalrAccountEnv,
            webhookendpoint: scalrAccountEnv
        },
        allScopes = {
            scalr: 'Scalr',
            account: 'Account',
            environment: 'Environment',
            role: 'Role',
            farm: 'Farm',
            farmrole: 'Farm Role'
        },
        scopes = typeScopes[type] || Ext.Object.getKeys(allScopes),
        availableScopes = [];

    scope = scope || Scalr.scope;
    Ext.each(scopes, function(sc) {
        availableScopes.push(sc);
        if (scope == sc) {
            return false;
        }
    });

    Ext.each(availableScopes, function(scope){
        result.push('<div style="line-height:24px;"><img src="' + Ext.BLANK_IMAGE_URL + '" class="scalr-scope-' + scope + '" /><span style="padding-left: 6px">' + allScopes[scope] + ' Scope</span></div>');
    });
    result = result.join('');
    //result = '<div><div style="0 0 6px">Scopes:</div>' + result+ '</div>';
    return !raw ? Ext.String.htmlEncode(result) : result;
};

Scalr.utils.getScopeInfo = function(type, scope, id) {
    var info = [],
        urls = {
            script: {
                account: '/account/scripts?scriptId='
            },
            'custom event': {
                account: '/account/events?eventId='
            },
            endpoint: {
                account: '/account/webhooks/endpoints?endpointId='
            },
            webhook: {
                account: '/account/webhooks/configs?webhookId='
            },
            'chef server': {
                account: '/account/services/chef/servers?chefServerId='
            }
        };
    info.push('To make changes to this ' + Ext.String.capitalize(type)+ ', you need to ');
    var s = 'access it from the ' + Ext.String.capitalize(scope) + ' Scope';
    if (urls[type] && urls[type][scope]) {
        info.push('<a href="#' + urls[type][scope] + id +'">'+s+'</a>');
    } else {
        info.push(s);
    }
    info.push('.<br/><span style="font-family: OpenSansItalic;">Note: extra permissions may be required.</span>');
    return info.join('');
};

Scalr.utils.getGovernance = function(category, name) {
    var governance = Scalr.governance || {};
    governance = governance[category] || {};
    return name !== undefined ? governance[name] : governance;
};

Scalr.utils.getDefaultValue = function (variableName) {
    variableName = 'SCALR_UI_DEFAULT_' + variableName;

    var value = Scalr.defaults[variableName];
    var defaultValue = {
        'SCALR_UI_DEFAULT_STORAGE_RE_USE': 1,
        'SCALR_UI_DEFAULT_REBOOT_AFTER_HOST_INIT': 0,
        'SCALR_UI_DEFAULT_AUTO_SCALING': 1,
        'SCALR_UI_DEFAULT_AWS_INSTANCE_INITIATED_SHUTDOWN_BEHAVIOR': 'terminate'
    }[variableName];

    if (!Ext.isEmpty(value)) {
        return Ext.isNumber(defaultValue) ? parseInt(value) : value;
    }

    return defaultValue;
};

Scalr.utils.getStatusHtml = function (entityName, status) {
    var mask = '<span class="x-semibold" style="color:#{0}; font-size: 12px; text-transform: uppercase;">{1}</span>';
    var statusCls = Scalr.ui.ColoredStatus.config[entityName][status].cls;
    var color = !Ext.isDefined(statusCls) ? 'aaa' : {
        green: '008000',
        yellow: 'e0811a',
        red: 'c83310'
    }[statusCls];

    return Ext.String.format(mask, color, status);
};

Scalr.strings = {
    'aws.revoked_credentials': 'This environment\'s AWS access credentials have been revoked, and Scalr is no longer able to manage any of its infrastructure. Please <a href="#/account/environments/{envId}/clouds?platform=ec2">click here</a> to update environment with new and functional credentials.',
    'deprecated_warning': 'This feature has been <span class="x-semibold">Deprecated</span> and will be removed from Scalr in the future! Please limit your usage and DO NOT create major dependencies with this feature.',
    'farmbuilder.hostname_format.info': 'You can use global variables in the following format: {GLOBAL_VAR_NAME}<br />'+
    '<span class="x-semibold">For example:</span> {SCALR_FARM_NAME} -> {SCALR_FARM_ROLE_ALIAS} #{SCALR_INSTANCE_INDEX}',
    'farmbuilder.available_variables.info':  '<span class="x-semibold">You can use the following variables:</span> %image_id%, %external_ip%, %internal_ip%, %role_name%, %isdbmaster%, %instance_index%, ' +
    '%server_id%, %farm_id%, %farm_name%, %env_id%, %env_name%, %cloud_location%, %instance_id%, %avail_zone%',
    'farmbuilder.vpc.enforced': 'The account owner has enforced a specific policy on launching farms in a VPC.',
    'account.need_env_config':  'Your Scalr account was created.<br><br>' +
    'To start managing cloud resources in %platform%, you\'ll need to grant Scalr access to your cloud account by providing API Credentials. Scalr will use those credentials to make API Calls on your behalf.<br><br>' +
    'If you\'re unsure how to find those credentials, follow the tutorial below.',
    'account.cloud_access.info': 'Enable cloud access for this environment by entering the appropriate cloud credentials.<br/>You can create <span class="x-semibold">hybrid cloud</span> infrastructure by <span class="x-semibold">enabling multiple clouds.</span>',
    'vpc.public_subnet.info': 'Public subnets are those that include a routing table entry which points traffic destined for 0.0.0.0/0 to an Internet Gateway. One Public IP will be automatically assigned per instance for public subnets. You can also optionally enable Elastic IP assignment.',
    'vpc.private_subnet.info': 'Private networks have no direct access to the internet. Before launching instances in a private subnet, please make sure that a valid network route exists between the subnet and Scalr. As an alternative, you may use a VPC Router as a NAT Router for Scalr messages.  <br/>Please follow the instructions on the <a target="_blank" href="https://scalr-wiki.atlassian.net/wiki/x/PYB6">Scalr Wiki</a>',

    rdsDbInstanceVpcEnforced: 'A VPC Policy is active in this Environment, and restricts the location(s) and network(s) where DB instances can be launched.',
    awsLoadBalancerVpcEnforced: 'A VPC Policy is active in this Environment, and restricts the location(s) and network(s) where Elastic Load Balancers can be created.'
};
/*
 CryptoJS v3.1.2
 code.google.com/p/crypto-js
 (c) 2009-2013 by Jeff Mott. All rights reserved.
 code.google.com/p/crypto-js/wiki/License
 http://crypto-js.googlecode.com/svn/tags/3.1.2/build/rollups/sha1.js
 */
var CryptoJS=CryptoJS||function(e,m){var p={},j=p.lib={},l=function(){},f=j.Base={extend:function(a){l.prototype=this;var c=new l;a&&c.mixIn(a);c.hasOwnProperty("init")||(c.init=function(){c.$super.init.apply(this,arguments)});c.init.prototype=c;c.$super=this;return c},create:function(){var a=this.extend();a.init.apply(a,arguments);return a},init:function(){},mixIn:function(a){for(var c in a)a.hasOwnProperty(c)&&(this[c]=a[c]);a.hasOwnProperty("toString")&&(this.toString=a.toString)},clone:function(){return this.init.prototype.extend(this)}},
        n=j.WordArray=f.extend({init:function(a,c){a=this.words=a||[];this.sigBytes=c!=m?c:4*a.length},toString:function(a){return(a||h).stringify(this)},concat:function(a){var c=this.words,q=a.words,d=this.sigBytes;a=a.sigBytes;this.clamp();if(d%4)for(var b=0;b<a;b++)c[d+b>>>2]|=(q[b>>>2]>>>24-8*(b%4)&255)<<24-8*((d+b)%4);else if(65535<q.length)for(b=0;b<a;b+=4)c[d+b>>>2]=q[b>>>2];else c.push.apply(c,q);this.sigBytes+=a;return this},clamp:function(){var a=this.words,c=this.sigBytes;a[c>>>2]&=4294967295<<
            32-8*(c%4);a.length=e.ceil(c/4)},clone:function(){var a=f.clone.call(this);a.words=this.words.slice(0);return a},random:function(a){for(var c=[],b=0;b<a;b+=4)c.push(4294967296*e.random()|0);return new n.init(c,a)}}),b=p.enc={},h=b.Hex={stringify:function(a){var c=a.words;a=a.sigBytes;for(var b=[],d=0;d<a;d++){var f=c[d>>>2]>>>24-8*(d%4)&255;b.push((f>>>4).toString(16));b.push((f&15).toString(16))}return b.join("")},parse:function(a){for(var c=a.length,b=[],d=0;d<c;d+=2)b[d>>>3]|=parseInt(a.substr(d,
                2),16)<<24-4*(d%8);return new n.init(b,c/2)}},g=b.Latin1={stringify:function(a){var c=a.words;a=a.sigBytes;for(var b=[],d=0;d<a;d++)b.push(String.fromCharCode(c[d>>>2]>>>24-8*(d%4)&255));return b.join("")},parse:function(a){for(var c=a.length,b=[],d=0;d<c;d++)b[d>>>2]|=(a.charCodeAt(d)&255)<<24-8*(d%4);return new n.init(b,c)}},r=b.Utf8={stringify:function(a){try{return decodeURIComponent(escape(g.stringify(a)))}catch(c){throw Error("Malformed UTF-8 data");}},parse:function(a){return g.parse(unescape(encodeURIComponent(a)))}},
        k=j.BufferedBlockAlgorithm=f.extend({reset:function(){this._data=new n.init;this._nDataBytes=0},_append:function(a){"string"==typeof a&&(a=r.parse(a));this._data.concat(a);this._nDataBytes+=a.sigBytes},_process:function(a){var c=this._data,b=c.words,d=c.sigBytes,f=this.blockSize,h=d/(4*f),h=a?e.ceil(h):e.max((h|0)-this._minBufferSize,0);a=h*f;d=e.min(4*a,d);if(a){for(var g=0;g<a;g+=f)this._doProcessBlock(b,g);g=b.splice(0,a);c.sigBytes-=d}return new n.init(g,d)},clone:function(){var a=f.clone.call(this);
            a._data=this._data.clone();return a},_minBufferSize:0});j.Hasher=k.extend({cfg:f.extend(),init:function(a){this.cfg=this.cfg.extend(a);this.reset()},reset:function(){k.reset.call(this);this._doReset()},update:function(a){this._append(a);this._process();return this},finalize:function(a){a&&this._append(a);return this._doFinalize()},blockSize:16,_createHelper:function(a){return function(c,b){return(new a.init(b)).finalize(c)}},_createHmacHelper:function(a){return function(b,f){return(new s.HMAC.init(a,
        f)).finalize(b)}}});var s=p.algo={};return p}(Math);
(function(){var e=CryptoJS,m=e.lib,p=m.WordArray,j=m.Hasher,l=[],m=e.algo.SHA1=j.extend({_doReset:function(){this._hash=new p.init([1732584193,4023233417,2562383102,271733878,3285377520])},_doProcessBlock:function(f,n){for(var b=this._hash.words,h=b[0],g=b[1],e=b[2],k=b[3],j=b[4],a=0;80>a;a++){if(16>a)l[a]=f[n+a]|0;else{var c=l[a-3]^l[a-8]^l[a-14]^l[a-16];l[a]=c<<1|c>>>31}c=(h<<5|h>>>27)+j+l[a];c=20>a?c+((g&e|~g&k)+1518500249):40>a?c+((g^e^k)+1859775393):60>a?c+((g&e|g&k|e&k)-1894007588):c+((g^e^
k)-899497514);j=k;k=e;e=g<<30|g>>>2;g=h;h=c}b[0]=b[0]+h|0;b[1]=b[1]+g|0;b[2]=b[2]+e|0;b[3]=b[3]+k|0;b[4]=b[4]+j|0},_doFinalize:function(){var f=this._data,e=f.words,b=8*this._nDataBytes,h=8*f.sigBytes;e[h>>>5]|=128<<24-h%32;e[(h+64>>>9<<4)+14]=Math.floor(b/4294967296);e[(h+64>>>9<<4)+15]=b;f.sigBytes=4*e.length;this._process();return this._hash},clone:function(){var e=j.clone.call(this);e._hash=this._hash.clone();return e}});e.SHA1=j._createHelper(m);e.HmacSHA1=j._createHmacHelper(m)})();

// shorter name
Scalr.Confirm = Scalr.utils.Confirm;
Scalr.Request = Scalr.utils.Request;
Scalr.isAllowed = Scalr.utils.isAllowed;
Scalr.isOpenstack = Scalr.utils.isOpenstack;
Scalr.isCloudstack = Scalr.utils.isCloudstack;
Scalr.isPlatformEnabled = Scalr.utils.isPlatformEnabled;
Scalr.getPlatformConfigValue = Scalr.utils.getPlatformConfigValue;
Scalr.loadInstanceTypes = Scalr.utils.loadInstanceTypes;
Scalr.loadCloudLocations = Scalr.utils.loadCloudLocations;
Scalr.getModel = Scalr.utils.getModel;
Scalr.getGovernance = Scalr.utils.getGovernance;
Scalr.getDefaultValue = Scalr.utils.getDefaultValue;
