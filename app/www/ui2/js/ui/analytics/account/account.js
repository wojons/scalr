Ext.define('Scalr.ui.FormFieldButtonGroupAnaliticsScalrResources', {
	extend: 'Scalr.ui.FormFieldButtonGroup',
	alias: 'widget.analyticsrecources',

    listeners: {
        beforetoggle: function(){return false;}
    },
    layout: 'hbox',
    
    items: [{
        value: 'projects',
        text: 'Projects',
        flex: .75,
        margin: '12 0 0',
        hrefTarget: '_self',
        href: '#/analytics/account/projects'
    },{
        value: 'environments',
        text: 'Environments',
        flex: 1,
        margin: '12 0 0',
        hrefTarget: '_self',
        href: '#/analytics/account/environments'
    },{
        value: 'budgets',
        text: 'Budgets',
        flex: .75,
        margin: '12 0 0',
        hrefTarget: '_self',
        href: '#/analytics/account/budgets'
    }]
});