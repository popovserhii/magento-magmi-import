<?php

/**
 * Image Import Test
 *
 * @category Popov
 * @package Popov_Magmi
 * @author Popov Sergiy <popov@agere.com.ua>
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

    public function testSimplestStructureOneImagePerProduct()
    {
        /** @var DigitalPianism_TestFramework_Model_Config $mageConfig */
        $mageConfig = Mage::getConfig();


        //$configurableProducts = Mage::getResourceModel('catalog/product_collection');
        $configurableProductsMock = m::mock('Popov_Magmi_Test_Fake_ProductCollectionFake');
        $configurableProductsMock->shouldReceive('addAttributeToFilter')
            ->with('sku', array('in' => ['00SKZP/0NTGA', '00SMKX/0AAOI']))
            ->andReturn([
                $configurable1 = m::mock('Popov_Magmi_Test_Fake_ConfigurableProductFake')->shouldReceive('getSku')->andReturn('00SKZP/0NTGA')->getMock(),
                $configurable2 = m::mock('Popov_Magmi_Test_Fake_ConfigurableProductFake')->shouldReceive('getSku')->andReturn('00SMKX/0AAOI')->getMock(),
            ])
            ->getMock();
        $mageConfig->setResourceModelTestDouble('catalog/product_collection', $configurableProductsMock);


        $imageCsv = new Popov_Magmi_Test_Fake_CsvFake();
        $imageImport = new Popov_Magmi_Import_Image();

        $imageImport->setImageCsv($imageCsv);
        $imageImport->setRunMode(Popov_Magmi_Import_Image::RUN_MODE_DEBUG);
        $imageImport->setConfig([
            'configurable_import' => [
                'scan' => [
                    //$this->imagesDir = new \SplFileInfo(Mage::getBaseDir('media') . '/import/images');
                    //$this->importFile = new \SplFileInfo(Mage::getBaseDir('var') . '/import/import-image.csv');

                    'source_path' => dirname(__DIR__) . '/data/structure3',
                    //'import_file' => dirname(__DIR__) . '/data/structure3',

                    'type' => 'file', // 'type' => 'file'
                    'product_type' => 'simple', // configurable
                    // If type "dir" than images put in this directory
                    // if type "file" than images should look in current directory
                    'strategy' => 'simple', // 'strategy' => 'pattern'
                    'name_to_attribute' => 'sku', // filename to attribute name
                    'images' => [
                        'image' => '%sku%.jpg',
                        #'small_image',
                        #'thumbnail',
                        //'media_gallery' => '%sku%*.jpg',
                    ]
                ],
            ],
        ]);
        $imageImport->run();

        $expected = [
            [
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKZP/0NTGA',
                4 => '+/00SKZP&Slash&0NTGA.jpg',
                5 => '+/00SKZP&Slash&0NTGA.jpg',
                6 => '+/00SKZP&Slash&0NTGA.jpg',
                7 => [] // without media_gallery
            ],
            [
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SMKX/0AAOI',
                4 => '+/00SMKX&Slash&0AAOI.jpg',
                5 => '+/00SMKX&Slash&0AAOI.jpg',
                6 => '+/00SMKX&Slash&0AAOI.jpg',
                7 => [] // without media_gallery
            ],
        ];

        $this->assertTrue($expected == $imageCsv->getData());
    }

    public function estStructureConfigurableWithSubSimples()
    {
        $imageCsv = new Popov_Magmi_Test_Fake_CsvFake();
        $imageImport = new Popov_Magmi_Import_Image();

        $imageImport->setImageCsv($imageCsv);
        $imageImport->setRunMode(Popov_Magmi_Import_Image::RUN_MODE_DEBUG);
        $imageImport->setConfig([
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
        ]);
        $imageImport->run();

        $expected = [
            [
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_1',
                4 => '+/images/00SKVS&Slash&0DANX/red/1.jpg',
                5 => '+/images/00SKVS&Slash&0DANX/red/main.jpg',
                6 => '+/images/00SKVS&Slash&0DANX/red/1.jpg;+/images/00SKVS&Slash&0DANX/red/main.jpg',
            ],
            [
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKVS/0DANX',
                4 => '+/images/00SKVS&Slash&0DANX/1.jpg',
                6 => '+/images/00SKVS&Slash&0DANX/main.jpg',
                7 => '+/images/00SKVS&Slash&0DANX/1.jpg;+/images/00SKVS&Slash&0DANX/main.jpg',
            ],
            [
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_2',
                6 => '+/images/00SKZP&Slash&0NTGA/black/1.jpg',
                4 => '+/images/00SKZP&Slash&0NTGA/black/main.jpg',
                7 => '+/images/00SKZP&Slash&0NTGA/black/1.jpg;+/images/00SKZP&Slash&0NTGA/black/2.jpg;+/images/00SKZP&Slash&0NTGA/black/main.jpg',
            ],
            [
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_3',
                5 => '+/images/00SKZP&Slash&0NTGA/multi color/1.jpg',
                6 => '+/images/00SKZP&Slash&0NTGA/multi color/main.jpg',
                7 => '+/images/00SKZP&Slash&0NTGA/multi color/1.jpg;+/images/00SKZP&Slash&0NTGA/multi color/2.jpg;+/images/00SKZP&Slash&0NTGA/multi color/3.jpg;+/images/00SKZP&Slash&0NTGA/multi color/main.jpg',
            ],
            [
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKZP/0NTGA',
                4 => '+/images/00SKZP&Slash&0NTGA/1.jpg',
                5 => '+/images/00SKZP&Slash&0NTGA/main.jpg',
                7 => '+/images/00SKZP&Slash&0NTGA/1.jpg;+/images/00SKZP&Slash&0NTGA/2.jpg;+/images/00SKZP&Slash&0NTGA/3.jpg;+/images/00SKZP&Slash&0NTGA/main.jpg',
            ],
        ];

        $this->assertEquals($expected, $imageCsv->getData());
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
                4 => '+/images/00SKVS&Slash&0DANX/red/main.jpg',
                5 => '+/images/00SKVS&Slash&0DANX/red/main.jpg',
                6 => '+/images/00SKVS&Slash&0DANX/red/main.jpg',
                7 => '+/images/00SKVS&Slash&0DANX/red/1.jpg;+/images/00SKVS&Slash&0DANX/red/main.jpg',
            ),
            array(
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKVS/0DANX',
                4 => '+/images/00SKVS&Slash&0DANX/main.jpg',
                5 => '+/images/00SKVS&Slash&0DANX/main.jpg',
                6 => '+/images/00SKVS&Slash&0DANX/main.jpg',
                7 => '+/images/00SKVS&Slash&0DANX/1.jpg;+/images/00SKVS&Slash&0DANX/main.jpg',
            ),
            array(
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_2',
                4 => '+/images/00SKZP&Slash&0NTGA/black/main.jpg',
                5 => '+/images/00SKZP&Slash&0NTGA/black/main.jpg',
                6 => '+/images/00SKZP&Slash&0NTGA/black/main.jpg',
                7 => '+/images/00SKZP&Slash&0NTGA/black/1.jpg;+/images/00SKZP&Slash&0NTGA/black/2.jpg;+/images/00SKZP&Slash&0NTGA/black/main.jpg',
            ),
            array(
                0 => 'admin',
                1 => 1,
                2 => 1,
                3 => 'SKU_PRODUCT_3',
                4 => '+/images/00SKZP&Slash&0NTGA/multi color/main.jpg',
                5 => '+/images/00SKZP&Slash&0NTGA/multi color/main.jpg',
                6 => '+/images/00SKZP&Slash&0NTGA/multi color/main.jpg',
                7 => '+/images/00SKZP&Slash&0NTGA/multi color/1.jpg;+/images/00SKZP&Slash&0NTGA/multi color/2.jpg;+/images/00SKZP&Slash&0NTGA/multi color/3.jpg;+/images/00SKZP&Slash&0NTGA/multi color/main.jpg',
            ),
            array(
                0 => 'admin',
                1 => 4,
                2 => 1,
                3 => '00SKZP/0NTGA',
                4 => '+/images/00SKZP&Slash&0NTGA/main.jpg',
                5 => '+/images/00SKZP&Slash&0NTGA/main.jpg',
                6 => '+/images/00SKZP&Slash&0NTGA/main.jpg',
                7 => '+/images/00SKZP&Slash&0NTGA/1.jpg;+/images/00SKZP&Slash&0NTGA/2.jpg;+/images/00SKZP&Slash&0NTGA/3.jpg;+/images/00SKZP&Slash&0NTGA/main.jpg',
            ),
        ];

        $this->assertEquals($expected, $imageCsv->getData());
    }
}