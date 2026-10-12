/* Aluleth › Settings › General: live preview of the layout choices. */
( function () {
	var sample = document.getElementById( 'alf-g-sample' );
	if ( ! sample ) { return; }
	var form = sample.closest( 'form' );
	function value( name ) {
		var el = form.querySelector( 'input[name="' + name + '"]:checked' );
		return el ? el.value : '';
	}
	function update() {
		sample.className = 'alf alf--' + value( 'style' ) + ' alf-labels-' + value( 'labels' ) + ' alf-help-' + value( 'help' );
		var summary = sample.querySelector( '.alf-summary' );
		if ( summary ) { summary.hidden = 'top' !== value( 'errors' ); }
	}
	form.addEventListener( 'change', function ( e ) {
		if ( e.target && /^(style|labels|help|errors)$/.test( e.target.name ) ) { update(); }
	} );
}() );
