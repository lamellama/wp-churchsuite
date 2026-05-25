( function() {
	var config = window.ChurchSuiteEventsQueryLoop;

	if ( ! config || ! window.wp || ! window.wp.blocks || ! window.wp.domReady ) {
		return;
	}

	window.wp.domReady( function() {
		window.wp.blocks.registerBlockVariation(
			'core/query',
			{
				name: config.namespace,
				title: 'Upcoming ChurchSuite Events',
				description: 'Displays upcoming ChurchSuite events from today onward.',
				icon: 'calendar-alt',
				scope: [ 'inserter', 'block' ],
				allowedControls: [
					'inherit',
					'postType',
					'order',
					'taxQuery',
					'search',
					'postCount',
					'offset',
					'pages'
				],
				attributes: {
					namespace: config.namespace,
					query: {
						perPage: 6,
						pages: 0,
						offset: 0,
						postType: config.postType,
						order: 'asc',
						orderBy: 'date',
						inherit: false,
						[ config.flag ]: true
					},
					displayLayout: {
						type: 'list'
					}
				},
				isActive: [ 'namespace' ]
			}
		);
	} );
}() );
