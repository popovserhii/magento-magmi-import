<?php
/**
 * Enter description here...
 *
 * @category Agere
 * @package Agere_<package>
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 26.05.2017 18:59
 */
return [
    'magmi-import' => [
        'tasks' => [
            //'product' => [],
            'image' => [
                'configurable_import' => [
                    'scan' => [
                        'product_type' => 'configurable', // simple
                        // If type "dir" than images put in this directory
                        // if type "file" than images should look in current directory
                        'type' => 'dir', // 'type' => 'file'
                        'strategy' => 'simple', // 'strategy' => 'pattern'
                        'name_to_attribute' => 'sku', // filename to attribute name
                        'images' => '%sku%/*.jpg',
                        'scan' => [
                            'type' => 'dir',
                            'product_type' => 'simple',
                            'name_to_attribute' => 'color',
                            'strategy' => 'simple',
                            'images' => [
                                'pattern' => '%color%/*.jpg',
                                // based on glob http://www.cowburn.info/2010/04/30/glob-patterns/
                            ],
                        ],
                    ],
                ],
            ],
        ]
    ]
];