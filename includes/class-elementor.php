<?php
/**
 * Elementor integration: an "Aluleth Form" widget in the Aluleth category.
 *
 * The hooks only fire when Elementor is active, so nothing is loaded otherwise.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor integration.
 */
final class Elementor {

	/**
	 * Category shared by every Aluleth widget.
	 */
	const CATEGORY = 'aluleth';

	/**
	 * Hooks into Elementor.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widget' ) );
	}

	/**
	 * Adds the "Aluleth" category, unless another Aluleth plugin already did.
	 *
	 * @param \Elementor\Elements_Manager $manager Elements manager.
	 * @return void
	 */
	public static function category( $manager ) {
		$categories = method_exists( $manager, 'get_categories' ) ? $manager->get_categories() : array();
		if ( isset( $categories[ self::CATEGORY ] ) ) {
			return;
		}
		$manager->add_category(
			self::CATEGORY,
			array(
				'title' => 'Aluleth',
				'icon'  => 'eicon-form-horizontal',
			)
		);
	}

	/**
	 * Registers the widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets Widgets manager.
	 * @return void
	 */
	public static function widget( $widgets ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		$widgets->register( new Elementor_Widget() );
	}

	/**
	 * Published forms, for the widget's form picker.
	 *
	 * @return array<int|string, string> Form id => title.
	 */
	public static function form_options() {
		$options = array();
		$forms   = get_posts(
			array(
				'post_type'      => Plugin::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $forms as $form ) {
			/* translators: %d: form id. */
			$options[ (string) $form->ID ] = '' !== $form->post_title ? $form->post_title : sprintf( __( 'Form #%d', 'aluleth-forms' ), $form->ID );
		}
		return $options;
	}
}
