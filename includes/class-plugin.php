<?php
/**
 * Aluleth Forms main class.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Admin as Core_Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress.
 */
final class Plugin {

	/**
	 * Post type that stores forms.
	 */
	const POST_TYPE = 'aluleth_form';

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks everything.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_shortcode( 'aluleth_form', array( $this, 'shortcode' ) );
		add_action( 'aluleth/contact/deleted', array( Entries::class, 'delete_for_contact' ) );
		add_filter( 'aluleth/event/label', array( $this, 'event_label' ), 10, 2 );
		add_filter( 'aluleth/queue/object_link', array( $this, 'entry_link' ), 10, 3 );
		add_filter( 'rest_pre_dispatch', array( $this, 'private_rest' ), 10, 3 );
		add_filter( 'aluleth/settings/uninstall', array( $this, 'uninstall_choice' ) );
		add_filter( 'aluleth/access/areas', array( $this, 'access_areas' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ALULETH_FORMS_FILE ), array( $this, 'action_links' ) );
		Submission::register();
		Builder::register();
		Elementor::register();
		Stats::register();
		General::register();
		AI::register();
		Payments::register();
		if ( is_admin() ) {
			Admin::register();
			Board::register();
			Start::register();
			Import_Screen::register();
		}
	}

	/**
	 * Access areas of Aluleth Forms (Aluleth › Settings › Access). By default
	 * editors create and edit forms, as before; entries stay with administrators.
	 *
	 * @param array $areas Areas.
	 * @return array
	 */
	public function access_areas( $areas ) {
		return array(
			'forms'          => array(
				'label'   => __( 'Create and edit forms', 'aluleth-forms' ),
				'default' => array( 'editor' ),
			),
			'entries'        => array(
				'label'   => __( 'See entries', 'aluleth-forms' ),
				'default' => array(),
				'help'    => __( 'Entries hold personal data.', 'aluleth-forms' ),
			),
			'entries_manage' => array(
				'label'   => __( 'Change status, add notes, delete and export entries', 'aluleth-forms' ),
				'default' => array(),
			),
		) + (array) $areas;
	}

	/**
	 * "Delete on uninstall" choice in Aluleth › Settings.
	 *
	 * @param array $choices Choices.
	 * @return array
	 */
	public function uninstall_choice( $choices ) {
		$choices['forms'] = array(
			'option' => 'aluleth_forms_delete_data',
			'label'  => __( 'Aluleth Forms: forms, entries and statistics', 'aluleth-forms' ),
			'help'   => __( 'Deleted with the plugin. Pages that show a form keep their shortcode or block, which then shows nothing.', 'aluleth-forms' ),
		);
		return $choices;
	}

	/**
	 * Links under the plugin's name on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		if ( current_user_can( Core_Admin::cap( 'forms' ) ) ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) ) . '">' . esc_html__( 'Forms', 'aluleth-forms' ) . '</a>' );
		}
		if ( current_user_can( \Aluleth\Core\Admin::cap( 'settings' ) ) ) {
			$links[] = '<a href="' . esc_url( \Aluleth\Core\Settings::url() ) . '">' . esc_html__( 'Settings', 'aluleth-forms' ) . '</a>';
		}
		return $links;
	}

	/**
	 * The forms REST route (used by the block editor) is for editors only:
	 * visitors must not list the forms.
	 *
	 * @param mixed            $result  Response so far.
	 * @param \WP_REST_Server  $server  Server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function private_rest( $result, $server, $request ) {
		unset( $server );
		if ( null === $result && 0 === strpos( $request->get_route(), '/wp/v2/aluleth-forms' ) && ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to see the forms.', 'aluleth-forms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return $result;
	}

	/**
	 * Updates the table when the plugin was updated.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( get_option( 'aluleth_forms_db_version' ) !== Installer::DB_VERSION ) {
			Installer::install();
		}
	}

	/**
	 * Registers the forms post type (shown under the Aluleth menu).
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Forms', 'aluleth-forms' ),
					'singular_name'      => __( 'Form', 'aluleth-forms' ),
					'add_new'            => __( 'New form', 'aluleth-forms' ),
					'add_new_item'       => __( 'New form', 'aluleth-forms' ),
					'edit_item'          => __( 'Edit form', 'aluleth-forms' ),
					'search_items'       => __( 'Search forms', 'aluleth-forms' ),
					'not_found'          => __( 'No forms yet.', 'aluleth-forms' ),
					'not_found_in_trash' => __( 'No forms in the trash.', 'aluleth-forms' ),
					'all_items'          => __( 'Forms', 'aluleth-forms' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => Core_Admin::MENU,
				'show_in_rest'    => true,
				'rest_base'       => 'aluleth-forms',
				'supports'        => array( 'title' ),
				'capability_type' => array( 'aluleth_form', 'aluleth_forms' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Registers the front-end assets (enqueued only when a form is printed).
	 *
	 * @return void
	 */
	public function register_assets() {
		$url = plugins_url( 'assets/', ALULETH_FORMS_FILE );
		wp_register_style( 'aluleth-forms', $url . 'forms.css', array(), ALULETH_FORMS_VERSION );
		wp_register_script(
			'aluleth-forms',
			$url . 'forms.js',
			array(),
			ALULETH_FORMS_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'aluleth-forms',
			'alulethForms',
			array(
				'endpoint' => esc_url_raw( rest_url( 'aluleth-forms/v1/submit' ) ),
				'views'    => esc_url_raw( rest_url( 'aluleth-forms/v1/view' ) ),
				'pay'      => esc_url_raw( rest_url( 'aluleth-forms/v1/pay' ) ),
				'currency' => \Aluleth\Core\PayPal::currency(),
				'i18n'     => array(
					'required'   => General::msg( 'required', __( 'This field is required.', 'aluleth-forms' ) ),
					'email'      => General::msg( 'email', __( 'Please enter a valid email address.', 'aluleth-forms' ) ),
					'choose'     => General::msg( 'choose', __( 'Please choose an option.', 'aluleth-forms' ) ),
					'consent'    => General::msg( 'consent', __( 'Please tick this box to continue.', 'aluleth-forms' ) ),
					'check'      => __( 'Please check the highlighted fields.', 'aluleth-forms' ),
					'summary'    => __( 'Please check these fields:', 'aluleth-forms' ),
					'sending'    => __( 'Sending…', 'aluleth-forms' ),
					'error'      => __( 'Your message could not be sent. Please try again.', 'aluleth-forms' ),
					'captcha'    => __( 'Please complete the anti-spam check, then send the form again.', 'aluleth-forms' ),
					'payNothing' => __( 'Please choose what you want to pay for.', 'aluleth-forms' ),
					'payConfirm' => __( 'Confirming your payment…', 'aluleth-forms' ),
					'payCancel'  => __( 'Payment cancelled. Nothing was charged; you can try again.', 'aluleth-forms' ),
					'payError'   => __( 'The payment could not be completed. Please try again.', 'aluleth-forms' ),
					'payCheck'   => __( 'We could not confirm your payment right away. If money was taken, your answers are safe and we will confirm by email.', 'aluleth-forms' ),
				),
			)
		);
	}

	/**
	 * Registers the "Aluleth Form" block.
	 *
	 * @return void
	 */
	public function register_block() {
		wp_register_script(
			'aluleth-forms-block',
			plugins_url( 'assets/block.js', ALULETH_FORMS_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-api-fetch', 'wp-i18n' ),
			ALULETH_FORMS_VERSION,
			true
		);
		wp_set_script_translations( 'aluleth-forms-block', 'aluleth-forms' );
		register_block_type(
			ALULETH_FORMS_DIR . '/assets/block.json',
			array(
				'editor_script'   => 'aluleth-forms-block',
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Block output.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$id = isset( $attributes['formId'] ) ? absint( $attributes['formId'] ) : 0;
		if ( ! $id ) {
			return '';
		}
		return Renderer::render( $id );
	}

	/**
	 * [aluleth_form id="12" style="soft"] shortcode (style is optional).
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'style' => '',
			),
			$atts,
			'aluleth_form'
		);
		return Renderer::render( absint( $atts['id'] ), array( 'style' => sanitize_key( $atts['style'] ) ) );
	}

	/**
	 * Link and label of an entry in the Connections log.
	 *
	 * @param array  $link Default link.
	 * @param string $type Object type.
	 * @param int    $id   Object id.
	 * @return array
	 */
	public function entry_link( $link, $type, $id ) {
		if ( 'entry' !== $type ) {
			return $link;
		}
		$entry = Entries::get( (int) $id );
		$form  = $entry ? Forms::get( (int) $entry->form_id, true ) : null;
		return array(
			'url'   => add_query_arg(
				array(
					'page'  => 'aluleth-forms-entries',
					'entry' => (int) $id,
				),
				admin_url( 'admin.php' )
			),
			'label' => $entry
				/* translators: 1: entry summary (name or email), 2: form title. */
				? sprintf( __( '%1$s · %2$s', 'aluleth-forms' ), Entries::summary( $entry->data ), $form ? $form['title'] : __( 'Deleted form', 'aluleth-forms' ) )
				/* translators: %d: entry id. */
				: sprintf( __( 'Deleted entry #%d', 'aluleth-forms' ), (int) $id ),
		);
	}

	/**
	 * Translated label of the form events in contact timelines.
	 *
	 * @param string $label Label.
	 * @param object $event Event.
	 * @return string
	 */
	public function event_label( $label, $event ) {
		if ( 'form.submitted' === $event->type ) {
			$title = is_array( $event->data ) && ! empty( $event->data['title'] ) ? $event->data['title'] : '';
			/* translators: %s: form title. */
			return $title ? sprintf( __( 'Sent the form "%s"', 'aluleth-forms' ), $title ) : __( 'Sent a form', 'aluleth-forms' );
		}
		return $label;
	}
}
