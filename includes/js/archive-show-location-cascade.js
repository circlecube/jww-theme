/**
 * Cascading Location Selects for Show Archive Filters
 * 
 * Handles the cascading behavior: Country -> City -> Venue
 */
(function() {
	'use strict';

	// Location data from PHP (will be localized)
	var locationData = {
		countries: [],
		cities: [],
		venues: []
	};

	// Wait for DOM to be ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initLocationCascade );
	} else {
		initLocationCascade();
	}

	function initLocationCascade() {
		// Get all location selects
		var countrySelect = document.getElementById( 'filter-location-country' );
		var citySelect = document.getElementById( 'filter-location-city' );
		var venueSelect = document.getElementById( 'filter-location' );

		if ( ! countrySelect || ! citySelect || ! venueSelect ) {
			return;
		}

		// Collect all location data from the selects
		collectLocationData( countrySelect, citySelect, venueSelect );

		// Set up event listeners
		countrySelect.addEventListener( 'change', function() {
			updateCitySelect( countrySelect, citySelect, venueSelect );
		} );

		citySelect.addEventListener( 'change', function() {
			updateVenueSelect( citySelect, venueSelect );
		} );

		// Initialize state based on current selections
		if ( countrySelect.value ) {
			updateCitySelect( countrySelect, citySelect, venueSelect );
			if ( citySelect.value ) {
				updateVenueSelect( citySelect, venueSelect );
			}
		}
	}

	function collectLocationData( countrySelect, citySelect, venueSelect ) {
		// Store all cities with their parent IDs
		var cityOptions = citySelect.querySelectorAll( 'option[data-parent-id]' );
		locationData.cities = Array.from( cityOptions ).map( function( option ) {
			return {
				value: option.value,
				text: option.textContent,
				parentId: option.getAttribute( 'data-parent-id' )
			};
		} );

		// Store all venues with their parent IDs
		var venueOptions = venueSelect.querySelectorAll( 'option[data-parent-id]' );
		locationData.venues = Array.from( venueOptions ).map( function( option ) {
			return {
				value: option.value,
				text: option.textContent,
				parentId: option.getAttribute( 'data-parent-id' )
			};
		} );
	}

	function updateCitySelect( countrySelect, citySelect, venueSelect ) {
		var selectedCountry = countrySelect.value;

		// Clear city select
		citySelect.innerHTML = '<option value="">All Cities</option>';
		venueSelect.innerHTML = '<option value="">All Venues</option>';

		// Hide city and venue selects if no country selected
		if ( ! selectedCountry ) {
			citySelect.style.display = 'none';
			venueSelect.style.display = 'none';
			return;
		}

		// Show city select
		citySelect.style.display = '';

		// Filter cities by selected country
		var filteredCities = locationData.cities.filter( function( city ) {
			return city.parentId == selectedCountry;
		} );

		// Add filtered cities to select
		filteredCities.forEach( function( city ) {
			var option = document.createElement( 'option' );
			option.value = city.value;
			option.textContent = city.text;
			option.setAttribute( 'data-parent-id', city.parentId );
			citySelect.appendChild( option );
		} );

		// Hide venue select until city is selected
		venueSelect.style.display = 'none';
	}

	function updateVenueSelect( citySelect, venueSelect ) {
		var selectedCity = citySelect.value;

		// Clear venue select
		venueSelect.innerHTML = '<option value="">All Venues</option>';

		// Hide venue select if no city selected
		if ( ! selectedCity ) {
			venueSelect.style.display = 'none';
			return;
		}

		// Show venue select
		venueSelect.style.display = '';

		// Filter venues by selected city
		var filteredVenues = locationData.venues.filter( function( venue ) {
			return venue.parentId == selectedCity;
		} );

		// Add filtered venues to select
		filteredVenues.forEach( function( venue ) {
			var option = document.createElement( 'option' );
			option.value = venue.value;
			option.textContent = venue.text;
			option.setAttribute( 'data-parent-id', venue.parentId );
			venueSelect.appendChild( option );
		} );
	}
})();
