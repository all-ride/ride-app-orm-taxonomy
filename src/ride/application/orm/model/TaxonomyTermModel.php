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
     * @return TaxonomyTerm Instance of a term
     */
    public function getByName($name, $vocabulary = null, $parent = null) {
        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->addCondition('{name} = %1%', $name);

        if ($vocabulary) {
            if (is_object($vocabulary)) {
                $query->addCondition('{vocabulary} = %1%', $vocabulary->id);
            } elseif (is_numeric($vocabulary)) {
                $query->addCondition('{vocabulary} = %1%', $vocabulary);
            } else {
                $query->addCondition('{vocabulary.slug} = %1%', $vocabulary);
            }
        }

        if ($parent) {
            if (is_object($parent)) {
                $query->addCondition('{parent} = %1%', $parent->id);
            } elseif (is_numeric($vocabulary)) {
                $query->addCondition('{parent} = %1%', $parent);
            } else {
                $query->addCondition('{parent.slug} = %1%', $parent);
            }
        }

        $term = $query->queryFirst();
        if (!$term) {
            $term = $this->createData();
            $term->name = $name;

            if ($vocabulary) {
                if (is_object($vocabulary)) {
                    $vocabulary = $vocabulary->id;
                } elseif (!is_numeric($vocabulary)) {
                    $vocabularyModel = $this->orm->getTaxonomyVocabularyModel();
                    $vocabulary = $vocabularyModel->getBy('slug', $vocabulary, 0);
                    if ($vocabulary) {
                        $vocabulary = $vocabulary->id;
                    }
                }

                $term->vocabulary = $vocabulary;
            }

            if ($parent) {
                if (is_object($parent)) {
                    $parent = $parent->id;
                } elseif (!is_numeric($parent)) {
                    $parent = $this->getBy('slug', $parent, 0);
                    if ($parent) {
                        $parent = $parent->id;
                    }
                }

                $term->parent = $parent;
            }
        }

        return $term;
    }

    /**
     * Calculates the cloud weight of the provided terms
     * @var array $terms Terms to calculate the weight for
     * @return array Provided terms
     */
    public function calculateCloud(array $terms) {
        foreach ($terms as $term) {
            if (!$this->isValidData($term)) {
                throw new OrmException('Could not generate cloud: invalid term provided');
            }

            $term->weight = $this->calculateCloudWeight($term);
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
            $modelWeight = $modelMeta->getOption('taxonomy.weight', 1);

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
