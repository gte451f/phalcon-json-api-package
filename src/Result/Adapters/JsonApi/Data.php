<?php
namespace PhalconRest\Result\Adapters\JsonApi;

class Data extends \PhalconRest\Result\Data
{

    /**
     * hook to describe how to encode this class for JSON
     *
     * @return array
     */
    public function JsonSerialize()
    {
        // if formatting is requested, well then format baby!
        $config = $this->di->get('config');
        if ($config['application']['propertyFormatTo'] == 'none') {
            $attributes = $this->attributes;
        } else {
            $inflector = $this->di->get('inflector');
            $attributes = [];
            foreach ($this->attributes as $key => $value) {
                $attributes[$inflector->normalize($key,
                    $config['application']['propertyFormatTo'])] = $value;
            }
        }

        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'attributes' => $attributes
        ];

        if ($this->relationships) {
            $result['relationships'] = $this->relationships;
        }

        return $result;
    }

}