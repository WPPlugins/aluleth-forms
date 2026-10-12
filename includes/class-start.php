<?php
/**
 * "Create a form" screen: describe the form to AI Writer, start from a
 * template, or start blank.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Admin as Core_Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Start screen.
 */
final class Start {

	/**
	 * Page slug.
	 */
	const PAGE = 'aluleth-forms-new';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 31 );
		add_action( 'admin_post_aluleth_forms_template', array( __CLASS__, 'from_template' ) );
		add_action( 'admin_post_aluleth_forms_ai', array( __CLASS__, 'from_ai' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
	}

	/**
	 * Keeps the Aluleth › Forms menu open on this screen.
	 *
	 * @param string $parent_file Parent menu.
	 * @return string
	 */
	public static function parent_file( $parent_file ) {
		global $plugin_page, $submenu_file, $submenu;
		if ( self::PAGE === $plugin_page ) {
			$submenu_file = 'edit.php?post_type=' . Plugin::POST_TYPE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- highlights the Forms menu item.
			// WordPress would reset the parent of a hidden page to none right after this filter.
			foreach ( isset( $submenu[''] ) ? (array) $submenu[''] : array() as $i => $item ) {
				if ( isset( $item[2] ) && self::PAGE === $item[2] ) {
					unset( $submenu[''][ $i ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- access was already checked.
				}
			}
			return \Aluleth\Core\Admin::MENU;
		}
		return $parent_file;
	}

	/**
	 * Hidden page (opened by "Add new form").
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( '', __( 'Create a form', 'aluleth-forms' ), __( 'Create a form', 'aluleth-forms' ), Core_Admin::cap( 'forms' ), self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Screen URL.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * Whether AI Writer can write forms on this site.
	 *
	 * @return string 'ready', 'no-provider' or 'missing'.
	 */
	public static function ai_state() {
		if ( ! class_exists( '\Aluleth_AIW_AI' ) ) {
			return 'missing';
		}
		return \Aluleth_AIW_AI::is_ready() ? 'ready' : 'no-provider';
	}

	/**
	 * Styles and the small script of the screen.
	 *
	 * @param string $hook Screen hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		$url = plugins_url( 'assets/', ALULETH_FORMS_FILE );
		wp_enqueue_style( 'aluleth-forms-start', $url . 'start.css', array(), ALULETH_FORMS_VERSION );
		wp_enqueue_script( 'aluleth-forms-start', $url . 'start.js', array(), ALULETH_FORMS_VERSION, true );
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( Core_Admin::cap( 'forms' ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to create forms.', 'aluleth-forms' ) );
		}
		$notice = get_transient( 'aluleth_forms_start_' . get_current_user_id() );
		delete_transient( 'aluleth_forms_start_' . get_current_user_id() );
		$brief = is_array( $notice ) && isset( $notice['brief'] ) ? (string) $notice['brief'] : '';
		$state = self::ai_state();

		echo '<div class="wrap alf-start">';
		echo '<header class="alf-start-head"><span class="alf-start-kicker">' . esc_html__( 'New form', 'aluleth-forms' ) . '</span><h1>' . esc_html__( 'Create a form', 'aluleth-forms' ) . '</h1>';
		echo '<p>' . esc_html( 'missing' === $state ? __( 'Start from one of our designs, or from a blank form.', 'aluleth-forms' ) : __( 'Describe what you need and AI Writer builds it, or start from one of our designs.', 'aluleth-forms' ) ) . '</p></header>';

		if ( is_array( $notice ) && ! empty( $notice['error'] ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $notice['error'] ) . '</p></div>';
		}

		if ( 'missing' !== $state ) {
			$chips = array(
				__( 'Contact', 'aluleth-forms' )       => __( 'A contact form for my business: name, email, phone and message.', 'aluleth-forms' ),
				__( 'Booking', 'aluleth-forms' )       => __( 'A booking form: the service, a preferred day and time, then name, phone and email.', 'aluleth-forms' ),
				__( 'Quote request', 'aluleth-forms' ) => __( 'A quote request: the service needed, budget, deadline and project details.', 'aluleth-forms' ),
				__( 'Survey', 'aluleth-forms' )        => __( 'A short customer survey: satisfaction, what they liked, what to improve.', 'aluleth-forms' ),
				__( 'Registration', 'aluleth-forms' )  => __( 'An event registration: name, email, number of people and dietary needs.', 'aluleth-forms' ),
			);
			echo '<form class="alf-start-ai" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'aluleth_forms_ai' );
			echo '<input type="hidden" name="action" value="aluleth_forms_ai">';
			echo '<label for="alf-brief">' . esc_html__( 'Describe the form you need', 'aluleth-forms' ) . '</label>';
			echo '<textarea id="alf-brief" name="brief" rows="3" maxlength="1000" required placeholder="' . esc_attr__( 'For example: a booking form for my bike repair workshop: name, phone, type of bike, the repair needed and a preferred day.', 'aluleth-forms' ) . '"' . ( 'ready' !== $state ? ' disabled' : '' ) . '>' . esc_textarea( $brief ) . '</textarea>';
			echo '<div class="alf-start-row"><div class="alf-start-chips">';
			foreach ( $chips as $label => $text ) {
				echo '<button type="button" class="alf-chip-btn" data-brief="' . esc_attr( $text ) . '"' . ( 'ready' !== $state ? ' disabled' : '' ) . '>' . esc_html( $label ) . '</button>';
			}
			echo '</div><button type="submit" class="alf-start-go"' . ( 'ready' !== $state ? ' disabled' : '' ) . '><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8z"/><path d="M19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/></svg><span>' . esc_html__( 'Write my form', 'aluleth-forms' ) . '</span></button></div>';
			if ( 'ready' === $state ) {
				echo '<p class="alf-start-note">' . esc_html__( 'Uses AI Writer with your own AI key. The form opens as a draft in the builder: you review every field before publishing.', 'aluleth-forms' ) . '</p>';
			} else {
				echo '<p class="alf-start-note">' . esc_html__( 'AI Writer needs an AI provider first.', 'aluleth-forms' ) . ' <a href="' . esc_url( \Aluleth_AIW_AI::connectors_url() ) . '">' . esc_html__( 'Connect one in Settings › Connectors', 'aluleth-forms' ) . '</a></p>';
			}
			echo '<p class="alf-start-wait" hidden role="status">' . esc_html__( 'AI Writer is writing your form. This takes about 10 to 30 seconds…', 'aluleth-forms' ) . '</p>';
			echo '</form>';
		}

		// Templates of add-ons can come in their own group (template key "group": its heading).
		$groups = array( '' => array() );
		foreach ( Templates::all() as $id => $template ) {
			$group                   = isset( $template['group'] ) ? (string) $template['group'] : '';
			$groups[ $group ][ $id ] = $template;
		}
		foreach ( $groups as $group => $templates ) {
			$heading = '' !== $group ? $group : ( 'missing' === $state ? __( 'Start from a design', 'aluleth-forms' ) : __( 'Or start from a design', 'aluleth-forms' ) );
			echo '<section class="alf-start-templates"><h2>' . esc_html( $heading ) . '</h2><div class="alf-start-grid">';
			foreach ( $templates as $id => $template ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="alf-start-card">';
				wp_nonce_field( 'aluleth_forms_template' );
				echo '<input type="hidden" name="action" value="aluleth_forms_template"><input type="hidden" name="template" value="' . esc_attr( $id ) . '">';
				$badge = ! empty( $template['badge'] ) ? '<em class="alf-start-badge">' . esc_html( (string) $template['badge'] ) . '</em>' : '';
				echo '<button type="submit">' . self::thumb( $template ) . '<span class="alf-start-card-text"><strong>' . esc_html( $template['title'] ) . $badge . '</strong><span>' . esc_html( $template['text'] ) . '</span></span></button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- thumb() and the badge are escaped.
			}
			if ( '' === $group ) {
				echo '<a class="alf-start-card alf-start-blank" href="' . esc_url( wp_nonce_url( admin_url( 'post-new.php?post_type=' . Plugin::POST_TYPE . '&blank=1' ), 'aluleth_forms_blank' ) ) . '"><span class="alf-start-plus" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span><strong>' . esc_html__( 'Blank form', 'aluleth-forms' ) . '</strong></a>';
			}
			echo '</div></section>';
		}
		$found = array_filter(
			Importer::found(),
			static function ( $item ) {
				return ! $item['imported'];
			}
		);
		if ( $found ) {
			/* translators: %d: number of forms. */
			echo '<p class="alf-start-import"><strong>' . esc_html( sprintf( _n( '%d form from another plugin was found on this site.', '%d forms from other plugins were found on this site.', count( $found ), 'aluleth-forms' ), count( $found ) ) ) . '</strong> <a href="' . esc_url( Import_Screen::url() ) . '">' . esc_html__( 'Import them with their entries', 'aluleth-forms' ) . '</a></p>';
		} else {
			echo '<p class="alf-start-import">' . esc_html__( 'Moving from another plugin?', 'aluleth-forms' ) . ' <a href="' . esc_url( Import_Screen::url() ) . '">' . esc_html__( 'Import your forms and entries', 'aluleth-forms' ) . '</a> ' . esc_html__( 'from Contact Form 7, WPForms, Elementor Forms or MetForm.', 'aluleth-forms' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Small drawing of a template in its style (bars for labels, fields and button).
	 *
	 * @param array $template Template.
	 * @return string HTML.
	 */
	private static function thumb( array $template ) {
		$style = isset( $template['style'] ) && in_array( $template['style'], array( 'clean', 'soft', 'dark' ), true ) ? $template['style'] : 'clean';
		$rows  = '';
		$count = 0;
		$half  = false;
		foreach ( $template['fields'] as $field ) {
			if ( $count >= 4 ) {
				break;
			}
			if ( in_array( $field['type'], array( 'step', 'heading', 'paragraph', 'hidden', 'consent' ), true ) ) {
				continue;
			}
			$is_half = isset( $field['width'] ) && 'half' === $field['width'];
			if ( $is_half && $half ) {
				$rows .= '<i class="h"></i></span>';
				$half  = false;
			} elseif ( $is_half ) {
				$rows .= '<span class="r"><i class="h"></i>';
				$half  = true;
			} else {
				if ( $half ) {
					$rows .= '</span>';
					$half  = false;
				}
				$rows .= in_array( $field['type'], array( 'radio', 'checkboxes', 'rating', 'scale' ), true ) ? '<span class="r c"><i></i><i></i><i></i></span>' : '<i' . ( 'textarea' === $field['type'] ? ' class="t"' : '' ) . '></i>';
			}
			++$count;
		}
		if ( $half ) {
			$rows .= '</span>';
		}
		$accent = isset( $template['settings']['design']['accent'] ) && preg_match( '/^#[0-9a-f]{6}$/i', (string) $template['settings']['design']['accent'] ) ? $template['settings']['design']['accent'] : '';
		return '<span class="alf-thumb alf-thumb--' . esc_attr( $style ) . ( '' !== $accent ? ' has-accent" style="--t-accent:' . esc_attr( $accent ) : '' ) . '" aria-hidden="true"><span class="p"><b></b>' . $rows . '<em></em></span></span>';
	}

	/**
	 * Creates a draft form from a template and opens the builder.
	 *
	 * @return void
	 */
	public static function from_template() {
		check_admin_referer( 'aluleth_forms_template' );
		if ( ! current_user_can( Core_Admin::cap( 'forms' ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to create forms.', 'aluleth-forms' ) );
		}
		$template = Templates::get( isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '' );
		if ( ! $template ) {
			wp_safe_redirect( self::url() );
			exit;
		}
		self::open_draft( $template['title'], $template['schema'] );
	}

	/**
	 * Asks AI Writer for a form, creates it as a draft and opens the builder.
	 *
	 * @return void
	 */
	public static function from_ai() {
		check_admin_referer( 'aluleth_forms_ai' );
		if ( ! current_user_can( Core_Admin::cap( 'forms' ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to create forms.', 'aluleth-forms' ) );
		}
		$brief = isset( $_POST['brief'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['brief'] ) ) ) : '';
		$brief = function_exists( 'mb_substr' ) ? mb_substr( $brief, 0, 1000 ) : substr( $brief, 0, 1000 );
		$form  = '' === $brief ? new \WP_Error( 'empty', __( 'Describe the form you need first.', 'aluleth-forms' ) ) : self::ai_form( $brief );
		if ( is_wp_error( $form ) ) {
			set_transient(
				'aluleth_forms_start_' . get_current_user_id(),
				array(
					'error' => $form->get_error_message(),
					'brief' => $brief,
				),
				10 * MINUTE_IN_SECONDS
			);
			wp_safe_redirect( self::url() );
			exit;
		}
		self::open_draft( $form['title'], $form['schema'] );
	}

	/**
	 * Saves a draft form and redirects to the builder.
	 *
	 * @param string $title  Title.
	 * @param array  $schema Clean definition.
	 * @return void
	 */
	private static function open_draft( $title, array $schema ) {
		$id = wp_insert_post(
			array(
				'post_type'   => Plugin::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => sanitize_text_field( $title ),
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			wp_die( esc_html__( 'The form could not be created.', 'aluleth-forms' ) );
		}
		Forms::save_schema( (int) $id, Forms::with_defaults( $schema, true ) );
		wp_safe_redirect( Builder::url( (int) $id ) );
		exit;
	}

	/**
	 * Builds a form from a description with AI Writer.
	 *
	 * @param string $brief Description.
	 * @return array|\WP_Error array( 'title', 'schema' ).
	 */
	public static function ai_form( $brief ) {
		if ( 'ready' !== self::ai_state() ) {
			return new \WP_Error( 'ai', __( 'AI Writer is not ready: connect an AI provider in Settings › Connectors.', 'aluleth-forms' ) );
		}
		$language = class_exists( '\Aluleth_AIW_Voice' ) && method_exists( '\Aluleth_AIW_Voice', 'site_language' ) ? \Aluleth_AIW_Voice::site_language() : get_bloginfo( 'language' );
		$system   = "You design web forms for a WordPress site. Return only what the JSON schema asks for.\n"
			. 'Write every label, option, title and message in ' . $language . " unless the description is clearly written in another language; then use that language.\n"
			. "Rules:\n"
			. "- Ask only for what the description needs; most forms have 3 to 8 fields. Short, clear labels.\n"
			. "- Field types: text, email, phone, number, textarea, select (one choice in a list), radio (one choice, 2 to 5 visible options), checkboxes (several choices), date, consent (a checkbox), heading, paragraph, step.\n"
			. "- When the site may answer the person, include an email field (required). Put first name and last name in one text field \"Your name\" with map full_name, unless the description asks for separate fields (then map first_name and last_name). Phone fields use map phone.\n"
			. "- Use width half for short fields that go well in pairs (name and email, phone and date).\n"
			. "- Add a consent field with marketing true only when the description mentions a newsletter, offers or marketing emails; its label says what the person agrees to receive and that they can unsubscribe.\n"
			. "- For long forms (more than 7 fields), split them into steps: a step field starts each step, including the first one, and its label is the step title.\n"
			. "- options is only for select, radio and checkboxes (2 to 8 options).\n"
			. "- statuses: 3 or 4 short names for the stages of follow-up of an entry (for example New, Confirmed, Done), in the same language.\n"
			. '- No placeholder text that repeats the label; leave placeholder empty in most cases.';
		$schema   = array(
			'type'                 => 'object',
			'properties'           => array(
				'title'           => array( 'type' => 'string' ),
				'submit_label'    => array( 'type' => 'string' ),
				'success_message' => array( 'type' => 'string' ),
				'statuses'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'fields'          => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'properties'           => array(
							'type'        => array(
								'type' => 'string',
								'enum' => array( 'text', 'email', 'phone', 'number', 'textarea', 'select', 'radio', 'checkboxes', 'date', 'consent', 'heading', 'paragraph', 'step' ),
							),
							'label'       => array( 'type' => 'string' ),
							'required'    => array( 'type' => 'boolean' ),
							'width'       => array(
								'type' => 'string',
								'enum' => array( 'full', 'half' ),
							),
							'map'         => array(
								'type' => 'string',
								'enum' => array( '', 'first_name', 'last_name', 'full_name', 'phone' ),
							),
							'options'     => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'placeholder' => array( 'type' => 'string' ),
							'help'        => array( 'type' => 'string' ),
							'marketing'   => array( 'type' => 'boolean' ),
							'text'        => array( 'type' => 'string' ),
						),
						'required'             => array( 'type', 'label' ),
						'additionalProperties' => false,
					),
				),
			),
			'required'             => array( 'title', 'fields' ),
			'additionalProperties' => false,
		);
		$data     = \Aluleth_AIW_AI::json( $system, 'Description of the form: ' . $brief, $schema, array( 'max_tokens' => 3000 ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$fields = array();
		foreach ( isset( $data['fields'] ) && is_array( $data['fields'] ) ? $data['fields'] : array() as $field ) {
			if ( ! is_array( $field ) || empty( $field['type'] ) ) {
				continue;
			}
			$field['id'] = substr( sanitize_key( str_replace( array( ' ', '-' ), '_', remove_accents( isset( $field['label'] ) ? (string) $field['label'] : '' ) ) ), 0, 30 );
			if ( 'email' === $field['type'] && ! self::has_type( $fields, 'email' ) ) {
				$field['id'] = 'email';
			}
			$fields[] = $field;
		}
		if ( ! $fields ) {
			return new \WP_Error( 'ai', __( 'AI Writer did not return any field. Try again with a few more details.', 'aluleth-forms' ) );
		}
		$settings = array();
		foreach ( array( 'submit_label', 'success_message' ) as $key ) {
			if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				$settings[ $key ] = $data[ $key ];
			}
		}
		if ( ! empty( $data['statuses'] ) && is_array( $data['statuses'] ) ) {
			$settings['statuses'] = array();
			foreach ( array_slice( $data['statuses'], 0, 6 ) as $label ) {
				$settings['statuses'][] = array(
					'key'   => '',
					'label' => (string) $label,
				);
			}
		}
		return array(
			'title'  => ! empty( $data['title'] ) ? (string) $data['title'] : __( 'New form', 'aluleth-forms' ),
			'schema' => Schema::clean(
				array(
					'fields'   => $fields,
					'settings' => $settings,
				)
			),
		);
	}

	/**
	 * Whether a list of fields already has a field type.
	 *
	 * @param array[] $fields Fields.
	 * @param string  $type   Type.
	 * @return bool
	 */
	private static function has_type( array $fields, $type ) {
		foreach ( $fields as $field ) {
			if ( isset( $field['type'] ) && $type === $field['type'] ) {
				return true;
			}
		}
		return false;
	}
}
