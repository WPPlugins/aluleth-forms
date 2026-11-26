/* Aluleth Forms – visual builder. Plain wp.element (no build step). */
( function ( wp, cfg ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var apiFetch = wp.apiFetch;
	var D = 'aluleth-forms';
	// Extensions (Aluleth Forms Pro) add field types, panels and settings sections with wp.hooks.
	var hooks = wp.hooks || { applyFilters: function ( name, value ) { return value; } };

	/* ---------- Field catalogue ---------- */

	var GROUPS = [
		{ id: 'text', label: __( 'Text', 'aluleth-forms' ), types: [ 'text', 'textarea', 'email', 'phone', 'number', 'name' ] },
		{ id: 'choice', label: __( 'Choice', 'aluleth-forms' ), types: [ 'select', 'checkboxes', 'radio', 'date', 'consent' ] },
		{ id: 'payment', label: __( 'Payment', 'aluleth-forms' ), types: [ 'price_one', 'price_many', 'price_fixed', 'amount', 'total' ] },
		{ id: 'layout', label: __( 'Layout and more', 'aluleth-forms' ), types: [ 'step', 'heading', 'paragraph', 'hidden' ] }
	];

	var TYPES = {
		text: { label: __( 'Short text', 'aluleth-forms' ), icon: 'M5 7h14M12 7v11', make: function () { return { type: 'text', label: __( 'Short text', 'aluleth-forms' ) }; } },
		textarea: { label: __( 'Paragraph', 'aluleth-forms' ), icon: 'M5 7h14M5 12h14M5 17h9', make: function () { return { type: 'textarea', label: __( 'Message', 'aluleth-forms' ) }; } },
		email: { label: __( 'Email', 'aluleth-forms' ), icon: 'M4 7l8 6 8-6M4 7h16v10H4z', make: function () { return { type: 'email', label: __( 'Email', 'aluleth-forms' ), required: true }; } },
		phone: { label: __( 'Phone', 'aluleth-forms' ), icon: 'M8 3h8v18H8zM11 18h2', make: function () { return { type: 'phone', label: __( 'Phone', 'aluleth-forms' ), map: 'phone' }; } },
		number: { label: __( 'Number', 'aluleth-forms' ), icon: 'M9 4L7 20M17 4l-2 16M4 9h16M3 15h16', make: function () { return { type: 'number', label: __( 'Number', 'aluleth-forms' ) }; } },
		name: { label: __( 'Name', 'aluleth-forms' ), icon: 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 20a8 8 0 0116 0', make: function () { return { type: 'text', label: __( 'Your name', 'aluleth-forms' ), map: 'full_name', autocomplete: 'name', required: true }; } },
		select: { label: __( 'Dropdown', 'aluleth-forms' ), icon: 'M4 6h16v12H4zM14 11l2 2 2-2', make: function () { return { type: 'select', label: __( 'Choose one', 'aluleth-forms' ), options: defaultOptions() }; } },
		checkboxes: { label: __( 'Checkboxes', 'aluleth-forms' ), icon: 'M4 5h6v6H4zM14 8h6M4 15h6v6H4zM14 18h6', make: function () { return { type: 'checkboxes', label: __( 'Choose one or more', 'aluleth-forms' ), options: defaultOptions() }; } },
		radio: { label: __( 'Single choice', 'aluleth-forms' ), icon: 'M7 8a2.5 2.5 0 100 .1M13 8h7M7 16a2.5 2.5 0 100 .1M13 16h7', make: function () { return { type: 'radio', label: __( 'Choose one', 'aluleth-forms' ), options: defaultOptions() }; } },
		date: { label: __( 'Date', 'aluleth-forms' ), icon: 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4', make: function () { return { type: 'date', label: __( 'Date', 'aluleth-forms' ) }; } },
		consent: { label: __( 'Consent', 'aluleth-forms' ), icon: 'M4 4h16v16H4zM8 12l3 3 5-6', make: function () { return { type: 'consent', label: __( 'Send me news and offers by email. I can unsubscribe at any time.', 'aluleth-forms' ), marketing: true }; } },
		step: { label: __( 'Step', 'aluleth-forms' ), icon: 'M4 12h4M10 12h4M16 12h4', make: function () { return { type: 'step', label: __( 'Next step', 'aluleth-forms' ) }; } },
		heading: { label: __( 'Heading', 'aluleth-forms' ), icon: 'M6 4v16M18 4v16M6 12h12', make: function () { return { type: 'heading', label: __( 'Heading', 'aluleth-forms' ) }; } },
		paragraph: { label: __( 'Text block', 'aluleth-forms' ), icon: 'M4 6h16M4 10h16M4 14h10', make: function () { return { type: 'paragraph', label: '', text: __( 'Write a short explanation here.', 'aluleth-forms' ) }; } },
		price_one: { label: __( 'Choice with prices', 'aluleth-forms' ), icon: 'M7 8a2.5 2.5 0 100 .1M13 8h7M7 16a2.5 2.5 0 100 .1M13 16h7', make: function () { return { type: 'product', mode: 'one', label: __( 'Choose a service', 'aluleth-forms' ), required: true, items: [ { label: __( 'Basic', 'aluleth-forms' ), price: 49 }, { label: __( 'Complete', 'aluleth-forms' ), price: 89 } ] }; } },
		price_many: { label: __( 'Extras with prices', 'aluleth-forms' ), icon: 'M4 5h6v6H4zM14 8h6M4 15h6v6H4zM14 18h6', make: function () { return { type: 'product', mode: 'many', label: __( 'Extras', 'aluleth-forms' ), items: [ { label: __( 'Extra option', 'aluleth-forms' ), price: 25 } ] }; } },
		price_fixed: { label: __( 'Fixed price', 'aluleth-forms' ), icon: 'M4 7h16v10H4zM12 10v4M10 12h4', make: function () { return { type: 'product', mode: 'single', label: __( 'Price', 'aluleth-forms' ), items: [ { label: __( 'Deposit', 'aluleth-forms' ), price: 30 } ] }; } },
		amount: { label: __( 'Free amount', 'aluleth-forms' ), icon: 'M12 3v18M17 7H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6', make: function () { return { type: 'amount', label: __( 'Amount', 'aluleth-forms' ), required: true, min: 1 }; } },
		total: { label: __( 'Total', 'aluleth-forms' ), icon: 'M5 6h14M5 12h14M5 18h14', make: function () { return { type: 'total', label: __( 'Total', 'aluleth-forms' ) }; } },
		hidden: { label: __( 'Hidden', 'aluleth-forms' ), icon: 'M3 12s3-6 9-6 9 6 9 6-3 6-9 6-9-6-9-6zM4 4l16 16', make: function () { return { type: 'hidden', label: __( 'Hidden value', 'aluleth-forms' ) }; } }
	};

	var TYPE_NAMES = {
		text: __( 'Short text', 'aluleth-forms' ), textarea: __( 'Paragraph', 'aluleth-forms' ), email: __( 'Email', 'aluleth-forms' ), phone: __( 'Phone', 'aluleth-forms' ), number: __( 'Number', 'aluleth-forms' ),
		select: __( 'Dropdown', 'aluleth-forms' ), checkboxes: __( 'Checkboxes', 'aluleth-forms' ), radio: __( 'Single choice', 'aluleth-forms' ), date: __( 'Date', 'aluleth-forms' ), consent: __( 'Consent', 'aluleth-forms' ),
		step: __( 'Step', 'aluleth-forms' ), heading: __( 'Heading', 'aluleth-forms' ), paragraph: __( 'Text block', 'aluleth-forms' ), hidden: __( 'Hidden', 'aluleth-forms' ),
		product: __( 'Price', 'aluleth-forms' ), amount: __( 'Free amount', 'aluleth-forms' ), total: __( 'Total', 'aluleth-forms' )
	};
	var PAY = cfg.payments || { ready: false, currency: 'USD', locale: 'en' };
	function money( v ) {
		try { return new Intl.NumberFormat( PAY.locale, { style: 'currency', currency: PAY.currency } ).format( Number( v ) || 0 ); } catch ( e ) { return ( Number( v ) || 0 ).toFixed( 2 ) + ' ' + PAY.currency; }
	}
	function productName( f ) {
		if ( f.type !== 'product' ) { return TYPE_NAMES[ f.type ]; }
		return { one: __( 'Choice with prices', 'aluleth-forms' ), many: __( 'Extras with prices', 'aluleth-forms' ), single: __( 'Fixed price', 'aluleth-forms' ) }[ f.mode ] || TYPE_NAMES.product;
	}

	var INPUTS = [ 'text', 'email', 'phone', 'number', 'textarea', 'select', 'radio', 'checkboxes', 'date', 'consent', 'hidden', 'product', 'amount' ];
	var PAY_TYPES = [ 'product', 'amount' ];
	var NO_COND = [ 'hidden', 'product', 'amount' ];
	var STYLES = [
		{ id: 'clean', label: __( 'Clean', 'aluleth-forms' ), text: __( 'White fields with a fine border.', 'aluleth-forms' ) },
		{ id: 'soft', label: __( 'Soft', 'aluleth-forms' ), text: __( 'Tinted fields, round corners.', 'aluleth-forms' ) },
		{ id: 'dark', label: __( 'Dark', 'aluleth-forms' ), text: __( 'A dark card with a bright button.', 'aluleth-forms' ) }
	];

	function defaultOptions() {
		return [ __( 'Option 1', 'aluleth-forms' ), __( 'Option 2', 'aluleth-forms' ), __( 'Option 3', 'aluleth-forms' ) ];
	}

	/* ---------- Helpers ---------- */

	function clone( v ) { return JSON.parse( JSON.stringify( v ) ); }

	function slug( text ) {
		return String( text || '' ).toLowerCase().normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).replace( /[^a-z0-9]+/g, '_' ).replace( /^_+|_+$/g, '' ).slice( 0, 30 ) || 'field';
	}

	function uniqueId( base, fields, skip ) {
		var ids = {};
		fields.forEach( function ( f, i ) { if ( i !== skip ) { ids[ f.id ] = true; } } );
		var id = base, n = 2;
		while ( ids[ id ] ) { id = base + '_' + n; n++; }
		return id;
	}

	function newField( key, fields ) {
		var f = TYPES[ key ].make();
		var base = f.type === 'email' ? 'email' : ( [ 'step', 'heading', 'paragraph', 'total' ].indexOf( f.type ) > -1 ? f.type : slug( f.label ) );
		f.id = uniqueId( base, fields );
		return normalise( f );
	}

	function normalise( f ) {
		var out = clone( f );
		if ( INPUTS.indexOf( out.type ) > -1 ) {
			out.required = !! out.required;
			out.placeholder = out.placeholder || '';
			out.help = out.help || '';
			out.width = out.width === 'half' ? 'half' : 'full';
			out.map = out.map || '';
			out.default = out.default || '';
			out.autocomplete = out.autocomplete || '';
		}
		if ( [ 'select', 'radio', 'checkboxes' ].indexOf( out.type ) > -1 && ! Array.isArray( out.options ) ) { out.options = defaultOptions(); }
		if ( out.type === 'consent' ) { out.marketing = !! out.marketing; }
		if ( out.type === 'paragraph' ) { out.text = out.text || ''; }
		if ( out.type === 'product' ) {
			out.mode = [ 'one', 'many', 'single' ].indexOf( out.mode ) > -1 ? out.mode : 'one';
			out.items = Array.isArray( out.items ) && out.items.length ? out.items : [ { label: __( 'Option 1', 'aluleth-forms' ), price: 10 } ];
		}
		if ( out.type === 'amount' ) { out.min = out.min === undefined || out.min === null ? 1 : out.min; out.max = out.max === undefined ? null : out.max; }
		return hooks.applyFilters( 'alulethForms.builder.normalise', out );
	}

	function stripTags( html ) { return new window.DOMParser().parseFromString( html || '', 'text/html' ).body.textContent || ''; }

	/* ---------- Small UI pieces ---------- */

	function Icon( props ) {
		return el( 'svg', { width: props.size || 18, height: props.size || 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true, focusable: 'false' },
			el( 'path', { d: props.d } ) );
	}

	var ICONS = {
		back: 'M15 18l-6-6 6-6', undo: 'M9 14L4 9l5-5M4 9h10a6 6 0 010 12h-3', redo: 'M15 14l5-5-5-5M20 9H10a6 6 0 000 12h3',
		desktop: 'M3 4h18v12H3zM8 20h8', phone: 'M7 3h10v18H7zM11 18h2', up: 'M12 19V5M6 11l6-6 6 6', down: 'M12 5v14M6 13l6 6 6-6',
		copy: 'M8 8h12v12H8zM16 8V4H4v12h4', trash: 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13', plus: 'M12 5v14M5 12h14', close: 'M6 6l12 12M18 6L6 18',
		search: 'M11 4a7 7 0 100 14 7 7 0 000-14zM20 20l-3.5-3.5', grip: 'M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01', link: 'M10 13a5 5 0 007 .5l3-3a5 5 0 00-7-7l-1.5 1.5M14 11a5 5 0 00-7-.5l-3 3a5 5 0 007 7l1.5-1.5'
	};

	function IconButton( props ) {
		return el( 'button', {
			type: 'button', className: 'alf-b-icon' + ( props.active ? ' is-active' : '' ) + ( props.className ? ' ' + props.className : '' ),
			'aria-label': props.label, title: props.label, onClick: props.onClick, disabled: props.disabled, 'aria-pressed': props.pressed
		}, el( Icon, { d: ICONS[ props.icon ] } ) );
	}

	function Field( props ) {
		var id = 'alf-b-' + props.name;
		return el( 'div', { className: 'alf-b-control' },
			el( 'label', { htmlFor: id }, props.label ),
			props.multiline
				? el( 'textarea', { id: id, rows: props.rows || 3, value: props.value || '', placeholder: props.placeholder || '', onChange: function ( e ) { props.onChange( e.target.value ); } } )
				: el( 'input', { id: id, type: props.type || 'text', disabled: !! props.disabled, value: props.value === null || props.value === undefined ? '' : props.value, placeholder: props.placeholder || '', onChange: function ( e ) { props.onChange( e.target.value ); } } ),
			props.help ? el( 'p', { className: 'alf-b-hint' }, props.help ) : null
		);
	}

	function Toggle( props ) {
		var id = 'alf-b-' + props.name;
		return el( 'div', { className: 'alf-b-toggle' },
			el( 'label', { htmlFor: id }, props.label, props.help ? el( 'span', { className: 'alf-b-hint' }, props.help ) : null ),
			el( 'button', { id: id, type: 'button', role: 'switch', 'aria-checked': !! props.checked, className: 'alf-b-switch' + ( props.checked ? ' is-on' : '' ), onClick: function () { props.onChange( ! props.checked ); } }, el( 'span' ) )
		);
	}

	function Segmented( props ) {
		return el( 'div', { className: 'alf-b-control' },
			el( 'span', { className: 'alf-b-label', id: 'alf-b-seg-' + props.name }, props.label ),
			el( 'div', { className: 'alf-b-seg', role: 'radiogroup', 'aria-labelledby': 'alf-b-seg-' + props.name },
				props.options.map( function ( o ) {
					return el( 'button', { key: o.value, type: 'button', role: 'radio', 'aria-checked': props.value === o.value, className: props.value === o.value ? 'is-on' : '', onClick: function () { props.onChange( o.value ); } }, o.label );
				} ) )
		);
	}

	/* ---------- Live preview of one field (same markup and classes as the front end) ---------- */

	function Preview( props ) {
		var f = props.field;
		var mark = f.required ? el( 'span', { className: 'alf-required', 'aria-hidden': true }, '*' ) : null;
		var label = function () { return el( 'span', { className: 'alf-label' }, stripTags( f.label ) || productName( f ), mark ); };
		var helpTop = !! props.helpTop && f.type !== 'consent';
		var help = f.help && ! helpTop ? el( 'p', { className: 'alf-help' }, f.help ) : null;
		var helpUp = f.help && helpTop ? el( 'p', { className: 'alf-help' }, f.help ) : null;
		var cls = 'alf-field alf-field--' + f.type;
		var custom = hooks.applyFilters( 'alulethForms.builder.preview', null, f, { label: label, help: help, helpUp: helpUp, mark: mark, cls: cls, ui: UI } );
		if ( custom ) { return custom; }

		switch ( f.type ) {
			case 'heading': return el( 'h3', { className: 'alf-heading' }, stripTags( f.label ) || TYPE_NAMES.heading );
			case 'paragraph': return el( 'p', { className: 'alf-paragraph' }, stripTags( f.text || f.label ) || TYPE_NAMES.paragraph );
			case 'step': return el( 'div', { className: 'alf-b-stepline' }, el( 'span', null, sprintf( /* translators: %s: step title. */ __( 'New step: %s', 'aluleth-forms' ), stripTags( f.label ) || TYPE_NAMES.step ) ) );
			case 'hidden': return el( 'div', { className: 'alf-b-hidden' }, el( Icon, { d: TYPES.hidden.icon, size: 16 } ), sprintf( /* translators: 1: field label, 2: its value. */ __( 'Hidden field “%1$s”%2$s', 'aluleth-forms' ), stripTags( f.label ), f.default ? ' = ' + f.default : '' ) );
			case 'consent':
				return el( 'div', { className: 'alf-field alf-field--consent' }, el( 'span', { className: 'alf-consent' }, el( 'input', { type: 'checkbox', tabIndex: -1, readOnly: true } ), el( 'span', null, stripTags( f.label ), mark ) ), help );
			case 'select':
				return el( 'div', { className: cls }, label(), helpUp, el( 'div', { className: 'alf-input alf-b-select' }, f.placeholder || __( 'Choose…', 'aluleth-forms' ) ), help );
			case 'radio':
			case 'checkboxes':
				return el( 'div', { className: cls }, label(), helpUp, el( 'div', { className: 'alf-choices' }, ( f.options || [] ).map( function ( o, i ) {
					return el( 'span', { key: i, className: 'alf-choice' }, el( 'input', { type: f.type === 'radio' ? 'radio' : 'checkbox', tabIndex: -1, readOnly: true } ), el( 'span', null, o ) );
				} ) ), help );
			case 'product':
				if ( f.mode === 'single' ) {
					var it = ( f.items || [] )[ 0 ] || { label: '', price: 0 };
					return el( 'div', { className: cls }, label(), el( 'p', { className: 'alf-line' }, el( 'span', null, it.label ), el( 'strong', null, money( it.price ) ) ), help );
				}
				return el( 'div', { className: cls }, label(), helpUp, el( 'div', { className: 'alf-choices alf-prices' }, ( f.items || [] ).map( function ( o, k ) {
					return el( 'span', { key: k, className: 'alf-choice alf-price-choice' }, el( 'input', { type: f.mode === 'one' ? 'radio' : 'checkbox', tabIndex: -1, readOnly: true } ), el( 'span', { className: 'alf-price-name' }, o.label ), el( 'strong', { className: 'alf-price' }, ( f.mode === 'many' ? '+ ' : '' ) + money( o.price ) ) );
				} ) ), help );
			case 'amount':
				return el( 'div', { className: cls }, label(), helpUp, el( 'span', { className: 'alf-amount' }, el( 'span', { className: 'alf-currency' }, PAY.currency ), el( 'div', { className: 'alf-input' }, f.placeholder ) ), help );
			case 'total':
				return el( 'div', { className: 'alf-field alf-field--total' }, el( 'div', { className: 'alf-total' }, el( 'p', { className: 'alf-total-row' }, el( 'span', null, stripTags( f.label ) || TYPE_NAMES.total ), el( 'strong', null, money( props.total || 0 ) ) ) ) );
			case 'textarea':
				return el( 'div', { className: cls }, label(), helpUp, el( 'div', { className: 'alf-input alf-b-area' }, f.placeholder ), help );
			default:
				return el( 'div', { className: cls }, label(), helpUp, el( 'div', { className: 'alf-input' }, f.placeholder || ( f.type === 'date' ? __( 'dd / mm / yyyy', 'aluleth-forms' ) : '' ) ), help );
		}
	}

	/* Pieces shared with extensions. */
	var UI = { el: el, Fragment: Fragment, useState: useState, useEffect: useEffect, apiFetch: apiFetch, Field: Field, Toggle: Toggle, Segmented: Segmented, Icon: Icon, IconButton: IconButton, ICONS: ICONS, money: money, clone: clone, stripTags: stripTags };

	/* ---------- App ---------- */

	function designVars( design ) {
		var site = cfg.site || { colors: [], fonts: [] };
		var out = {};
		var hex = /^#[0-9a-f]{6}$/i.test( design.accent || '' ) ? design.accent : ( site.colors.filter( function ( c ) { return c.token === design.accent; } )[ 0 ] || {} ).hex;
		if ( hex ) {
			var rgb = [ 1, 3, 5 ].map( function ( i ) { var c = parseInt( hex.substr( i, 2 ), 16 ) / 255; return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 ); } );
			var lum = 0.2126 * rgb[ 0 ] + 0.7152 * rgb[ 1 ] + 0.0722 * rgb[ 2 ];
			out[ '--alf-accent' ] = hex; out[ '--alf-focus' ] = hex;
			out[ '--alf-on-accent' ] = ( 1.05 / ( lum + 0.05 ) ) >= ( ( lum + 0.05 ) / 0.0558 ) ? '#ffffff' : '#0e1116';
		}
		var radius = { square: '4px', rounded: '10px', pill: '24px' }[ design.radius ];
		if ( radius ) { out[ '--alf-radius' ] = radius; }
		var font = site.fonts.filter( function ( f ) { return f.token === design.font; } )[ 0 ];
		if ( font ) { out.fontFamily = font.family.indexOf( ',' ) > -1 ? font.family : '"' + font.family + '", sans-serif'; }
		return out;
	}

	function App() {
		var _form = useState( null ), form = _form[ 0 ], setForm = _form[ 1 ];
		var _saved = useState( '' ), saved = _saved[ 0 ], setSaved = _saved[ 1 ];
		var _sel = useState( -1 ), selected = _sel[ 0 ], setSelected = _sel[ 1 ];
		var _tab = useState( cfg.tab || 'build' ), tab = _tab[ 0 ], setTab = _tab[ 1 ];
		var _dev = useState( 'desktop' ), device = _dev[ 0 ], setDevice = _dev[ 1 ];
		var _status = useState( { kind: '', text: '' } ), status = _status[ 0 ], setStatus = _status[ 1 ];
		var _busy = useState( false ), busy = _busy[ 0 ], setBusy = _busy[ 1 ];
		var _drop = useState( -1 ), dropAt = _drop[ 0 ], setDropAt = _drop[ 1 ];
		var _q = useState( '' ), query = _q[ 0 ], setQuery = _q[ 1 ];
		var _embed = useState( false ), embed = _embed[ 0 ], setEmbed = _embed[ 1 ];
		var _section = useState( cfg.section || 'general' ), section = _section[ 0 ], setSection = _section[ 1 ];
		var _tests = useState( {} ), tests = _tests[ 0 ], setTests = _tests[ 1 ];
		var _svc = useState( cfg.services || [] ), SERVICES = _svc[ 0 ], setServices = _svc[ 1 ];
		var _refresh = useState( false ), refreshing = _refresh[ 0 ], setRefreshing = _refresh[ 1 ];
		var history = useRef( { past: [], future: [], key: '', time: 0 } );
		var formRef = useRef( null );
		formRef.current = form;

		var path = '/aluleth-forms/v1/forms/' + cfg.formId;

		useEffect( function () {
			apiFetch( { path: path } ).then( function ( data ) {
				var f = { title: data.title, status: data.status, schema: data.schema, shortcode: data.shortcode, entries: data.entries };
				f.schema.fields = f.schema.fields.map( normalise );
				setForm( f );
				setSaved( snapshot( f ) );
			} ).catch( function ( e ) {
				setStatus( { kind: 'error', text: ( e && e.message ) || __( 'The form could not be loaded.', 'aluleth-forms' ) } );
			} );
		}, [] );

		function snapshot( f ) { return JSON.stringify( { t: f.title, s: f.status, x: f.schema } ); }
		var dirty = form ? snapshot( form ) !== saved : false;

		useEffect( function () {
			function warn( e ) { if ( dirty ) { e.preventDefault(); e.returnValue = ''; } }
			window.addEventListener( 'beforeunload', warn );
			return function () { window.removeEventListener( 'beforeunload', warn ); };
		}, [ dirty ] );

		/* history-aware update; typing in the same control is grouped into one undo step */
		function commit( next, key ) {
			var h = history.current, now = Date.now();
			if ( ! ( key && key === h.key && now - h.time < 900 ) ) {
				h.past.push( clone( formRef.current ) );
				if ( h.past.length > 100 ) { h.past.shift(); }
			}
			h.future = [];
			h.key = key || '';
			h.time = now;
			setForm( next );
		}
		function undo() {
			var h = history.current;
			if ( ! h.past.length ) { return; }
			h.future.push( clone( formRef.current ) );
			setForm( h.past.pop() );
			h.key = '';
		}
		function redo() {
			var h = history.current;
			if ( ! h.future.length ) { return; }
			h.past.push( clone( formRef.current ) );
			setForm( h.future.pop() );
			h.key = '';
		}

		function setFields( fields, key ) {
			var next = clone( formRef.current );
			next.schema.fields = fields;
			commit( next, key );
		}
		function setSetting( name, value ) {
			var next = clone( formRef.current );
			next.schema.settings[ name ] = value;
			commit( next, 'setting:' + name );
		}
		function updateField( index, patch, key ) {
			var fields = clone( formRef.current.schema.fields );
			fields[ index ] = Object.assign( {}, fields[ index ], patch );
			setFields( fields, key ? 'field:' + index + ':' + key : '' );
		}
		function addField( typeKey, at ) {
			var fields = clone( formRef.current.schema.fields );
			var f = newField( typeKey, fields );
			var pos = typeof at === 'number' && at >= 0 ? at : ( selected > -1 ? selected + 1 : fields.length );
			fields.splice( pos, 0, f );
			setFields( fields );
			setSelected( pos );
			setTab( 'build' );
		}
		function moveField( from, to ) {
			var fields = clone( formRef.current.schema.fields );
			if ( to < 0 || to > fields.length ) { return; }
			var item = fields.splice( from, 1 )[ 0 ];
			if ( to > from ) { to--; }
			fields.splice( to, 0, item );
			setFields( fields );
			setSelected( to );
		}
		function duplicateField( index ) {
			var fields = clone( formRef.current.schema.fields );
			var copy = clone( fields[ index ] );
			copy.id = uniqueId( copy.id.replace( /_\d+$/, '' ), fields );
			fields.splice( index + 1, 0, copy );
			setFields( fields );
			setSelected( index + 1 );
		}
		function removeField( index ) {
			var fields = clone( formRef.current.schema.fields );
			fields.splice( index, 1 );
			setFields( fields );
			setSelected( Math.min( index, fields.length - 1 ) );
		}

		function save( publish ) {
			if ( ! formRef.current || busy ) { return; }
			setBusy( true );
			setStatus( { kind: '', text: __( 'Saving…', 'aluleth-forms' ) } );
			var body = { title: formRef.current.title, schema: formRef.current.schema };
			if ( publish ) { body.status = 'publish'; }
			apiFetch( { path: path, method: 'POST', data: body } ).then( function ( data ) {
				var f = clone( formRef.current );
				f.title = data.title;
				f.status = data.status;
				f.schema = data.schema;
				f.schema.fields = f.schema.fields.map( normalise );
				setForm( f );
				setSaved( snapshot( f ) );
				setStatus( { kind: 'ok', text: publish ? __( 'Published. Your form can now be added to any page.', 'aluleth-forms' ) : __( 'Saved.', 'aluleth-forms' ) } );
			} ).catch( function ( e ) {
				setStatus( { kind: 'error', text: sprintf( /* translators: %s: error message. */ __( 'Not saved: %s', 'aluleth-forms' ), ( e && e.message ) || __( 'unknown error', 'aluleth-forms' ) ) } );
			} ).then( function () { setBusy( false ); } );
		}

		/* keyboard shortcuts */
		useEffect( function () {
			function onKey( e ) {
				var mod = e.ctrlKey || e.metaKey;
				var t = e.target, typing = t && ( /^(INPUT|TEXTAREA|SELECT)$/.test( t.tagName ) || t.isContentEditable );
				if ( mod && e.key.toLowerCase() === 's' ) { e.preventDefault(); save( false ); return; }
				if ( mod && e.key.toLowerCase() === 'z' && ! typing ) { e.preventDefault(); if ( e.shiftKey ) { redo(); } else { undo(); } return; }
				if ( mod && e.key.toLowerCase() === 'y' && ! typing ) { e.preventDefault(); redo(); return; }
				if ( typing ) { return; }
				if ( ( e.key === 'Delete' || e.key === 'Backspace' ) && selected > -1 && tab === 'build' ) { e.preventDefault(); removeField( selected ); }
				if ( e.key === 'Escape' ) { setSelected( -1 ); setEmbed( false ); }
				if ( e.key === '/' && tab === 'build' ) { var s = document.getElementById( 'alf-b-search' ); if ( s ) { e.preventDefault(); s.focus(); } }
			}
			document.addEventListener( 'keydown', onKey );
			return function () { document.removeEventListener( 'keydown', onKey ); };
		} );

		if ( ! form ) {
			return el( 'div', { className: 'alf-b-loading' }, status.text || __( 'Loading the builder…', 'aluleth-forms' ) );
		}

		var fields = form.schema.fields;
		var settings = form.schema.settings;

		/* ----- who gets each new entry by email (shown in the top bar and the Embed box) ----- */
		var mailTo = ! settings.notify ? [] : ( String( settings.notify_to || '' ).trim() ? String( settings.notify_to ).split( ',' ) : [ cfg.adminEmail ] ).map( function ( a ) { return a.trim().replace( '{admin_email}', cfg.adminEmail ); } ).filter( Boolean );
		var mailText = mailTo.length ? sprintf( /* translators: %s: email addresses. */ __( 'New entries emailed to %s', 'aluleth-forms' ), mailTo.join( ', ' ) ) : __( 'New entries are not emailed', 'aluleth-forms' );
		var openEmails = function () { setEmbed( false ); setTab( 'settings' ); setSection( 'emails' ); };
		var mailLine = function ( cls ) {
			return el( 'span', { className: cls, title: mailText },
				el( 'span', { className: 'alf-b-mailto-text' }, mailText ),
				el( 'button', { type: 'button', className: 'alf-b-link', onClick: openEmails, 'aria-label': __( 'Change who gets each new entry by email', 'aluleth-forms' ) }, __( 'Change', 'aluleth-forms' ) )
			);
		};

		/* ----- top bar ----- */
		var top = el( 'header', { className: 'alf-b-top' },
			el( 'div', { className: 'alf-b-top-left' },
				el( 'a', { className: 'alf-b-icon', href: cfg.formsUrl, 'aria-label': __( 'Back to forms', 'aluleth-forms' ), title: __( 'Back to forms', 'aluleth-forms' ) }, el( Icon, { d: ICONS.back } ) ),
				el( 'div', { className: 'alf-b-title' },
					el( 'label', { className: 'screen-reader-text', htmlFor: 'alf-b-title' }, __( 'Form name', 'aluleth-forms' ) ),
					el( 'input', { id: 'alf-b-title', value: form.title, onChange: function ( e ) { var n = clone( form ); n.title = e.target.value; commit( n, 'title' ); } } ),
					el( 'span', { className: 'alf-b-sub' }, form.status === 'publish' ? __( 'Published', 'aluleth-forms' ) : __( 'Draft', 'aluleth-forms' ), dirty ? ' · ' + __( 'Unsaved changes', 'aluleth-forms' ) : '' ),
					mailLine( 'alf-b-mailto' )
				)
			),
			el( 'nav', { className: 'alf-b-tabs', 'aria-label': __( 'Builder sections', 'aluleth-forms' ) },
				[ [ 'build', __( 'Build', 'aluleth-forms' ) ], [ 'design', __( 'Design', 'aluleth-forms' ) ], [ 'settings', __( 'Settings', 'aluleth-forms' ) ] ].map( function ( t ) {
					return el( 'button', { key: t[ 0 ], type: 'button', className: tab === t[ 0 ] ? 'is-on' : '', 'aria-current': tab === t[ 0 ] ? 'page' : undefined, onClick: function () { setTab( t[ 0 ] ); } }, t[ 1 ] );
				} ),
				cfg.entriesUrl ? el( 'a', { href: cfg.entriesUrl }, __( 'Entries', 'aluleth-forms' ), el( 'span', { className: 'alf-b-count' }, String( form.entries ) ) ) : null
			),
			el( 'div', { className: 'alf-b-top-right' },
				el( IconButton, { icon: 'undo', label: __( 'Undo (Ctrl+Z)', 'aluleth-forms' ), onClick: undo, disabled: ! history.current.past.length } ),
				el( IconButton, { icon: 'redo', label: __( 'Redo (Ctrl+Shift+Z)', 'aluleth-forms' ), onClick: redo, disabled: ! history.current.future.length } ),
				el( 'div', { className: 'alf-b-devices', role: 'group', 'aria-label': __( 'Preview size', 'aluleth-forms' ) },
					el( IconButton, { icon: 'desktop', label: __( 'Desktop preview', 'aluleth-forms' ), active: device === 'desktop', pressed: device === 'desktop', onClick: function () { setDevice( 'desktop' ); } } ),
					el( IconButton, { icon: 'phone', label: __( 'Phone preview', 'aluleth-forms' ), active: device === 'phone', pressed: device === 'phone', onClick: function () { setDevice( 'phone' ); } } )
				),
				el( 'div', { className: 'alf-b-embed-wrap' },
					el( 'button', { type: 'button', className: 'alf-b-btn', 'aria-expanded': embed, onClick: function () { setEmbed( ! embed ); } }, __( 'Embed', 'aluleth-forms' ) ),
					embed ? el( 'div', { className: 'alf-b-pop', role: 'dialog', 'aria-label': __( 'Embed this form', 'aluleth-forms' ) },
						el( 'p', null, __( 'Add the “Aluleth Form” block to any page and choose this form, or paste this shortcode:', 'aluleth-forms' ) ),
						el( 'div', { className: 'alf-b-code' },
							el( 'code', null, form.shortcode ),
							el( 'button', { type: 'button', className: 'alf-b-btn', onClick: function () { if ( navigator.clipboard ) { navigator.clipboard.writeText( form.shortcode ); setStatus( { kind: 'ok', text: __( 'Shortcode copied.', 'aluleth-forms' ) } ); } } }, __( 'Copy', 'aluleth-forms' ) )
						),
						form.status !== 'publish' ? el( 'p', { className: 'alf-b-hint' }, __( 'Publish the form first: drafts are only visible to you.', 'aluleth-forms' ) ) : null,
						el( 'p', { className: 'alf-b-hint alf-b-pop-mail' }, mailLine( 'alf-b-mailto is-wrap' ) )
					) : null
				),
				form.status !== 'publish' ? el( 'button', { type: 'button', className: 'alf-b-btn', onClick: function () { save( false ); }, disabled: busy }, __( 'Save draft', 'aluleth-forms' ) ) : null,
				el( 'button', { type: 'button', className: 'alf-b-btn alf-b-primary', onClick: function () { save( form.status !== 'publish' ); }, disabled: busy || ( form.status === 'publish' && ! dirty ) }, form.status === 'publish' ? ( dirty ? __( 'Save', 'aluleth-forms' ) : __( 'Saved', 'aluleth-forms' ) ) : __( 'Publish', 'aluleth-forms' ) )
			)
		);

		/* ----- palette ----- */
		var q = query.trim().toLowerCase();
		var palette = el( 'aside', { className: 'alf-b-palette', 'aria-label': __( 'Fields', 'aluleth-forms' ) },
			el( 'label', { className: 'alf-b-search' },
				el( Icon, { d: ICONS.search, size: 16 } ),
				el( 'span', { className: 'screen-reader-text' }, __( 'Search fields', 'aluleth-forms' ) ),
				el( 'input', { id: 'alf-b-search', type: 'search', placeholder: __( 'Search fields ( / )', 'aluleth-forms' ), value: query, onChange: function ( e ) { setQuery( e.target.value ); } } )
			),
			GROUPS.map( function ( g ) {
				var items = g.types.filter( function ( k ) { return ! q || TYPES[ k ].label.toLowerCase().indexOf( q ) > -1; } );
				if ( ! items.length ) { return null; }
				return el( 'div', { key: g.id, className: 'alf-b-group' },
					el( 'h2', null, g.label ),
					el( 'div', { className: 'alf-b-items' }, items.map( function ( k ) {
						return el( 'button', {
							key: k, type: 'button', className: 'alf-b-item', draggable: true,
							onClick: function () { addField( k ); },
							onDragStart: function ( e ) { e.dataTransfer.setData( 'text/plain', 'new:' + k ); e.dataTransfer.effectAllowed = 'copy'; },
							onDragEnd: function () { setDropAt( -1 ); }
						}, el( Icon, { d: TYPES[ k ].icon, size: 16 } ), TYPES[ k ].label );
					} ) )
				);
			} ),
			el( 'p', { className: 'alf-b-hint' }, __( 'Click a field to add it after the selected one, or drag it into the form.', 'aluleth-forms' ) )
		);

		/* ----- canvas ----- */
		function onDragOverItem( e, index ) {
			e.preventDefault();
			var r = e.currentTarget.getBoundingClientRect();
			var at = e.clientY < r.top + r.height / 2 ? index : index + 1;
			if ( at !== dropAt ) { setDropAt( at ); }
		}
		function onDrop( e ) {
			e.preventDefault();
			var data = e.dataTransfer.getData( 'text/plain' ) || '';
			var at = dropAt < 0 ? fields.length : dropAt;
			setDropAt( -1 );
			if ( data.indexOf( 'new:' ) === 0 && TYPES[ data.slice( 4 ) ] ) { addField( data.slice( 4 ), at ); }
			if ( data.indexOf( 'move:' ) === 0 ) { var from = parseInt( data.slice( 5 ), 10 ); if ( from !== at && from + 1 !== at ) { moveField( from, at ); } }
		}

		var editable = tab === 'build';
		var layout = settings.design || {};
		var hasPay = fields.some( function ( f ) { return PAY_TYPES.indexOf( f.type ) > -1; } );
		var payPreview = hasPay ? hooks.applyFilters( 'alulethForms.builder.payPreview', null, { settings: settings, fields: fields, ui: UI } ) : null;
		var sampleTotal = fields.reduce( function ( sum, f ) {
			if ( f.type === 'product' && ( f.mode === 'single' || f.mode === 'one' ) && f.items && f.items[ 0 ] ) { return sum + ( Number( f.items[ 0 ].price ) || 0 ); }
			if ( f.type === 'amount' ) { return sum + ( Number( f.min ) || 0 ); }
			return sum;
		}, 0 );
		var canvasForm = el( 'div', { className: 'alf alf--' + settings.style + ' alf-b-form alf-labels-' + ( layout.labels || 'above' ) + ' alf-help-' + ( layout.help || 'below' ), style: designVars( layout ) },
			el( 'div', { className: 'alf-form' },
				fields.length ? fields.map( function ( f, i ) {
					var isSel = editable && i === selected;
					return el( Fragment, { key: f.id + i },
						editable && dropAt === i ? el( 'div', { className: 'alf-b-drop', 'aria-hidden': true } ) : null,
						el( 'div', {
							className: 'alf-b-field' + ( f.width === 'half' ? ' alf-half' : '' ) + ( f.width === 'third' ? ' alf-third' : '' ) + ( isSel ? ' is-selected' : '' ) + ( f.type === 'step' ? ' is-step' : '' ) + ( f.conditions ? ' has-cond' : '' ),
							role: editable ? 'button' : undefined, tabIndex: editable ? 0 : undefined,
							'aria-label': editable ? sprintf( /* translators: %s: field label. */ __( 'Edit field: %s', 'aluleth-forms' ), stripTags( f.label ) || TYPE_NAMES[ f.type ] ) : undefined,
							'aria-pressed': editable ? isSel : undefined,
							draggable: editable,
							onClick: editable ? function () { setSelected( i ); } : undefined,
							onKeyDown: editable ? function ( e ) { if ( e.target === e.currentTarget && ( e.key === 'Enter' || e.key === ' ' ) ) { e.preventDefault(); setSelected( i ); } } : undefined,
							onDragStart: editable ? function ( e ) { e.dataTransfer.setData( 'text/plain', 'move:' + i ); e.dataTransfer.effectAllowed = 'move'; } : undefined,
							onDragOver: editable ? function ( e ) { onDragOverItem( e, i ); } : undefined,
							onDrop: editable ? onDrop : undefined
						},
							f.conditions ? el( 'span', { className: 'alf-b-badge' }, __( 'Shown if…', 'aluleth-forms' ) ) : null,
							el( Preview, { field: f, total: sampleTotal, helpTop: ( layout.help || 'below' ) === 'label' && layout.labels !== 'inside' } ),
							isSel ? el( 'div', { className: 'alf-b-tools' },
								el( IconButton, { icon: 'up', label: __( 'Move up', 'aluleth-forms' ), disabled: i === 0, onClick: function ( e ) { e.stopPropagation(); moveField( i, i - 1 ); } } ),
								el( IconButton, { icon: 'down', label: __( 'Move down', 'aluleth-forms' ), disabled: i === fields.length - 1, onClick: function ( e ) { e.stopPropagation(); moveField( i, i + 2 ); } } ),
								el( IconButton, { icon: 'copy', label: __( 'Duplicate', 'aluleth-forms' ), onClick: function ( e ) { e.stopPropagation(); duplicateField( i ); } } ),
								el( IconButton, { icon: 'trash', label: __( 'Delete', 'aluleth-forms' ), onClick: function ( e ) { e.stopPropagation(); removeField( i ); } } )
							) : null
						)
					);
				} ) : el( 'div', { className: 'alf-b-empty' }, __( 'Your form is empty. Add a field from the left, or drag one here.', 'aluleth-forms' ) ),
				editable && dropAt === fields.length ? el( 'div', { className: 'alf-b-drop', 'aria-hidden': true } ) : null,
				( function () {
					var conf = cfg.captcha || {};
					var services = conf.services || {};
					var id = 'default' === ( settings.captcha || 'default' ) ? conf[ 'default' ] : settings.captcha;
					var svc = id && services[ id ];
					return svc && svc.ready && svc.widget ? el( 'div', { className: 'alf-captcha alf-captcha--preview' }, sprintf( /* translators: %s: anti-spam service name. */ __( 'Anti-spam check (%s)', 'aluleth-forms' ), svc.label ) ) : null;
				}() ),
				payPreview ? el( 'div', { className: 'alf-actions' }, payPreview ) : hasPay ? el( 'div', { className: 'alf-actions' }, el( 'div', { className: 'alf-pay alf-pay--preview' },
					el( 'span', null, __( 'PayPal buttons: PayPal, Pay Later, card', 'aluleth-forms' ) ),
					! PAY.ready ? el( 'a', { href: PAY.url, className: 'alf-b-paylink' }, __( 'Set up PayPal so visitors can pay', 'aluleth-forms' ) ) : null
				) ) : el( 'div', { className: 'alf-actions' }, el( 'span', { className: 'alf-button' }, settings.submit_label || __( 'Send', 'aluleth-forms' ) ) )
			)
		);

		var canvas = el( 'main', {
			className: 'alf-b-canvas', onDragOver: editable ? function ( e ) { e.preventDefault(); } : undefined, onDrop: editable ? onDrop : undefined,
			onClick: function ( e ) { if ( e.target === e.currentTarget ) { setSelected( -1 ); } }
		},
			el( 'div', { className: 'alf-b-paper' + ( device === 'phone' ? ' is-phone' : '' ) + ( settings.style === 'dark' ? ' is-dark' : '' ) }, canvasForm )
		);

		/* ----- right panel: selected field ----- */
		function fieldPanel() {
			if ( selected < 0 || ! fields[ selected ] ) {
				return el( 'div', { className: 'alf-b-none' },
					el( 'h2', null, __( 'Select a field', 'aluleth-forms' ) ),
					el( 'p', null, __( 'Click a field in the form to change its label, make it required or save it to the contact.', 'aluleth-forms' ) ),
					el( 'p', { className: 'alf-b-hint' }, __( 'Shortcuts: Ctrl+S save · Ctrl+Z undo · Delete removes the selected field · / search fields', 'aluleth-forms' ) )
				);
			}
			var f = fields[ selected ];
			var i = selected;
			var set = function ( k ) { return function ( v ) { var p = {}; p[ k ] = v; updateField( i, p, k ); }; };
			var isInput = INPUTS.indexOf( f.type ) > -1;
			var parts = [];

			parts.push( el( 'div', { key: 'head', className: 'alf-b-panel-head' }, el( 'strong', null, productName( f ) ), el( 'code', null, f.id ) ) );

			if ( f.type === 'paragraph' ) {
				parts.push( el( Field, { key: 'text', name: 'text', label: __( 'Text', 'aluleth-forms' ), multiline: true, value: f.text, onChange: set( 'text' ) } ) );
			} else {
				var labelName = f.type === 'step' ? __( 'Step name', 'aluleth-forms' ) : ( f.type === 'heading' ? __( 'Heading', 'aluleth-forms' ) : ( f.type === 'consent' ? __( 'Text next to the box', 'aluleth-forms' ) : ( f.type === 'hidden' ? __( 'Name (only you see it)', 'aluleth-forms' ) : __( 'Label', 'aluleth-forms' ) ) ) );
				parts.push( el( Field, { key: 'label', name: 'label', label: labelName, multiline: f.type === 'consent', value: f.label, onChange: set( 'label' ) } ) );
			}
			if ( [ 'text', 'email', 'phone', 'number', 'textarea', 'select', 'date' ].indexOf( f.type ) > -1 && f.type !== 'date' ) {
				parts.push( el( Field, { key: 'ph', name: 'placeholder', label: f.type === 'select' ? __( 'Text before a choice is made', 'aluleth-forms' ) : __( 'Placeholder', 'aluleth-forms' ), value: f.placeholder, onChange: set( 'placeholder' ) } ) );
			}
			if ( isInput && f.type !== 'hidden' ) {
				parts.push( el( Field, { key: 'help', name: 'help', label: __( 'Help text', 'aluleth-forms' ), value: f.help, onChange: set( 'help' ) } ) );
			}
			if ( isInput && f.type !== 'hidden' && ! ( f.type === 'product' && f.mode === 'single' ) ) {
				parts.push( el( Toggle, { key: 'req', name: 'required', label: __( 'Required', 'aluleth-forms' ), checked: f.required, onChange: function ( v ) { updateField( i, { required: v } ); } } ) );
			}
			if ( [ 'select', 'radio', 'checkboxes' ].indexOf( f.type ) > -1 ) {
				parts.push( el( 'div', { key: 'opts', className: 'alf-b-control' },
					el( 'span', { className: 'alf-b-label' }, __( 'Options', 'aluleth-forms' ) ),
					el( 'ul', { className: 'alf-b-options' }, ( f.options || [] ).map( function ( o, k ) {
						return el( 'li', { key: k },
							el( 'label', { className: 'screen-reader-text', htmlFor: 'alf-b-opt-' + k }, sprintf( /* translators: %d: option number. */ __( 'Option %d', 'aluleth-forms' ), k + 1 ) ),
							el( 'input', { id: 'alf-b-opt-' + k, value: o, onChange: function ( e ) { var opts = f.options.slice(); opts[ k ] = e.target.value; updateField( i, { options: opts }, 'opt' + k ); } } ),
							el( IconButton, { icon: 'up', label: __( 'Move option up', 'aluleth-forms' ), disabled: k === 0, onClick: function () { var opts = f.options.slice(); var t = opts[ k - 1 ]; opts[ k - 1 ] = opts[ k ]; opts[ k ] = t; updateField( i, { options: opts } ); } } ),
							el( IconButton, { icon: 'trash', label: __( 'Remove option', 'aluleth-forms' ), disabled: f.options.length < 2, onClick: function () { var opts = f.options.slice(); opts.splice( k, 1 ); updateField( i, { options: opts } ); } } )
						);
					} ) ),
					el( 'button', { type: 'button', className: 'alf-b-btn alf-b-add', onClick: function () { var opts = ( f.options || [] ).slice(); opts.push( sprintf( /* translators: %d: option number. */ __( 'Option %d', 'aluleth-forms' ), opts.length + 1 ) ); updateField( i, { options: opts } ); } }, el( Icon, { d: ICONS.plus, size: 14 } ), __( 'Add an option', 'aluleth-forms' ) )
				) );
			}
			if ( f.type === 'product' ) {
				parts.push( el( Segmented, { key: 'mode', name: 'mode', label: __( 'Kind', 'aluleth-forms' ), value: f.mode, options: [ { value: 'one', label: __( 'One choice', 'aluleth-forms' ) }, { value: 'many', label: __( 'Extras', 'aluleth-forms' ) }, { value: 'single', label: __( 'Fixed', 'aluleth-forms' ) } ], onChange: function ( v ) { updateField( i, { mode: v, items: v === 'single' ? f.items.slice( 0, 1 ) : f.items, required: v === 'single' ? false : f.required } ); } } ) );
				parts.push( el( 'div', { key: 'items', className: 'alf-b-control' },
					el( 'span', { className: 'alf-b-label' }, f.mode === 'single' ? __( 'Item and price', 'aluleth-forms' ) : __( 'Items and prices', 'aluleth-forms' ) ),
					el( 'ul', { className: 'alf-b-options alf-b-prices' }, ( f.items || [] ).map( function ( o, k ) {
						var setItem = function ( patch, key ) { var items = clone( f.items ); items[ k ] = Object.assign( {}, items[ k ], patch ); updateField( i, { items: items }, key ); };
						return el( 'li', { key: k },
							el( 'label', { className: 'screen-reader-text', htmlFor: 'alf-b-item-' + k }, sprintf( /* translators: %d: item number. */ __( 'Item %d', 'aluleth-forms' ), k + 1 ) ),
							el( 'input', { id: 'alf-b-item-' + k, value: o.label, onChange: function ( e ) { setItem( { label: e.target.value }, 'item' + k ); } } ),
							el( 'label', { className: 'screen-reader-text', htmlFor: 'alf-b-price-' + k }, sprintf( /* translators: %d: item number. */ __( 'Price of item %d', 'aluleth-forms' ), k + 1 ) ),
							el( 'input', { id: 'alf-b-price-' + k, className: 'alf-b-price', type: 'number', min: 0, step: '0.01', value: o.price, onChange: function ( e ) { setItem( { price: e.target.value === '' ? 0 : Number( e.target.value ) }, 'price' + k ); } } ),
							f.mode !== 'single' ? el( IconButton, { icon: 'trash', label: __( 'Remove item', 'aluleth-forms' ), disabled: f.items.length < 2, onClick: function () { var items = clone( f.items ); items.splice( k, 1 ); updateField( i, { items: items } ); } } ) : null
						);
					} ) ),
					f.mode !== 'single' ? el( 'button', { type: 'button', className: 'alf-b-btn alf-b-add', onClick: function () { var items = clone( f.items || [] ); items.push( { label: sprintf( /* translators: %d: item number. */ __( 'Item %d', 'aluleth-forms' ), items.length + 1 ), price: 10 } ); updateField( i, { items: items } ); } }, el( Icon, { d: ICONS.plus, size: 14 } ), __( 'Add an item', 'aluleth-forms' ) ) : null,
					el( 'p', { className: 'alf-b-hint' }, sprintf( /* translators: %s: currency code. */ __( 'Prices in %s (Aluleth › Settings › Payments).', 'aluleth-forms' ), PAY.currency ) )
				) );
			}
			if ( f.type === 'amount' ) {
				parts.push( el( 'div', { key: 'amm', className: 'alf-b-row' },
					el( Field, { name: 'min', type: 'number', label: __( 'Minimum', 'aluleth-forms' ), value: f.min, onChange: function ( v ) { updateField( i, { min: v === '' ? 1 : Number( v ) }, 'min' ); } } ),
					el( Field, { name: 'max', type: 'number', label: __( 'Maximum', 'aluleth-forms' ), value: f.max, onChange: function ( v ) { updateField( i, { max: v === '' ? null : Number( v ) }, 'max' ); } } )
				) );
			}
			if ( f.type === 'total' ) {
				parts.push( el( 'p', { key: 'tot', className: 'alf-b-note' }, __( 'Shows the items chosen and the total, updated as the visitor fills the form. The amount paid is always computed again on your site.', 'aluleth-forms' ) ) );
			}
			if ( f.type === 'number' ) {
				parts.push( el( 'div', { key: 'minmax', className: 'alf-b-row' },
					el( Field, { name: 'min', type: 'number', label: __( 'Minimum', 'aluleth-forms' ), value: f.min, onChange: function ( v ) { updateField( i, { min: v === '' ? null : Number( v ) }, 'min' ); } } ),
					el( Field, { name: 'max', type: 'number', label: __( 'Maximum', 'aluleth-forms' ), value: f.max, onChange: function ( v ) { updateField( i, { max: v === '' ? null : Number( v ) }, 'max' ); } } )
				) );
			}
			if ( f.type === 'text' || f.type === 'textarea' ) {
				parts.push( el( Field, { key: 'maxl', name: 'maxlength', type: 'number', label: __( 'Maximum characters (0 = no limit)', 'aluleth-forms' ), value: f.maxlength || 0, onChange: function ( v ) { updateField( i, { maxlength: Math.max( 0, parseInt( v, 10 ) || 0 ) }, 'maxlength' ); } } ) );
			}
			if ( f.type === 'hidden' ) {
				parts.push( el( Field, { key: 'def', name: 'default', label: __( 'Value sent with the form', 'aluleth-forms' ), value: f.default, onChange: set( 'default' ), help: __( 'For example the name of the campaign or of the page.', 'aluleth-forms' ) } ) );
			}
			if ( f.type === 'consent' ) {
				parts.push( el( Toggle, { key: 'mkt', name: 'marketing', label: __( 'This is a newsletter consent', 'aluleth-forms' ), help: __( 'When ticked, the contact is subscribed and the consent is recorded with its date and text.', 'aluleth-forms' ), checked: f.marketing, onChange: function ( v ) { updateField( i, { marketing: v } ); } } ) );
			}
			if ( isInput && [ 'consent', 'hidden' ].indexOf( f.type ) < 0 ) {
				parts.push( el( Segmented, { key: 'w', name: 'width', label: __( 'Width', 'aluleth-forms' ), value: f.width, options: [ { value: 'third', label: __( 'One third', 'aluleth-forms' ) }, { value: 'half', label: __( 'Half', 'aluleth-forms' ) }, { value: 'full', label: __( 'Full', 'aluleth-forms' ) } ], onChange: function ( v ) { updateField( i, { width: v } ); } } ) );
			}
			if ( f.type === 'phone' ) {
				parts.push( el( 'p', { key: 'ph-note', className: 'alf-b-note' }, __( 'Saved as the contact’s phone number.', 'aluleth-forms' ) ) );
			}
			if ( f.type === 'text' ) {
				parts.push( el( 'div', { key: 'map', className: 'alf-b-control' },
					el( 'label', { htmlFor: 'alf-b-map' }, __( 'Save to the contact', 'aluleth-forms' ) ),
					el( 'select', { id: 'alf-b-map', value: f.map, onChange: function ( e ) { updateField( i, { map: e.target.value } ); } },
						Object.keys( cfg.maps ).map( function ( k ) { return el( 'option', { key: k, value: k }, cfg.maps[ k ] ); } ) ),
					el( 'p', { className: 'alf-b-hint' }, __( 'Contacts are shared by every Aluleth plugin on this site.', 'aluleth-forms' ) )
				) );
			}
			parts = hooks.applyFilters( 'alulethForms.builder.fieldPanel', parts, f, { index: i, update: function ( patch, key ) { updateField( i, patch, key ); }, fields: fields, settings: settings, ui: UI } );
			if ( f.type !== 'step' ) {
				var targets = fields.filter( function ( t, k ) { return k !== i && INPUTS.indexOf( t.type ) > -1 && NO_COND.indexOf( t.type ) < 0; } );
				var cond = f.conditions;
				var setCond = function ( c, key ) { updateField( i, { conditions: c }, key ); };
				var opsFor = function ( t ) {
					if ( ! t ) { return [ 'is', 'is_not' ]; }
					if ( t.type === 'consent' ) { return [ 'not_empty', 'empty' ]; }
					if ( [ 'select', 'radio', 'checkboxes' ].indexOf( t.type ) > -1 ) { return [ 'is', 'is_not', 'not_empty', 'empty' ]; }
					if ( [ 'number', 'rating', 'scale' ].indexOf( t.type ) > -1 ) { return [ 'is', 'is_not', 'gt', 'lt', 'not_empty', 'empty' ]; }
					return [ 'is', 'is_not', 'contains', 'not_contains', 'not_empty', 'empty' ];
				};
				var OPL = {
					is: __( 'is', 'aluleth-forms' ), is_not: __( 'is not', 'aluleth-forms' ), contains: __( 'contains', 'aluleth-forms' ), not_contains: __( 'does not contain', 'aluleth-forms' ),
					empty: __( 'is empty', 'aluleth-forms' ), not_empty: __( 'is filled in', 'aluleth-forms' ), gt: __( 'is more than', 'aluleth-forms' ), lt: __( 'is less than', 'aluleth-forms' )
				};
				var consentOPL = { not_empty: __( 'is ticked', 'aluleth-forms' ), empty: __( 'is not ticked', 'aluleth-forms' ) };
				var byId = function ( id ) { return targets.filter( function ( t ) { return t.id === id; } )[ 0 ]; };
				var box = [];
				box.push( el( Toggle, { key: 'ct', name: 'cond', label: __( 'Show this field only when…', 'aluleth-forms' ), help: targets.length ? '' : __( 'Add another field first.', 'aluleth-forms' ), checked: !! cond, onChange: function ( v ) {
					if ( ! v ) { setCond( null ); return; }
					if ( ! targets.length ) { return; }
					var t = targets[ 0 ];
					setCond( { match: 'all', rules: [ { field: t.id, op: opsFor( t )[ 0 ], value: t.options && t.options.length ? t.options[ 0 ] : '' } ] } );
				} } ) );
				if ( cond ) {
					box.push( el( 'div', { key: 'cm', className: 'alf-b-control' },
						el( 'label', { htmlFor: 'alf-b-match' }, __( 'Show it when', 'aluleth-forms' ) ),
						el( 'select', { id: 'alf-b-match', value: cond.match, onChange: function ( e ) { setCond( Object.assign( {}, cond, { match: e.target.value } ) ); } },
							el( 'option', { value: 'all' }, __( 'all these rules are true', 'aluleth-forms' ) ),
							el( 'option', { value: 'any' }, __( 'any of these rules is true', 'aluleth-forms' ) ) )
					) );
					cond.rules.forEach( function ( r, k ) {
						var t = byId( r.field );
						var ops = opsFor( t );
						var setRule = function ( patch, key ) {
							var rules = cond.rules.map( function ( x, n ) { return n === k ? Object.assign( {}, x, patch ) : x; } );
							setCond( Object.assign( {}, cond, { rules: rules } ), key );
						};
						var needsValue = [ 'empty', 'not_empty' ].indexOf( r.op ) < 0;
						box.push( el( 'div', { key: 'r' + k, className: 'alf-b-rule' },
							el( 'label', { className: 'screen-reader-text', htmlFor: 'alf-b-rf-' + k }, __( 'Field', 'aluleth-forms' ) ),
							el( 'select', { id: 'alf-b-rf-' + k, value: r.field, onChange: function ( e ) { var nt = byId( e.target.value ); setRule( { field: e.target.value, op: opsFor( nt )[ 0 ], value: nt && nt.options && nt.options.length ? nt.options[ 0 ] : '' } ); } },
								targets.map( function ( t2 ) { return el( 'option', { key: t2.id, value: t2.id }, stripTags( t2.label ) || t2.id ); } ) ),
							el( 'label', { className: 'screen-reader-text', htmlFor: 'alf-b-ro-' + k }, __( 'Condition', 'aluleth-forms' ) ),
							el( 'select', { id: 'alf-b-ro-' + k, value: r.op, onChange: function ( e ) { setRule( { op: e.target.value } ); } },
								ops.map( function ( o ) { return el( 'option', { key: o, value: o }, t && t.type === 'consent' ? consentOPL[ o ] : OPL[ o ] ); } ) ),
							needsValue ? ( t && t.options ? el( 'select', { 'aria-label': __( 'Value', 'aluleth-forms' ), value: r.value, onChange: function ( e ) { setRule( { value: e.target.value } ); } },
								t.options.map( function ( o ) { return el( 'option', { key: o, value: o }, o ); } ) )
								: el( 'input', { 'aria-label': __( 'Value', 'aluleth-forms' ), value: r.value, onChange: function ( e ) { setRule( { value: e.target.value }, 'rv' + k ); } } ) ) : null,
							el( IconButton, { icon: 'trash', label: __( 'Remove rule', 'aluleth-forms' ), onClick: function () {
								var rules = cond.rules.filter( function ( x, n ) { return n !== k; } );
								setCond( rules.length ? Object.assign( {}, cond, { rules: rules } ) : null );
							} } )
						) );
					} );
					box.push( el( 'button', { key: 'add', type: 'button', className: 'alf-b-btn alf-b-add', onClick: function () {
						var t = targets[ 0 ];
						setCond( Object.assign( {}, cond, { rules: cond.rules.concat( [ { field: t.id, op: opsFor( t )[ 0 ], value: t.options && t.options.length ? t.options[ 0 ] : '' } ] ) } ) );
					} }, el( Icon, { d: ICONS.plus, size: 14 } ), __( 'Add a rule', 'aluleth-forms' ) ) );
					box.push( el( 'p', { key: 'note', className: 'alf-b-hint' }, __( 'Hidden fields are not required and are not saved.', 'aluleth-forms' ) ) );
				}
				parts.push( el( 'div', { key: 'cond', className: 'alf-b-section' }, box ) );
			}
			if ( f.type === 'email' ) {
				parts.push( el( 'p', { key: 'em', className: 'alf-b-note' }, __( 'The first email field creates or updates the contact.', 'aluleth-forms' ) ) );
			}
			if ( isInput ) {
				parts.push( el( Field, { key: 'id', name: 'fid', label: __( 'Field key', 'aluleth-forms' ), value: f.id, help: __( 'Used in exports and integrations. Letters, numbers and underscores.', 'aluleth-forms' ), onChange: function ( v ) {
					var clean = slug( v ) || f.id;
					updateField( i, { id: uniqueId( clean, fields, i ) }, 'id' );
				} } ) );
			}
			parts.push( el( 'div', { key: 'danger', className: 'alf-b-panel-foot' },
				el( 'button', { type: 'button', className: 'alf-b-btn', onClick: function () { duplicateField( i ); } }, __( 'Duplicate', 'aluleth-forms' ) ),
				el( 'button', { type: 'button', className: 'alf-b-btn alf-b-danger', onClick: function () { removeField( i ); } }, __( 'Delete field', 'aluleth-forms' ) )
			) );
			return parts;
		}

		/* ----- design tab ----- */
		var SITE = cfg.site || { colors: [], fonts: [] };
		var design = settings.design || { accent: '', radius: '', font: '' };
		function setDesign( patch ) { setSetting( 'design', Object.assign( {}, design, patch ) ); }
		var accentHex = ( function () {
			if ( /^#[0-9a-f]{6}$/i.test( design.accent || '' ) ) { return design.accent; }
			var c = SITE.colors.filter( function ( x ) { return x.token === design.accent; } )[ 0 ];
			return c ? c.hex : '';
		}() );
		var designPanel = el( 'aside', { className: 'alf-b-palette alf-b-design' },
			el( 'h2', { className: 'alf-b-h' }, __( 'Style', 'aluleth-forms' ) ),
			el( 'div', { className: 'alf-b-styles', role: 'radiogroup', 'aria-label': __( 'Style', 'aluleth-forms' ) },
				STYLES.map( function ( s ) {
					return el( 'button', { key: s.id, type: 'button', role: 'radio', 'aria-checked': settings.style === s.id, className: 'alf-b-style alf-b-style--' + s.id + ( settings.style === s.id ? ' is-on' : '' ), onClick: function () { setSetting( 'style', s.id ); } },
						el( 'span', { className: 'alf-b-swatch', 'aria-hidden': true }, el( 'i' ), el( 'i' ), el( 'b' ) ),
						el( 'strong', null, s.label ), el( 'span', null, s.text ) );
				} ) ),
			el( 'h2', { className: 'alf-b-h' }, __( 'Button and accent colour', 'aluleth-forms' ) ),
			el( 'div', { className: 'alf-b-swatches', role: 'radiogroup', 'aria-label': __( 'Button and accent colour', 'aluleth-forms' ) },
				el( 'button', { type: 'button', role: 'radio', 'aria-checked': ! design.accent, className: 'alf-b-swatch-btn is-default' + ( ! design.accent ? ' is-on' : '' ), title: __( 'The style’s own colour', 'aluleth-forms' ), onClick: function () { setDesign( { accent: '' } ); } }, el( 'span', { 'aria-hidden': true } ), el( 'em', null, __( 'Style', 'aluleth-forms' ) ) ),
				SITE.colors.map( function ( c ) {
					return el( 'button', { key: c.token, type: 'button', role: 'radio', 'aria-checked': design.accent === c.token, className: 'alf-b-swatch-btn' + ( design.accent === c.token ? ' is-on' : '' ), title: c.name + ' · ' + c.source + ' (' + c.hex + ')', onClick: function () { setDesign( { accent: c.token } ); } }, el( 'span', { style: { background: c.hex }, 'aria-hidden': true } ), el( 'em', null, c.name ) );
				} )
			),
			el( 'div', { className: 'alf-b-control alf-b-custom-color' },
				el( 'label', { htmlFor: 'alf-b-accent' }, __( 'Or your own colour', 'aluleth-forms' ) ),
				el( 'span', { className: 'alf-b-inline' },
					el( 'input', { id: 'alf-b-accent', type: 'color', value: accentHex || '#0e1116', onChange: function ( e ) { setDesign( { accent: e.target.value.toLowerCase() } ); } } ),
					el( 'code', null, accentHex || __( 'style colour', 'aluleth-forms' ) ) )
			),
			SITE.colors.length ? el( 'p', { className: 'alf-b-hint' }, __( 'Colours from your site follow it: if you change them in your theme or Elementor, the form changes too.', 'aluleth-forms' ) ) : el( 'p', { className: 'alf-b-hint' }, __( 'No site colours found (theme.json or Elementor kit). Pick your own colour.', 'aluleth-forms' ) ),
			el( Segmented, { name: 'radius', label: __( 'Corners', 'aluleth-forms' ), value: design.radius || '', options: [ { value: '', label: __( 'Style', 'aluleth-forms' ) }, { value: 'square', label: __( 'Square', 'aluleth-forms' ) }, { value: 'rounded', label: __( 'Rounded', 'aluleth-forms' ) }, { value: 'pill', label: __( 'Pill', 'aluleth-forms' ) } ], onChange: function ( v ) { setDesign( { radius: v } ); } } ),
			el( 'div', { className: 'alf-b-control' },
				el( 'label', { htmlFor: 'alf-b-font' }, __( 'Font', 'aluleth-forms' ) ),
				el( 'select', { id: 'alf-b-font', value: design.font || '', onChange: function ( e ) { setDesign( { font: e.target.value } ); } },
					el( 'option', { value: '' }, __( 'Same as the page', 'aluleth-forms' ) ),
					SITE.fonts.map( function ( f ) { return el( 'option', { key: f.token, value: f.token }, f.name + ' (' + f.source + ')' ); } ) ),
				el( 'p', { className: 'alf-b-hint' }, __( '“Same as the page” uses the text font around the form. Fonts listed here are the ones your site already uses.', 'aluleth-forms' ) )
			),
			el( 'h2', { className: 'alf-b-h' }, __( 'Layout', 'aluleth-forms' ) ),
			el( Segmented, { name: 'labels', label: __( 'Labels', 'aluleth-forms' ), value: design.labels || 'above', options: [ { value: 'above', label: __( 'Above', 'aluleth-forms' ) }, { value: 'beside', label: __( 'Beside', 'aluleth-forms' ) }, { value: 'inside', label: __( 'Inside', 'aluleth-forms' ) } ], onChange: function ( v ) { setDesign( { labels: v } ); } } ),
			design.labels === 'beside' ? el( 'p', { className: 'alf-b-hint' }, __( 'On phones and narrow columns, labels go back above the fields.', 'aluleth-forms' ) ) : null,
			el( Segmented, { name: 'help', label: __( 'Help text', 'aluleth-forms' ), value: design.help || 'below', options: [ { value: 'below', label: __( 'Under the field', 'aluleth-forms' ) }, { value: 'label', label: __( 'Under the label', 'aluleth-forms' ) } ], onChange: function ( v ) { setDesign( { help: v } ); } } ),
			design.labels === 'inside' && design.help === 'label' ? el( 'p', { className: 'alf-b-hint' }, __( 'With labels inside the fields, help texts stay under the field.', 'aluleth-forms' ) ) : null,
			el( Segmented, { name: 'errors', label: __( 'Error messages', 'aluleth-forms' ), value: design.errors || 'field', options: [ { value: 'field', label: __( 'Under the field', 'aluleth-forms' ) }, { value: 'top', label: __( 'Also at the top', 'aluleth-forms' ) } ], onChange: function ( v ) { setDesign( { errors: v } ); } } ),
			el( Field, { name: 'submit', label: __( 'Button text', 'aluleth-forms' ), value: settings.submit_label, onChange: function ( v ) { setSetting( 'submit_label', v ); } } ),
			el( 'p', { className: 'alf-b-hint' }, __( 'Every style and setting is free. In Elementor, the widget’s Style tab can still change each colour.', 'aluleth-forms' ) )
		);

		/* ----- integrations (settings section) ----- */
		function refreshServices() {
			setRefreshing( true );
			apiFetch( { path: '/aluleth/v1/integrations?refresh=1' } ).then( function ( list ) { setServices( list ); } ).catch( function () {} ).then( function () { setRefreshing( false ); } );
		}
		function serviceOf( id ) { return SERVICES.filter( function ( s ) { return s.id === id; } )[ 0 ]; }
		function supports( s, what ) { return !! s && ( s.supports || [] ).indexOf( what ) > -1; }
		function randomId( size ) {
			var bytes = new Uint8Array( size );
			( window.crypto || window.msCrypto ).getRandomValues( bytes );
			return Array.prototype.map.call( bytes, function ( b ) { return ( '0' + b.toString( 16 ) ).slice( -2 ); } ).join( '' );
		}
		function newFeed( s ) {
			return {
				id: 'f' + randomId( 4 ), service: s.id, name: '', enabled: true,
				lists: s.lists && s.lists.length === 1 ? [ s.lists[ 0 ].id ] : [], tags: [], double_optin: supports( s, 'double_optin' ),
				consent: s.id === 'webhook' || supports( s, 'alert' ) ? 'always' : 'marketing', map: {}, url: '', format: 'json', secret: ''
			};
		}
		function Checks( props ) {
			return el( 'fieldset', { className: 'alf-b-checks' },
				el( 'legend', null, props.label ),
				props.items.length ? el( 'div', { className: 'alf-b-checklist' }, props.items.map( function ( item ) {
					var on = props.value.indexOf( item.id ) > -1;
					return el( 'label', { key: item.id, className: on ? 'is-on' : '' },
						el( 'input', { type: 'checkbox', checked: on, onChange: function () { props.onChange( on ? props.value.filter( function ( v ) { return v !== item.id; } ) : props.value.concat( [ item.id ] ) ); } } ),
						item.name );
				} ) ) : el( 'p', { className: 'alf-b-hint' }, props.empty )
			);
		}
		function integrationsBody() {
			var feeds = settings.integrations || [];
			var setFeeds = function ( next ) { setSetting( 'integrations', next ); };
			var update = function ( i, patch ) { setFeeds( feeds.map( function ( f, j ) { return j === i ? Object.assign( {}, f, patch ) : f; } ) ); };
			var inputs = fields.filter( function ( f ) { return INPUTS.indexOf( f.type ) > -1; } );
			var hasEmail = fields.some( function ( f ) { return f.type === 'email'; } );
			var hasConsent = fields.some( function ( f ) { return f.type === 'consent' && f.marketing; } );

			function test( feed ) {
				var next = {}; next[ feed.id ] = { busy: true };
				setTests( Object.assign( {}, tests, next ) );
				apiFetch( { path: '/aluleth/v1/integrations/test', method: 'POST', data: {
					feed: feed, type: 'form', id: cfg.formId, title: form.title,
					fields: inputs.map( function ( f ) { return { key: f.id, label: stripTags( f.label ) || f.id }; } )
				} } ).then( function ( r ) {
					var done = {}; done[ feed.id ] = r;
					setTests( function ( old ) { return Object.assign( {}, old, done ); } );
				} ).catch( function ( e ) {
					var done = {}; done[ feed.id ] = { ok: false, message: e.message || __( 'The test could not be sent.', 'aluleth-forms' ) };
					setTests( function ( old ) { return Object.assign( {}, old, done ); } );
				} );
			}

			function card( feed, i ) {
				var s = serviceOf( feed.service ) || { id: feed.service, label: feed.service, supports: [], lists: [], tags: [], fields: [], available: false, missing: '' };
				var key = 'feed-' + feed.id + '-';
				var needsConsent = feed.service !== 'webhook' && ( ! supports( s, 'consent_choice' ) || feed.consent !== 'always' );
				var parts = [];
				if ( ! s.available ) {
					parts.push( el( 'p', { key: 'miss', className: 'alf-b-warn' }, s.missing || __( 'This tool is not available on this site.', 'aluleth-forms' ) ) );
				}
				var result = tests[ feed.id ];
				if ( s.url ) {
					var badUrl = feed.url && s.url.prefix && feed.url.indexOf( s.url.prefix ) !== 0 && ! ( s.id === 'discord' && /^https:\/\/(canary\.|ptb\.)?discord(app)?\.com\/api\/webhooks\//.test( feed.url ) );
					parts.push( el( Field, { key: 'url', name: key + 'url', type: 'url', label: s.url.label, placeholder: s.url.placeholder, value: feed.url, help: s.url.help, onChange: function ( v ) { update( i, { url: v.trim() } ); } } ) );
					if ( badUrl ) {
						parts.push( el( 'p', { key: 'badurl', className: 'alf-b-warn' }, sprintf( /* translators: %s: start of a valid address. */ __( 'This address should start with %s.', 'aluleth-forms' ), s.url.prefix ) ) );
					}
				}
				if ( s.id === 'webhook' ) {
					parts.push( el( Segmented, { key: 'fmt', name: key + 'format', label: __( 'Format', 'aluleth-forms' ), value: feed.format, options: [ { value: 'json', label: 'JSON' }, { value: 'form', label: __( 'Form fields', 'aluleth-forms' ) } ], onChange: function ( v ) { update( i, { format: v } ); } } ) );
					parts.push( el( Segmented, { key: 'who', name: key + 'consent', label: __( 'Who is sent', 'aluleth-forms' ), value: feed.consent, options: [ { value: 'always', label: __( 'Every entry', 'aluleth-forms' ) }, { value: 'marketing', label: __( 'Only with newsletter consent', 'aluleth-forms' ) } ], onChange: function ( v ) { update( i, { consent: v } ); } } ) );
					parts.push( el( 'div', { key: 'sec', className: 'alf-b-control' },
						el( 'label', { htmlFor: 'alf-b-' + key + 'secret' }, __( 'Signing secret (optional)', 'aluleth-forms' ) ),
						el( 'span', { className: 'alf-b-inline' },
							el( 'input', { id: 'alf-b-' + key + 'secret', type: 'text', value: feed.secret, autoComplete: 'off', spellCheck: false, onChange: function ( e ) { update( i, { secret: e.target.value.trim() } ); } } ),
							el( 'button', { type: 'button', className: 'alf-b-btn', onClick: function () { update( i, { secret: randomId( 24 ) } ); } }, __( 'Generate', 'aluleth-forms' ) ) ),
						el( 'p', { className: 'alf-b-hint' }, __( 'With a secret, each request carries an X-Aluleth-Signature header (HMAC-SHA256 of the X-Aluleth-Timestamp value, a dot and the body), so your receiver can check it comes from this site.', 'aluleth-forms' ) )
					) );
				}
				if ( supports( s, 'lists' ) && s.available ) {
					parts.push( el( Checks, { key: 'lists', label: s.list_label || __( 'Lists', 'aluleth-forms' ), items: s.lists || [], value: feed.lists || [], empty: sprintf( /* translators: %s: tool name, e.g. MailPoet. */ __( 'Nothing found in %s yet. Create it there, then click “Refresh”.', 'aluleth-forms' ), s.label ), onChange: function ( v ) { update( i, { lists: v } ); } } ) );
				}
				if ( supports( s, 'tags' ) && s.available ) {
					parts.push( el( Checks, { key: 'tags', label: __( 'Tags', 'aluleth-forms' ), items: s.tags || [], value: feed.tags || [], empty: sprintf( /* translators: %s: tool name, e.g. FluentCRM. */ __( 'No tag in %s yet (optional).', 'aluleth-forms' ), s.label ), onChange: function ( v ) { update( i, { tags: v } ); } } ) );
				}
				if ( supports( s, 'tag_names' ) ) {
					parts.push( el( Field, { key: 'tagn', name: key + 'tags', label: __( 'Tags (optional)', 'aluleth-forms' ), value: ( feed.tags || [] ).join( ', ' ), help: __( 'Separated by commas. Missing tags are created.', 'aluleth-forms' ), onChange: function ( v ) { update( i, { tags: v.split( ',' ).map( function ( t ) { return t.trim(); } ).filter( Boolean ) } ); } } ) );
				}
				if ( supports( s, 'double_optin' ) ) {
					parts.push( el( Toggle, { key: 'doi', name: key + 'doi', label: __( 'Double opt-in', 'aluleth-forms' ), help: sprintf( /* translators: %s: tool name, e.g. FluentCRM. */ __( '%s emails a confirmation link; the person is subscribed once they click it.', 'aluleth-forms' ), s.label ), checked: feed.double_optin, onChange: function ( v ) { update( i, { double_optin: v } ); } } ) );
				}
				if ( supports( s, 'alert' ) ) {
					parts.push( el( Segmented, { key: 'who', name: key + 'consent', label: __( 'Which entries', 'aluleth-forms' ), value: feed.consent, options: [ { value: 'always', label: __( 'Every entry', 'aluleth-forms' ) }, { value: 'marketing', label: __( 'Only with newsletter consent', 'aluleth-forms' ) } ], onChange: function ( v ) { update( i, { consent: v } ); } } ) );
				} else if ( supports( s, 'consent_choice' ) && s.id !== 'webhook' ) {
					parts.push( el( Segmented, { key: 'who', name: key + 'consent', label: __( 'Who is sent', 'aluleth-forms' ), value: feed.consent, options: [ { value: 'marketing', label: __( 'Only with newsletter consent', 'aluleth-forms' ) }, { value: 'always', label: __( 'Everyone', 'aluleth-forms' ) } ], onChange: function ( v ) { update( i, { consent: v } ); } } ) );
					if ( feed.consent === 'always' ) {
						parts.push( el( 'p', { key: 'whoh', className: 'alf-b-hint' }, __( 'People without newsletter consent are saved as contacts who do not receive marketing emails.', 'aluleth-forms' ) ) );
					}
				}
				if ( s.note ) {
					parts.push( el( 'p', { key: 'note', className: 'alf-b-hint' }, s.note ) );
				}
				if ( s.testable && s.available ) {
					var canTest = s.url ? !! feed.url : ( feed.lists || [] ).length > 0;
					parts.push( el( 'div', { key: 'test', className: 'alf-b-test' },
						el( 'button', { type: 'button', className: 'alf-b-btn', disabled: ! canTest || ( result && result.busy ), onClick: function () { test( feed ); } }, result && result.busy ? __( 'Sending…', 'aluleth-forms' ) : __( 'Send a test', 'aluleth-forms' ) ),
						result && ! result.busy ? el( 'span', { className: 'alf-b-test-result ' + ( result.ok ? 'is-ok' : 'is-error' ), role: 'status' }, result.message ) : el( 'span', { className: 'alf-b-hint' }, s.id === 'webhook' ? __( 'Sends example answers to the address now, without saving the form.', 'aluleth-forms' ) : __( 'Sends a message with example answers now, without saving the form.', 'aluleth-forms' ) )
					) );
				}
				if ( s.kind === 'api' && s.available ) {
					parts.push( el( 'div', { key: 'refresh', className: 'alf-b-test' },
						el( 'button', { type: 'button', className: 'alf-b-btn', disabled: refreshing, onClick: refreshServices }, refreshing ? __( 'Refreshing…', 'aluleth-forms' ) : __( 'Refresh', 'aluleth-forms' ) ),
						el( 'span', { className: 'alf-b-hint' }, supports( s, 'alert' ) ? sprintf( /* translators: %s: tool name, e.g. Telegram. */ __( 'Reloads the chats from %s.', 'aluleth-forms' ), s.label ) : sprintf( /* translators: %s: tool name, e.g. Mailchimp. */ __( 'Reloads the lists, tags and fields from %s.', 'aluleth-forms' ), s.label ) ) ) );
				}
				if ( needsConsent && ! hasConsent ) {
					parts.push( el( 'p', { key: 'nc', className: 'alf-b-warn' }, __( 'This form has no newsletter consent field, so nobody will be sent. Add a Consent field and keep “This is a newsletter consent” on.', 'aluleth-forms' ) ) );
				}
				if ( supports( s, 'fields' ) && s.available && s.fields && s.fields.length ) {
					parts.push( el( 'div', { key: 'map', className: 'alf-b-map' },
						el( 'span', { className: 'alf-b-label' }, sprintf( /* translators: %s: tool name, e.g. MailPoet. */ __( '%s custom fields', 'aluleth-forms' ), s.label ) ),
						s.fields.map( function ( rf ) {
							var map = feed.map || {};
							return el( 'label', { key: rf.id, className: 'alf-b-map-row' },
								el( 'span', null, rf.name ),
								el( 'select', { value: map[ rf.id ] || '', onChange: function ( e ) { var m = Object.assign( {}, map ); if ( e.target.value ) { m[ rf.id ] = e.target.value; } else { delete m[ rf.id ]; } update( i, { map: m } ); } },
									el( 'option', { value: '' }, __( 'Not filled', 'aluleth-forms' ) ),
									inputs.map( function ( f ) { return el( 'option', { key: f.id, value: f.id }, stripTags( f.label ) || f.id ); } ) ) );
						} ),
						el( 'p', { className: 'alf-b-hint' }, __( 'Email, name and phone are sent automatically from the fields saved to the contact.', 'aluleth-forms' ) )
					) );
				} else if ( s.id !== 'webhook' && ! supports( s, 'alert' ) ) {
					parts.push( el( 'p', { key: 'auto', className: 'alf-b-hint' }, __( 'Email, name and phone are sent automatically from the fields saved to the contact.', 'aluleth-forms' ) ) );
				}

				return el( 'section', { key: feed.id, className: 'alf-b-feed' + ( feed.enabled ? '' : ' is-off' ), 'aria-label': s.label },
					el( 'header', { className: 'alf-b-feed-head' },
						el( 'span', { className: 'alf-b-feed-logo', 'aria-hidden': true }, s.label.charAt( 0 ).toUpperCase() ),
						el( 'strong', null, s.label ),
						el( 'input', { type: 'text', className: 'alf-b-feed-name', maxLength: 60, value: feed.name, placeholder: __( 'Name (optional)', 'aluleth-forms' ), 'aria-label': sprintf( /* translators: %s: tool name, e.g. MailPoet. */ __( 'Name of this %s integration', 'aluleth-forms' ), s.label ), onChange: function ( e ) { update( i, { name: e.target.value } ); } } ),
						el( 'button', { type: 'button', role: 'switch', 'aria-checked': !! feed.enabled, 'aria-label': __( 'Active', 'aluleth-forms' ), title: feed.enabled ? __( 'Active', 'aluleth-forms' ) : __( 'Paused', 'aluleth-forms' ), className: 'alf-b-switch' + ( feed.enabled ? ' is-on' : '' ), onClick: function () { update( i, { enabled: ! feed.enabled } ); } }, el( 'span' ) ),
						el( IconButton, { icon: 'trash', label: __( 'Remove this integration', 'aluleth-forms' ), onClick: function () { setFeeds( feeds.filter( function ( x, j ) { return j !== i; } ) ); } } )
					),
					el( 'div', { className: 'alf-b-feed-body' }, parts )
				);
			}

			return [
				el( 'h2', { key: 'h', className: 'alf-b-h' }, __( 'Integrations', 'aluleth-forms' ) ),
				el( 'p', { key: 'p', className: 'alf-b-hint' }, __( 'Send each entry to the tools you use. Sending happens in the background after the visitor sees your message, so a slow tool never slows down the form. Each send is listed on the entry.', 'aluleth-forms' ) ),
				! hasEmail ? el( 'p', { key: 'ne', className: 'alf-b-warn' }, __( 'This form has no Email field: newsletter and CRM tools need one.', 'aluleth-forms' ) ) : null,
				feeds.length ? el( 'div', { key: 'list', className: 'alf-b-feeds' }, feeds.map( card ) ) : null,
				feeds.length < 10 ? el( 'div', { key: 'add', className: 'alf-b-svc-add' },
					el( 'span', { className: 'alf-b-label' }, feeds.length ? __( 'Add another integration', 'aluleth-forms' ) : __( 'Add an integration', 'aluleth-forms' ) ),
					el( 'div', { className: 'alf-b-svc-list' }, SERVICES.map( function ( s ) {
						if ( ! s.available && s.kind === 'api' ) {
							return el( 'a', { key: s.id, className: 'alf-b-svc is-off', href: cfg.connectUrl + '#al-svc-' + s.id },
								el( 'span', { className: 'alf-b-feed-logo', 'aria-hidden': true }, s.label.charAt( 0 ).toUpperCase() ),
								el( 'span', null, el( 'strong', null, s.label ), el( 'small', null, __( 'Not connected yet: connect it in Aluleth › Connections.', 'aluleth-forms' ) ) ) );
						}
						return el( 'button', { key: s.id, type: 'button', className: 'alf-b-svc', disabled: ! s.available, title: s.available ? s.description : s.missing, onClick: function () { setFeeds( feeds.concat( [ newFeed( s ) ] ) ); } },
							el( 'span', { className: 'alf-b-feed-logo', 'aria-hidden': true }, s.label.charAt( 0 ).toUpperCase() ),
							el( 'span', null, el( 'strong', null, s.label ), el( 'small', null, s.available ? s.description : s.missing ) ) );
					} ) )
				) : null,
				el( 'p', { key: 'more', className: 'alf-b-hint' }, el( 'a', { href: cfg.connectUrl }, __( 'Connections and recent sends', 'aluleth-forms' ) ) )
			];
		}

		/* ----- settings tab ----- */
		var ADMIN_ONLY = __( 'Only an administrator can change this.', 'aluleth-forms' );
		var sections = [
			[ 'general', __( 'After sending', 'aluleth-forms' ) ],
			[ 'emails', __( 'Email notifications', 'aluleth-forms' ) ],
			[ 'contact', __( 'Contact', 'aluleth-forms' ) ],
			cfg.canManage ? [ 'integrations', __( 'Integrations', 'aluleth-forms' ) ] : null,
			[ 'statuses', __( 'Entry statuses', 'aluleth-forms' ) ],
			[ 'spam', __( 'Spam protection', 'aluleth-forms' ) ]
		].filter( Boolean );
		sections = hooks.applyFilters( 'alulethForms.builder.sections', sections, { settings: settings, fields: fields, canManage: !! cfg.canManage } );
		var DOTS = [ '#e8590c', '#2f5bea', '#7c3aed', '#0e7490', '#b45309', '#be185d', '#1e3a8a' ];
		function dotColor( i, n ) { return i === n - 1 && n > 1 ? '#5b6370' : DOTS[ i % DOTS.length ]; }
		function settingsBody() {
			var customSection = hooks.applyFilters( 'alulethForms.builder.section', null, section, { settings: settings, setSetting: setSetting, fields: fields, canManage: !! cfg.canManage, ui: UI } );
			if ( customSection ) { return customSection; }
			if ( section === 'integrations' && cfg.canManage ) {
				return integrationsBody();
			}
			if ( section === 'statuses' ) {
				var list = settings.statuses && settings.statuses.length ? settings.statuses : [];
				var setList = function ( next ) { setSetting( 'statuses', next ); };
				var move = function ( i, j ) { var next = list.slice(); var item = next.splice( i, 1 )[ 0 ]; next.splice( j, 0, item ); setList( next ); };
				return [
					el( 'h2', { key: 'h', className: 'alf-b-h' }, __( 'Entry statuses', 'aluleth-forms' ) ),
					el( 'p', { key: 'p', className: 'alf-b-hint' }, __( 'They are the columns of your entries board. New entries arrive in the first one.', 'aluleth-forms' ) ),
					el( 'ol', { key: 'l', className: 'alf-b-statuses' }, list.map( function ( st, i ) {
						return el( 'li', { key: st.key ? st.key : 'new-' + i, className: 'alf-b-status-row' },
							el( 'span', { className: 'alf-b-dot', style: { background: dotColor( i, list.length ) }, 'aria-hidden': true } ),
							el( 'input', { type: 'text', maxLength: 40, value: st.label, 'aria-label': sprintf( /* translators: %d: status number. */ __( 'Name of status %d', 'aluleth-forms' ), i + 1 ), onChange: function ( e ) { setList( list.map( function ( x, j ) { return j === i ? Object.assign( {}, x, { label: e.target.value } ) : x; } ) ); } } ),
							el( IconButton, { icon: 'up', label: __( 'Move up', 'aluleth-forms' ), disabled: i === 0, onClick: function () { move( i, i - 1 ); } } ),
							el( IconButton, { icon: 'down', label: __( 'Move down', 'aluleth-forms' ), disabled: i === list.length - 1, onClick: function () { move( i, i + 1 ); } } ),
							el( IconButton, { icon: 'trash', label: __( 'Remove this status', 'aluleth-forms' ), disabled: list.length < 2, onClick: function () { setList( list.filter( function ( x, j ) { return j !== i; } ) ); } } )
						);
					} ) ),
					list.length < 8 ? el( 'button', { key: 'a', type: 'button', className: 'alf-b-add', onClick: function () { setList( list.concat( [ { key: '', label: __( 'New status', 'aluleth-forms' ) } ] ) ); } }, el( Icon, { d: ICONS.plus, size: 16 } ), __( 'Add a status', 'aluleth-forms' ) ) : null,
					el( 'p', { key: 'n', className: 'alf-b-hint' }, __( 'Renaming a status keeps its entries. Entries of a removed status go back to the first column.', 'aluleth-forms' ) )
				];
			}
			if ( section === 'emails' ) {
				return [
					el( 'h2', { key: 'h1', className: 'alf-b-h' }, __( 'Email to your team', 'aluleth-forms' ) ),
					el( Toggle, { key: 'n', name: 'notify', label: __( 'Send each new entry by email', 'aluleth-forms' ), checked: settings.notify, onChange: function ( v ) { setSetting( 'notify', v ); } } ),
					settings.notify ? el( Field, { key: 'to', name: 'notify_to', label: __( 'Send to', 'aluleth-forms' ), placeholder: cfg.adminEmail, value: settings.notify_to, disabled: ! cfg.canManage, help: cfg.canManage ? sprintf( /* translators: %s: the site admin email. */ __( 'Who gets each new entry. Several addresses: separate them with commas. Left empty, entries go to the site admin email (%s).', 'aluleth-forms' ), cfg.adminEmail ) : ADMIN_ONLY, onChange: function ( v ) { setSetting( 'notify_to', v ); } } ) : null,
					settings.notify ? el( Field, { key: 'su', name: 'notify_subject', label: __( 'Subject', 'aluleth-forms' ), value: settings.notify_subject, help: __( '{form_title} and {site_name} are replaced automatically.', 'aluleth-forms' ), onChange: function ( v ) { setSetting( 'notify_subject', v ); } } ) : null,
					el( 'h2', { key: 'h2', className: 'alf-b-h' }, __( 'Email to the visitor', 'aluleth-forms' ) ),
					el( Toggle, { key: 'c', name: 'confirm', label: __( 'Send a confirmation with a copy of their answers', 'aluleth-forms' ), checked: settings.confirm, onChange: function ( v ) { setSetting( 'confirm', v ); } } ),
					settings.confirm ? el( Field, { key: 'cs', name: 'confirm_subject', label: __( 'Subject', 'aluleth-forms' ), value: settings.confirm_subject, onChange: function ( v ) { setSetting( 'confirm_subject', v ); } } ) : null,
					settings.confirm ? el( Field, { key: 'cm', name: 'confirm_message', label: __( 'Message', 'aluleth-forms' ), multiline: true, rows: 5, value: settings.confirm_message, onChange: function ( v ) { setSetting( 'confirm_message', v ); } } ) : null
				];
			}
			if ( section === 'contact' ) {
				return [
					el( 'h2', { key: 'h', className: 'alf-b-h' }, __( 'Contact', 'aluleth-forms' ) ),
					el( 'p', { key: 'p', className: 'alf-b-hint' }, __( 'Each entry with an email creates or updates a contact, shared by every Aluleth plugin.', 'aluleth-forms' ) ),
					el( Field, { key: 't', name: 'tags', label: __( 'Tags added to the contact', 'aluleth-forms' ), value: ( settings.tags || [] ).join( ', ' ), help: __( 'Separated by commas.', 'aluleth-forms' ), onChange: function ( v ) { setSetting( 'tags', v.split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean ) ); } } )
				];
			}
			if ( section === 'spam' ) {
				return [
					el( 'h2', { key: 'h', className: 'alf-b-h' }, __( 'Spam protection', 'aluleth-forms' ) ),
					el( 'p', { key: 'p', className: 'alf-b-hint' }, __( 'Always on, with no outside service: a hidden field that only robots fill in, a check of how fast the form was sent, and a limit of sends per visitor (Aluleth › Settings › Security).', 'aluleth-forms' ) ),
					el( Field, { key: 'm', name: 'min_seconds', type: 'number', label: __( 'Minimum seconds before sending', 'aluleth-forms' ), value: settings.min_seconds, help: __( 'Forms sent faster than this are treated as robots. 3 seconds suits most forms.', 'aluleth-forms' ), onChange: function ( v ) { setSetting( 'min_seconds', Math.max( 0, Math.min( 60, parseInt( v, 10 ) || 0 ) ) ); } } ),
					( function () {
						var conf = cfg.captcha || {};
						var services = conf.services || {};
						var def = conf[ 'default' ] && services[ conf[ 'default' ] ] && services[ conf[ 'default' ] ].ready ? services[ conf[ 'default' ] ].label : __( 'none', 'aluleth-forms' );
						var opts = [ el( 'option', { key: 'default', value: 'default' }, sprintf( /* translators: %s: anti-spam service name. */ __( 'Default (%s)', 'aluleth-forms' ), def ) ), el( 'option', { key: 'none', value: 'none' }, __( 'None (built-in protection only)', 'aluleth-forms' ) ) ];
						Object.keys( services ).forEach( function ( id ) {
							opts.push( el( 'option', { key: id, value: id, disabled: ! services[ id ].ready }, services[ id ].label + ( services[ id ].ready ? '' : ' – ' + __( 'not set up', 'aluleth-forms' ) ) ) );
						} );
						return el( 'div', { key: 'c', className: 'alf-b-control' },
							el( 'label', { htmlFor: 'alf-b-captcha' }, __( 'Captcha or anti-spam service', 'aluleth-forms' ) ),
							el( 'select', { id: 'alf-b-captcha', value: settings.captcha || 'default', onChange: function ( e ) { setSetting( 'captcha', e.target.value ); } }, opts ),
							el( 'p', { className: 'alf-b-hint' }, __( 'Services are set up once for every form in Aluleth › Settings › Security.', 'aluleth-forms' ), conf.settingsUrl ? [ ' ', el( 'a', { key: 'l', href: conf.settingsUrl }, __( 'Open the security settings', 'aluleth-forms' ) ) ] : null )
						);
					}() )
				];
			}
			return [
				el( 'h2', { key: 'h', className: 'alf-b-h' }, __( 'After sending', 'aluleth-forms' ) ),
				el( Field, { key: 'm', name: 'success', label: __( 'Message shown after sending', 'aluleth-forms' ), multiline: true, value: settings.success_message, onChange: function ( v ) { setSetting( 'success_message', v ); } } ),
				el( Field, { key: 'r', name: 'redirect', type: 'url', label: __( 'Or go to this page instead', 'aluleth-forms' ), placeholder: 'https://', value: settings.redirect, disabled: ! cfg.canManage, help: cfg.canManage ? __( 'Leave empty to show the message.', 'aluleth-forms' ) : ADMIN_ONLY, onChange: function ( v ) { setSetting( 'redirect', v ); } } ),
				el( Field, { key: 'b', name: 'submit2', label: __( 'Button text', 'aluleth-forms' ), value: settings.submit_label, onChange: function ( v ) { setSetting( 'submit_label', v ); } } )
			];
		}

		var body;
		if ( tab === 'settings' ) {
			body = el( 'div', { className: 'alf-b-body' },
				el( 'nav', { className: 'alf-b-palette alf-b-sections', 'aria-label': __( 'Settings sections', 'aluleth-forms' ) },
					sections.map( function ( s ) { return el( 'button', { key: s[ 0 ], type: 'button', className: section === s[ 0 ] ? 'is-on' : '', 'aria-current': section === s[ 0 ] ? 'true' : undefined, onClick: function () { setSection( s[ 0 ] ); } }, s[ 1 ] ); } ) ),
				el( 'main', { className: 'alf-b-settings' }, el( 'div', { className: 'alf-b-card' }, settingsBody() ) )
			);
		} else if ( tab === 'design' ) {
			body = el( 'div', { className: 'alf-b-body' }, designPanel, canvas );
		} else {
			body = el( 'div', { className: 'alf-b-body' }, palette, canvas, el( 'aside', { className: 'alf-b-panel', 'aria-label': __( 'Field settings', 'aluleth-forms' ) }, fieldPanel() ) );
		}

		return el( Fragment, null,
			top,
			body,
			el( 'div', { className: 'alf-b-status' + ( status.kind ? ' is-' + status.kind : '' ), role: 'status', 'aria-live': 'polite' }, status.text )
		);
	}

	function start() {
		var root = document.getElementById( 'alf-builder' );
		if ( ! root ) { return; }
		GROUPS = hooks.applyFilters( 'alulethForms.builder.groups', GROUPS );
		TYPES = hooks.applyFilters( 'alulethForms.builder.types', TYPES );
		TYPE_NAMES = hooks.applyFilters( 'alulethForms.builder.typeNames', TYPE_NAMES );
		INPUTS = hooks.applyFilters( 'alulethForms.builder.inputs', INPUTS );
		PAY_TYPES = hooks.applyFilters( 'alulethForms.builder.payTypes', PAY_TYPES );
		NO_COND = hooks.applyFilters( 'alulethForms.builder.noConditionTargets', NO_COND );
		if ( wp.element.createRoot ) { wp.element.createRoot( root ).render( el( App ) ); } else { wp.element.render( el( App ), root ); }
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', start ); } else { start(); }
}( window.wp, window.alulethFormsBuilder || {} ) );
