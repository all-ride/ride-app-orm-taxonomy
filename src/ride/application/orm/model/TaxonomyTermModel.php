<?php

namespace ride\application\orm\model;

use ride\library\orm\definition\ModelTable;
use ride\library\orm\model\GenericModel;

/**
 * Model for the taxonomy terms
 */
class TaxonomyTermModel extends GenericModel {

    /**
     * Gets a term by it's name
     * @param string $name Name of the term
     * @param integer|string|TaxonomyVocabulary $vocabulary Vocabulary where the
     * requested term should reside in
     * @param integer|string|TaxonomyTerm $parent Parent term where the
     * requested term should reside in
     * @return TaxonomyTerm Instance of the existing term or a new instance if
     * the term does not exist
     */
    public function getByName($name, $vocabulary = null, $parent = null, $locale = null) {
        $query = $this->createTermQuery($vocabulary, $parent, $locale);
        $query->addCondition('{name} = %1%', $name);

        $term = $query->queryFirst();
        if ($term) {
            // term exists, return it
            return $term;
        }

        // create a new term for the requested name
        $term = $this->createEntry();
        $term->setName($name);

        if ($vocabulary) {
            // set the vocabulary to the new term
            if (!is_object($vocabulary)) {
                $vocabularyModel = $this->orm->getTaxonomyVocabularyModel();
                if (is_numeric($vocabulary)) {
                    $vocabulary = $vocabularyModel->createProxy($vocabulary);
                } else {
                    $vocabulary = $vocabularyModel->getBy(array('filter' => array('slug' => $vocabulary)));
                }
            }

            $term->setVocabulary($vocabulary);
        }

        if ($parent) {
            // set a parent to the new term
            if (!is_object($parent)) {
                if (is_numeric($parent)) {
                    $parent = $this->createProxy($parent);
                } else {
                    $parent = $this->getBy(array('filter' => array('slug' => $parent)));
                }
            }

            $term->setParent($parent);
        }

        // ready, go back
        return $term;
    }

    /**
     * Gets terms with the provided parent
     * @param integer|string|TaxonomyVocabulary $vocabulary Vocabulary where the
     * requested term should reside in
     * @param integer|string|TaxonomyTerm $parent Parent term where the
     * requested term should reside in
     * @param string $locale Code of the locale
     * @param string $orderField Name of the order field
     * @return array Array with the term for the provided arguments
     */
    public function findByParent($vocabulary = null, $parent = null, $locale = null, $orderField = null) {
        $query = $this->createTermQuery($vocabulary, $parent, $locale);
        $query->setFetchUnlocalized(true);

        if ($orderField) {
            $query->addOrderBy('{parent.' . $orderField . '} ASC, {' . $orderField . '} ASC');
        }

        return $query->query();
    }

    /**
     * Creates a term query
     * @param integer|string|TaxonomyVocabulary $vocabulary Vocabulary where the
     * requested term should reside in
     * @param integer|string|TaxonomyTerm $parent Parent term where the
     * requested term should reside in
     * @param string $locale Code of the locale
     * @return \ride\library\orm\query\ModelQuery
     */
    protected function createTermQuery($vocabulary = null, $parent = null, $locale = null) {
        $query = $this->createQuery($locale);

        if ($vocabulary !== null) {
            if (!$vocabulary) {
                $query->addCondition('{vocabulary} IS NULL');
            } elseif (is_object($vocabulary)) {
                $query->addCondition('{vocabulary} = %1%', $vocabulary->getId());
            } elseif (is_numeric($vocabulary)) {
                $query->addCondition('{vocabulary} = %1%', $vocabulary);
            } else {
                $query->addCondition('{vocabulary.slug} = %1%', $vocabulary);
            }
        }

        if ($parent !== null) {
            if (!$parent) {
                $query->addCondition('{parent} IS NULL');
            } elseif (is_object($parent)) {
                $query->addCondition('{parent} = %1%', $parent->getId());
            } elseif (is_numeric($vocabulary)) {
                $query->addCondition('{parent} = %1%', $parent);
            } else {
                $query->addCondition('{parent.slug} = %1%', $parent);
            }
        }

        return $query;
    }

    /**
     * Gets a list of taxonomy terms in their hierarchy
     * @param integer|string|TaxonomyVocabulary $vocabulary Vocabulary where the
     * requested term should reside in
     * @param integer|string|TaxonomyTerm $parent Parent term where the
     * requested term should reside in
     * @param string $locale Code of the locale
     * @param string $orderField Name of the order field
     * @return array Array with the id of the term as key and the hierarchic
     * name as value
     */
    public function getTaxonomyTree($vocabulary = null, $parent = null, $locale = null, $orderField = null, $prefix = null) {
        $tree = array();

        if ($parent === null) {
            $parent = 0;
        }

        $terms = $this->findByParent($vocabulary, $parent, $locale, $orderField);
        foreach ($terms as $term) {
            $name = $prefix . '/' . $term->getName();

            $tree[$term->getId()] = $name;

            $tree += $this->getTaxonomyTree($vocabulary, $term, $locale, $orderField, $name);
        }

        return $tree;
    }

    /**
     * Calculates the cloud weight of the provided terms
     * @var array $terms Terms to calculate the weight for
     * @return array Provided terms
     */
    public function calculateCloud(array $terms) {
        foreach ($terms as $term) {
            if (!$this->isValidEntry($term)) {
                throw new OrmException('Could not generate cloud: invalid term provided');
            }

            $term->setWeight($this->calculateCloudWeight($term));
        }

        return $terms;
    }

    /**
     * Calculates the weight of the provided term in the cloud
     * @param TaxonomyTerm $term
     * @return integer Weight for the provided term
     */
    public function calculateCloudWeight(TaxonomyTerm $term) {
        $weight = 0;

        $models = $this->meta->getUnlinkedModels();
        foreach ($models as $modelName) {
            $model = $this->orm->getModel($modelName);
            $modelMeta = $model->getMeta();
            $modelWeight = $modelMeta->getOption('taxonomy.cloud.weight', 1);

            if ($modelMeta->hasField('taxonomyTerm')) {
                $query = $model->createQuery();
                $query->addCondition('{taxonomyTerm} = %1%', $term->id);

                $weight += $query->count() * $modelWeight;
            } else {
                $fields = $modelMeta->getRelation('TaxonomyTerm', ModelTable::BELONGS_TO);
                foreach ($fields as $field) {
                    $query = $model->createQuery();
                    $query->addCondition('{' . $field->getName() . '} = %1%', $term->id);

                    $weight += $query->count() * $modelWeight;
                }
            }
        }

        return $weight;
    }

}
