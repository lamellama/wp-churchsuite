( function() {
	if ( ! window.wp || ! window.wp.blocks || ! window.wp.element ) {
		return;
	}

	var __ = window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : function( text ) {
		return text;
	};
	var createElement = window.wp.element.createElement;
	var ServerSideRender = window.wp.serverSideRender;

	window.wp.blocks.registerBlockType( 'churchsuite-events/category-filter', {
		apiVersion: 2,
		title: __( 'ChurchSuite Event Category Filter', 'churchsuite-events' ),
		description: __( 'Adds a category dropdown that filters ChurchSuite event Query Loops using the page URL.', 'churchsuite-events' ),
		category: 'widgets',
		icon: 'filter',
		supports: {
			html: false
		},
		edit: function() {
			if ( ServerSideRender ) {
				return createElement( ServerSideRender, {
					block: 'churchsuite-events/category-filter'
				} );
			}

			return createElement(
				'div',
				{
					className: 'churchsuite-event-category-filter'
				},
				__( 'ChurchSuite category filter', 'churchsuite-events' )
			);
		},
		save: function() {
			return null;
		}
	} );
}() );
