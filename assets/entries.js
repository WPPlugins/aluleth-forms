/* Aluleth Forms – entries board: drag and drop, details panel, notes. No dependencies. */
( function () {
	'use strict';
	var cfg = window.alulethFormsEntries || {};
	var t = cfg.i18n || {};

	function qa( root, sel ) { return Array.prototype.slice.call( root.querySelectorAll( sel ) ); }

	// Filters: changing the form reloads the screen.
	qa( document, '.alf-autosubmit' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () { select.form.submit(); } );
	} );

	// Two-step delete buttons (no browser dialog).
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest && e.target.closest( '[data-confirm]' );
		if ( ! btn ) { return; }
		if ( btn.getAttribute( 'data-armed' ) !== '1' ) {
			e.preventDefault();
			btn.setAttribute( 'data-armed', '1' );
			btn.setAttribute( 'data-label', btn.textContent );
			btn.textContent = btn.getAttribute( 'data-confirm' );
			window.setTimeout( function () {
				btn.removeAttribute( 'data-armed' );
				btn.textContent = btn.getAttribute( 'data-label' );
			}, 4000 );
		}
	} );

	var board = document.querySelector( '.alf-board' );
	if ( ! board || ! window.fetch || ! window.FormData ) { return; }
	var nonce = board.getAttribute( 'data-nonce' );
	var panel = document.getElementById( 'alf-panel' );
	var openId = 0;
	var opener = null;

	function toast( text, error ) {
		var el = document.createElement( 'div' );
		el.className = 'alf-toast' + ( error ? ' is-error' : '' );
		el.setAttribute( 'role', 'status' );
		el.textContent = text;
		document.body.appendChild( el );
		window.setTimeout( function () { el.remove(); }, 2600 );
	}

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( '_ajax_nonce', nonce );
		Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
		return fetch( cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! res || ! res.success ) { throw new Error( ( res && res.data && res.data.message ) || t.error ); }
				return res.data;
			} );
	}

	function column( status ) { return board.querySelector( '.alf-col[data-status="' + status + '"]' ); }
	function card( id ) { return board.querySelector( '.alf-card[data-entry="' + id + '"]' ); }

	function recount() {
		qa( board, '.alf-col' ).forEach( function ( col ) {
			var n = qa( col, '.alf-card' ).length;
			var count = col.querySelector( '.alf-count' );
			var base = parseInt( count.getAttribute( 'data-base' ) || count.textContent.replace( /\D/g, '' ), 10 ) || 0;
			if ( ! count.hasAttribute( 'data-base' ) ) { count.setAttribute( 'data-base', base ); count.setAttribute( 'data-shown', n ); }
			var shown = parseInt( count.getAttribute( 'data-shown' ), 10 ) || 0;
			count.textContent = String( Math.max( 0, base + ( n - shown ) ) );
			var empty = col.querySelector( '.alf-col-empty' );
			if ( empty ) { empty.hidden = n > 0; }
		} );
	}
	recount();

	// Moves a card to another column, then saves; puts it back if saving fails.
	function move( el, status ) {
		var from = el.closest( '.alf-col' );
		var to = column( status );
		if ( ! to || from === to ) { return; }
		var next = el.nextSibling;
		to.querySelector( '.alf-cards' ).insertBefore( el, to.querySelector( '.alf-card' ) );
		el.classList.add( 'is-saving' );
		recount();
		post( 'aluleth_forms_board_move', { entry: el.getAttribute( 'data-entry' ), status: status } )
			.then( function () {
				var title = to.querySelector( 'h2' ).textContent;
				toast( ( t.moved || '%s' ).replace( '%s', title ) );
				var select = panel && panel.querySelector( '.alf-p-status select' );
				if ( select && openId === parseInt( el.getAttribute( 'data-entry' ), 10 ) ) { select.value = status; }
			} )
			.catch( function ( err ) {
				from.querySelector( '.alf-cards' ).insertBefore( el, next && next.parentNode === from.querySelector( '.alf-cards' ) ? next : null );
				recount();
				toast( err.message || t.error, true );
			} )
			.then( function () { el.classList.remove( 'is-saving' ); } );
	}

	// Drag and drop.
	var dragged = null;
	board.addEventListener( 'dragstart', function ( e ) {
		var el = e.target.closest && e.target.closest( '.alf-card' );
		if ( ! el ) { return; }
		dragged = el;
		el.classList.add( 'is-dragging' );
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData( 'text/plain', el.getAttribute( 'data-entry' ) );
	} );
	board.addEventListener( 'dragend', function () {
		if ( dragged ) { dragged.classList.remove( 'is-dragging' ); }
		dragged = null;
		qa( board, '.alf-col.is-over' ).forEach( function ( c ) { c.classList.remove( 'is-over' ); } );
	} );
	board.addEventListener( 'dragover', function ( e ) {
		var col = e.target.closest && e.target.closest( '.alf-col' );
		if ( ! col || ! dragged ) { return; }
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		qa( board, '.alf-col.is-over' ).forEach( function ( c ) { if ( c !== col ) { c.classList.remove( 'is-over' ); } } );
		col.classList.add( 'is-over' );
	} );
	board.addEventListener( 'drop', function ( e ) {
		var col = e.target.closest && e.target.closest( '.alf-col' );
		if ( ! col || ! dragged ) { return; }
		e.preventDefault();
		col.classList.remove( 'is-over' );
		move( dragged, col.getAttribute( 'data-status' ) );
	} );

	// Details panel.
	function closePanel() {
		panel.hidden = true;
		panel.innerHTML = '';
		qa( board, '.alf-card.is-open' ).forEach( function ( c ) { c.classList.remove( 'is-open' ); } );
		openId = 0;
		if ( opener ) { opener.focus(); opener = null; }
	}

	function openPanel( el ) {
		var id = parseInt( el.getAttribute( 'data-entry' ), 10 );
		qa( board, '.alf-card.is-open' ).forEach( function ( c ) { c.classList.remove( 'is-open' ); } );
		el.classList.add( 'is-open' );
		openId = id;
		opener = el.querySelector( '.alf-card-link' );
		panel.hidden = false;
		panel.classList.add( 'is-loading' );
		if ( ! panel.innerHTML ) { panel.innerHTML = '<p class="alf-p">' + ( t.loading || '' ) + '</p>'; }
		post( 'aluleth_forms_board_panel', { entry: id } )
			.then( function ( data ) {
				if ( openId !== id ) { return; }
				panel.innerHTML = data.html;
				var title = panel.querySelector( '.alf-p-title' );
				if ( title ) { title.setAttribute( 'tabindex', '-1' ); title.focus(); }
			} )
			.catch( function ( err ) { toast( err.message || t.error, true ); closePanel(); } )
			.then( function () { panel.classList.remove( 'is-loading' ); } );
	}

	board.addEventListener( 'click', function ( e ) {
		var link = e.target.closest && e.target.closest( '.alf-card-link' );
		if ( ! link || e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1 ) { return; }
		e.preventDefault();
		openPanel( link.closest( '.alf-card' ) );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && ! panel.hidden ) { closePanel(); }
	} );

	panel.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.alf-p-close' ) ) { closePanel(); }
	} );

	// Status changed in the panel: move the card.
	panel.addEventListener( 'change', function ( e ) {
		if ( ! e.target.matches( '.alf-p-status select' ) ) { return; }
		var el = card( openId );
		if ( el ) { move( el, e.target.value ); }
	} );

	panel.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( form.classList.contains( 'alf-p-status' ) ) {
			e.preventDefault();
			var el = card( openId );
			if ( el ) { move( el, form.querySelector( 'select' ).value ); }
			return;
		}
		if ( form.classList.contains( 'alf-p-note' ) ) {
			e.preventDefault();
			var area = form.querySelector( 'textarea' );
			if ( ! area.value.trim() ) { area.focus(); return; }
			var button = form.querySelector( 'button' );
			button.disabled = true;
			post( 'aluleth_forms_board_note', { entry: openId, note: area.value } )
				.then( function ( data ) {
					var list = panel.querySelector( '.alf-p-notelist' );
					var wrap = document.createElement( 'div' );
					wrap.innerHTML = data.html;
					list.parentNode.replaceChild( wrap.firstChild, list );
					area.value = '';
					var el = card( openId );
					if ( el ) {
						var chip = el.querySelector( '.alf-chip--note' );
						var chips = el.querySelector( '.alf-chips' );
						if ( ! chips ) { chips = document.createElement( 'span' ); chips.className = 'alf-chips'; el.querySelector( '.alf-card-link' ).appendChild( chips ); }
						if ( ! chip ) { chip = document.createElement( 'span' ); chip.className = 'alf-chip alf-chip--note'; chips.appendChild( chip ); }
						var forms = t.notes || {};
						chip.textContent = String( ( data.count === 1 ? forms.singular : forms.plural ) || '%d' ).replace( '%d', data.count );
					}
				} )
				.catch( function ( err ) { toast( err.message || t.error, true ); } )
				.then( function () { button.disabled = false; } );
		}
	} );
}() );
