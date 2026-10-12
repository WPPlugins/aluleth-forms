<?php
/**
 * The visual form builder: full-screen admin page and its REST routes.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Admin as Core_Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Builder.
 */
final class Builder {

	/**
	 * Admin page slug.
	 */
	const PAGE = 'aluleth-forms-builder';

	/**
	 * Hooks the builder.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'load-post.php', array( __CLASS__, 'redirect_edit' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'redirect_new' ) );
		add_filter( 'get_edit_post_link', array( __CLASS__, 'edit_link' ), 10, 2 );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * URL of the builder for a form.
	 *
	 * @param int $form_id Form id.
	 * @return string
	 */
	public static function url( $form_id ) {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'form' => (int) $form_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Registers the hidden builder page.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( '', __( 'Form builder', 'aluleth-forms' ), __( 'Form builder', 'aluleth-forms' ), Core_Admin::cap( 'forms' ), self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * The classic edit screen of a form opens the builder instead.
	 *
	 * @return void
	 */
	public static function redirect_edit() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reads which screen to open.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$classic = isset( $_GET['alf_classic'] );
		// phpcs:enable
		if ( $post_id && 'edit' === $action && ! $classic && Plugin::POST_TYPE === get_post_type( $post_id ) ) {
			wp_safe_redirect( self::url( $post_id ) );
			exit;
		}
	}

	/**
	 * "New form" creates a draft and opens it in the builder.
	 *
	 * @return void
	 */
	public static function redirect_new() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which screen to open.
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( Plugin::POST_TYPE !== $type || ! current_user_can( Core_Admin::cap( 'forms' ) ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( empty( $_GET['blank'] ) || ! wp_verify_nonce( $nonce, 'aluleth_forms_blank' ) ) {
			wp_safe_redirect( Start::url() );
			exit;
		}
		$id = wp_insert_post(
			array(
				'post_type'   => Plugin::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => __( 'New form', 'aluleth-forms' ),
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			Forms::save_schema(
				(int) $id,
				Forms::with_defaults(
					array(
						'fields' => array(
							array(
								'id'       => 'email',
								'type'     => 'email',
								'label'    => __( 'Email', 'aluleth-forms' ),
								'required' => true,
							),
						),
					)
				)
			);
			wp_safe_redirect( self::url( (int) $id ) );
			exit;
		}
	}

	/**
	 * Edit links of forms point to the builder.
	 *
	 * @param string $link    Link.
	 * @param int    $post_id Post id.
	 * @return string
	 */
	public static function edit_link( $link, $post_id ) {
		return Plugin::POST_TYPE === get_post_type( $post_id ) ? self::url( $post_id ) : $link;
	}

	/**
	 * Full-screen body class on the builder page.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, self::PAGE ) ) {
			$classes .= ' alf-builder-screen';
		}
		return $classes;
	}

	/**
	 * Loads the builder app.
	 *
	 * @param string $hook Screen hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reads which form and tab to open.
		$form_id = isset( $_GET['form'] ) ? absint( $_GET['form'] ) : 0;
		$tab     = isset( $_GET['tab'] ) && in_array( $_GET['tab'], array( 'build', 'design', 'settings' ), true ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'build';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable
		$url = plugins_url( 'assets/', ALULETH_FORMS_FILE );

		wp_enqueue_style( 'aluleth-forms' );
		wp_enqueue_style( 'aluleth-forms-builder', $url . 'builder.css', array( 'aluleth-forms' ), ALULETH_FORMS_VERSION );
		wp_enqueue_script( 'aluleth-forms-builder', $url . 'builder.js', array( 'wp-element', 'wp-api-fetch', 'wp-i18n', 'wp-hooks' ), ALULETH_FORMS_VERSION, true );
		wp_set_script_translations( 'aluleth-forms-builder', 'aluleth-forms' );
		/**
		 * Fires when the builder's scripts are enqueued, so an extension can add
		 * its own (it hooks into the builder with wp.hooks).
		 *
		 * @param int $form_id Form id.
		 */
		do_action( 'aluleth_forms/builder_enqueue', $form_id );
		wp_localize_script(
			'aluleth-forms-builder',
			'alulethFormsBuilder',
			array(
				'formId'     => $form_id,
				'tab'        => $tab,
				'section'    => '' !== $section ? $section : 'general',
				'formsUrl'   => admin_url( 'edit.php?post_type=' . Plugin::POST_TYPE ),
				'entriesUrl' => current_user_can( Core_Admin::cap( 'entries' ) ) ? admin_url( 'admin.php?page=aluleth-forms-entries&form=' . $form_id ) : '',
				'maps'       => array(
					''           => __( 'Not saved to the contact', 'aluleth-forms' ),
					'first_name' => __( 'First name', 'aluleth-forms' ),
					'last_name'  => __( 'Last name', 'aluleth-forms' ),
					'full_name'  => __( 'Full name (split into first and last name)', 'aluleth-forms' ),
					'phone'      => __( 'Phone', 'aluleth-forms' ),
				),
				'adminEmail' => get_option( 'admin_email' ),
				'services'   => class_exists( '\\Aluleth\\Core\\Integrations' ) ? \Aluleth\Core\Integrations::catalog() : array(),
				'connectUrl' => admin_url( 'admin.php?page=aluleth-connections' ),
				'canManage'  => self::can_manage(),
				'payments'   => array(
					'ready'    => \Aluleth\Core\PayPal::ready(),
					'currency' => \Aluleth\Core\PayPal::currency(),
					'locale'   => str_replace( '_', '-', get_locale() ),
					'url'      => \Aluleth\Core\Settings::url( 'payments' ),
				),
				'captcha'    => self::captcha_config(),
				'site'       => class_exists( '\\Aluleth\\Core\\Site_Style' ) ? \Aluleth\Core\Site_Style::get() : array(
					'colors' => array(),
					'fonts'  => array(),
				),
			)
		);
	}

	/**
	 * Anti-spam services for the form's Settings › Spam protection.
	 *
	 * @return array default (service id or ''), services (id => label, ready), settingsUrl.
	 */
	private static function captcha_config() {
		if ( ! class_exists( '\\Aluleth\\Core\\Security' ) ) {
			return array(
				'default'     => '',
				'services'    => array(),
				'settingsUrl' => '',
			);
		}
		$services = array();
		foreach ( \Aluleth\Core\Security::services() as $id => $service ) {
			$services[ $id ] = array(
				'label'  => $service['label'],
				'ready'  => \Aluleth\Core\Security::ready( $id ),
				'widget' => ! empty( $service['widget'] ),
			);
		}
		return array(
			'default'     => (string) \Aluleth\Core\Security::get()['default_captcha'],
			'services'    => $services,
			'settingsUrl' => \Aluleth\Core\Settings::url( 'security' ),
		);
	}

	/**
	 * Builder page container (the app fills it).
	 *
	 * @return void
	 */
	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which form to open.
		$form_id = isset( $_GET['form'] ) ? absint( $_GET['form'] ) : 0;
		if ( ! $form_id || ! current_user_can( 'edit_post', $form_id ) || Plugin::POST_TYPE !== get_post_type( $form_id ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'This form does not exist.', 'aluleth-forms' ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . Plugin::POST_TYPE ) ) . '">' . esc_html__( 'Back to forms', 'aluleth-forms' ) . '</a></p></div>';
			return;
		}
		echo '<div id="alf-builder" class="alf-b"><p class="alf-b-loading">' . esc_html__( 'Loading the builder…', 'aluleth-forms' ) . '</p></div>';
	}

