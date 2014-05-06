<?php

namespace ride\application\orm\model;

use ride\library\orm\model\data\Data;

/**
 * Data container for a vocabulary entry
 */
class TaxonomyVocabulary extends Data {

    /**
     * Name of the term
     * @var string
     */
    public $name;

    /**
     * Gets a string representation of this term
     * @return string
     */
    public function __toString() {
        return $this->name;
    }

}
