/* Aluleth Forms – "Create a form": example chips and waiting state. */
( function () {
	'use strict';
	var form = document.querySelector( '.alf-start-ai' );
	if ( ! form ) { return; }
	var area = form.querySelector( 'textarea' );
	Array.prototype.forEach.call( form.querySelectorAll( '.alf-chip-btn' ), function ( chip ) {
		chip.addEventListener( 'click', function () {
			area.value = chip.getAttribute( 'data-brief' );
			Array.prototype.forEach.call( form.querySelectorAll( '.alf-chip-btn' ), function ( c ) { c.classList.toggle( 'is-on', c === chip ); } );
			area.focus();
		} );
	} );
	form.addEventListener( 'submit', function () {
		if ( ! area.value.trim() ) { return; }
		var go = form.querySelector( '.alf-start-go' );
		go.classList.add( 'is-busy' );
		go.setAttribute( 'aria-disabled', 'true' );
		window.setTimeout( function () { go.disabled = true; }, 0 );
		form.querySelector( '.alf-start-wait' ).hidden = false;
	} );
}() );
