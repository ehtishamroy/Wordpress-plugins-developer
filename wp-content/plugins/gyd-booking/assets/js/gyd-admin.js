/* global jQuery, wp, GYDBAdmin */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $input = $( '#gyd_photo_id' );
		var $preview = $( '.gydb-photo-preview' );
		var $previewImg = $preview.find( 'img' );
		var $remove = $( '.gydb-remove-photo' );

		$( '.gydb-upload-photo' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: GYDBAdmin.title,
				button: { text: GYDBAdmin.button },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var url = attachment.url;
				if ( attachment.sizes && attachment.sizes.medium ) {
					url = attachment.sizes.medium.url;
				}
				$input.val( attachment.id );
				$previewImg.attr( 'src', url );
				$preview.show();
				$remove.show();
			} );

			frame.open();
		} );

		$remove.on( 'click', function ( e ) {
			e.preventDefault();
			$input.val( '' );
			$previewImg.attr( 'src', '' );
			$preview.hide();
			$( this ).hide();
		} );
	} );
}( jQuery ) );
