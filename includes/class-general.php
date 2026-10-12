<?php
/**
 * Aluleth › Settings › General: defaults for new forms, validation messages,
 * weekly email summary, entry retention, sender and "do not store entries".
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Queue;
use Aluleth\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * General settings of Aluleth Forms.
 */
final class General {

	/**
	 * Option name.
	 */
	const OPTION = 'aluleth_forms_general';

	/**
	 * Daily task: weekly summary (on the chosen day) and entry retention.
	 */
	const CRON = 'aluleth_forms_daily';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'aluleth/settings/sections', array( __CLASS__, 'section' ), 5 );
		add_action( self::CRON, array( __CLASS__, 'daily' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'style'      => 'clean',
			'labels'     => 'above',
			'help'       => 'below',
			'errors'     => 'field',
			'messages'   => array(),
			'summary'    => array(
				'on'    => false,
				'to'    => 'admin',
				'email' => '',
				'day'   => 1,
			),
			'retention'  => 0,
			'spam_days'  => 30,
			'from_name'  => '',
			'from_email' => '',
			'no_store'   => false,
		);
	}

	/**
	 * Saved values merged with the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$conf  = array_merge( self::defaults(), $saved );

		$conf['summary']  = array_merge( self::defaults()['summary'], is_array( $conf['summary'] ) ? $conf['summary'] : array() );
		$conf['messages'] = is_array( $conf['messages'] ) ? $conf['messages'] : array();
		return $conf;
	}

	/**
	 * Messages people can change, with their default wording.
	 *
	 * @return array<string, array{label: string, default: string, help?: string}>
	 */
	public static function message_list() {
		return array(
			'required' => array(
				'label'   => __( 'Required field', 'aluleth-forms' ),
				'default' => __( 'This field is required.', 'aluleth-forms' ),
			),
			'choose'   => array(
				'label'   => __( 'Choice not made', 'aluleth-forms' ),
				'default' => __( 'Please choose an option.', 'aluleth-forms' ),
			),
			'consent'  => array(
				'label'   => __( 'Box to tick', 'aluleth-forms' ),
				'default' => __( 'Please tick this box to continue.', 'aluleth-forms' ),
			),
			'email'    => array(
				'label'   => __( 'Email address', 'aluleth-forms' ),
				'default' => __( 'Please enter a valid email address.', 'aluleth-forms' ),
			),
			'phone'    => array(
				'label'   => __( 'Phone number', 'aluleth-forms' ),
				'default' => __( 'Please enter a valid phone number.', 'aluleth-forms' ),
			),
			'number'   => array(
				'label'   => __( 'Not a number', 'aluleth-forms' ),
				'default' => __( 'Please enter a number.', 'aluleth-forms' ),
			),
			'min'      => array(
				'label'   => __( 'Number too small', 'aluleth-forms' ),
				/* translators: {value} is replaced by the field's minimum. Keep it as it is. */
				'default' => __( 'Please enter {value} or more.', 'aluleth-forms' ),
				'help'    => __( '{value} is replaced by the lowest number allowed.', 'aluleth-forms' ),
			),
			'max'      => array(
				'label'   => __( 'Number too big', 'aluleth-forms' ),
				/* translators: {value} is replaced by the field's maximum. Keep it as it is. */
				'default' => __( 'Please enter {value} or less.', 'aluleth-forms' ),
				'help'    => __( '{value} is replaced by the highest number allowed.', 'aluleth-forms' ),
			),
			'date'     => array(
				'label'   => __( 'Date', 'aluleth-forms' ),
				'default' => __( 'Please enter a valid date.', 'aluleth-forms' ),
			),
			'length'   => array(
				'label'   => __( 'Answer too long', 'aluleth-forms' ),
				/* translators: {max} is replaced by the maximum number of characters. Keep it as it is. */
				'default' => __( 'Please use {max} characters or fewer.', 'aluleth-forms' ),
				'help'    => __( '{max} is replaced by the number of characters allowed.', 'aluleth-forms' ),
			),
		);
	}

	/**
	 * A validation message: the site owner's wording, or the default one.
	 *
	 * @param string $key     Message key (see message_list()).
	 * @param string $fallback Wording used when nothing was changed (already translated).
	 * @param array  $vars    Tokens to replace, e.g. array( '{value}' => '10' ).
	 * @return string
	 */
	public static function msg( $key, $fallback = '', array $vars = array() ) {
		$conf = self::get();
		$text = isset( $conf['messages'][ $key ] ) && '' !== trim( (string) $conf['messages'][ $key ] ) ? (string) $conf['messages'][ $key ] : $fallback;
		if ( '' === $text ) {
			$list = self::message_list();
			$text = isset( $list[ $key ] ) ? $list[ $key ]['default'] : '';
		}
		return strtr( $text, $vars );
	}

	/**
	 * Design defaults of a new form (style and layout).
	 *
	 * @return array{style: string, labels: string, help: string, errors: string}
	 */
	public static function form_defaults() {
		$conf = self::get();
		return array(
			'style'  => $conf['style'],
			'labels' => $conf['labels'],
			'help'   => $conf['help'],
			'errors' => $conf['errors'],
		);
	}

	/**
	 * Adds the General section, first in the menu.
	 *
	 * @param array $sections Sections.
	 * @return array
	 */
	public static function section( $sections ) {
		$general = array(
			'general' => array(
				'label'   => __( 'General', 'aluleth-forms' ),
				'intro'   => __( 'Defaults for new forms. Each form can still change them in its Design tab.', 'aluleth-forms' ),
				'render'  => array( __CLASS__, 'render' ),
				'save'    => array( __CLASS__, 'save' ),
				'anchors' => array(
					'layout'   => __( 'Layout and style', 'aluleth-forms' ),
					'messages' => __( 'Validation messages', 'aluleth-forms' ),
					'summary'  => __( 'Email summaries', 'aluleth-forms' ),
					'other'    => __( 'Other', 'aluleth-forms' ),
				),
			),
		);
		return $general + (array) $sections;
	}

	/**
	 * Saves the section.
	 *
	 * @param array $post Unslashed $_POST.
	 * @return string Notice.
	 */
	public static function save( array $post ) {
		$conf = self::get();
		$pick = static function ( $key, array $allowed, $current ) use ( $post ) {
			return isset( $post[ $key ] ) && in_array( $post[ $key ], $allowed, true ) ? $post[ $key ] : $current;
		};

		$conf['style']  = $pick( 'style', array( 'clean', 'soft', 'dark' ), $conf['style'] );
		$conf['labels'] = $pick( 'labels', array( 'above', 'beside', 'inside' ), $conf['labels'] );
		$conf['help']   = $pick( 'help', array( 'below', 'label' ), $conf['help'] );
		$conf['errors'] = $pick( 'errors', array( 'field', 'top' ), $conf['errors'] );

		$messages = array();
		foreach ( array_keys( self::message_list() ) as $key ) {
			$value = isset( $post[ 'msg_' . $key ] ) ? trim( sanitize_text_field( (string) $post[ 'msg_' . $key ] ) ) : '';
			if ( '' !== $value ) {
				$messages[ $key ] = substr( $value, 0, 200 );
			}
		}
		$conf['messages'] = $messages;

		$email           = isset( $post['summary_email'] ) ? sanitize_email( (string) $post['summary_email'] ) : '';
		$conf['summary'] = array(
			'on'    => ! empty( $post['summary_on'] ),
			'to'    => isset( $post['summary_to'] ) && 'custom' === $post['summary_to'] && is_email( $email ) ? 'custom' : 'admin',
			'email' => is_email( $email ) ? $email : '',
			'day'   => isset( $post['summary_day'] ) ? max( 0, min( 6, (int) $post['summary_day'] ) ) : 1,
		);

		$conf['retention']  = isset( $post['retention'] ) && in_array( (int) $post['retention'], array( 0, 1, 3, 6, 12, 24 ), true ) ? (int) $post['retention'] : 0;
		$conf['spam_days']  = empty( $post['spam_clean'] ) ? 0 : 30;
		$conf['from_name']  = isset( $post['from_name'] ) ? substr( sanitize_text_field( (string) $post['from_name'] ), 0, 100 ) : '';
		$from_email         = isset( $post['from_email'] ) ? sanitize_email( (string) $post['from_email'] ) : '';
		$conf['from_email'] = is_email( $from_email ) ? $from_email : '';
		$conf['no_store']   = ! empty( $post['no_store'] );
		update_option( self::OPTION, $conf, false );

		if ( ! empty( $post['test_summary'] ) ) {
			$to = self::summary_to( $conf );
			return self::send_summary( true ) ? sprintf( /* translators: %s: email address. */ __( 'Saved. A summary was sent to %s.', 'aluleth-forms' ), $to ) : 'error:' . __( 'Saved, but the summary could not be sent. Check that your site can send emails.', 'aluleth-forms' );
		}
		if ( ! empty( $post['summary_to'] ) && 'custom' === $post['summary_to'] && ! is_email( $email ) ) {
			return 'error:' . __( 'Saved. The summary goes to the site administrator email, because the address you typed is not valid.', 'aluleth-forms' );
		}
		return '';
	}

	/**
	 * Renders the section.
	 *
	 * @return void
	 */
	public static function render() {
		$conf = self::get();
		wp_enqueue_style( 'aluleth-forms', plugins_url( 'assets/forms.css', ALULETH_FORMS_FILE ), array(), ALULETH_FORMS_VERSION );
		wp_enqueue_script( 'aluleth-forms-general', plugins_url( 'assets/general.js', ALULETH_FORMS_FILE ), array(), ALULETH_FORMS_VERSION, true );
		wp_enqueue_style( 'aluleth-forms-general', plugins_url( 'assets/general.css', ALULETH_FORMS_FILE ), array( 'aluleth-forms' ), ALULETH_FORMS_VERSION );

		// Layout and style.
		echo '<section class="al-card al-sec" id="layout"><div class="al-sec-head"><div><h2>' . esc_html__( 'Layout and style', 'aluleth-forms' ) . '</h2><p>' . esc_html__( 'Used by the forms you create from now on. Your existing forms keep their own design.', 'aluleth-forms' ) . '</p></div></div>';
		echo '<div class="alf-g-layout"><div class="alf-g-controls">';
		self::radios(
			'labels',
			__( 'Labels', 'aluleth-forms' ),
			$conf['labels'],
			array(
				'above'  => __( 'Above the field', 'aluleth-forms' ),
				'beside' => __( 'Beside the field', 'aluleth-forms' ),
				'inside' => __( 'Inside the field', 'aluleth-forms' ),
			)
		);
		self::radios(
			'help',
			__( 'Help text', 'aluleth-forms' ),
			$conf['help'],
			array(
				'below' => __( 'Under the field', 'aluleth-forms' ),
				'label' => __( 'Under the label', 'aluleth-forms' ),
			)
		);
		self::radios(
			'errors',
			__( 'Error messages', 'aluleth-forms' ),
			$conf['errors'],
			array(
				'field' => __( 'Under the field', 'aluleth-forms' ),
				'top'   => __( 'Under the field and listed at the top', 'aluleth-forms' ),
			)
		);
		self::radios(
			'style',
			__( 'Style for new forms', 'aluleth-forms' ),
			$conf['style'],
			array(
				'clean' => __( 'Clean', 'aluleth-forms' ),
				'soft'  => __( 'Soft', 'aluleth-forms' ),
				'dark'  => __( 'Dark', 'aluleth-forms' ),
			)
		);
		echo '<p class="al-help">' . esc_html__( 'Button colour and font come from your site (theme or Elementor kit) unless a form picks its own.', 'aluleth-forms' ) . '</p></div>';

		// Live preview, with the real form styles.
		echo '<div class="alf-g-preview"><span class="al-kicker">' . esc_html__( 'Preview', 'aluleth-forms' ) . '</span>';
		echo '<div class="alf alf--' . esc_attr( $conf['style'] ) . ' alf-labels-' . esc_attr( $conf['labels'] ) . ' alf-help-' . esc_attr( $conf['help'] ) . '" id="alf-g-sample" aria-hidden="true"><div class="alf-form">';
		echo '<div class="alf-summary"' . ( 'top' === $conf['errors'] ? '' : ' hidden' ) . '><p class="alf-summary-title">' . esc_html__( 'Please check these fields:', 'aluleth-forms' ) . '</p><ul><li><span>' . esc_html__( 'Email', 'aluleth-forms' ) . '</span></li></ul></div>';
		echo '<div class="alf-field alf-field--text"><label class="alf-label">' . esc_html__( 'Your name', 'aluleth-forms' ) . '</label><p class="alf-help alf-help--label">' . esc_html__( 'As you would like us to call you.', 'aluleth-forms' ) . '</p><input class="alf-input" type="text" tabindex="-1" placeholder=" " value="' . esc_attr__( 'Camille', 'aluleth-forms' ) . '"><p class="alf-help alf-help--below">' . esc_html__( 'As you would like us to call you.', 'aluleth-forms' ) . '</p></div>';
		echo '<div class="alf-field alf-field--email has-error"><label class="alf-label">' . esc_html__( 'Email', 'aluleth-forms' ) . '<span class="alf-required">*</span></label><input class="alf-input" type="email" tabindex="-1" placeholder=" " value="camille@"><p class="alf-error">' . esc_html( self::msg( 'email', __( 'Please enter a valid email address.', 'aluleth-forms' ) ) ) . '</p></div>';
		echo '<div class="alf-actions"><span class="alf-button">' . esc_html__( 'Send', 'aluleth-forms' ) . '</span></div>';
		echo '</div></div></div></div></section>';

		// Validation messages.
		echo '<section class="al-card al-sec" id="messages"><div class="al-sec-head"><div><h2>' . esc_html__( 'Validation messages', 'aluleth-forms' ) . '</h2><p>' . esc_html__( 'Already translated into your site\'s language. Change them only if you want your own words; leave a box empty to keep the default.', 'aluleth-forms' ) . '</p></div></div><div class="al-grid2">';
		foreach ( self::message_list() as $key => $message ) {
			$value = isset( $conf['messages'][ $key ] ) ? $conf['messages'][ $key ] : '';
			echo '<label class="al-field"><span>' . esc_html( $message['label'] ) . '</span><input type="text" name="msg_' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $message['default'] ) . '" maxlength="200">';
			if ( ! empty( $message['help'] ) ) {
				echo '<small>' . esc_html( $message['help'] ) . '</small>';
			}
			echo '</label>';
		}
		echo '</div></section>';

		// Email summaries.
		$days = array(
			1 => __( 'Monday', 'aluleth-forms' ),
			2 => __( 'Tuesday', 'aluleth-forms' ),
			3 => __( 'Wednesday', 'aluleth-forms' ),
			4 => __( 'Thursday', 'aluleth-forms' ),
			5 => __( 'Friday', 'aluleth-forms' ),
			6 => __( 'Saturday', 'aluleth-forms' ),
			0 => __( 'Sunday', 'aluleth-forms' ),
		);
		$s    = $conf['summary'];
		echo '<section class="al-card al-sec" id="summary"><div class="al-sec-head"><div><h2>' . esc_html__( 'Email summaries', 'aluleth-forms' ) . '</h2><p>' . esc_html__( 'One short email a week with what happened on your forms.', 'aluleth-forms' ) . '</p></div></div>';
		echo '<label class="al-checkcard"><input type="checkbox" name="summary_on" value="1"' . checked( $s['on'], true, false ) . '> <span><strong>' . esc_html__( 'Send me a weekly summary of my forms', 'aluleth-forms' ) . '</strong><small>' . esc_html__( 'Entries and the change from the week before, new contacts and subscribers, your best form, what is waiting for you, spam kept aside and sends that failed.', 'aluleth-forms' ) . '</small></span></label>';
		echo '<div class="al-grid3"><label class="al-field"><span>' . esc_html__( 'Send to', 'aluleth-forms' ) . '</span><select name="summary_to"><option value="admin"' . selected( $s['to'], 'admin', false ) . '>' . esc_html__( 'Site administrator email', 'aluleth-forms' ) . '</option><option value="custom"' . selected( $s['to'], 'custom', false ) . '>' . esc_html__( 'Another address', 'aluleth-forms' ) . '</option></select><small>' . esc_html( (string) get_option( 'admin_email' ) ) . '</small></label>';
		echo '<label class="al-field"><span>' . esc_html__( 'Other address', 'aluleth-forms' ) . '</span><input type="email" name="summary_email" value="' . esc_attr( $s['email'] ) . '" autocomplete="off"></label>';
		echo '<label class="al-field"><span>' . esc_html__( 'Every', 'aluleth-forms' ) . '</span><select name="summary_day">';
		foreach ( $days as $num => $day ) {
			/* translators: %s: day of the week. */
			echo '<option value="' . esc_attr( (string) $num ) . '"' . selected( (int) $s['day'], $num, false ) . '>' . esc_html( sprintf( __( '%s morning', 'aluleth-forms' ), $day ) ) . '</option>';
		}
		echo '</select></label></div>';
		echo '<div class="al-row"><button type="submit" name="test_summary" value="1" class="al-button al-button--ghost">' . esc_html__( 'Save and send a summary now', 'aluleth-forms' ) . '</button></div></section>';

		// Other.
		echo '<section class="al-card al-sec" id="other"><div class="al-sec-head"><div><h2>' . esc_html__( 'Other', 'aluleth-forms' ) . '</h2></div></div><div class="al-grid2">';
		echo '<label class="al-field"><span>' . esc_html__( 'Keep entries for', 'aluleth-forms' ) . '</span><select name="retention">';
		$keep = array(
			0  => __( 'Forever', 'aluleth-forms' ),
			1  => __( '1 month', 'aluleth-forms' ),
			3  => __( '3 months', 'aluleth-forms' ),
			6  => __( '6 months', 'aluleth-forms' ),
			12 => __( '12 months', 'aluleth-forms' ),
			24 => __( '24 months', 'aluleth-forms' ),
		);
		foreach ( $keep as $months => $label ) {
			echo '<option value="' . esc_attr( (string) $months ) . '"' . selected( (int) $conf['retention'], $months, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><small>' . esc_html__( 'Older entries are deleted for good once a day, with their notes. Contacts are kept.', 'aluleth-forms' ) . '</small></label>';
		echo '<label class="al-checkcard"><input type="checkbox" name="spam_clean" value="1"' . checked( (int) $conf['spam_days'] > 0, true, false ) . '> <span><strong>' . esc_html__( 'Delete spam after 30 days', 'aluleth-forms' ) . '</strong><small>' . esc_html__( 'Entries kept aside as spam are deleted for good 30 days after they arrived.', 'aluleth-forms' ) . '</small></span></label></div>';
		echo '<div class="al-grid2"><label class="al-field"><span>' . esc_html__( 'Notifications sent from (name)', 'aluleth-forms' ) . '</span><input type="text" name="from_name" value="' . esc_attr( $conf['from_name'] ) . '" placeholder="' . esc_attr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) . '"></label>';
		echo '<label class="al-field"><span>' . esc_html__( 'Notifications sent from (email)', 'aluleth-forms' ) . '</span><input type="email" name="from_email" value="' . esc_attr( $conf['from_email'] ) . '" placeholder="' . esc_attr( self::default_from() ) . '"><small>' . esc_html__( 'Use an address of your own domain, so the emails are not taken for spam. An SMTP plugin may replace it.', 'aluleth-forms' ) . '</small>' . ( '' === $conf['from_email'] ? '<small class="al-warn">' . esc_html( sprintf( /* translators: %s: default sender address. */ __( 'Not set: emails leave from %s, an address that usually does not exist. Some mailboxes then refuse your notifications and receipts.', 'aluleth-forms' ), self::default_from() ) ) . '</small>' : '' ) . '</label></div>';
		echo '<label class="al-checkcard"><input type="checkbox" name="no_store" value="1"' . checked( $conf['no_store'], true, false ) . '> <span><strong>' . esc_html__( 'Do not save entries, only send the notification email', 'aluleth-forms' ) . '</strong><small>' . esc_html__( 'The answers are emailed, passed to your connections, then deleted from your site. The entries board stays empty. Contacts and consent are still saved.', 'aluleth-forms' ) . '</small></span></label>';
		echo '</section>';
	}

	/**
	 * A group of radio buttons.
	 *
	 * @param string $name    Field name.
	 * @param string $legend  Legend.
	 * @param string $current Current value.
	 * @param array  $options Value => label.
	 * @return void
	 */
	private static function radios( $name, $legend, $current, array $options ) {
		echo '<fieldset class="al-radios al-radios--stack"><legend>' . esc_html( $legend ) . '</legend>';
		foreach ( $options as $value => $label ) {
			echo '<label><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . checked( $current, $value, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset>';
	}

	/**
	 * The address WordPress sends from when nothing is set.
	 *
	 * @return string
	 */
	private static function default_from() {
		$host = wp_parse_url( network_home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? preg_replace( '/^www\./', '', $host ) : 'example.com';
		return 'wordpress@' . $host;
	}

	/**
	 * Sender of the form notifications, for Aluleth\Core\Mail.
	 *
	 * @return array{from_name: string, from_email: string}
	 */
	public static function sender() {
		$conf = self::get();
		return array(
			'from_name'  => (string) $conf['from_name'],
			'from_email' => (string) $conf['from_email'],
		);
	}

	/**
	 * Schedules the daily task at 7:00 site time.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( wp_next_scheduled( self::CRON ) ) {
			return;
		}
		$next = new \DateTime( 'tomorrow 07:00', wp_timezone() );
		$now  = new \DateTime( 'today 07:00', wp_timezone() );
		wp_schedule_event( $now->getTimestamp() > time() ? $now->getTimestamp() : $next->getTimestamp(), 'daily', self::CRON );
	}

	/**
	 * Daily task.
	 *
	 * @return void
	 */
	public static function daily() {
		self::retention();
		$conf = self::get();
		if ( ! $conf['summary']['on'] ) {
			return;
		}
		$today = wp_date( 'w' );
		$week  = wp_date( 'o-W' );
		if ( (int) $today === (int) $conf['summary']['day'] && get_option( 'aluleth_forms_summary_sent' ) !== $week ) {
			update_option( 'aluleth_forms_summary_sent', $week, false );
			self::send_summary();
		}
	}

	/**
	 * Deletes entries past the retention time, and old spam.
	 *
	 * @return int Number of entries deleted.
	 */
	public static function retention() {
		global $wpdb;
		$conf    = self::get();
		$deleted = 0;
		if ( (int) $conf['retention'] > 0 ) {
			$before   = gmdate( 'Y-m-d H:i:s', strtotime( '-' . (int) $conf['retention'] . ' months' ) );
			$deleted += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aluleth_form_entries WHERE created_at < %s", $before ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		if ( (int) $conf['spam_days'] > 0 ) {
			$before   = gmdate( 'Y-m-d H:i:s', time() - (int) $conf['spam_days'] * DAY_IN_SECONDS );
			$deleted += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aluleth_form_entries WHERE status = %s AND created_at < %s", Entries::SPAM, $before ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return $deleted;
	}

	/**
	 * Recipient of the summary.
	 *
	 * @param array $conf Settings.
	 * @return string
	 */
	private static function summary_to( array $conf ) {
		return 'custom' === $conf['summary']['to'] && is_email( $conf['summary']['email'] ) ? $conf['summary']['email'] : (string) get_option( 'admin_email' );
	}

	/**
	 * Numbers of the last 7 days (and the 7 before).
	 *
	 * @return array
	 */
	public static function summary_data() {
		global $wpdb;
		$p     = $wpdb->prefix;
		$now   = time();
		$since = gmdate( 'Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS );
		$prev  = gmdate( 'Y-m-d H:i:s', $now - 14 * DAY_IN_SECONDS );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from $wpdb->prefix.
		$entries     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}aluleth_form_entries WHERE created_at >= %s AND status <> %s", $since, Entries::SPAM ) );
		$before      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}aluleth_form_entries WHERE created_at >= %s AND created_at < %s AND status <> %s", $prev, $since, Entries::SPAM ) );
		$spam        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}aluleth_form_entries WHERE created_at >= %s AND status = %s", $since, Entries::SPAM ) );
		$contacts    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}aluleth_contacts WHERE created_at >= %s AND source <> %s", $since, 'import' ) );
		$subscribers = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT contact_id) FROM {$p}aluleth_consents WHERE purpose = %s AND status = %s AND created_at >= %s", 'email_marketing', 'given', $since ) );
		$by_form     = $wpdb->get_results( $wpdb->prepare( "SELECT form_id, COUNT(*) AS n FROM {$p}aluleth_form_entries WHERE created_at >= %s AND status <> %s GROUP BY form_id", $since, Entries::SPAM ) );
		// phpcs:enable

		// Best form: highest conversion with at least 10 views, else the most entries.
		$best      = null;
		$day_since = wp_date( 'Y-m-d', $now - 7 * DAY_IN_SECONDS );
		foreach ( (array) $by_form as $row ) {
			$views = Stats::views( $day_since, (int) $row->form_id );
			$rate  = $views >= 10 ? min( 100, (int) $row->n * 100 / $views ) : null;
			$score = null !== $rate ? 1000 + $rate : (int) $row->n / 1000;
			if ( ! $best || $score > $best['score'] ) {
				$best = array(
					'title' => get_the_title( (int) $row->form_id ),
					'n'     => (int) $row->n,
					'rate'  => $rate,
					'score' => $score,
				);
			}
		}

		$waiting = 0;
		foreach ( get_posts(
			array(
				'post_type'      => Plugin::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 50,
				'fields'         => 'ids',
			)
		) as $form_id ) {
			$keys     = array_keys( Entries::statuses( $form_id ) );
			$column   = Entries::column( $form_id, $keys[0], array_slice( $keys, 1 ), true, '', 1 );
			$waiting += (int) $column['total'];
		}

		return array(
			'entries'     => $entries,
			'before'      => $before,
			'spam'        => $spam,
			'contacts'    => $contacts,
			'subscribers' => $subscribers,
			'best'        => $best,
			'waiting'     => $waiting,
			'failed'      => class_exists( '\\Aluleth\\Core\\Queue' ) ? (int) Queue::failed_count( $since ) : 0,
			'payments'    => Payments::received( $since ),
		);
	}

	/**
	 * Sends the weekly summary.
	 *
	 * @param bool $test Sent from the settings screen.
	 * @return bool
	 */
	public static function send_summary( $test = false ) {
		$conf = self::get();
		$d    = self::summary_data();
		$to   = self::summary_to( $conf );

		if ( $d['before'] > 0 ) {
			$change = (int) round( ( $d['entries'] - $d['before'] ) * 100 / $d['before'] );
			/* translators: %s: change in percent, with its sign. */
			$change = sprintf( __( '%s vs the week before', 'aluleth-forms' ), ( $change > 0 ? '+' : '' ) . number_format_i18n( $change ) . '%' );
		} else {
			$change = $d['entries'] ? __( 'none the week before', 'aluleth-forms' ) : '';
		}
		$rows = array(
			array( __( 'Entries this week', 'aluleth-forms' ), number_format_i18n( $d['entries'] ), $change ),
			array( __( 'New contacts', 'aluleth-forms' ), number_format_i18n( $d['contacts'] ), sprintf( /* translators: %s: number of new newsletter subscribers. */ _n( '%s new subscriber', '%s new subscribers', $d['subscribers'], 'aluleth-forms' ), number_format_i18n( $d['subscribers'] ) ) ),
		);
		if ( $d['best'] ) {
			$rows[] = array( __( 'Best form', 'aluleth-forms' ), $d['best']['title'], null !== $d['best']['rate'] ? sprintf( /* translators: %s: conversion rate in percent. */ __( '%s conversion', 'aluleth-forms' ), number_format_i18n( $d['best']['rate'], 1 ) . '%' ) : sprintf( /* translators: %s: number of entries. */ _n( '%s entry', '%s entries', $d['best']['n'], 'aluleth-forms' ), number_format_i18n( $d['best']['n'] ) ) );
		}
		if ( ! empty( $d['payments'] ) ) {
			$amounts = array();
			foreach ( $d['payments'] as $currency => $amount ) {
				$amounts[] = \Aluleth\Core\PayPal::format( $amount, $currency );
			}
			$rows[] = array( __( 'Payments received', 'aluleth-forms' ), implode( ' + ', $amounts ), __( 'with PayPal, test payments left out', 'aluleth-forms' ) );
		}
		$rows[] = array( __( 'Waiting for you', 'aluleth-forms' ), sprintf( /* translators: %s: number of entries. */ _n( '%s entry', '%s entries', $d['waiting'], 'aluleth-forms' ), number_format_i18n( $d['waiting'] ) ), __( 'in the first column of your boards', 'aluleth-forms' ) );
		$rows[] = array( __( 'Spam kept aside', 'aluleth-forms' ), number_format_i18n( $d['spam'] ), '' );
		$rows[] = array( __( 'Sends that failed', 'aluleth-forms' ), number_format_i18n( $d['failed'] ), $d['failed'] ? __( 'see Aluleth › Connections', 'aluleth-forms' ) : '' );

		$html  = '<h2 style="margin:0 0 4px;font-size:20px">' . esc_html__( 'Your forms this week', 'aluleth-forms' ) . '</h2>';
		$html .= '<p style="margin:0 0 18px;color:#5b6370">' . esc_html( wp_date( get_option( 'date_format' ), time() - 7 * DAY_IN_SECONDS ) . ' – ' . wp_date( get_option( 'date_format' ) ) ) . '</p><table style="border-collapse:collapse;width:100%">';
		foreach ( $rows as $row ) {
			$html .= '<tr><td style="padding:10px 12px 10px 0;border-top:1px solid #e3e6eb;color:#5b6370;vertical-align:top">' . esc_html( $row[0] ) . '</td><td style="padding:10px 0;border-top:1px solid #e3e6eb;vertical-align:top"><strong style="font-size:16px">' . esc_html( $row[1] ) . '</strong>' . ( '' !== $row[2] ? '<br><span style="color:#5b6370;font-size:13px">' . esc_html( $row[2] ) . '</span>' : '' ) . '</td></tr>';
		}
		$html .= '</table><p style="margin:22px 0 0"><a href="' . esc_url( admin_url( 'admin.php?page=aluleth-forms-entries' ) ) . '" style="display:inline-block;background:#0e1116;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600">' . esc_html__( 'Open the entries', 'aluleth-forms' ) . '</a></p>';
		$html .= '<p style="margin:18px 0 0;color:#5b6370;font-size:12px">' . esc_html__( 'You get this email because the weekly summary is on in Aluleth › Settings › General.', 'aluleth-forms' ) . ' <a href="' . esc_url( Settings::url( 'general' ) . '#summary' ) . '" style="color:#5b6370">' . esc_html__( 'Change it', 'aluleth-forms' ) . '</a></p>';

		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		/* translators: %s: site name. */
		$subject = sprintf( __( 'Your forms this week – %s', 'aluleth-forms' ), $site );
		if ( $test ) {
			$subject = '[' . __( 'Test', 'aluleth-forms' ) . '] ' . $subject;
		}
		return aluleth_core()->mail()->send( $to, $subject, $html, self::sender() );
	}
}
