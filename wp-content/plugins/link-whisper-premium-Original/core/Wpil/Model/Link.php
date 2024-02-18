<?php

/**
 * Model for links
 *
 * Class Wpil_Model_Link
 */
class Wpil_Model_Link
{
    public $url = '';
    public $host = '';
    public $internal = false;
    public $post = false;
    public $anchor = '';
    public $added_by_plugin = false;

    public function __construct($params = [])
    {
        //fill model properties from initial array
        foreach ($params as $key => $value) {
            if (isset($this->{$key})) {
                $this->{$key} = $value;
            }
        }
    }
}