	/**
	 * REST routes used by the builder.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			'aluleth-forms/v1',
			'/forms/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_form' ),
					'permission_callback' => array( __CLASS__, 'can_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_form' ),
					'permission_callback' => array( __CLASS__, 'can_edit' ),
				),
			)
		);
	}

	/**
	 * Permission: the user can edit this form.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_edit( $request ) {
		$id = (int) $request['id'];
		return $id && Plugin::POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
	}

	/**
	 * Returns a form for the builder.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_form( $request ) {
		return new \WP_REST_Response( self::payload( (int) $request['id'] ) );
	}

	/**
	 * Saves the title, status and definition of a form.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function save_form( $request ) {
		$id     = (int) $request['id'];
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$update = array( 'ID' => $id );
		if ( isset( $params['title'] ) ) {
			$title                = sanitize_text_field( (string) $params['title'] );
			$update['post_title'] = '' !== $title ? $title : __( 'Untitled form', 'aluleth-forms' );
		}
		if ( isset( $params['status'] ) && in_array( $params['status'], array( 'publish', 'draft' ), true ) ) {
			if ( 'publish' === $params['status'] && ! current_user_can( Core_Admin::cap( 'forms' ) ) ) {
				return new \WP_Error( 'alf_cannot_publish', __( 'You are not allowed to publish forms.', 'aluleth-forms' ), array( 'status' => 403 ) );
			}
			$update['post_status'] = $params['status'];
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( isset( $params['schema'] ) && is_array( $params['schema'] ) ) {
			Forms::save_schema( $id, self::protect( $params['schema'], $id ) );
		}
		return new \WP_REST_Response( self::payload( $id ) );
	}

	/**
	 * Settings only administrators may change: where entries are sent
	 * (integrations, notification address) and where visitors are redirected.
	 *
	 * @return string[]
	 */
	public static function protected_settings() {
		return array( 'integrations', 'notify_to', 'redirect' );
	}

