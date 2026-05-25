( function() {
	var config = window.ChurchSuiteEventsQueryLoop;

	if ( ! config || ! window.wp || ! window.wp.blocks || ! window.wp.domReady ) {
		return;
	}

	function isChurchSuiteEventQuery( attributes ) {
		return !! (
			attributes &&
			attributes.query &&
			attributes.query.postType === config.postType
		);
	}

	function setQueryValue( attributes, setAttributes, values ) {
		setAttributes( {
			query: Object.assign( {}, attributes.query || {}, values )
		} );
	}

	function addChurchSuiteEventControls( BlockEdit ) {
		return function( props ) {
			var InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
			var PanelBody = window.wp.components && window.wp.components.PanelBody;
			var SelectControl = window.wp.components && window.wp.components.SelectControl;
			var ToggleControl = window.wp.components && window.wp.components.ToggleControl;
			var Fragment = window.wp.element && window.wp.element.Fragment;
			var createElement = window.wp.element && window.wp.element.createElement;
			var __ = window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : function( text ) {
				return text;
			};
			var query = props.attributes && props.attributes.query ? props.attributes.query : {};

			if ( ! createElement ) {
				return BlockEdit( props );
			}

			if (
				props.name !== 'core/query' ||
				! isChurchSuiteEventQuery( props.attributes ) ||
				! InspectorControls ||
				! PanelBody ||
				! SelectControl ||
				! ToggleControl ||
				! Fragment ||
				! createElement
			) {
				return createElement( BlockEdit, props );
			}

			return createElement(
				Fragment,
				null,
				createElement( BlockEdit, props ),
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{
							title: __( 'ChurchSuite events', 'churchsuite-events' ),
							initialOpen: true
						},
						createElement( ToggleControl, {
							label: __( 'Only show events from today onward', 'churchsuite-events' ),
							checked: !! query[ config.flag ],
							onChange: function( value ) {
								setQueryValue( props.attributes, props.setAttributes, {
									[ config.flag ]: value,
									order: value ? 'asc' : query.order,
									orderBy: value ? 'date' : query.orderBy
								} );
							}
						} ),
						createElement( SelectControl, {
							label: __( 'Event order', 'churchsuite-events' ),
							value: 'desc' === query.order ? 'event-date-desc' : 'event-date-asc',
							options: [
								{
									label: __( 'Event date, nearest first', 'churchsuite-events' ),
									value: 'event-date-asc'
								},
								{
									label: __( 'Event date, furthest first', 'churchsuite-events' ),
									value: 'event-date-desc'
								}
							],
							onChange: function( value ) {
								setQueryValue( props.attributes, props.setAttributes, {
									orderBy: 'date',
									order: 'event-date-desc' === value ? 'desc' : 'asc'
								} );
							}
						} )
					)
				)
			);
		};
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
					'orderBy',
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

	if ( window.wp.hooks && window.wp.compose && window.wp.compose.createHigherOrderComponent ) {
		window.wp.hooks.addFilter(
			'editor.BlockEdit',
			'churchsuite-events/query-loop-controls',
			window.wp.compose.createHigherOrderComponent( addChurchSuiteEventControls, 'withChurchSuiteEventControls' )
		);
	}
}() );
