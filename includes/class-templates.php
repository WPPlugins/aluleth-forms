<?php
/**
 * Ready-made forms (templates) shown on the "Create a form" screen.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Templates.
 */
final class Templates {

	/**
	 * Consent field (newsletter).
	 *
	 * @param bool $required Whether ticking it is required.
	 * @return array
	 */
	private static function consent( $required = false ) {
		return array(
			'id'        => 'newsletter',
			'type'      => 'consent',
			'label'     => __( 'Send me news and offers by email. I can unsubscribe at any time.', 'aluleth-forms' ),
			'marketing' => true,
			'required'  => $required,
		);
	}

	/**
	 * Name, email (and phone) fields.
	 *
	 * @param bool $phone          Add a phone field.
	 * @param bool $phone_required Phone required.
	 * @return array[]
	 */
	private static function person( $phone = false, $phone_required = false ) {
		$fields = array(
			array(
				'id'           => 'name',
				'type'         => 'text',
				'label'        => __( 'Your name', 'aluleth-forms' ),
				'required'     => true,
				'width'        => 'half',
				'map'          => 'full_name',
				'autocomplete' => 'name',
			),
			array(
				'id'       => 'email',
				'type'     => 'email',
				'label'    => __( 'Email', 'aluleth-forms' ),
				'required' => true,
				'width'    => 'half',
			),
		);
		if ( $phone ) {
			$fields[] = array(
				'id'       => 'phone',
				'type'     => 'phone',
				'label'    => __( 'Phone', 'aluleth-forms' ),
				'required' => $phone_required,
				'map'      => 'phone',
			);
		}
		return $fields;
	}

	/**
	 * Statuses from labels.
	 *
	 * @param string[] $labels Labels.
	 * @return array[]
	 */
	private static function statuses( array $labels ) {
		$out = array();
		foreach ( $labels as $label ) {
			$out[] = array(
				'key'   => '',
				'label' => $label,
			);
		}
		return $out;
	}

