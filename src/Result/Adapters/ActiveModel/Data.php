<?php
namespace PhalconRest\Result\Adapters\ActiveModel;

class Data extends \PhalconRest\Result\Data
{

    /**
     * hook to describe how to encode this class for ActiveModel
     *
     * @return array
     */
    public function JsonSerialize()
    {
        // if formatting is requested, well then format baby!
        $config = $this->di->get('config');
        $formatTo = $config['application']['propertyFormatTo'];

        if ($formatTo == 'none') {
            $result = $this->attributes;
        } else {
            $inflector = $this->di->get('inflector');
            $result = [];
            foreach ($this->attributes as $key => $value) {
                $result[$inflector->normalize($key, $formatTo)] = $value;
            }
        }

        // TODO fix this hack
        $result['id'] = $this->getId();

        return $result;
    }

}