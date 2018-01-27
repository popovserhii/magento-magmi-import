<?php

/**
 * Image Import Test
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popow.serhii@gmail.com>
 * @datetime: 28.05.2017 18:34
 */
use Mockery as m;

class Popov_Magmi_Test_Import_ImageTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
    }

    protected function tearDown()
    {
        m::close();
    }

    /**
     * @dataProvider configProvider
     */
    public function testSimplestStructureOneImagePerProduct($config, $expected)
    {
        /** @var DigitalPianism_TestFramework_Model_Config $mageConfig */
        $mageConfig = Mage::getConfig();

        $productsMock = $this->getConfigurableProductsMock();
        $mageConfig->setResourceModelTestDouble('catalog/product_collection', $productsMock);

        $imageCsv = new Popov_Magmi_Test_Fake_CsvFake();
        $imageImport = new Popov_Magmi_Import_Image();

        $imageImport->setImageCsv($imageCsv);
        $imageImport->setRunMode(Popov_Magmi_Import_Image::RUN_MODE_DEBUG);
        $imageImport->setConfig($config);
        $imageImport->run();


        $this->assertTrue($expected == $imageCsv->getData());
    }

    //public function testSimpleStructureOneImagePerProductЦшWithCustomAttributeIdentifier()
    public function testSimpleStructureOneImagePerProductWithCustomAttributeIdentifier()
    {
        /** @var DigitalPianism_TestFramework_Model_Config $mageConfig */
        $mageConfig = Mage::getConfig();

        $simplesMock = [
            $product1 = m::mock('Varien_Object')
                ->shouldReceive('getData')->with('sku')->andReturn('00SKVS/0DANX')->getMock()
                ->shouldReceive('getData')->with('supplier_sku')->andReturn('1_notSkuButUniqId')->getMock(),
            $product2 = m::mock('Varien_Object')
                ->shouldReceive('getData')->with('sku')->andReturn('00SKZP/0NTGA')->getMock()
                ->shouldReceive('getData')->with('supplier_sku')->andReturn('2_notSkuButUniqId')->getMock()
        ];

        $productsMock = m::mock('Popov_Magmi_Test_Fake_ProductCollectionFake');
        $productsMock->shouldReceive('addAttributeToFilter')
            ->with('supplier_sku', array('in' => ['1_notSkuButUniqId', '2_notSkuButUniqId']))
            ->andReturn($simplesMock)
            ->getMock();

        //$productsMock = $this->getConfigurableProductsMock();

        $mageConfig->setResourceModelTestDouble('catalog/product_collection', $productsMock);

        $imageCsv = new Popov_Magmi_Test_Fake_CsvFake();
        $imageImport = new Popov_Magmi_Import_Image();

        $imageImport->setImageCsv($imageCsv);
        $imageImport->setRunMode(Popov_Magmi_Import_Image::RUN_MODE_DEBUG);
        $imageImport->setConfig([
            'import_products_with_sku_name_and_custom_attribute' => [
                'source_path' => dirname(__DIR__) . '/data/structure5',
                'type' => 'file', // 'type' => 'file'
                'product_type' => 'simple', // configurable
                'name' => [
                    'to_attribute' => 'supplier_sku', // filename to attribute name
                ],

                'images' => [
                    'image' => '{{var product.supplier_sku}}.{jpg,png}',
                    #'small_image',
                    #'thumbnail',
                    #'media_gallery' => '%sku%*.jpg',
                ],

                /*'options' => [
                    'encode' => [
                        'encode.product.sku',
                        'product.supplier_sku',
                    ]
                ],*/
            ],
        ]);
        $imageImport->run();

        $expected = [
            [
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKVS/0DANX',
                4 => '+/1_notSkuButUniqId.jpg',
                5 => '+/1_notSkuButUniqId.jpg',
                6 => '+/1_notSkuButUniqId.jpg',
                7 => '',
            ],
            [
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKZP/0NTGA',
                4 => '+/2_notSkuButUniqId.png',
                5 => '+/2_notSkuButUniqId.png',
                6 => '+/2_notSkuButUniqId.png',
                7 => '',
            ],
        ];

        $this->assertTrue($expected == $imageCsv->getData());
    }

    public function testStructureConfigurableWithSubSimples()
    {
        /** @var DigitalPianism_TestFramework_Model_Config $mageConfig */
        $mageConfig = Mage::getConfig();

        $productsMock = $this->getConfigurableProductsMock();
        $mageConfig->setResourceModelTestDouble('catalog/product_collection', $productsMock);

        $simpleProductMock = m::mock('Popov_Magmi_Test_Fake_SimpleProductFake') // Popov_Magmi_Test_Fake_SimpleProductFake
            ->shouldReceive('getResource')->andReturnSelf()->getMock()
            ->shouldReceive('getAttribute')->andReturnSelf()->getMock()
            ->shouldReceive('setStoreId')->with(Mage_Core_Model_App::ADMIN_STORE_ID)->getMock()
            ->shouldReceive('usesSource')->andReturn(true)->getMock()
            ->shouldReceive('getAttributeText')->andReturn('red', 'black', 'multi color')->getMock()
            ->shouldReceive('getData')->with('sku')->andReturn('SKU_PRODUCT_1', 'SKU_PRODUCT_2', 'SKU_PRODUCT_3')->getMock();

        $simpleProducts1Mock = [$simpleProductMock];
        $simpleProducts2Mock = [$simpleProductMock, $simpleProductMock];

        $catalogModelMock = m::mock('Popov_Magmi_Test_Fake_ConfigurableTypeProductFake') // Catalog_Model_Product_Type_Configurable
        ->shouldReceive('getUsedProducts')
            ->andReturn($simpleProducts1Mock, $simpleProducts2Mock) // return first on first call and second on second
            ->getMock();
        $mageConfig->setModelTestDouble('catalog/product_type_configurable', $catalogModelMock);

        $imageCsv = new Popov_Magmi_Test_Fake_CsvFake();
        $imageImport = new Popov_Magmi_Import_Image();

        $imageImport->setImageCsv($imageCsv);
        $imageImport->setRunMode(Popov_Magmi_Import_Image::RUN_MODE_DEBUG);
        $imageImport->setConfig([
            'configurable_import' => [
                'source_path' => dirname(__DIR__) . '/data/structure1',
                'product_type' => 'configurable', // simple
                'type' => 'dir', // 'type' => 'file'
                'name' => [
                    'to_attribute' => 'sku', // filename to attribute name
                ],
                'images' => [
                    'image' => 'main.jpg',
                    'small_image' => '1.jpg',
                    'thumbnail' => '1.jpg',
                    'media_gallery' => '*.jpg',
                ],
                'scan' => [
                    'type' => 'dir',
                    'product_type' => 'simple',
                    'name' => [
                        'to_attribute' => 'sku', // filename to attribute name
                    ],
                    'images' => [
                        'image' => 'main.jpg',
                        'small_image' => '1.jpg',
                        'thumbnail' => '1.jpg',
                        'media_gallery' => '*.jpg',
                    ],
                    'options' => [
                        'values' => [
                            'visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE, // set not visible for simple products
                        ],
                    ],
                ],
            ],
        ]);
        $imageImport->run();

        $expected = [
            [
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_1',
                4 => '+/00SKVS&Slash&0DANX/red/main.jpg',
                5 => '+/00SKVS&Slash&0DANX/red/1.jpg',
                6 => '+/00SKVS&Slash&0DANX/red/1.jpg',
                7 => '+/00SKVS&Slash&0DANX/red/1.jpg;+/00SKVS&Slash&0DANX/red/main.jpg',
            ],
            [
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKVS/0DANX',
                4 => '+/00SKVS&Slash&0DANX/main.jpg',
                5 => '+/00SKVS&Slash&0DANX/1.jpg',
                6 => '+/00SKVS&Slash&0DANX/1.jpg',
                7 => '+/00SKVS&Slash&0DANX/1.jpg;+/00SKVS&Slash&0DANX/main.jpg',
            ],
            [
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_2',
                4 => '+/00SKZP&Slash&0NTGA/black/main.jpg',
                5 => '+/00SKZP&Slash&0NTGA/black/1.jpg',
                6 => '+/00SKZP&Slash&0NTGA/black/1.jpg',
                7 => '+/00SKZP&Slash&0NTGA/black/1.jpg;+/00SKZP&Slash&0NTGA/black/2.jpg;+/00SKZP&Slash&0NTGA/black/main.jpg',
            ],
            [
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_3',
                4 => '+/00SKZP&Slash&0NTGA/multi color/main.jpg',
                5 => '+/00SKZP&Slash&0NTGA/multi color/1.jpg',
                6 => '+/00SKZP&Slash&0NTGA/multi color/1.jpg',
                7 => '+/00SKZP&Slash&0NTGA/multi color/1.jpg;+/00SKZP&Slash&0NTGA/multi color/2.jpg;+/00SKZP&Slash&0NTGA/multi color/3.jpg;+/00SKZP&Slash&0NTGA/multi color/main.jpg',
            ],
            [
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKZP/0NTGA',
                4 => '+/00SKZP&Slash&0NTGA/main.jpg',
                5 => '+/00SKZP&Slash&0NTGA/1.jpg',
                6 => '+/00SKZP&Slash&0NTGA/1.jpg',
                7 => '+/00SKZP&Slash&0NTGA/1.jpg;+/00SKZP&Slash&0NTGA/2.jpg;+/00SKZP&Slash&0NTGA/3.jpg;+/00SKZP&Slash&0NTGA/main.jpg',
            ],
        ];

        $this->assertTrue($expected == $imageCsv->getData());
    }

    /**
     * To run this test you mast create media/images/import with directories structure
     */
    public function estOldStructureBasedOnConfigurableAndSubSimpleProduct()
    {
        /** @var DigitalPianism_TestFramework_Model_Config $mageConfig */
        $mageConfig = Mage::getConfig();


        //$configurableProducts = Mage::getResourceModel('catalog/product_collection');
        $configurableProductsMock = m::mock('Popov_Magmi_Test_Fake_ProductCollectionFake');
        $configurableProductsMock->shouldReceive('addAttributeToFilter')
            ->with('sku', array('in' => ['00SKVS/0DANX', '00SKZP/0NTGA']))
            ->andReturn([
                $configurable1 = m::mock('Popov_Magmi_Test_Fake_ConfigurableProductFake')->shouldReceive('getSku')->andReturn('00SKVS/0DANX')->getMock(),
                $configurable2 = m::mock('Popov_Magmi_Test_Fake_ConfigurableProductFake')->shouldReceive('getSku')->andReturn('00SKZP/0NTGA')->getMock(),
            ])
            ->getMock();

        //$array = $configurableProductsMock->addAttributeToFilter('sku', array('in' => ['00SKVS&Slash&0DANX', '00SKZP&Slash&0NTGA']));

        $mageConfig->setResourceModelTestDouble('catalog/product_collection', $configurableProductsMock);

        $simpleProducts1Mock = [
            m::mock('Popov_Magmi_Test_Fake_SimpleProductFake') // Popov_Magmi_Test_Fake_SimpleProductFake
                ->shouldReceive('getResource')->andReturnSelf()->getMock()
                ->shouldReceive('getAttribute')->andReturnSelf()->getMock()
                ->shouldReceive('setStoreId')->with(Mage_Core_Model_App::ADMIN_STORE_ID)->getMock()
                ->shouldReceive('usesSource')->andReturn(true)->getMock()
                ->shouldReceive('getAttributeText')->andReturn('red')->getMock()
                ->shouldReceive('getSku')->andReturn('SKU_PRODUCT_1')->getMock(),
        ];

        $simpleProducts2Mock = [
            m::mock('Popov_Magmi_Test_Fake_SimpleProductFake')
                ->shouldReceive('getResource')->andReturnSelf()->getMock()
                ->shouldReceive('getAttribute')->andReturnSelf()->getMock()
                ->shouldReceive('setStoreId')->with(Mage_Core_Model_App::ADMIN_STORE_ID)->getMock()
                ->shouldReceive('usesSource')->andReturn(true)->getMock()
                ->shouldReceive('getAttributeText')->andReturn('black')->getMock()
                ->shouldReceive('getSku')->andReturn('SKU_PRODUCT_2')->getMock(),
            m::mock('Popov_Magmi_Test_Fake_SimpleProductFake')
                ->shouldReceive('getResource')->andReturnSelf()->getMock()
                ->shouldReceive('getAttribute')->andReturnSelf()->getMock()
                ->shouldReceive('setStoreId')->with(Mage_Core_Model_App::ADMIN_STORE_ID)->getMock()
                ->shouldReceive('usesSource')->andReturn(true)->getMock()
                ->shouldReceive('getAttributeText')->andReturn('multi color')->getMock()
                ->shouldReceive('getSku')->andReturn('SKU_PRODUCT_3')->getMock(),
        ];

        $catalogModelMock = m::mock('Popov_Magmi_Test_Fake_ConfigurableTypeProductFake') // Catalog_Model_Product_Type_Configurable
            ->shouldReceive('getUsedProducts')
            ->andReturn($simpleProducts1Mock, $simpleProducts2Mock) // return first on first call and second on second
            ->getMock();

        $mageConfig->setModelTestDouble('catalog/product_type_configurable', $catalogModelMock);

        $imageCsv = new Popov_Magmi_Test_Fake_CsvFake();

        $imageImport = new Popov_Magmi_Import_Image();
        $imageImport->setImageCsv($imageCsv);
        $imageImport->setRunMode(Popov_Magmi_Import_Image::RUN_MODE_DEBUG);
        $imageImport->run();

        $expected = [
            array(
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_1',
                4 => '+/00SKVS&Slash&0DANX/red/main.jpg',
                5 => '+/00SKVS&Slash&0DANX/red/main.jpg',
                6 => '+/00SKVS&Slash&0DANX/red/main.jpg',
                7 => '+/00SKVS&Slash&0DANX/red/1.jpg;+/00SKVS&Slash&0DANX/red/main.jpg',
            ),
            array(
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKVS/0DANX',
                4 => '+/00SKVS&Slash&0DANX/main.jpg',
                5 => '+/00SKVS&Slash&0DANX/main.jpg',
                6 => '+/00SKVS&Slash&0DANX/main.jpg',
                7 => '+/00SKVS&Slash&0DANX/1.jpg;+/00SKVS&Slash&0DANX/main.jpg',
            ),
            array(
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_2',
                4 => '+/00SKZP&Slash&0NTGA/black/main.jpg',
                5 => '+/00SKZP&Slash&0NTGA/black/main.jpg',
                6 => '+/00SKZP&Slash&0NTGA/black/main.jpg',
                7 => '+/00SKZP&Slash&0NTGA/black/1.jpg;+/00SKZP&Slash&0NTGA/black/2.jpg;+/00SKZP&Slash&0NTGA/black/main.jpg',
            ),
            array(
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_3',
                4 => '+/00SKZP&Slash&0NTGA/multi color/main.jpg',
                5 => '+/00SKZP&Slash&0NTGA/multi color/main.jpg',
                6 => '+/00SKZP&Slash&0NTGA/multi color/main.jpg',
                7 => '+/00SKZP&Slash&0NTGA/multi color/1.jpg;+/00SKZP&Slash&0NTGA/multi color/2.jpg;+/00SKZP&Slash&0NTGA/multi color/3.jpg;+/00SKZP&Slash&0NTGA/multi color/main.jpg',
            ),
            array(
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKZP/0NTGA',
                4 => '+/00SKZP&Slash&0NTGA/main.jpg',
                5 => '+/00SKZP&Slash&0NTGA/main.jpg',
                6 => '+/00SKZP&Slash&0NTGA/main.jpg',
                7 => '+/00SKZP&Slash&0NTGA/1.jpg;+/00SKZP&Slash&0NTGA/2.jpg;+/00SKZP&Slash&0NTGA/3.jpg;+/00SKZP&Slash&0NTGA/main.jpg',
            ),
        ];

        $this->assertEquals($expected, $imageCsv->getData());
    }

    protected function getConfigurableProductsMock()
    {
        $configurableMock = [
            $product1 = m::mock('Varien_Object')->shouldReceive('getData')->with('sku')->andReturn('00SKVS/0DANX')->getMock(),
            $product2 = m::mock('Varien_Object')->shouldReceive('getData')->with('sku')->andReturn('00SKZP/0NTGA')->getMock(),
        ];

        $productsMock = m::mock('Popov_Magmi_Test_Fake_ProductCollectionFake');
        $productsMock->shouldReceive('addAttributeToFilter')
            ->with('sku', array('in' => ['00SKVS/0DANX', '00SKZP/0NTGA']))
            ->andReturn($configurableMock)
            ->getMock();

        return $productsMock;
    }

    public static function configProvider()
    {
        return [
            [
                [
                    'import_products_with_sku_name' => [
                        'source_path' => dirname(__DIR__) . '/data/structure3',
                        'type' => 'file', // 'type' => 'file'
                        'product_type' => 'simple', // configurable
                        'name' => [
                            'to_attribute' => 'sku', // filename to attribute name
                        ],

                        'images' => [
                            'image' => '{{specChar encode=$product.sku}}.jpg',
                            #'small_image',
                            #'thumbnail',
                            #'media_gallery' => '%sku%*.jpg',
                        ],
                    ],
                ],
                [
                    [
                        0 => 'admin',
                        1 => 4,
                        2 => 1,
                        3 => '00SKVS/0DANX',
                        4 => '+/00SKVS&Slash&0DANX.jpg',
                        5 => '+/00SKVS&Slash&0DANX.jpg',
                        6 => '+/00SKVS&Slash&0DANX.jpg',
                        7 => '' // without media_gallery
                    ],
                    [
                        0 => 'admin',
                        1 => 4,
                        2 => 1,
                        3 => '00SKZP/0NTGA',
                        4 => '+/00SKZP&Slash&0NTGA.jpg',
                        5 => '+/00SKZP&Slash&0NTGA.jpg',
                        6 => '+/00SKZP&Slash&0NTGA.jpg',
                        7 => '' // without media_gallery
                    ],
                ]
            ],
            [
                [
                    'import_products_with_sku_folder' => [
                        'source_path' => dirname(__DIR__) . '/data/structure2',
                        'type' => 'dir', // 'type' => 'file'
                        'product_type' => 'simple', // configurable
                        'strategy' => 'simple', // 'strategy' => 'pattern'
                        'name' => [
                            'to_attribute' => 'sku', // filename to attribute name
                        ],
                        'images' => [
                            'image' => 'main.jpg',
                            'small_image' => '1.jpg',
                            'thumbnail' => '1.jpg',
                            'media_gallery' => '*.jpg',
                        ],
                    ],
                ],
                [
                    [
                        0 => 'admin',
                        1 => 4,
                        2 => 1,
                        3 => '00SKVS/0DANX',
                        4 => '+/00SKVS&Slash&0DANX/main.jpg',
                        5 => '+/00SKVS&Slash&0DANX/1.jpg',
                        6 => '+/00SKVS&Slash&0DANX/1.jpg',
                        7 => '+/00SKVS&Slash&0DANX/1.jpg;+/00SKVS&Slash&0DANX/main.jpg',
                    ],
                    [
                        0 => 'admin',
                        1 => 4,
                        2 => 1,
                        3 => '00SKZP/0NTGA',
                        4 => '+/00SKZP&Slash&0NTGA/main.jpg',
                        5 => '+/00SKZP&Slash&0NTGA/1.jpg',
                        6 => '+/00SKZP&Slash&0NTGA/1.jpg',
                        7 => '+/00SKZP&Slash&0NTGA/1.jpg;+/00SKZP&Slash&0NTGA/main.jpg',
                    ],
                ]
            ],
            [
                [
                    'import_products_with_sku_name_in_same_folder' => [
                        'source_path' => dirname(__DIR__) . '/data/structure4',
                        'type' => 'file', // 'type' => 'file'
                        'product_type' => 'simple', // configurable
                        'name' => [
                            'pattern' => '(?P<sku>.*)_[\d]+.jpg',
                            'to_attribute' => 'sku', // filename to attribute name
                        ],
                        'images' => [
                            'media_gallery' => '{{specChar encode=$product.sku}}_*.jpg',
                        ],
                    ],
                ],
                [
                    [
                        0 => 'admin',
                        1 => 4,
                        2 => 1,
                        3 => '00SKVS/0DANX',
                        4 => '+/00SKVS&Slash&0DANX_1.jpg',
                        5 => '+/00SKVS&Slash&0DANX_1.jpg',
                        6 => '+/00SKVS&Slash&0DANX_1.jpg',
                        7 => '+/00SKVS&Slash&0DANX_1.jpg;+/00SKVS&Slash&0DANX_2.jpg;+/00SKVS&Slash&0DANX_3.jpg',
                    ],
                    [
                        0 => 'admin',
                        1 => 4,
                        2 => 1,
                        3 => '00SKZP/0NTGA',
                        4 => '+/00SKZP&Slash&0NTGA_1.jpg',
                        5 => '+/00SKZP&Slash&0NTGA_1.jpg',
                        6 => '+/00SKZP&Slash&0NTGA_1.jpg',
                        7 => '+/00SKZP&Slash&0NTGA_1.jpg;+/00SKZP&Slash&0NTGA_2.jpg;+/00SKZP&Slash&0NTGA_3.jpg',
                    ],
                ]
            ],
        ];
    }
}