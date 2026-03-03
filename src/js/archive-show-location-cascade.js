/**
 * Cascading Location Selects for Show Archive Filters
 *
 * Handles Country -> State (optional) -> City -> Venue.
 * When the selected country has state-level terms, the State dropdown is shown and cities are filtered by state.
 */
(function() {
	'use strict';

	var locationData = {
		states: [],
		cities: [],
		venues: []
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initLocationCascade );
	} else {
		initLocationCascade();
	}

	function initLocationCascade() {
		var countrySelect = document.getElementById( 'filter-location-country' );
		var stateSelect = document.getElementById( 'filter-location-state' );
		var citySelect = document.getElementById( 'filter-location-city' );
		var venueSelect = document.getElementById( 'filter-location' );

		if ( ! countrySelect || ! citySelect || ! venueSelect ) {
			return;
		}

		collectLocationData( stateSelect, citySelect, venueSelect );

		countrySelect.addEventListener( 'change', function() {
			onCountryChange( countrySelect, stateSelect, citySelect, venueSelect );
		} );

		if ( stateSelect ) {
			stateSelect.addEventListener( 'change', function() {
				onStateChange( stateSelect, citySelect, venueSelect );
			} );
		}

		citySelect.addEventListener( 'change', function() {
			updateVenueSelect( citySelect, venueSelect );
		} );

		// Initial sync from current selections (preserve URL/GET selections when repopulating)
		if ( countrySelect.value ) {
			var savedState = stateSelect ? stateSelect.value : '';
			var savedCity = citySelect.value;
			var savedVenue = venueSelect.value;
			onCountryChange( countrySelect, stateSelect, citySelect, venueSelect );
			if ( stateSelect && savedState ) {
				stateSelect.value = savedState;
				onStateChange( stateSelect, citySelect, venueSelect );
			}
			if ( savedCity ) {
				citySelect.value = savedCity;
				updateVenueSelect( citySelect, venueSelect );
			}
			if ( savedVenue ) {
				venueSelect.value = savedVenue;
			}
		}
	}

	function collectLocationData( stateSelect, citySelect, venueSelect ) {
		if ( stateSelect ) {
			var stateOptions = stateSelect.querySelectorAll( 'option[data-parent-id]' );
			locationData.states = Array.from( stateOptions ).map( function( option ) {
				return {
					value: option.value,
					text: option.textContent,
					parentId: option.getAttribute( 'data-parent-id' )
				};
			} );
		}

		var cityOptions = citySelect.querySelectorAll( 'option[data-parent-id]' );
		locationData.cities = Array.from( cityOptions ).map( function( option ) {
			return {
				value: option.value,
				text: option.textContent,
				parentId: option.getAttribute( 'data-parent-id' )
			};
		} );

		var venueOptions = venueSelect.querySelectorAll( 'option[data-parent-id]' );
		locationData.venues = Array.from( venueOptions ).map( function( option ) {
			return {
				value: option.value,
				text: option.textContent,
				parentId: option.getAttribute( 'data-parent-id' )
			};
		} );
	}

	function onCountryChange( countrySelect, stateSelect, citySelect, venueSelect ) {
		var selectedCountry = countrySelect.value;

		citySelect.innerHTML = '<option value="">All Cities</option>';
		venueSelect.innerHTML = '<option value="">All Venues</option>';
		if ( stateSelect ) {
			stateSelect.innerHTML = '<option value="">All States/Provinces</option>';
			stateSelect.style.display = 'none';
		}
		citySelect.style.display = 'none';
		venueSelect.style.display = 'none';

		if ( ! selectedCountry ) {
			return;
		}

		var hasStates = stateSelect && locationData.states.some( function( s ) { return s.parentId == selectedCountry; } );

		if ( hasStates && stateSelect ) {
			stateSelect.style.display = '';
			var filteredStates = locationData.states.filter( function( s ) { return s.parentId == selectedCountry; } );
			filteredStates.forEach( function( s ) {
				var option = document.createElement( 'option' );
				option.value = s.value;
				option.textContent = s.text;
				option.setAttribute( 'data-parent-id', s.parentId );
				stateSelect.appendChild( option );
			} );
			// City and venue stay hidden until state is selected
			return;
		}

		// No state level: show cities under country
		citySelect.style.display = '';
		var filteredCities = locationData.cities.filter( function( c ) { return c.parentId == selectedCountry; } );
		filteredCities.forEach( function( c ) {
			var option = document.createElement( 'option' );
			option.value = c.value;
			option.textContent = c.text;
			option.setAttribute( 'data-parent-id', c.parentId );
			citySelect.appendChild( option );
		} );
	}

	function onStateChange( stateSelect, citySelect, venueSelect ) {
		var selectedState = stateSelect.value;

		citySelect.innerHTML = '<option value="">All Cities</option>';
		venueSelect.innerHTML = '<option value="">All Venues</option>';

		if ( ! selectedState ) {
			citySelect.style.display = 'none';
			venueSelect.style.display = 'none';
			return;
		}

		citySelect.style.display = '';
		var filteredCities = locationData.cities.filter( function( c ) { return c.parentId == selectedState; } );
		filteredCities.forEach( function( c ) {
			var option = document.createElement( 'option' );
			option.value = c.value;
			option.textContent = c.text;
			option.setAttribute( 'data-parent-id', c.parentId );
			citySelect.appendChild( option );
		} );
		venueSelect.style.display = 'none';
	}

	function updateVenueSelect( citySelect, venueSelect ) {
		var selectedCity = citySelect.value;

		venueSelect.innerHTML = '<option value="">All Venues</option>';

		if ( ! selectedCity ) {
			venueSelect.style.display = 'none';
			return;
		}

		venueSelect.style.display = '';
		var filteredVenues = locationData.venues.filter( function( v ) { return v.parentId == selectedCity; } );
		filteredVenues.forEach( function( v ) {
			var option = document.createElement( 'option' );
			option.value = v.value;
			option.textContent = v.text;
			option.setAttribute( 'data-parent-id', v.parentId );
			venueSelect.appendChild( option );
		} );
	}
})();
