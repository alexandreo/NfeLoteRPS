<?php

namespace MatheusHack\NfLoteRPS\Factories;

use MatheusHack\NfLoteRPS\Entities\Config;
use MatheusHack\NfLoteRPS\Constants\Layout;
use MatheusHack\NfLoteRPS\Constants\NfType;
use MatheusHack\NfLoteRPS\Constants\FieldType;
use MatheusHack\NfLoteRPS\Constants\LayoutType;
use MatheusHack\NfLoteRPS\Constants\FieldParameter;
use MatheusHack\NfLoteRPS\Exceptions\LayoutException;

class YamlFactory
{
    protected $options;

    public function setOptions(Config $config)
    {
        $this->options = $config;
    }

    public function loadYml($file)
    {
        $filename = "{$this->options->pathYml}/v{$this->options->layout}/{$this->options->typeNf}/{$this->options->type}/$file";

        if (!file_exists($filename))
            throw new LayoutException("Layout {$file} of type {$this->options->type} not found for version {$this->options->layout}");

        $this->fields = spyc_load_file($filename);

        return $this->validateLayout();
    }

    private function validateLayout()
    {
        if(empty($this->fields))
            throw new LayoutException("No field found");
        
        $this->validateCollision();
        $this->validateType();
        $this->validateDefault();
        $this->validateParameters();

        return $this->fields;
    }

    private function validateCollision()
    {
        foreach($this->fields as $name => $field){
            $pos_start = $field['pos'][0];
            $pos_end = $field['pos'][1];

            foreach($this->fields as $current_name => $current_field){
                if ($current_name === $name)
                    continue;

                $current_pos_start = $current_field['pos'][0];
                $current_pos_end = $current_field['pos'][1];

                if(!is_numeric($current_pos_start) || !is_numeric($current_pos_end))
                    continue;

                if($current_pos_start > $current_pos_end)
                    throw new LayoutException("In the {$current_name} field the starting position ({$current_pos_start}) must be less than or equal to the final position ({$current_pos_end})");

                if(($pos_start >= $current_pos_start && $pos_start <= $current_pos_end) || ($pos_end <= $current_pos_end && $pos_end >= $current_pos_start))
                    throw new LayoutException("The {$name} field collides with the field {$current_name}");
            }
        }
    }

    private function validateType()
    {
        foreach($this->fields as $field => $options){
            if(!in_array($options['type'], FieldType::arrayAllowed()))
                throw new LayoutException("In the {$field} field the type is invalid");
        }      
    }

    private function validateDefault()
    {
        foreach($this->fields as $field => $options){
            if(!isset($options['default']))
                continue;
            
            switch($options['type']){
                default:
                case FieldType::TEXT: 
                    continue;
                break;
                case FieldType::NUMBER: 
                    if(!validateNumeric($options['default']))
                        throw new LayoutException("The default value of the {$field} field must be a number");
                break;
                case FieldType::DATE: 
                    if(!validateDate($options['default'], 'Ymd'))
                        throw new LayoutException("The default value of the {$field} field must be filled in the date format (YYYYMMDD)");
                break;
                case FieldType::CHARACTER: 
                    if(!validateCharacter($options['default']))
                        throw new LayoutException("The default {$field} field value must be a character");
                break;
            }

            $amountCharacters = ($options['pos'][1] - $options['pos'][0]) + 1;

            if($options['pos'][0] == $options['pos'][1] && strlen($options['default']) > 1)
                throw new LayoutException("The default value of the {$field} field is greater than the number of characters allowed");
            else if($options['pos'][0] != $options['pos'][1] && strlen($options['default']) > $amountCharacters)
                throw new LayoutException("The default value of the {$field} field is greater than the number of characters allowed");
        }      
    }

    private function validateParameters()
    {
        foreach($this->fields as $field => $options){
            foreach($options as $option => $value){
                if(!in_array($option, FieldParameter::arrayAllowed()))
                    throw new LayoutException("There are invalid parameters in {$field} field");
            }
        }
    }    
}
