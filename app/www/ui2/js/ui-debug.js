Scalr.application.addDocked({
    xtype: 'toolbar',
    dock: 'bottom',
    layout: 'vbox',
    items: [{
        xtype: 'button',
        enableToggle: true,
        text: 'SQL',
        handler: function() {
            Scalr.application.getDockedComponent('debugSql')[ this.pressed ? 'show' : 'hide' ]();
            Scalr.application.layout.onOwnResize();
        }
    }]
});

Scalr.application.addDocked({
    xtype: 'dataview',
    dock: 'top',
    hidden: true,
    itemId: 'debugSql',
    maxHeight: 400,
    store: {
        fields: [ 'url', 'report' ],
        proxy: 'object'
    },
    deferEmptyText: false,
    emptyText: '<div class="x-grid-empty">No records found</div>',
    itemSelector: 'x-dataview-item',
    autoScroll: true,
    tpl:
        '<tpl for=".">' +
            '<span style="font-weight: bold">&nbsp;{url}</span>' +
            '<ul>' +
            '<tpl for="report">' +
            '<li>{sql}</li>' +
            '</tpl>' +
            '</ul>' +
            '</tpl>'

});

Ext.Ajax.on('requestcomplete', function(conn, response) {
    var result = Ext.decode(response.responseText, true), store = Scalr.application.getDockedComponent('debugSql').getStore();
    store.add({
        url: response.request.options.url,
        report: result ? result['scalrDebugModeSql'] : []
    });
});
