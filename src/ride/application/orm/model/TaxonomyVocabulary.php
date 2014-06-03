<?php

namespace ride\application\orm\model;

use ride\application\orm\entry\TaxonomyVocabularyEntry;

/**
 * Data container for a vocabulary entry
 */
class TaxonomyVocabulary extends TaxonomyVocabularyEntry {

    /**
     * Gets a string representation of this term
     * @return string
     */
    public function __toString() {
        return $this->getName();
    }

}
