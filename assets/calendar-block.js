( function() {
	if ( ! window.wp || ! window.wp.blocks || ! window.wp.element ) {
		return;
	}

	var __ = window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : function( text ) {
		return text;
	};
	var createElement = window.wp.element.createElement;
	var ServerSideRender = window.wp.serverSideRender;

	window.wp.blocks.registerBlockType( 'churchsuite-events/calendar', {
		apiVersion: 2,
		title: __( 'ChurchSuite Events Calendar', 'churchsuite-events' ),
		description: __( 'Displays ChurchSuite events in an interactive weekly calendar.', 'churchsuite-events' ),
		category: 'widgets',
		icon: 'calendar-alt',
		supports: {
			html: false
		},
		edit: function() {
			if ( ServerSideRender ) {
				return createElement( ServerSideRender, {
					block: 'churchsuite-events/calendar'
				} );
			}

			return createElement(
				'div',
				{
					className: 'churchsuite-events-calendar'
				},
				__( 'ChurchSuite events calendar', 'churchsuite-events' )
			);
		},
		save: function() {
			return null;
		}
	} );
}() );
