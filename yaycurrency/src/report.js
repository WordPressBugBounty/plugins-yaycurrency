"use strict";
const searchParams = new URLSearchParams( window.location.search );
const currency     = searchParams.get( "currency" );

jQuery( "#yay-currency-dropdown-reports" ).css( "float", "right" );

if (currency) {
	jQuery( "#yay-currency-dropdown-reports select" ).val( currency );
}

jQuery( "#yay-currency-dropdown-reports select" ).change(
	function (e) {
		const link    = jQuery( e.target ).children( "option:selected" ).data( "url" );
		location.href = link;
	}
);
