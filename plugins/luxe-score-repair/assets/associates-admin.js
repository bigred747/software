( function () {
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-lah-copy]' );
		if ( ! button ) {
			return;
		}
		var node = document.getElementById( button.getAttribute( 'data-lah-copy' ) );
		if ( ! node ) {
			return;
		}
		var text = node.value || node.textContent || '';
		var done = function () {
			var previous = button.textContent;
			button.textContent = 'Copied';
			window.setTimeout( function () {
				button.textContent = previous;
			}, 1600 );
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}
		node.focus();
		node.select();
		try {
			if ( document.execCommand( 'copy' ) ) {
				done();
			}
		} catch ( error ) {
			button.textContent = 'Select the text and copy it';
		}
	} );
}() );
