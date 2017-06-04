<?php

/**
 * @copyright   Copyright (c) 2014 http://agere.com.ua
 * @author        Serhii Popov
 * @license     http://opensource.org/licenses/gpl-license.php  GNU General Public License (GPL)
 */
class Popov_Magmi_Helper_SpecialChar extends Mage_Core_Helper_Abstract
{
    /**
     * Special chars maps for replace
     *
     * @var array
     */
    protected $specialCharsMap = [
        '&Slash&' => '/',
        '&Backslash&' => '\\',
        '&Asterisk&' => '*',
        '&Pipe&' => '|',
        '&Colon&' => ':',
        '&quot&' => '"',
        '&lt&' => '<',
        '&gt&' => '>',
        '&Questionmark&' => '?',
    ];

    public function decode($name)
    {
        static $mapped = [];
        if (!isset($mapped['from'])) { // optimize code
            $mapped['from'] = array_keys($this->specialCharsMap);
            $mapped['to'] = array_values($this->specialCharsMap);
        }

        return str_replace($mapped['from'], $mapped['to'], $name);
    }

    public function encode($name)
    {
        static $mapped = [];
        if (!isset($mapped['from'])) { // optimize code
            $mapped['to'] = array_keys($this->specialCharsMap);
            $mapped['from'] = array_values($this->specialCharsMap);
        }

        return str_replace($mapped['from'], $mapped['to'], $name);
    }
}