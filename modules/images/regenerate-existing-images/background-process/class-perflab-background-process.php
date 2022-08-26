<?php
/**
 * Background process runner class.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Class Perflab_Background_Process.
 *
 * Runs the heavy lifting tasks in background in separate process.
 */
class Perflab_Background_Process {
	/**
	 * Job instance.
	 *
	 * @var Perflab_Background_Job
	 */
	private $job;

	/**
	 * Checks if the background process is completed.
	 *
	 * @return bool
	 */
	public function completed() {
		return false;
	}

	/**
	 * Checks whether the memory is exceeded for the current process.
	 *
	 * @return bool
	 */
	private function memory_exceeded() {
		return false;
	}

	/**
	 * Checks if current execution time is exceeded for the process.
	 *
	 * @return bool
	 */
	private function time_exceeded() {
		return false;
	}

	/**
	 * Locks the process. It tells that process is running.
	 *
	 * @return void
	 */
	private function lock() {

	}

	/**
	 * Unlocks the process. It tells that process is running.
	 *
	 * @return void
	 */
	private function unlock() {

	}
}
