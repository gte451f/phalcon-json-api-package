<?php

namespace PhalconRest\Traits;

use PhalconRest\Exception\HTTPException;

trait TableNamespace
{
    /**
     * Given a particular fieldName, look through the current model's column map and see if that
     * particular fieldName appears in it, if it does, then return the appropriate namespace for this
     * fieldName
     *
     *
     * Columns from related tables can be searched via a colon such as related_table_name:column_name
     * A concrete example is
     * resources:matters_id=123
     * resources:child_table=note||subject
     *
     * A rather obscure feature of this implementation is that providing no table prefix often works correctly
     *
     * this function now checks parent models if not matching column is found in the primary table
     *
     *
     * @param $name
     * @param bool $escape
     * @throws HTTPException
     * @return string|boolean - the correct table's namespace or an empty string ''
     */
    protected function getTableNameSpace(string $name, $escape = true)
    {
        $searchBits = explode(':', $name);

        // assign a default value
        $colMap = [];

        // if a related table is referenced, then search related model column maps instead of the primary model
        if (count($searchBits) == 2) {
            // build field name from 2nd value
            $fieldName = $searchBits[1];
            // start search for a related model
            $matchFound = false;
            foreach ($this->entity->activeRelations as $relation) {
                if ($searchBits[0] == $relation->getTableName()) {

                    // detect the relationship alias
                    $alias = $relation->getAlias();
                    if (!$alias) {
                        $alias = $relation->getReferencedModel();
                    }

                    // set namespace for later pickup
                    $modelNameSpace = $relation->getReferencedModel();
                    $relatedModel = new $modelNameSpace();
                    $colMap = $relatedModel->getAllColumns();
                    $matchFound = true;
                    break;
                }
            }

            // if we made it this far, than a prefix was supplied but it did not match any known hasOne relationship
            if ($matchFound == false) {
                throw new HTTPException("Unknown table prefix supplied in filter.", 500, array(
                    'dev' => "Encountered a table prefix that did not match any known hasOne relationships in the model.  
                    Encountered Search: $fieldName",
                    'code' => '891488651361948131461849'
                ));
            }
        } else {
            // kept here so subsequent logic can refer to the same field
            $fieldName = $searchBits[0];

            $alias = $this->model->getModelNameSpace();
            $colMap = $this->model->getAllColumns(false);
        }

        // return modelNameSpace if the field is detected in the selected model's column map
        foreach ($colMap as $field) {
            if ($fieldName == $field) {
                return ($escape == true ? "[$alias]" : $alias);
            }
        }

        // still here?  try the parent model and prepend the parent model alias if the field is detected in that model's column map
        $currentModel = $this->model;
        while ($currentModel) {
            $parentModelNameSpace = $currentModel->getParentModel(true);
            $parentModelName = $currentModel->getParentModel(false);
            // if not parent name specified, skip this part
            if ($parentModelName) {
                $parentModel = new $parentModelNameSpace();
                // loop through all relationships to reference this one by its alias
                foreach ($this->entity->activeRelations as $relation) {
                    $alias = $relation->getAlias();
                    // to detect the parent model we'll compare against either the alias or the full model name
                    if ($parentModelName == $alias || $parentModelName == $relation->getModelName()) {
                        $colMap = $parentModel->getAllColumns(false);
                        foreach ($colMap as $field) {
                            if ($fieldName == $field) {
                                return ($escape == true ? "[$alias]" : $alias);
                            }
                        }
                    }
                }
                $currentModel = $parentModel;
            } else {
                //escape the loop!
                $currentModel = false;
            }
        }

        // if you are here, that means no processing was required?
        return false;
    }
}