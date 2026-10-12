/* Aluleth Form block (editor). No build step: plain wp.* globals. */
( function ( wp ) {
	'use strict';
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	var SSR = wp.serverSideRender;

	wp.blocks.registerBlockType( 'aluleth/form', {
		edit: function ( props ) {
			var formId = props.attributes.formId;
			var state = useState( null );
			var forms = state[ 0 ];
			var setForms = state[ 1 ];
			var blockProps = be.useBlockProps();

			useEffect( function () {
				wp.apiFetch( { path: '/wp/v2/aluleth-forms?per_page=100&status=publish&_fields=id,title' } )
					.then( function ( list ) { setForms( list || [] ); } )
					.catch( function () { setForms( [] ); } );
			}, [] );

			var options = [ { value: 0, label: __( 'Choose a form…', 'aluleth-forms' ) } ].concat(
				( forms || [] ).map( function ( f ) {
					return { value: f.id, label: ( f.title && f.title.rendered ) || ( '#' + f.id ) };
				} )
			);

			var picker = el( c.SelectControl, {
				label: __( 'Form', 'aluleth-forms' ),
				value: formId,
				options: options,
				onChange: function ( v ) { props.setAttributes( { formId: parseInt( v, 10 ) || 0 } ); },
				__nextHasNoMarginBottom: true
			} );

			return el(
				'div',
				blockProps,
				el( be.InspectorControls, {}, el( c.PanelBody, { title: __( 'Form', 'aluleth-forms' ) }, picker ) ),
				formId
					? el( SSR, { block: 'aluleth/form', attributes: { formId: formId } } )
					: el( c.Placeholder, { icon: 'feedback', label: __( 'Aluleth Form', 'aluleth-forms' ) },
						forms === null ? el( c.Spinner ) : ( forms.length ? picker : el( 'p', {}, __( 'No published form yet. Create one in Aluleth › Forms.', 'aluleth-forms' ) ) )
					)
			);
		},
		save: function () { return null; }
	} );
}( window.wp ) );
