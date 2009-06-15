<?php

abstract class BfoxSequenceList {
	protected $sequences = array();

	public function is_valid() {
		return (!empty($this->sequences));
	}

	/**
	 * Return the sequences
	 *
	 * @return array of objects
	 */
	public function get_seqs() {
		return $this->sequences;
	}

	public function add_seqs($seqs) {
		foreach ($seqs as $seq) $this->add_seq($seq);
	}

	public function sub_seqs($seqs) {
		foreach ($seqs as $seq) $this->sub_seq($seq);
	}

	/**
	 * Prepares sequence input for adding or subtracting from the list of sequences
	 *
	 * @param mixed $start
	 * @param integer $end
	 * @return stdObject sequence
	 */
	private static function prepare_seq($start, $end = 0) {
		if (is_array($start)) list($start, $end) = $start;
		elseif (is_object($start)) {
			$end = $start->end;
			$start = $start->start;
		}

		// If the end is not set, it should equal the start
		if (empty($end)) $end = $start;

		$seq = (object) array('start' => $start, 'end' => $end);

		// If the end is less than the start, just switch them around
		if ($end < $start) {
			$seq->end = $start;
			$seq->start = $end;
		}

		return $seq;
	}

	/**
	 * Adds a new sequence to the sequence list
	 *
	 * This function maintains that there are no overlapping sequences and that they are in order from lowest to highest
	 *
	 * @param integer $start
	 * @param integer $end
	 */
	public function add_seq($start, $end = 0) {
		$new_seq = self::prepare_seq($start, $end);

		$new_seqs = array();
		foreach ($this->sequences as $seq) {
			if (isset($new_seq)) {
				// If the new seq starts before seq
				if ($new_seq->start < $seq->start) {
					// If the new seq also ends before, then we've found the spot to place it
					// Otherwise, it intersects, so modify the new seq to include seq
					if (($new_seq->end + 1) < $seq->start) {
						$new_seqs []= $new_seq;
						$new_seqs []= $seq;
						unset($new_seq);
					}
					else {
						if ($new_seq->end < $seq->end) $new_seq->end = $seq->end;
					}
				}
				else {
					// The new seq starts with or after seq
					// If the new seq starts before seq ends, we have an intersection
					// Otherwise, we passed seq without intersecting it, so add it to the array
					if (($new_seq->start - 1) <= $seq->end) {
						$new_seq->start = $seq->start;
						if ($new_seq->end < $seq->end) $new_seq->end = $seq->end;
					}
					else {
						$new_seqs []= $seq;
					}
				}
			}
			else $new_seqs []= $seq;
		}
		if (isset($new_seq)) $new_seqs []= $new_seq;

		$this->sequences = $new_seqs;
	}

	/**
	 * Subtracts a sequence from the list
	 *
	 * @param integer $start
	 * @param integer $end
	 */
	public function sub_seq($start, $end = 0) {
		$sub_seq = self::prepare_seq($start, $end);

		$new_seqs = array();
		foreach ($this->sequences as $seq) {
			if (isset($sub_seq)) {
				// If the seq starts before sub_seq
				if ($seq->start < $sub_seq->start) {
					// If the seq also ends before sub seq, then it is fine
					if ($seq->end < $sub_seq->start) {
						$new_seqs []= $seq;
					}
					// Otherwise, if the seq ends before sub_seq ends, we need to adjust the end
					elseif ($seq->end <= $sub_seq->end) {
						$seq->end = $sub_seq->start - 1;
						$new_seqs []= $seq;
					}
					// Otherwise, the seq ends after sub_seq ends, so we need to split the seq
					else {
						// Create a new seq that starts after the sub_seq
						$new_seq->start = $sub_seq->end + 1;
						$new_seq->end = $seq->end;

						// Adjust the old seq to end before the sub_seq
						$seq->end = $sub_seq->start - 1;

						// Add the seqs
						$new_seqs []= $seq;
						$new_seqs []= $new_seq;
					}
				}
				else {
					// The seq starts between or after sub_seq
					// If the seq starts between...
					// Otherwise, the seq starts after and is fine
					if ($seq->start <= $sub_seq->end) {
						// If the seq ends after the sub_seq, then we can add the last portion
						if ($seq->end > $sub_seq->end) {
							$seq->start = $sub_seq->end + 1;
							$new_seqs []= $seq;
						}
					}
					else {
						$new_seqs []= $seq;
						// We've passed the sub_seq, so we don't need it anymore
						unset($sub_seq);
					}
				}
			}
			else $new_seqs []= $seq;
		}

		$this->sequences = $new_seqs;
	}
}

?>