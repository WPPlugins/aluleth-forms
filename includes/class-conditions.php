<?php
/**
 * Conditional logic: show a field only when other answers match.
 *
 * A field may carry:
 * "conditions": { "match": "all"|"any", "rules": [ { "field": "bike", "op": "is", "value": "E-bike" } ] }
 * The same rules run in the browser (forms.js) and on the server, so hidden
 * fields are neither required nor saved.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Conditions.
 */
final class Conditions {

	/**
	 * Allowed operators.
	 */
	const OPS = array( 'is', 'is_not', 'contains', 'not_contains', 'empty', 'not_empty', 'gt', 'lt' );

	/**
	 * Cleans the conditions of one field.
	 *
	 * @param mixed    $raw       Raw conditions.
	 * @param string   $field_id  Id of the field that carries them.
	 * @param string[] $input_ids Ids of the fields that can be tested.
	 * @return array|null Clean conditions, or null when there is no valid rule.
	 */
	public static function clean( $raw, $field_id, array $input_ids ) {
		if ( ! is_array( $raw ) || empty( $raw['rules'] ) || ! is_array( $raw['rules'] ) ) {
			return null;
		}
		$rules = array();
		foreach ( $raw['rules'] as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['field'] ) ) {
				continue;
			}
			$target = sanitize_key( $rule['field'] );
			$op     = isset( $rule['op'] ) ? sanitize_key( $rule['op'] ) : 'is';
			if ( $target === $field_id || ! in_array( $target, $input_ids, true ) || ! in_array( $op, self::OPS, true ) ) {
				continue;
			}
			$rules[] = array(
				'field' => $target,
				'op'    => $op,
				'value' => isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
			);
			if ( count( $rules ) >= 10 ) {
				break;
			}
		}
		if ( ! $rules ) {
			return null;
		}
		return array(
			'match' => isset( $raw['match'] ) && 'any' === $raw['match'] ? 'any' : 'all',
			'rules' => $rules,
		);
	}

	/**
	 * Which fields are visible for the given answers.
	 *
	 * Fields are evaluated in order; a hidden field counts as empty for the
	 * rules of the fields after it.
	 *
	 * @param array $schema Form definition.
	 * @param array $input  Submitted answers (field id => raw value).
	 * @return array<string, bool> Field id => visible.
	 */
	public static function visibility( array $schema, array $input ) {
		$values  = array();
		$visible = array();
		foreach ( $schema['fields'] as $field ) {
			$values[ $field['id'] ] = self::normalise( isset( $input[ $field['id'] ] ) ? $input[ $field['id'] ] : '' );
		}
		foreach ( $schema['fields'] as $field ) {
			$show                    = empty( $field['conditions'] ) || self::matches( $field['conditions'], $values );
			$visible[ $field['id'] ] = $show;
			if ( ! $show ) {
				$values[ $field['id'] ] = '';
			}
		}
		return $visible;
	}

	/**
	 * Whether the conditions match.
	 *
	 * @param array $conditions Conditions.
	 * @param array $values     Normalised answers.
	 * @return bool
	 */
	public static function matches( array $conditions, array $values ) {
		$any = 'any' === $conditions['match'];
		foreach ( $conditions['rules'] as $rule ) {
			$ok = self::test( $rule, isset( $values[ $rule['field'] ] ) ? $values[ $rule['field'] ] : '' );
			if ( $any && $ok ) {
				return true;
			}
			if ( ! $any && ! $ok ) {
				return false;
			}
		}
		return ! $any;
	}

	/**
	 * Tests one rule.
	 *
	 * @param array        $rule  Rule.
	 * @param string|array $value Normalised answer.
	 * @return bool
	 */
	private static function test( array $rule, $value ) {
		$list   = is_array( $value ) ? $value : ( '' === $value ? array() : array( $value ) );
		$wanted = strtolower( $rule['value'] );
		$lower  = array_map( 'strtolower', $list );
		switch ( $rule['op'] ) {
			case 'empty':
				return ! $list;
			case 'not_empty':
				return (bool) $list;
			case 'is':
				return in_array( $wanted, $lower, true );
			case 'is_not':
				return ! in_array( $wanted, $lower, true );
			case 'contains':
				foreach ( $lower as $item ) {
					if ( '' !== $wanted && false !== strpos( $item, $wanted ) ) {
						return true;
					}
				}
				return false;
			case 'not_contains':
				foreach ( $lower as $item ) {
					if ( '' !== $wanted && false !== strpos( $item, $wanted ) ) {
						return false;
					}
				}
				return true;
			case 'gt':
			case 'lt':
				if ( ! $list || ! is_numeric( str_replace( ',', '.', $list[0] ) ) || ! is_numeric( str_replace( ',', '.', $wanted ) ) ) {
					return false;
				}
				$a = (float) str_replace( ',', '.', $list[0] );
				$b = (float) str_replace( ',', '.', $wanted );
				return 'gt' === $rule['op'] ? $a > $b : $a < $b;
		}
		return false;
	}

	/**
	 * Turns a raw answer into a trimmed string or a list of strings.
	 *
	 * @param mixed $raw Raw answer.
	 * @return string|string[]
	 */
	private static function normalise( $raw ) {
		if ( is_array( $raw ) ) {
			$out = array();
			foreach ( $raw as $item ) {
				if ( ! is_scalar( $item ) ) {
					continue;
				}
				$item = trim( sanitize_text_field( (string) $item ) );
				if ( '' !== $item ) {
					$out[] = $item;
				}
			}
			return $out;
		}
		return trim( sanitize_text_field( (string) $raw ) );
	}
}
