/**
 * Sortable Table Functionality for Show Archives
 */
(function() {
	'use strict';

	// Wait for DOM to be ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initSortableTables );
	} else {
		initSortableTables();
	}

	function initSortableTables() {
		const tables = document.querySelectorAll( '.sortable-table' );
		
		tables.forEach( function( table ) {
			const headers = table.querySelectorAll( 'thead th.sortable' );
			
			headers.forEach( function( header ) {
				header.style.cursor = 'pointer';
				header.style.userSelect = 'none';
				header.setAttribute( 'title', 'Click to sort' );
				
				header.addEventListener( 'click', function() {
					sortTable( table, header );
				} );
			} );
		} );
	}

	function sortTable( table, header ) {
		const tbody = table.querySelector( 'tbody' );
		const rows = Array.from( tbody.querySelectorAll( 'tr' ) );
		const columnIndex = Array.from( header.parentElement.children ).indexOf( header );
		const sortType = header.getAttribute( 'data-sort-type' );
		const currentSort = header.getAttribute( 'data-sort-direction' ) || 'asc';
		const newSort = currentSort === 'asc' ? 'desc' : 'asc';
		
		// Remove sort indicators from all headers in this table
		const allHeaders = table.querySelectorAll( 'thead th.sortable' );
		allHeaders.forEach( function( h ) {
			h.removeAttribute( 'data-sort-direction' );
			const indicator = h.querySelector( '.sort-indicator' );
			if ( indicator ) {
				indicator.textContent = '';
			}
		} );
		
		// Set sort direction on clicked header
		header.setAttribute( 'data-sort-direction', newSort );
		const indicator = header.querySelector( '.sort-indicator' );
		if ( indicator ) {
			indicator.textContent = newSort === 'asc' ? ' ↑' : ' ↓';
		}
		
		// Sort rows
		rows.sort( function( a, b ) {
			const aCell = a.children[columnIndex];
			const bCell = b.children[columnIndex];
			
			if ( ! aCell || ! bCell ) {
				return 0;
			}
			
			const aValue = aCell.getAttribute( 'data-sort-value' ) || aCell.textContent.trim();
			const bValue = bCell.getAttribute( 'data-sort-value' ) || bCell.textContent.trim();
			
			let comparison = 0;
			
			if ( sortType === 'number' ) {
				const aNum = parseFloat( aValue ) || 0;
				const bNum = parseFloat( bValue ) || 0;
				comparison = aNum - bNum;
			} else if ( sortType === 'date' ) {
				const aDate = new Date( aValue );
				const bDate = new Date( bValue );
				comparison = aDate - bDate;
			} else {
				// Text sorting
				comparison = aValue.localeCompare( bValue, undefined, { 
					numeric: true, 
					sensitivity: 'base' 
				} );
			}
			
			return newSort === 'asc' ? comparison : -comparison;
		} );
		
		// Re-append sorted rows
		rows.forEach( function( row ) {
			tbody.appendChild( row );
		} );
	}
})();
