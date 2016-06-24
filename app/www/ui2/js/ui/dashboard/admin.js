//@deprecated replaced with Scalr.ui.dashboard.GettingStarted
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
            '<li><h2><a href="https://scalr-wiki.atlassian.net/wiki/x/igAeAQ" target="_blank">First Steps - Login as an administrator</a></h2></li>' +
            '<li><h2><a href="https://scalr-wiki.atlassian.net/wiki/x/iAAeAQ" target="_blank">First Steps - Create a new user</a></h2></li>' +
            '<li><h2><a href="https://scalr-wiki.atlassian.net/wiki/x/kgAeAQ" target="_blank">First Steps - Add Cloud Credentials</a></h2></li>' +
            '<li><h2><a href="https://scalr-wiki.atlassian.net/wiki/x/ngAeAQ" target="_blank">First Steps - Add Images and Roles</a></h2></li>' +
        '</p></ul>'
    })
});
