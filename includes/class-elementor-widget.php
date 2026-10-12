<?php
/**
 * "Aluleth Form" Elementor widget.
 *
 * Only loaded from Elementor's widgets hook, so Elementor's classes exist.
 * Style controls set the form's CSS variables, so the three form styles keep
 * working and any colour can come from the site's global colours.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget.
 */
class Elementor_Widget extends Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'aluleth-form';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Aluleth Form', 'aluleth-forms' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	/**
	 * Widget categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( Elementor::CATEGORY );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'form', 'contact', 'aluleth', 'newsletter', 'quote', 'booking' );
	}

	/**
	 * Front-end styles.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'aluleth-forms' );
	}

	/**
	 * Front-end scripts.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'aluleth-forms' );
	}

	/**
	 * No extra inner wrapper (lighter markup).
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * The form carries a time token, so its HTML must not be cached by Elementor.
	 *
	 * @return bool
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/**
	 * Panel controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->content_controls();
		$this->colour_controls();
		$this->field_controls();
		$this->button_controls();
		$this->box_controls();
	}

	/**
	 * Content tab: which form, which style.
	 *
	 * @return void
	 */
	private function content_controls() {
		$forms = Elementor::form_options();

		$this->start_controls_section(
			'section_form',
			array(
				'label' => __( 'Form', 'aluleth-forms' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		if ( ! $forms ) {
			$this->add_control(
				'no_forms',
				array(
					'type'            => Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'You have no published form yet.', 'aluleth-forms' ) . ' <a href="' . esc_url( admin_url( 'post-new.php?post_type=' . Plugin::POST_TYPE ) ) . '" target="_blank">' . esc_html__( 'Create a form', 'aluleth-forms' ) . '</a>',
					'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				)
			);
		}

		$this->add_control(
			'form_id',
			array(
				'label'   => __( 'Form', 'aluleth-forms' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array( '' => __( '— Choose a form —', 'aluleth-forms' ) ) + $forms,
				'default' => $forms ? (string) key( $forms ) : '',
			)
		);

		$this->add_control(
			'form_style',
			array(
				'label'       => __( 'Style', 'aluleth-forms' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					''      => __( 'Style chosen in the form', 'aluleth-forms' ),
					'clean' => __( 'Clean', 'aluleth-forms' ),
					'soft'  => __( 'Soft', 'aluleth-forms' ),
					'dark'  => __( 'Dark', 'aluleth-forms' ),
				),
				'default'     => '',
				'description' => __( 'Fine-tune colours, fields and the button in the Style tab.', 'aluleth-forms' ),
			)
		);

		$this->add_control(
			'manage',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Fields, emails and entries are managed in Aluleth › Forms.', 'aluleth-forms' ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . Plugin::POST_TYPE ) ) . '" target="_blank">' . esc_html__( 'Open your forms', 'aluleth-forms' ) . '</a>',
				'content_classes' => 'elementor-descriptor',
				'separator'       => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: colours (CSS variables of the form).
	 *
	 * @return void
	 */
	private function colour_controls() {
		$this->start_controls_section(
			'section_colours',
			array(
				'label' => __( 'Colours', 'aluleth-forms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$colours = array(
			'accent'     => array( __( 'Accent (button, choices)', 'aluleth-forms' ), '--alf-accent' ),
			'on_accent'  => array( __( 'Button text', 'aluleth-forms' ), '--alf-on-accent' ),
			'text'       => array( __( 'Text and labels', 'aluleth-forms' ), '--alf-text' ),
			'muted'      => array( __( 'Help text and placeholders', 'aluleth-forms' ), '--alf-muted' ),
			'field'      => array( __( 'Field background', 'aluleth-forms' ), '--alf-field' ),
			'field_text' => array( __( 'Field text', 'aluleth-forms' ), '--alf-field-text' ),
			'border'     => array( __( 'Field border', 'aluleth-forms' ), '--alf-border' ),
			'focus'      => array( __( 'Focus ring', 'aluleth-forms' ), '--alf-focus' ),
			'error'      => array( __( 'Errors', 'aluleth-forms' ), '--alf-error' ),
			'bg'         => array( __( 'Form background', 'aluleth-forms' ), '--alf-bg' ),
		);
		foreach ( $colours as $key => $colour ) {
			$this->add_control(
				'colour_' . $key,
				array(
					'label'     => $colour[0],
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .alf.alf' => $colour[1] . ': {{VALUE}};',
					),
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * Style tab: labels and fields.
	 *
	 * @return void
	 */
	private function field_controls() {
		$this->start_controls_section(
			'section_fields',
			array(
				'label' => __( 'Fields', 'aluleth-forms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'label'    => __( 'Label typography', 'aluleth-forms' ),
				'selector' => '{{WRAPPER}} .alf .alf-label',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'field_typography',
				'label'    => __( 'Field typography', 'aluleth-forms' ),
				'selector' => '{{WRAPPER}} .alf .alf-input, {{WRAPPER}} .alf .alf-choice, {{WRAPPER}} .alf .alf-consent',
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Space between fields', 'aluleth-forms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .alf.alf' => '--alf-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'radius',
			array(
				'label'      => __( 'Corner radius', 'aluleth-forms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .alf.alf' => '--alf-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'border_width',
			array(
				'label'      => __( 'Border width', 'aluleth-forms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 4,
						'step' => 0.5,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .alf .alf-input, {{WRAPPER}} .alf .alf-choice' => 'border-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'field_padding',
			array(
				'label'      => __( 'Field padding', 'aluleth-forms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .alf .alf-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: buttons.
	 *
	 * @return void
	 */
	private function button_controls() {
		$this->start_controls_section(
			'section_button',
			array(
				'label' => __( 'Button', 'aluleth-forms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .alf .alf-button',
			)
		);

		$this->add_responsive_control(
			'button_align',
			array(
				'label'     => __( 'Alignment', 'aluleth-forms' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'aluleth-forms' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center'     => array(
						'title' => __( 'Centre', 'aluleth-forms' ),
						'icon'  => 'eicon-h-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'aluleth-forms' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .alf .alf-actions' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_full',
			array(
				'label'        => __( 'Full width', 'aluleth-forms' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'selectors'    => array(
					'{{WRAPPER}} .alf .alf-submit, {{WRAPPER}} .alf .alf-next' => 'flex: 1 1 auto;',
				),
			)
		);

		$this->add_control(
			'button_hover',
			array(
				'label'     => __( 'Background on hover', 'aluleth-forms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .alf .alf-submit:hover, {{WRAPPER}} .alf .alf-next:hover' => 'background: {{VALUE}}; opacity: 1;',
				),
			)
		);

		$this->add_control(
			'button_radius',
			array(
				'label'      => __( 'Corner radius', 'aluleth-forms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .alf .alf-button' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => __( 'Padding', 'aluleth-forms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .alf .alf-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: the box around the form.
	 *
	 * @return void
	 */
	private function box_controls() {
		$this->start_controls_section(
			'section_box',
			array(
				'label' => __( 'Form box', 'aluleth-forms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'box_padding',
			array(
				'label'      => __( 'Padding', 'aluleth-forms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .alf.alf' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'box_border',
				'selector' => '{{WRAPPER}} .alf.alf',
			)
		);

		$this->add_control(
			'box_radius',
			array(
				'label'      => __( 'Corner radius', 'aluleth-forms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .alf.alf' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'selector' => '{{WRAPPER}} .alf.alf',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Output.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$form_id  = isset( $settings['form_id'] ) ? absint( $settings['form_id'] ) : 0;
		$editing  = self::is_editor();

		if ( ! $form_id ) {
			if ( $editing ) {
				echo '<div class="alf-elementor-empty" style="padding:24px;border:1.5px dashed #c9ced6;border-radius:10px;text-align:center;font-size:14px">' . esc_html__( 'Choose a form in the panel on the left.', 'aluleth-forms' ) . '</div>';
			}
			return;
		}

		$style = isset( $settings['form_style'] ) && in_array( $settings['form_style'], array( 'clean', 'soft', 'dark' ), true ) ? $settings['form_style'] : '';
		$html  = Renderer::render(
			$form_id,
			array(
				'style'   => $style,
				'preview' => $editing,
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by Renderer::render().
	}

	/**
	 * Whether the widget is shown inside the Elementor editor.
	 *
	 * @return bool
	 */
	private static function is_editor() {
		$elementor = \Elementor\Plugin::$instance;
		return ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) || ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() );
	}
}
