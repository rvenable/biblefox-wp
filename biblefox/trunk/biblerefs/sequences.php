<?php

class BfoxSequence {
	public $start, $end;

	public function __construct($start, $end) {
		$this->start = $start;
		$this->end = $end;
	}
}

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

	protected function add_seqs($seqs) {
		foreach ($seqs as $seq) $this->add_seq($seq);
	}

	protected function sub_seqs($seqs) {
		foreach ($seqs as $seq) $this->sub_seq($seq);
	}

	/**
	 * Adds a new sequence to the sequence list
	 *
	 * This function maintains that there are no overlapping sequences and that they are in order from lowest to highest
	 *
	 * @param integer $start
	 * @param integer $end
	 */
	public function add_seq(BfoxSequence $new_seq) {
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
	public function sub_seq(BfoxSequence $sub_seq) {
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