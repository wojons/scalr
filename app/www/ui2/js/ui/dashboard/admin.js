Scalr.regPage('Scalr.ui.dashboard.admin', function (loadParams, moduleParams) {
    //Scalr.message.Warning('Dashboard in development');

    //return Ext.create('Ext.container.Container', {
    return Ext.create('Ext.panel.Panel', {
        scalrOptions: {
            maximize: 'all',
            reload: false,
            menuTitle: 'Admin Dashboard'
        },
        stateId: 'panel-admin-dashboard',
        bodyPadding: 20,
        border: true,

        /*layout: {
         type: 'column'
         },*/
        bodyStyle: 'font-size: 13px',
        //title: 'Welcome to Scalr Admin!',
        html: '<h1>Did you just deploy your new Scalr install? Follow these instructions to get started.</h1><p><ul>' +
            '<li><a href="https://scalr-wiki.atlassian.net/wiki/x/igAeAQ" target="_blank"><h2>First Steps - Login as an administrator</h2></a></li>' +
            '<li><a href="https://scalr-wiki.atlassian.net/wiki/x/iAAeAQ" target="_blank"><h2>First Steps - Create a new user</h2></a></li>' +
            '<li><a href="https://scalr-wiki.atlassian.net/wiki/x/kgAeAQ" target="_blank"><h2>First Steps - Add Cloud Credentials</h2></a></li>' +
            '<li><a href="https://scalr-wiki.atlassian.net/wiki/x/ngAeAQ" target="_blank"><h2>First Steps - Add Images and Roles</h2></a></li>' +
        '</p></ul>'
    })
});