	/**
	 * Whether the current user may change the protected settings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( Core_Admin::cap( 'settings' ) );
	}

	/**
	 * Keeps the stored protected settings when the user is not an administrator,
	 * so an editor cannot send entries to their own address or tools.
	 *
	 * @param array $schema  Submitted definition.
	 * @param int   $form_id Form id.
	 * @return array
	 */
	public static function protect( array $schema, $form_id ) {
		if ( self::can_manage() ) {
			return $schema;
		}
		$stored             = Forms::get( (int) $form_id, true );
		$stored             = $stored ? $stored['schema']['settings'] : Schema::default_settings();
		$schema['settings'] = isset( $schema['settings'] ) && is_array( $schema['settings'] ) ? $schema['settings'] : array();
		foreach ( self::protected_settings() as $key ) {
			$schema['settings'][ $key ] = isset( $stored[ $key ] ) ? $stored[ $key ] : Schema::default_settings()[ $key ];
		}
		return $schema;
	}

	/**
	 * Definition sent to the builder: integrations (webhook addresses and secrets)
	 * are only shown to administrators.
	 *
	 * @param array $schema Definition.
	 * @return array
	 */
	private static function visible_schema( array $schema ) {
		if ( ! self::can_manage() ) {
			$schema['settings']['integrations'] = array();
		}
		return $schema;
	}

	/**
	 * Builder data of a form.
	 *
	 * @param int $id Form id.
	 * @return array
	 */
	private static function payload( $id ) {
		$form    = Forms::get( $id, true );
		$entries = Entries::query(
			array(
				'form_id'  => $id,
				'per_page' => 1,
			)
		);
		return array(
			'id'        => $id,
			'title'     => get_the_title( $id ),
			'status'    => get_post_status( $id ),
			'schema'    => self::visible_schema( $form ? $form['schema'] : Schema::clean( array() ) ),
			'shortcode' => '[aluleth_form id="' . $id . '"]',
			'entries'   => (int) $entries['total'],
			'modified'  => get_post_modified_time( 'U', true, $id ),
		);
	}
}
