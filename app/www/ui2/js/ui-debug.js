Scalr.application.addDocked({
    xtype: 'toolbar',
    dock: 'bottom',
    layout: 'hbox',
    items: [{
        xtype: 'button',
        enableToggle: true,
        text: 'SQL',
        pressed: Scalr.storage.get('system-debug-sql', true),
        handler: function() {
            var dataview = Scalr.application.getDockedComponent('debugSql');
            dataview[this.pressed ? 'show' : 'hide' ]();
            Scalr.storage.set('system-debug-sql', this.pressed, true);
            Scalr.application.layout.onOwnResize();
            dataview.updateLayout();
        }
    }, {
        xtype: 'button',
        enableToggle: true,
        text: 'Lock scroll',
        margin: '0 0 0 10',
        handler: function() {
            Scalr.application.getDockedComponent('debugSql')['lockScroll'] = this.pressed;
        }
    }]
});

Scalr.application.addDocked({
    xtype: 'dataview',
    dock: 'top',
    hidden: !Scalr.storage.get('system-debug-sql', true),
    itemId: 'debugSql',
    flex: 1,
    store: {
        fields: [ 'url', 'report', 'expanded' ],
        proxy: 'object'
    },
    lockScroll: false,
    deferEmptyText: false,
    emptyText: '<div class="x-grid-empty">No records found</div>',
    autoScroll: true,
    style: 'bottom: 41px; padding-top: 4px',
    itemTpl: new Ext.XTemplate(
            '<b style="margin: 0 0 10px 4px; display: block; cursor: pointer" class="<tpl if="report.length">clickable</tpl>">&nbsp;{url} ({report.length})</b>' +
            '<tpl if="report.length">' +
                '<ul style="padding-left: 20px; margin: 0 0 8px 0; list-style-type: none; display: none">' +
                    '<tpl for="report">' +
                        '<li style="margin: 2px; white-space: pre-wrap; color: {[this.color(values)]}">{value}</li>' +
                    '</tpl>' +
                    '<a href="#" class="collapse">&#8593; Collapse</a>'+
                '</ul>' +
            '</tpl>'
        , {
            color: function(vs) {
                var r = /^([0-9\.]+)\sms/mi, result = r.exec(vs.value), tm = result ? parseFloat(result[1]) : 0;
                if (tm > 500) {
                    return 'orange';
                } else if (tm > 200) {
                    return 'blue';
                } else if (tm > 0) {
                    return '#5555' + tm.toString(16);
                } else {
                    return 'red';
                }
            }
        }),
    listeners: {
        afterrender: function() {
            this.el.on('click', function(e, t) {
                var s = e.getTarget('b.clickable', 5, true);
                if (s) {
                    var ul = s.next('ul');
                    if (ul) {
                        ul.setVisibilityMode(Ext.dom.Element.DISPLAY);
                        ul[ul.isVisible() ? 'hide' : 'show']();
                    }
                } else {
                    s = e.getTarget('a.collapse', 5, true);
                    if (s) {
                        var ul = s.up('ul');
                        if (ul) {
                            ul.setVisibilityMode(Ext.dom.Element.DISPLAY);
                            ul.hide();
                        }
                        e.stopEvent();
                    }
                }
            });
        },
        itemadd: function() {
            if (this.rendered && !this.lockScroll) {
                this.scrollBy(0, 99999);
            }
        }
    }
});

Ext.Ajax.on('requestcomplete', function(conn, response) {
    var result = Ext.decode(response.responseText, true), comp = Scalr.application.getDockedComponent('debugSql'), store = comp.getStore();
    store.add({
        url: response.request ? response.request.options.url : (response.responseXML ? response.responseXML.URL : ''),
        report: result ? result['scalrDebugLog'] : []
    });
});
