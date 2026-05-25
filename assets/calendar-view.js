( function() {
	function parseWeekDate( value ) {
		var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec( value || '' );

		if ( ! parts ) {
			return new Date();
		}

		return new Date( Number( parts[1] ), Number( parts[2] ) - 1, Number( parts[3] ) );
	}

	function formatDate( date ) {
		var year = date.getFullYear();
		var month = String( date.getMonth() + 1 ).padStart( 2, '0' );
		var day = String( date.getDate() ).padStart( 2, '0' );

		return year + '-' + month + '-' + day;
	}

	function addDays( value, days ) {
		var date = parseWeekDate( value );
		date.setDate( date.getDate() + days );

		return formatDate( date );
	}

	function getCategory( calendar ) {
		var params = new URLSearchParams( window.location.search );

		return calendar.dataset.category || params.get( 'churchsuite_event_category' ) || params.get( 'churchsuite_category' ) || '';
	}

	function setLoading( calendar, isLoading ) {
		var status = calendar.querySelector( '[data-calendar-status]' );

		calendar.classList.toggle( 'is-loading', isLoading );
		if ( status && isLoading ) {
			status.textContent = isLoading ? 'Loading events...' : '';
		}
	}

	function updateCalendar( calendar, weekStart ) {
		var restUrl = calendar.dataset.restUrl;
		var grid = calendar.querySelector( '[data-calendar-grid]' );
		var range = calendar.querySelector( '[data-calendar-range]' );
		var url;

		if ( ! restUrl || ! grid ) {
			return;
		}

		url = new URL( restUrl, window.location.origin );
		url.searchParams.set( 'week_start', weekStart );

		if ( getCategory( calendar ) ) {
			url.searchParams.set( 'category', getCategory( calendar ) );
		}

		setLoading( calendar, true );

		window.fetch( url.toString(), {
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json'
			}
		} )
			.then( function( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Calendar request failed' );
				}

				return response.json();
			} )
			.then( function( data ) {
				if ( data.html ) {
					grid.innerHTML = data.html;
				}
				if ( range && data.weekLabel ) {
					range.textContent = data.weekLabel;
				}
				if ( data.weekStart ) {
					calendar.dataset.weekStart = data.weekStart;
				}
				if ( calendar.querySelector( '[data-calendar-status]' ) ) {
					calendar.querySelector( '[data-calendar-status]' ).textContent = '';
				}
			} )
			.catch( function() {
				var status = calendar.querySelector( '[data-calendar-status]' );
				if ( status ) {
					status.textContent = 'Events could not be loaded.';
				}
			} )
			.finally( function() {
				setLoading( calendar, false );
			} );
	}

	function bindCalendar( calendar ) {
		calendar.addEventListener( 'click', function( event ) {
			var button = event.target.closest( '[data-calendar-action]' );
			var action;
			var weekStart;

			if ( ! button || ! calendar.contains( button ) ) {
				return;
			}

			action = button.dataset.calendarAction;
			weekStart = calendar.dataset.weekStart;

			if ( 'previous' === action ) {
				updateCalendar( calendar, addDays( weekStart, -7 ) );
			} else if ( 'next' === action ) {
				updateCalendar( calendar, addDays( weekStart, 7 ) );
			} else if ( 'today' === action ) {
				updateCalendar( calendar, calendar.dataset.currentWeekStart || formatDate( new Date() ) );
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		document.querySelectorAll( '.churchsuite-events-calendar' ).forEach( bindCalendar );
	} );
}() );