	/**
	 * All templates.
	 *
	 * @return array<string, array> Id => title, text, style, fields, settings.
	 */
	public static function all() {
		$templates = array(
			'contact'    => array(
				'title'    => __( 'Contact', 'aluleth-forms' ),
				'text'     => __( 'Name, email and a message.', 'aluleth-forms' ),
				'style'    => 'clean',
				'fields'   => array_merge(
					self::person(),
					array(
						array(
							'id'       => 'message',
							'type'     => 'textarea',
							'label'    => __( 'Message', 'aluleth-forms' ),
							'required' => true,
						),
						self::consent(),
					)
				),
				'settings' => array(),
			),
			'quote'      => array(
				'title'    => __( 'Quote request', 'aluleth-forms' ),
				'text'     => __( 'Service, budget, deadline and project details.', 'aluleth-forms' ),
				'style'    => 'soft',
				'fields'   => array_merge(
					self::person( true ),
					array(
						array(
							'id'    => 'company',
							'type'  => 'text',
							'label' => __( 'Company', 'aluleth-forms' ),
						),
						array(
							'id'       => 'service',
							'type'     => 'select',
							'label'    => __( 'What do you need?', 'aluleth-forms' ),
							'required' => true,
							'options'  => array( __( 'Website', 'aluleth-forms' ), __( 'Branding', 'aluleth-forms' ), __( 'Marketing', 'aluleth-forms' ), __( 'Something else', 'aluleth-forms' ) ),
						),
						array(
							'id'      => 'budget',
							'type'    => 'radio',
							'label'   => __( 'Budget', 'aluleth-forms' ),
							'options' => array( __( 'Under 1,000', 'aluleth-forms' ), __( '1,000 to 5,000', 'aluleth-forms' ), __( '5,000 to 15,000', 'aluleth-forms' ), __( 'Over 15,000', 'aluleth-forms' ) ),
						),
						array(
							'id'    => 'deadline',
							'type'  => 'date',
							'label' => __( 'Ideal deadline', 'aluleth-forms' ),
						),
						array(
							'id'       => 'details',
							'type'     => 'textarea',
							'label'    => __( 'Tell us about your project', 'aluleth-forms' ),
							'required' => true,
						),
						self::consent(),
					)
				),
				'settings' => array(
					'success_message' => __( 'Thank you! We will send you a quote within two working days.', 'aluleth-forms' ),
					'statuses'        => self::statuses( array( __( 'New', 'aluleth-forms' ), __( 'Quote sent', 'aluleth-forms' ), __( 'Won', 'aluleth-forms' ), __( 'Lost', 'aluleth-forms' ) ) ),
				),
			),
			'booking'    => array(
				'title'    => __( 'Appointment booking', 'aluleth-forms' ),
				'text'     => __( 'Two steps: the appointment, then the person.', 'aluleth-forms' ),
				'style'    => 'soft',
				'fields'   => array_merge(
					array(
						array(
							'id'    => 'step_1',
							'type'  => 'step',
							'label' => __( 'Your appointment', 'aluleth-forms' ),
						),
						array(
							'id'       => 'service',
							'type'     => 'select',
							'label'    => __( 'Service', 'aluleth-forms' ),
							'required' => true,
							'options'  => array( __( 'First visit', 'aluleth-forms' ), __( 'Follow-up', 'aluleth-forms' ), __( 'Consultation', 'aluleth-forms' ) ),
						),
						array(
							'id'       => 'day',
							'type'     => 'date',
							'label'    => __( 'Preferred day', 'aluleth-forms' ),
							'required' => true,
							'width'    => 'half',
						),
						array(
							'id'      => 'time',
							'type'    => 'radio',
							'label'   => __( 'Preferred time', 'aluleth-forms' ),
							'options' => array( __( 'Morning', 'aluleth-forms' ), __( 'Afternoon', 'aluleth-forms' ), __( 'Evening', 'aluleth-forms' ) ),
						),
						array(
							'id'    => 'step_2',
							'type'  => 'step',
							'label' => __( 'Your details', 'aluleth-forms' ),
						),
					),
					self::person( true, true ),
					array(
						array(
							'id'    => 'notes',
							'type'  => 'textarea',
							'label' => __( 'Anything we should know?', 'aluleth-forms' ),
						),
					)
				),
				'settings' => array(
					'success_message' => __( 'Thank you! We will confirm your appointment shortly.', 'aluleth-forms' ),
					'submit_label'    => __( 'Book', 'aluleth-forms' ),
					'statuses'        => self::statuses( array( __( 'New', 'aluleth-forms' ), __( 'Confirmed', 'aluleth-forms' ), __( 'Done', 'aluleth-forms' ), __( 'Cancelled', 'aluleth-forms' ) ) ),
				),
			),
			'newsletter' => array(
				'title'    => __( 'Newsletter', 'aluleth-forms' ),
				'text'     => __( 'First name, email and consent.', 'aluleth-forms' ),
				'style'    => 'dark',
				'fields'   => array(
					array(
						'id'    => 'intro',
						'type'  => 'heading',
						'label' => __( 'Get our news first', 'aluleth-forms' ),
					),
					array(
						'id'           => 'first_name',
						'type'         => 'text',
						'label'        => __( 'First name', 'aluleth-forms' ),
						'width'        => 'half',
						'map'          => 'first_name',
						'autocomplete' => 'given-name',
					),
					array(
						'id'       => 'email',
						'type'     => 'email',
						'label'    => __( 'Email', 'aluleth-forms' ),
						'required' => true,
						'width'    => 'half',
					),
					self::consent( true ),
				),
				'settings' => array(
					'submit_label'    => __( 'Subscribe', 'aluleth-forms' ),
					'success_message' => __( 'Thank you! You are on the list.', 'aluleth-forms' ),
					'notify'          => false,
					'tags'            => array( 'newsletter' ),
				),
			),
			'callback'   => array(
				'title'    => __( 'Call me back', 'aluleth-forms' ),
				'text'     => __( 'Phone number and the best time to call.', 'aluleth-forms' ),
				'style'    => 'clean',
				'fields'   => array(
					array(
						'id'           => 'name',
						'type'         => 'text',
						'label'        => __( 'Your name', 'aluleth-forms' ),
						'required'     => true,
						'width'        => 'half',
						'map'          => 'full_name',
						'autocomplete' => 'name',
					),
					array(
						'id'       => 'phone',
						'type'     => 'phone',
						'label'    => __( 'Phone', 'aluleth-forms' ),
						'required' => true,
						'width'    => 'half',
						'map'      => 'phone',
					),
					array(
						'id'      => 'when',
						'type'    => 'radio',
						'label'   => __( 'Best time to call', 'aluleth-forms' ),
						'options' => array( __( 'Morning', 'aluleth-forms' ), __( 'Lunchtime', 'aluleth-forms' ), __( 'Afternoon', 'aluleth-forms' ), __( 'Evening', 'aluleth-forms' ) ),
					),
					array(
						'id'    => 'topic',
						'type'  => 'text',
						'label' => __( 'What is it about?', 'aluleth-forms' ),
					),
				),
				'settings' => array(
					'submit_label'    => __( 'Call me back', 'aluleth-forms' ),
					'success_message' => __( 'Thank you! We will call you back soon.', 'aluleth-forms' ),
					'statuses'        => self::statuses( array( __( 'To call', 'aluleth-forms' ), __( 'Called', 'aluleth-forms' ), __( 'Done', 'aluleth-forms' ) ) ),
				),
			),
			'event'      => array(
				'title'    => __( 'Event registration', 'aluleth-forms' ),
				'text'     => __( 'Attendees, number of seats and diet.', 'aluleth-forms' ),
				'style'    => 'clean',
				'fields'   => array_merge(
					self::person( true ),
					array(
						array(
							'id'       => 'seats',
							'type'     => 'number',
							'label'    => __( 'Number of people', 'aluleth-forms' ),
							'required' => true,
							'min'      => 1,
							'max'      => 10,
							'default'  => '1',
							'width'    => 'half',
						),
						array(
							'id'      => 'diet',
							'type'    => 'checkboxes',
							'label'   => __( 'Dietary needs', 'aluleth-forms' ),
							'options' => array( __( 'Vegetarian', 'aluleth-forms' ), __( 'Vegan', 'aluleth-forms' ), __( 'Gluten-free', 'aluleth-forms' ) ),
						),
						self::consent(),
					)
				),
				'settings' => array(
					'submit_label'    => __( 'Register', 'aluleth-forms' ),
					'success_message' => __( 'You are registered! We look forward to seeing you.', 'aluleth-forms' ),
					'statuses'        => self::statuses( array( __( 'Registered', 'aluleth-forms' ), __( 'Confirmed', 'aluleth-forms' ), __( 'Attended', 'aluleth-forms' ) ) ),
				),
			),
			'feedback'   => array(
				'title'    => __( 'Customer feedback', 'aluleth-forms' ),
				'text'     => __( 'Satisfaction, what went well, what to improve.', 'aluleth-forms' ),
				'style'    => 'soft',
				'fields'   => array(
					array(
						'id'       => 'rating',
						'type'     => 'radio',
						'label'    => __( 'How satisfied are you?', 'aluleth-forms' ),
						'required' => true,
						'options'  => array( __( 'Very satisfied', 'aluleth-forms' ), __( 'Satisfied', 'aluleth-forms' ), __( 'Neutral', 'aluleth-forms' ), __( 'Not satisfied', 'aluleth-forms' ) ),
					),
					array(
						'id'    => 'liked',
						'type'  => 'textarea',
						'label' => __( 'What did you like?', 'aluleth-forms' ),
					),
					array(
						'id'    => 'improve',
						'type'  => 'textarea',
						'label' => __( 'What could we do better?', 'aluleth-forms' ),
					),
					array(
						'id'      => 'recommend',
						'type'    => 'radio',
						'label'   => __( 'Would you recommend us?', 'aluleth-forms' ),
						'options' => array( __( 'Yes', 'aluleth-forms' ), __( 'Maybe', 'aluleth-forms' ), __( 'No', 'aluleth-forms' ) ),
					),
					array(
						'id'    => 'email',
						'type'  => 'email',
						'label' => __( 'Email (if you would like an answer)', 'aluleth-forms' ),
					),
				),
				'settings' => array(
					'success_message' => __( 'Thank you for your feedback!', 'aluleth-forms' ),
				),
			),
			'support'    => array(
				'title'    => __( 'Support request', 'aluleth-forms' ),
				'text'     => __( 'Topic, priority and order number.', 'aluleth-forms' ),
				'style'    => 'clean',
				'fields'   => array_merge(
					self::person(),
					array(
						array(
							'id'    => 'reference',
							'type'  => 'text',
							'label' => __( 'Order or reference number', 'aluleth-forms' ),
							'width' => 'half',
						),
						array(
							'id'       => 'topic',
							'type'     => 'select',
							'label'    => __( 'Topic', 'aluleth-forms' ),
							'required' => true,
							'width'    => 'half',
							'options'  => array( __( 'Order', 'aluleth-forms' ), __( 'Payment', 'aluleth-forms' ), __( 'Technical problem', 'aluleth-forms' ), __( 'Other', 'aluleth-forms' ) ),
						),
						array(
							'id'      => 'priority',
							'type'    => 'radio',
							'label'   => __( 'Priority', 'aluleth-forms' ),
							'options' => array( __( 'Normal', 'aluleth-forms' ), __( 'Urgent', 'aluleth-forms' ) ),
						),
						array(
							'id'       => 'message',
							'type'     => 'textarea',
							'label'    => __( 'Describe the problem', 'aluleth-forms' ),
							'required' => true,
						),
					)
				),
				'settings' => array(
					'success_message' => __( 'Thank you! Our team will get back to you shortly.', 'aluleth-forms' ),
					'statuses'        => self::statuses( array( __( 'New', 'aluleth-forms' ), __( 'In progress', 'aluleth-forms' ), __( 'Waiting for the customer', 'aluleth-forms' ), __( 'Solved', 'aluleth-forms' ) ) ),
				),
			),
			'waitlist'   => array(
				'title'    => __( 'Waitlist', 'aluleth-forms' ),
				'text'     => __( 'Early access or launch list.', 'aluleth-forms' ),
				'style'    => 'dark',
				'fields'   => array(
					array(
						'id'       => 'email',
						'type'     => 'email',
						'label'    => __( 'Email', 'aluleth-forms' ),
						'required' => true,
					),
					array(
						'id'      => 'source',
						'type'    => 'select',
						'label'   => __( 'How did you hear about us?', 'aluleth-forms' ),
						'options' => array( __( 'Search engine', 'aluleth-forms' ), __( 'Social media', 'aluleth-forms' ), __( 'A friend', 'aluleth-forms' ), __( 'Other', 'aluleth-forms' ) ),
					),
					self::consent( true ),
				),
				'settings' => array(
					'submit_label'    => __( 'Join the list', 'aluleth-forms' ),
					'success_message' => __( 'You are on the list. We will email you first.', 'aluleth-forms' ),
					'notify'          => false,
					'tags'            => array( 'waitlist' ),
				),
			),
		);

		/**
		 * Filters the templates of the "Create a form" screen.
		 *
		 * @param array $templates Id => title, text, style, fields, settings.
		 */
		return (array) apply_filters( 'aluleth_forms/templates', $templates );
	}

	/**
	 * Clean definition of a template.
	 *
	 * @param string $id Template id.
	 * @return array|null array( 'title', 'schema' ).
	 */
	public static function get( $id ) {
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			return null;
		}
		$template          = $all[ $id ];
		$settings          = isset( $template['settings'] ) ? (array) $template['settings'] : array();
		$settings['style'] = isset( $template['style'] ) ? $template['style'] : 'clean';
		return array(
			'title'  => $template['title'],
			'schema' => Schema::clean(
				array(
					'fields'   => $template['fields'],
					'settings' => $settings,
				)
			),
		);
	}
}
