Scalr.data.add([{
	name: 'webhooks.endpoints',
	dataUrl: '/webhooks/xGetData',
	dataLoaded: false,
	fields: [
		{name: 'endpointId', type: 'string'},
		'url',
        'isValid',
        'validationToken',
        'securityKey',
        {name: 'id', convert: function(v, record){;return record.data.endpointId;}}
	],
	listeners: {
		update: function(store, record, operation, fields){
			Scalr.data.fireRefresh('webhooks.configs');
		},
		add: function(){
			Scalr.data.fireRefresh('webhooks.configs');
		},
		remove: function(store, record){
			var endpointId = record.get('endpointId'),
				webhooks = Scalr.data.get('webhooks.configs');
			if (webhooks) {
				webhooks.each(function(webhooksRecord){
					var webhookEndpoints = webhooksRecord.get('endpoints');
					if (webhookEndpoints) {
						var newWebhookEndpoints = [];
						for (var i=0, len=webhookEndpoints.length; i<len; i++) {
							if (webhookEndpoints[i].id != endpointId) {
								newWebhookEndpoints.push(webhookEndpoints[i]);
							}
						}
						webhooksRecord.set('endpoints', newWebhookEndpoints);
					}
				});
			}
            Scalr.data.fireRefresh('webhooks.configs');
		}
	}
},{
	name: 'webhooks.configs',
	dataUrl: '/webhooks/xGetData',
	dataLoaded: false,
	fields: [
        {name: 'webhookId', type: 'string'},
        'name',
        'postData',
        'skipPrivateGv',
        'endpoints',
        'events',
        'farms',
        {name: 'id', convert: function(v, record){;return record.data.webhookId;}}
    ],
	listeners: {
		update: function(){
			Scalr.data.fireRefresh('webhooks.endpoints');
		},
		add: function(){
			Scalr.data.fireRefresh('webhooks.endpoints');
		}
	}
}]);