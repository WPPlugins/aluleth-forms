<?php
/**
 * Steps of Submission::finish() already done for one entry, kept by the code
 * that saves the entry (an order, a pending payment) so that a finish that was
 * interrupted is taken up where it stopped: each step (contact, team email,
 * confirmation, receipt, outside tools, "entry created") is recorded only once
 * it is done, and a step recorded is never done again.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Finish steps.
 */
final class Finish_Steps {

	/**
	 * Steps done: step => what the step returned + 1 (the contact id + 1 for "contact", else 1).
	 *
	 * @var array<string, int>
	 */
	private $done;

	/**
	 * Saves the steps done (called after each step).
	 *
	 * @var callable
	 */
	private $save;

	/**
	 * Steps.
	 *
	 * @param array    $done Steps done so far (as saved).
	 * @param callable $save Receives the steps done after each new one, and must store them.
	 */
	public function __construct( array $done, callable $save ) {
		$this->done = array();
		foreach ( $done as $step => $value ) {
			if ( is_string( $step ) && '' !== $step ) {
				$this->done[ $step ] = (int) $value;
			}
		}
		$this->save = $save;
	}

	/**
	 * Whether a step is done.
	 *
	 * @param string $step Step.
	 * @return bool
	 */
	public function has( $step ) {
		return array_key_exists( (string) $step, $this->done );
	}

	/**
	 * Value recorded for a step (0 when none; see run()).
	 *
	 * @param string $step Step.
	 * @return int
	 */
	public function get( $step ) {
		return $this->has( $step ) ? (int) $this->done[ (string) $step ] : 0;
	}

	/**
	 * Records a step as done, and stores the steps at once.
	 *
	 * @param string $step  Step.
	 * @param int    $value Value recorded (at least 1).
	 * @return void
	 */
	public function mark( $step, $value = 1 ) {
		$this->done[ (string) $step ] = max( 1, (int) $value );
		call_user_func( $this->save, $this->done );
	}

	/**
	 * Steps done.
	 *
	 * @return array<string, int>
	 */
	public function all() {
		return $this->done;
	}

	/**
	 * Runs a step unless it is done, then records it. A step that throws is not
	 * recorded (it runs again next time).
	 *
	 * @param Finish_Steps|null $steps Steps (null: just run it).
	 * @param string            $step  Step.
	 * @param callable          $work  Work; may return an int to record (the contact id).
	 * @return int What the step returned, or what was recorded when it was done before.
	 */
	public static function run( $steps, $step, callable $work ) {
		if ( $steps instanceof self && $steps->has( $step ) ) {
			return $steps->get( $step ) - 1;
		}
		$result = max( 0, (int) call_user_func( $work ) );
		if ( $steps instanceof self ) {
			$steps->mark( $step, $result + 1 );
		}
		return $result;
	}
}
