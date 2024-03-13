/* eslint camelcase: "off" */
/* global jQuery:false, perflabAutoloadSettings:false */

( function ( $, document ) {
	$( document ).on( 'click', '.update-autoload', function ( e ) {
		e.preventDefault();
		const button = $( this );
		const optionName = button.data( 'option-name' );
		const autoload = button.data( 'autoload' );
		const value = button.data( 'value' );
		const data = {
			option_name: optionName,
			autoload,
			value,
		};

		$.ajax( {
			url:
				perflabAutoloadSettings.root + 'perflab-aao/v1/update-autoload',
			method: 'POST',
			data,
			success( response ) {
				if ( response.success ) {
					button.attr( 'disabled', true );
				} else {
					button.append(
						'<span class=""> ' + response.message + '</span>'
					);
					$(
						'<div style="margin-top:5px;border:1px solid #d63638;padding-left:5px;">' +
							response.message +
							'</div>'
					).insertAfter( button );
				}
			},
			error( error ) {
				$(
					'<div style="margin-top:5px;border:1px solid #d63638;padding-left:5px;">' +
						error.responseJSON.message +
						'</div>'
				).insertAfter( button );
			},
		} );
	} );
} )( jQuery, document );
