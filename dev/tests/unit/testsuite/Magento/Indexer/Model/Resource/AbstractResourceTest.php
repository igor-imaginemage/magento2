<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Indexer\Model\Resource;

class AbstractResourceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Indexer\Model\Resource\AbstractResourceStub
     */
    protected $model;

    /**
     * @var \Magento\Framework\App\Resource|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $_resourceMock;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $_categoryFactoryMock;

    /**
     * @var \Magento\Catalog\Model\Resource\Category\CollectionFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $_categoryCollectionFactoryMock;

    /**
     * @var \Magento\Framework\Store\StoreManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $_storeManagerMock;

    /**
     * @var \Magento\Catalog\Model\Config|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $_catalogConfigMock;

    /**
     * @var \Magento\Framework\Event\ManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $_eventManagerMock;

    protected function setUp()
    {
        $this->_resourceMock = $this->getMockBuilder('Magento\Framework\App\Resource')
            ->disableOriginalConstructor()
            ->getMock();
        $this->_categoryFactoryMock = $this->getMockBuilder('Magento\Catalog\Model\CategoryFactory')
            ->disableOriginalConstructor()
            ->getMock();
        $this->_categoryCollectionFactoryMock = $this->getMockBuilder(
            'Magento\Catalog\Model\Resource\Category\CollectionFactory'
        )->disableOriginalConstructor()->getMock();
        $this->_storeManagerMock = $this->getMock('Magento\Framework\Store\StoreManagerInterface');
        $this->_catalogConfigMock = $this->getMockBuilder('Magento\Catalog\Model\Config')
            ->disableOriginalConstructor()
            ->getMock();
        $this->_eventManagerMock = $this->getMock('Magento\Framework\Event\ManagerInterface');

        $this->model = new \Magento\Indexer\Model\Resource\AbstractResourceStub(
            $this->_resourceMock,
            $this->_categoryFactoryMock,
            $this->_categoryCollectionFactoryMock,
            $this->_storeManagerMock,
            $this->_catalogConfigMock,
            $this->_eventManagerMock
        );
    }

    public function testReindexAll()
    {
        $this->model->reindexAll();
        $this->assertEquals('test_idx', $this->model->getIdxTable('test'));
    }

    public function testUseIdxTable()
    {
        $this->model->useIdxTable(true);
        $this->assertEquals('test_idx', $this->model->getIdxTable('test'));
        $this->model->useIdxTable(false);
        $this->assertEquals('test_tmp', $this->model->getIdxTable('test'));
    }

    public function testClearTemporaryIndexTable()
    {
        $connectionMock = $this->getMock('Magento\Framework\DB\Adapter\AdapterInterface', [], [], '', false);
        $storeMock = $this->getMock('Magento\Store\Model\Store', [], [], '', false);
        $this->_resourceMock->expects($this->any())->method('getConnection')->will($this->returnValue($connectionMock));
        $this->_storeManagerMock->expects($this->any())->method('getStore')->will($this->returnValue($storeMock));
        $connectionMock->expects($this->once())->method('delete')->will($this->returnSelf());
        $this->model->clearTemporaryIndexTable();
    }

    public function testSyncData()
    {
        $resultTable = 'catalog_category_flat';
        $resultColumns = [0 => 'column'];
        $describeTable = ['column' => 'column'];

        $selectMock = $this->getMock('Magento\Framework\DB\Select', [], [], '', false);
        $connectionMock = $this->getMock('Magento\Framework\DB\Adapter\AdapterInterface', [], [], '', false);
        $storeMock = $this->getMock('Magento\Store\Model\Store', [], [], '', false);

        $connectionMock->expects($this->any())->method('describeTable')->will($this->returnValue($describeTable));
        $connectionMock->expects($this->any())->method('select')->will($this->returnValue($selectMock));
        $selectMock->expects($this->any())->method('from')->will($this->returnSelf());

        $selectMock->expects($this->once())->method('insertFromSelect')->with(
            $resultTable,
            $resultColumns
        )->will($this->returnSelf());

        $this->_storeManagerMock->expects($this->any())->method('getStore')->will($this->returnValue($storeMock));
        $this->_resourceMock->expects($this->any())->method('getConnection')->will($this->returnValue($connectionMock));
        $this->_resourceMock->expects($this->any())->method('getTableName')->will($this->returnArgument(0));

        $this->assertInstanceOf(
            'Magento\Indexer\Model\Resource\AbstractResourceStub',
            $this->model->syncData()
        );
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage array_keys() expects parameter 1 to be array, null given
     */
    public function testSyncDataException()
    {
        $connectionMock = $this->getMock('Magento\Framework\DB\Adapter\AdapterInterface', [], [], '', false);
        $this->_resourceMock->expects($this->any())->method('getConnection')->will($this->returnValue($connectionMock));
        $this->_resourceMock->expects($this->any())->method('getTableName')->will($this->returnArgument(0));
        $this->model->syncData();
    }

    /**
     * @param bool $readToIndex
     * @dataProvider insertFromTableData
     */
    public function testInsertFromTable($readToIndex)
    {
        $sourceTable = 'catalog_category_flat';
        $destTable = 'catalog_category_flat';
        $resultColumns = [0 => 'column'];
        $tableColumns = ['column' => 'column'];

        $selectMock = $this->getMock('Magento\Framework\DB\Select', [], [], '', false);
        $connectionMock = $this->getMock('Magento\Framework\DB\Adapter\AdapterInterface', [], [], '', false);
        $storeMock = $this->getMock('Magento\Store\Model\Store', [], [], '', false);

        $connectionMock->expects($this->any())->method('describeTable')->will($this->returnValue($tableColumns));
        $connectionMock->expects($this->any())->method('select')->will($this->returnValue($selectMock));
        $selectMock->expects($this->any())->method('from')->will($this->returnSelf());

        $this->_storeManagerMock->expects($this->any())->method('getStore')->will($this->returnValue($storeMock));
        if ($readToIndex) {
            $connectionCustomMock = $this->getMock(
                'Magento\Framework\DB\Adapter\CustomAdapterInterface',
                ['describeTable', 'query', 'select', 'insertArray'],
                [],
                '',
                false
            );
            $pdoMock = $this->getMock('Zend_Db_Statement_Pdo', [], [], '', false);
            $connectionCustomMock->expects($this->any())->method('query')->will($this->returnValue($selectMock));
            $connectionCustomMock->expects($this->any())->method('select')->will($this->returnValue($selectMock));
            $connectionCustomMock->expects($this->any())->method('describeTable')->will(
                $this->returnValue($tableColumns)
            );
            $connectionCustomMock->expects($this->exactly(1))->method('insertArray')->with(
                $destTable,
                $resultColumns
            )->will($this->returnValue(1));
            $connectionMock->expects($this->any())->method('query')->will($this->returnValue($pdoMock));
            $pdoMock->expects($this->at(0))->method('fetch')->will($this->returnValue([$tableColumns]));
            $pdoMock->expects($this->at(1))->method('fetch')->will($this->returnValue([$tableColumns]));

            $this->model->newIndexAdapter();
            $this->_resourceMock->expects($this->at(0))->method('getConnection')->with('core_write')->will(
                $this->returnValue($connectionMock)
            );
            $this->_resourceMock->expects($this->at(1))->method('getConnection')->with('core_new_write')->will(
                $this->returnValue($connectionCustomMock)
            );
        } else {
            $selectMock->expects($this->once())->method('insertFromSelect')->with(
                $destTable,
                $resultColumns
            )->will($this->returnSelf());

            $this->_resourceMock->expects($this->any())->method('getTableName')->will($this->returnArgument(0));
            $this->_resourceMock->expects($this->any())->method('getConnection')->with('core_write')->will(
                $this->returnValue($connectionMock)
            );
        }
        $this->assertInstanceOf(
            'Magento\Indexer\Model\Resource\AbstractResourceStub',
            $this->model->insertFromTable($sourceTable, $destTable, $readToIndex)
        );
    }

    public function insertFromTableData()
    {
        return [[false], [true]];
    }
}
