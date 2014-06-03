<?php

namespace ride\application\orm\model;

use ride\application\orm\entry\TaxonomyTermEntry;

/**
 * Data container for a term entry
 */
class TaxonomyTerm extends TaxonomyTermEntry {

    /**
     * Gets a string representation of this term
     * @return string
     */
    public function __toString() {
        return $this->getName();
    }

}
