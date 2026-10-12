/* Aluleth Forms – front end. No dependencies. */
( function () {
	'use strict';
	var cfg = window.alulethForms || {};
	var t = cfg.i18n || {};

	function q( root, sel ) { return root.querySelector( sel ); }
	function qa( root, sel ) { return Array.prototype.slice.call( root.querySelectorAll( sel ) ); }

	function setError( field, msg ) {
		var box = q( field, '.alf-error' );
		var inputs = qa( field, 'input,select,textarea' );
		field.classList.toggle( 'has-error', !! msg );
		if ( box ) {
			box.textContent = msg || '';
			box.hidden = ! msg;
		}
		inputs.forEach( function ( el ) {
			if ( msg ) { el.setAttribute( 'aria-invalid', 'true' ); } else { el.removeAttribute( 'aria-invalid' ); }
		} );
	}

	function check( field ) {
		// Fields added by extensions bring their own check (window.alulethForms.checks).
		var holder = field.hasAttribute( 'data-alf-check' ) ? field : q( field, '[data-alf-check]' );
		var custom = holder && holder.getAttribute( 'data-alf-check' );
		if ( custom && cfg.checks && typeof cfg.checks[ custom ] === 'function' ) { return cfg.checks[ custom ]( field, t ) || ''; }
		var group = q( field, '.alf-choices' );
		if ( group ) {
			if ( group.getAttribute( 'data-required' ) && ! q( group, 'input:checked' ) ) { return t.choose; }
			return '';
		}
		var el = q( field, 'input,select,textarea' );
		if ( ! el || el.type === 'hidden' ) { return ''; }
		if ( el.type === 'checkbox' ) { return el.required && ! el.checked ? ( t.consent || t.required ) : ''; }
		var v = ( el.value || '' ).trim();
		if ( el.required && ! v ) { return t.required; }
		if ( v && el.validity && ! el.validity.valid ) { return el.type === 'email' ? t.email : ( el.validationMessage || t.required ); }
		return '';
	}

	function isOff( field ) {
		return !! ( field.closest( '[data-alf-cond][hidden]' ) );
	}

	function answer( form, id ) {
		var els = qa( form, '[name="alf[' + id + ']"],[name="alf[' + id + '][]"]' );
		if ( ! els.length || isOff( els[ 0 ] ) ) { return ''; }
		if ( els[ 0 ].type === 'checkbox' || els[ 0 ].type === 'radio' ) {
			var on = els.filter( function ( e ) { return e.checked; } ).map( function ( e ) { return e.value; } );
			return els[ 0 ].type === 'checkbox' && els.length === 1 && els[ 0 ].name.slice( -2 ) !== '[]' ? ( on.length ? '1' : '' ) : on;
		}
		return ( els[ 0 ].value || '' ).trim();
	}

	function test( rule, value ) {
		var list = Array.isArray( value ) ? value : ( value === '' ? [] : [ value ] );
		var low = list.map( function ( v ) { return String( v ).toLowerCase(); } );
		var want = String( rule.value || '' ).toLowerCase();
		var num = function ( v ) { return parseFloat( String( v ).replace( ',', '.' ) ); };
		switch ( rule.op ) {
			case 'empty': return ! list.length;
			case 'not_empty': return !! list.length;
			case 'is': return low.indexOf( want ) > -1;
			case 'is_not': return low.indexOf( want ) < 0;
			case 'contains': return !! want && low.some( function ( v ) { return v.indexOf( want ) > -1; } );
			case 'not_contains': return ! want || ! low.some( function ( v ) { return v.indexOf( want ) > -1; } );
			case 'gt': return list.length > 0 && ! isNaN( num( list[ 0 ] ) ) && ! isNaN( num( want ) ) && num( list[ 0 ] ) > num( want );
			case 'lt': return list.length > 0 && ! isNaN( num( list[ 0 ] ) ) && ! isNaN( num( want ) ) && num( list[ 0 ] ) < num( want );
		}
		return false;
	}

	function applyConditions( form ) {
		qa( form, '[data-alf-cond]' ).forEach( function ( box ) {
			var cond;
			try { cond = JSON.parse( box.getAttribute( 'data-alf-cond' ) ); } catch ( e ) { return; }
			var any = cond.match === 'any';
			var ok = any ? cond.rules.some( function ( r ) { return test( r, answer( form, r.field ) ); } ) : cond.rules.every( function ( r ) { return test( r, answer( form, r.field ) ); } );
			box.hidden = ! ok;
			qa( box, 'input,select,textarea' ).forEach( function ( el ) { el.disabled = ! ok; } );
			if ( ! ok ) { setError( box, '' ); }
		} );
	}

	// Error summary at the top of the form (Design › Error messages). Returns true when shown.
	function summarize( form, scope ) {
		var box = q( form, '.alf-summary' );
		if ( ! box ) { return false; }
		var list = q( box, 'ul' );
		list.textContent = '';
		qa( scope || form, '.alf-field.has-error' ).forEach( function ( field ) {
			if ( isOff( field ) ) { return; }
			var label = q( field, '.alf-label' );
			var err = q( field, '.alf-error' );
			var input = q( field, 'input:not([type=hidden]),select,textarea' );
			var li = document.createElement( 'li' );
			var a = document.createElement( 'a' );
			a.href = '#' + ( input && input.id ? input.id : '' );
			a.textContent = label ? label.textContent.replace( /\*\s*$/, '' ).trim() : '';
			a.addEventListener( 'click', function ( ev ) { if ( input ) { ev.preventDefault(); input.focus(); } } );
			li.appendChild( a );
			li.appendChild( document.createTextNode( ': ' + ( err ? err.textContent : '' ) ) );
			list.appendChild( li );
		} );
		box.hidden = ! list.children.length;
		if ( ! box.hidden ) { box.focus(); }
		return ! box.hidden;
	}

	function validate( scope ) {
		var first = null;
		qa( scope, '.alf-field' ).forEach( function ( field ) {
			if ( isOff( field ) ) { setError( field, '' ); return; }
			var msg = check( field );
			setError( field, msg );
			if ( msg && ! first ) { first = field; }
		} );
		if ( first ) {
			var el = q( first, 'input,select,textarea' );
			if ( el ) { el.focus(); }
		}
		return ! first;
	}

	// Anti-spam widget (Cloudflare Turnstile, hCaptcha or Google reCAPTCHA), shown
	// once the service's script is ready. Its answer goes in the hidden "alf_captcha".
	function captcha( form ) {
		var box = q( form, '.alf-captcha[data-provider]' );
		var input = q( form, 'input[name="alf_captcha"]' );
		if ( ! box || ! input ) { return null; }
		var provider = box.getAttribute( 'data-provider' );
		var key = box.getAttribute( 'data-sitekey' );
		var v3 = 'recaptcha' === provider && 'v3' === box.getAttribute( 'data-version' );
		var api = { id: null, invisible: v3 };
		var opts = {
			sitekey: key,
			callback: function ( token ) { input.value = token; },
			'expired-callback': function () { input.value = ''; }
		};
		function render() {
			try {
				if ( 'turnstile' === provider && window.turnstile ) {
					opts.appearance = box.getAttribute( 'data-appearance' ) || 'interaction-only';
					api.id = window.turnstile.render( box, opts );
					return true;
				}
				if ( 'hcaptcha' === provider && window.hcaptcha ) { api.id = window.hcaptcha.render( box, opts ); return true; }
				if ( 'recaptcha' === provider && window.grecaptcha ) {
					if ( v3 ) { return true; }
					if ( window.grecaptcha.render ) { api.id = window.grecaptcha.render( box, opts ); return true; }
				}
			} catch ( err ) { return true; }
			return false;
		}
		( function wait( n ) {
			if ( render() || n > 150 ) { return; }
			setTimeout( function () { wait( n + 1 ); }, 100 );
		}( 0 ) );
		api.token = function () {
			if ( v3 && window.grecaptcha && window.grecaptcha.ready ) {
				return new Promise( function ( resolve ) {
					window.grecaptcha.ready( function () {
						window.grecaptcha.execute( key, { action: 'submit' } ).then( function ( token ) { input.value = token; resolve( token ); }, function () { resolve( '' ); } );
					} );
				} );
			}
			return Promise.resolve( input.value );
		};
		api.reset = function () {
			input.value = '';
			try {
				if ( 'turnstile' === provider && window.turnstile ) { window.turnstile.reset( api.id ); }
				if ( 'hcaptcha' === provider && window.hcaptcha ) { window.hcaptcha.reset( api.id ); }
				if ( 'recaptcha' === provider && ! v3 && window.grecaptcha ) { window.grecaptcha.reset( api.id ); }
			} catch ( err ) {}
		};
		return api;
	}

	// Payment forms: live total, and the PayPal buttons (the amount is computed again on the server).
	function money( value, box ) {
		var currency = ( box && box.getAttribute( 'data-currency' ) ) || cfg.currency || 'USD';
		var locale = ( box && box.getAttribute( 'data-locale' ) ) || document.documentElement.lang || 'en';
		try { return new Intl.NumberFormat( locale, { style: 'currency', currency: currency } ).format( value ); } catch ( e ) { return value.toFixed( 2 ) + ' ' + currency; }
	}

	function cart( form ) {
		var items = [];
		qa( form, '[data-price]' ).forEach( function ( el ) {
			if ( isOff( el ) ) { return; }
			if ( ( el.type === 'radio' || el.type === 'checkbox' ) && ! el.checked ) { return; }
			if ( el.tagName === 'OPTION' && ! el.selected ) { return; }
			var price = parseFloat( el.getAttribute( 'data-price' ) ) || 0;
			// Quantity: the element itself (data-qty on a number input), or the input it points to.
			var qty = 1;
			if ( el.hasAttribute( 'data-qty' ) ) {
				var src = el.getAttribute( 'data-qty' ) ? form.querySelector( '#' + el.getAttribute( 'data-qty' ) ) : el;
				qty = src ? parseInt( src.value, 10 ) || 0 : 0;
			}
			if ( price > 0 && qty > 0 ) { items.push( { name: el.getAttribute( 'data-item' ), price: price, quantity: qty } ); }
		} );
		qa( form, '[data-amount]' ).forEach( function ( el ) {
			if ( isOff( el ) ) { return; }
			var v = parseFloat( String( el.value || '' ).replace( /\s/g, '' ).replace( ',', '.' ) );
			if ( v > 0 ) { items.push( { name: el.getAttribute( 'data-item' ), price: Math.round( v * 100 ) / 100, quantity: 1 } ); }
		} );
		var total = items.reduce( function ( sum, it ) { return sum + it.price * it.quantity; }, 0 );
		return { items: items, total: Math.round( total * 100 ) / 100 };
	}

	function showTotal( form ) {
		var pay = q( form, '.alf-pay' ) || q( form, '[data-currency]' );
		var c = cart( form );
		qa( form, '.alf-total' ).forEach( function ( box ) {
			var list = q( box, '.alf-total-lines' );
			list.textContent = '';
			c.items.forEach( function ( it ) {
				var li = document.createElement( 'li' );
				var name = document.createElement( 'span' );
				var price = document.createElement( 'span' );
				name.textContent = it.quantity > 1 ? it.name + ' × ' + it.quantity : it.name;
				price.textContent = money( it.price * it.quantity, pay );
				li.appendChild( name );
				li.appendChild( price );
				list.appendChild( li );
			} );
			q( box, '.alf-total-value' ).textContent = money( c.total, pay );
		} );
		// Extensions (store totals with tax and coupons) listen to this event.
		try { form.dispatchEvent( new window.CustomEvent( 'alf:cart', { detail: c } ) ); } catch ( e ) {}
		return c;
	}

	function payButtons( form, ctx ) {
		var pay = q( form, '.alf-pay' );
		var box = pay && q( pay, '.alf-paypal' );
		if ( ! box || ! cfg.pay ) { return; }
		var key = '';
		function report( res ) {
			var errors = ( res && res.errors ) || {};
			qa( form, '.alf-field' ).forEach( function ( field ) { setError( field, errors[ field.getAttribute( 'data-field' ) ] || '' ); } );
			summarize( form );
			ctx.status.className = 'alf-status is-error';
			ctx.status.textContent = ( res && res.message ) || t.error;
		}
		function render() {
			if ( ! window.paypal || ! window.paypal.Buttons ) { return false; }
			window.paypal.Buttons( {
				style: { layout: 'vertical', color: pay.getAttribute( 'data-color' ) || 'gold', shape: pay.getAttribute( 'data-shape' ) || 'rect', label: 'pay' },
				onClick: function ( data, actions ) {
					ctx.status.className = 'alf-status';
					ctx.status.textContent = '';
					if ( ! validate( form ) ) {
						summarize( form );
						ctx.status.className = 'alf-status is-error';
						ctx.status.textContent = t.check;
						return actions.reject();
					}
					if ( showTotal( form ).total <= 0 ) {
						ctx.status.className = 'alf-status is-error';
						ctx.status.textContent = t.payNothing || t.error;
						return actions.reject();
					}
					return actions.resolve();
				},
				createOrder: function () {
					return ( ctx.guard ? ctx.guard.token() : Promise.resolve( 'none' ) ).then( function ( token ) {
						if ( ! token ) { var w = new Error( 'captcha' ); w.alfCaptcha = true; throw w; }
						return fetch( cfg.pay + '/create', { method: 'POST', body: new FormData( form ), credentials: 'same-origin' } );
					} ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
						if ( ! res || ! res.success ) { report( res ); if ( ctx.guard ) { ctx.guard.reset(); } throw new Error( 'alf-handled' ); }
						key = res.key;
						return res.orderID;
					} );
				},
				onApprove: function ( data ) {
					ctx.status.className = 'alf-status';
					ctx.status.textContent = t.payConfirm || t.sending;
					form.classList.add( 'is-sending' );
					return fetch( cfg.pay + '/capture', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify( { orderID: data.orderID, key: key } ), credentials: 'same-origin' } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) {
							form.classList.remove( 'is-sending' );
							if ( res && res.success ) { ctx.done( res ); return; }
							report( res );
						} )
						.catch( function () { form.classList.remove( 'is-sending' ); report( { message: t.payCheck || t.error } ); } );
				},
				onCancel: function () {
					ctx.status.className = 'alf-status';
					ctx.status.textContent = t.payCancel || '';
				},
				onError: function ( err ) {
					if ( err && ( err.message === 'alf-handled' || String( err ).indexOf( 'alf-handled' ) > -1 ) ) { return; }
					ctx.status.className = 'alf-status is-error';
					ctx.status.textContent = err && err.alfCaptcha ? ( t.captcha || t.error ) : ( t.payError || t.error );
				}
			} ).render( box );
			return true;
		}
		( function wait( n ) {
			if ( render() || n > 200 ) { return; }
			setTimeout( function () { wait( n + 1 ); }, 100 );
		}( 0 ) );
	}

	function init( form ) {
		if ( form.alfReady ) { return; }
		form.alfReady = true;
		var steps = qa( form, '.alf-step[data-step]' );
		var progress = qa( form, '.alf-progress-item' );
		var status = q( form, '.alf-status' );
		var next = q( form, '.alf-next' );
		var back = q( form, '.alf-back' );
		var submit = q( form, '.alf-submit' );
		var guard = captcha( form );
		var current = 0;
		var isPay = !! q( form, '.alf-pay' );
		// Used by add-ons that bring their own way to pay (window.alulethForms).
		form.alfGuard = guard;
		form.alfCheck = function () {
			status.className = 'alf-status';
			status.textContent = '';
			if ( validate( form ) ) { summarize( form ); return true; }
			summarize( form );
			status.className = 'alf-status is-error';
			status.textContent = t.check;
			return false;
		};
		form.alfReport = function ( res ) {
			var errors = ( res && res.errors ) || {};
			qa( form, '.alf-field' ).forEach( function ( field ) { setError( field, errors[ field.getAttribute( 'data-field' ) ] || '' ); } );
			summarize( form );
			status.className = 'alf-status is-error';
			status.textContent = ( res && res.message ) || t.error;
			if ( guard ) { guard.reset(); }
		};
		// Replaces the form with the success message, then tells add-ons
		// (an "alf:done" event on the message, with the server's answer).
		function finish( res ) {
			var box = document.createElement( 'div' );
			var parent = form.parentNode;
			box.className = 'alf-done';
			box.setAttribute( 'role', 'status' );
			box.setAttribute( 'tabindex', '-1' );
			box.textContent = res.message;
			parent.replaceChild( box, form );
			box.focus();
			if ( typeof window.CustomEvent === 'function' ) {
				box.dispatchEvent( new CustomEvent( 'alf:done', { bubbles: true, detail: { res: res, box: box, form: form } } ) );
			}
		}
		form.alfDone = function ( message, res ) {
			var data = res && typeof res === 'object' ? res : {};
			data.message = message;
			finish( data );
		};
		var hasCart = isPay || !! q( form, '[data-price],.alf-total' );

		function show( index ) {
			current = index;
			steps.forEach( function ( step, i ) { step.hidden = i !== index; } );
			progress.forEach( function ( item, i ) {
				item.classList.toggle( 'is-current', i === index );
				item.classList.toggle( 'is-done', i < index );
				if ( i === index ) { item.setAttribute( 'aria-current', 'step' ); } else { item.removeAttribute( 'aria-current' ); }
			} );
			if ( back ) { back.hidden = index === 0; }
			if ( next ) { next.hidden = index === steps.length - 1; }
			if ( submit ) { submit.hidden = steps.length > 1 && index !== steps.length - 1; }
			if ( form.getBoundingClientRect().top < 0 ) { form.scrollIntoView( { block: 'start' } ); }
			var el = q( steps[ index ], 'input:not([type=hidden]),select,textarea' );
			if ( el ) { el.focus( { preventScroll: true } ); }
		}

		function hasFields( step ) {
			return qa( step, '.alf-field' ).some( function ( f ) { return ! isOff( f ); } );
		}
		function nextIndex( from, dir ) {
			var i = from + dir;
			while ( i > 0 && i < steps.length - 1 && ! hasFields( steps[ i ] ) ) { i += dir; }
			return Math.max( 0, Math.min( steps.length - 1, i ) );
		}

		if ( next ) {
			next.addEventListener( 'click', function () {
				if ( validate( steps[ current ] ) ) {
					summarize( form, steps[ current ] );
					show( nextIndex( current, 1 ) );
				} else {
					summarize( form, steps[ current ] );
				}
			} );
		}
		if ( back ) {
			back.addEventListener( 'click', function () { show( nextIndex( current, -1 ) ); } );
		}

		function done( res ) {
			if ( res.redirect ) { window.location.href = res.redirect; return; }
			finish( res );
		}

		applyConditions( form );
		form.addEventListener( 'change', function () { applyConditions( form ); if ( hasCart ) { showTotal( form ); } } );
		if ( hasCart ) {
			form.addEventListener( 'input', function () { showTotal( form ); } );
			showTotal( form );
		}
		if ( isPay ) {
			payButtons( form, { status: status, guard: guard, done: done } );
		}
		form.addEventListener( 'input', function ( e ) {
			applyConditions( form );
			var field = e.target.closest && e.target.closest( '.alf-field.has-error' );
			if ( field && ! check( field ) ) { setError( field, '' ); }
		} );

		form.addEventListener( 'submit', function ( e ) {
			// Inside a page builder's editor the form is a preview: never send it.
			if ( document.body.classList.contains( 'elementor-editor-active' ) ) { e.preventDefault(); return; }
			if ( isPay ) { e.preventDefault(); return; }
			if ( ! window.fetch || ! window.FormData || ! cfg.endpoint ) { return; }
			e.preventDefault();
			if ( ! validate( form ) ) {
				status.textContent = t.check;
				status.className = 'alf-status is-error';
				summarize( form );
				return;
			}
			summarize( form );
			submit.disabled = true;
			form.classList.add( 'is-sending' );
			status.className = 'alf-status';
			status.textContent = t.sending;

			( guard ? guard.token() : Promise.resolve( 'none' ) )
				.then( function ( token ) {
					if ( ! token ) {
						var waitErr = new Error( 'captcha' );
						waitErr.alfCaptcha = true;
						throw waitErr;
					}
					return fetch( cfg.endpoint, { method: 'POST', body: new FormData( form ), credentials: 'same-origin' } );
				} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) {
						if ( res.redirect ) { window.location.href = res.redirect; return; }
						finish( res );
						return;
					}
					var errors = ( res && res.errors ) || {};
					var firstStep = -1;
					qa( form, '.alf-field' ).forEach( function ( field ) {
						var id = field.getAttribute( 'data-field' );
						setError( field, errors[ id ] || '' );
						if ( errors[ id ] && firstStep < 0 ) {
							var step = field.closest( '.alf-step[data-step]' );
							firstStep = step ? parseInt( step.getAttribute( 'data-step' ), 10 ) : 0;
						}
					} );
					if ( firstStep >= 0 && steps.length ) { show( firstStep ); }
					summarize( form );
					status.className = 'alf-status is-error';
					status.textContent = ( res && res.message ) || t.error;
					// A captcha answer works once: ask for a new one.
					if ( guard ) { guard.reset(); }
				} )
				.catch( function ( err ) {
					status.className = 'alf-status is-error';
					status.textContent = err && err.alfCaptcha ? ( t.captcha || t.error ) : t.error;
				} )
				.then( function () {
					if ( submit ) { submit.disabled = false; }
					form.classList.remove( 'is-sending' );
				} );
		} );
	}

	function start( root ) { qa( root && root.querySelectorAll ? root : document, 'form[data-alf]' ).forEach( init ); }
	// One view per page for the forms shown (no cookie, no personal data).
	function countViews() {
		if ( ! cfg.views || ! navigator.sendBeacon || ! window.FormData ) { return; }
		var ids = [], pairs = [];
		qa( document, 'form[data-alf]' ).forEach( function ( f ) {
			var id = f.getAttribute( 'data-alf' ), t = f.querySelector( 'input[name="alf_token"]' );
			if ( f.getAttribute( 'data-alf-count' ) !== '0' && t && ids.indexOf( id ) < 0 ) { ids.push( id ); pairs.push( id + ':' + t.value ); }
		} );
		if ( ! ids.length ) { return; }
		var body = new FormData();
		body.append( 'forms', pairs.slice( 0, 5 ).join( ',' ) );
		try { navigator.sendBeacon( cfg.views, body ); } catch ( e ) {}
	}
	cfg.init = start;
	cfg.checks = cfg.checks || {};
	cfg.money = money;
	cfg.cart = cart;
	window.alulethForms = cfg;
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', function () { start(); countViews(); } ); } else { start(); countViews(); }

	// Forms added later by Elementor (editor preview, popups, loops).
	function elementorHook() {
		var ef = window.elementorFrontend;
		if ( ! ef || ! ef.hooks ) { return false; }
		ef.hooks.addAction( 'frontend/element_ready/aluleth-form.default', function ( $el ) { start( $el && $el[ 0 ] ? $el[ 0 ] : $el ); } );
		return true;
	}
	if ( ! elementorHook() ) { window.addEventListener( 'elementor/frontend/init', elementorHook ); }
}() );
