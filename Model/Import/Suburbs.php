<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Forgeonline\Unittesting\Model\Import;

use Forgeonline\Unittesting\Api\Data\SuburbsInterface;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\Framework\App\ResourceConnection;


/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Suburbs extends \Magento\ImportExport\Model\Import\Entity\AbstractEntity
{
    /**#@+
     * Permanent column names
     */
    const COLUMN_NAME = 'suburb_name';

    const COLUMN_CODE = 'suburb_code';

    const COLUMN_STATE = 'suburb_state';

    const COLUMN_IS_ACTIVE = 'is_active';


    /**#@-*/

    /**#@+
     * Error codes
     */
    const ERROR_NAME_IS_EMPTY = 'suburbNameIsEmpty';

    const ERROR_CODE_IS_EMPTY = 'suburbCodeIsEmpty';
    
    const ERROR_STATE_IS_EMPTY = 'suburbStateIsEmpty';
    
    const ERROR_IS_ACTIVE_IS_EMPTY = 'isActiveIsEmpty';

    const ERROR_VALUE_IS_REQUIRED = 'valueIsRequired';

    const ERROR_SUBURB_NOT_FOUND = 'suburbNameNotFound';

    const ERROR_INVALID_CODE = 'invalidSuburbCode';
    
    const ERROR_INVALID_DATA = 'invalidData';
    /**#@+
     * Keys which used to build result data array for future update
     */
    const ERROR_DUPLICATE_SUBURB_CODE = 'duplicateSuburbCode';
    
    const ERROR_ROW_IS_ORPHAN = 'rowIsOrphan';

    const ENTITIES_TO_CREATE_KEY = 'entities_to_create';

    const ENTITIES_TO_UPDATE_KEY = 'entities_to_update';

    const ATTRIBUTES_TO_SAVE_KEY = 'attributes_to_save';
    
    /**#@+
     * Component entity names
     */
    const COMPONENT_ENTITY_SUBURBS = 'suburbs';
    
    const TABLE_SUBURBS = 'cabby_suburbs';
    
    /**
     * Suburb information from import file
     *
     * @var array
     */
    protected $_newSuburbs = [];

    /**
     * Suburb entity DB table name.
     *
     * @var string
     */
    protected $_entityTable;

    /**
     * Subrub model
     *
     * @var \Forgeonline\Unittesting\Model\Suburbs
     */
    protected $_suburbsModel;

    /**
     * Id of next suburb entity row
     *
     * @var int
     */
    protected $_nextEntityId;

    /**
     * @var \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory
     */
    protected $_resourceFactory;
    
    
    /**
     * @var \Magento\ImportExport\Model\ResourceModel\Helper
     */
    protected $_resourceHelper;

    /**
     * Suburb fields in file
     */
    protected $validColumnNames = [
        SuburbsInterface::ID,
        SuburbsInterface::NAME,
        SuburbsInterface::CODE,
        SuburbsInterface::STATE,
        SuburbsInterface::ISACTIVE
    ];
    
    
    protected $logger;
    
    /**
     * DB data source models
     *
     * @var \Magento\ImportExport\Model\ResourceModel\Import\Data[]
     */
    protected $_dataSourceModels;

    /**
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\ImportExport\Model\ImportFactory $importFactory
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\ImportExport\Model\Export\Factory $collectionFactory
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Stdlib\StringUtils $string,
        ProcessingErrorAggregatorInterface $errorAggregator,
        \Magento\ImportExport\Model\ImportFactory $importFactory,
        \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory,
        \Forgeonline\Unittesting\Model\ResourceModel\Suburbs $dataSourceModel,
        \Psr\Log\LoggerInterface $logger, //log injection
        array $data = []
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_resourceFactory = $resourceFactory;
        $this->_dataSourceModel = $importData;
        $this->errorAggregator = $errorAggregator;
        $this->_connection = $resource->getConnection('write');
        $this->logger = $logger;
        foreach (array_merge($this->errorMessageTemplates, $this->_messageTemplates) as $errorCode => $message) {
            $this->getErrorAggregator()->addErrorMessageTemplate($errorCode, $message);
        }
    }
    
    /**
     * EAV entity type code getter
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return 'cabby_suburbs';
    }
    
    /**
     * Validate data row
     *
     * @param array $rowData
     * @param int $rowNumber
     * @return bool
     */
    public function validateRow(array $rowData, $rowNumber)
    {
        if (isset($this->_validatedRows[$rowNumber])) {
            // check that row is already validated
            return !$this->getErrorAggregator()->isRowInvalid($rowNumber);
        }
        $this->_validatedRows[$rowNumber] = true;
        if ($this->getBehavior($rowData) == \Magento\ImportExport\Model\Import::BEHAVIOR_APPEND) {
            $this->_validateRowForUpdate($rowData, $rowNumber);
        } elseif ($this->getBehavior($rowData) == \Magento\ImportExport\Model\Import::BEHAVIOR_DELETE) {
            $this->_validateRowForDelete($rowData, $rowNumber);
        }
        $this->logger->debug($this->getBehavior($rowData));
        return !$this->getErrorAggregator()->isRowInvalid($rowNumber);
    }
    
    /**
     * Delete list of Suburbs
     *
     * @param array $entitiesToDelete Suburbs id list
     * @return $this
     */
    protected function _deleteSuburbsEntities(array $entitiesToDelete)
    {
        $condition = $this->_connection->quoteInto('entity_id IN (?)', $entitiesToDelete);
        $this->_connection->delete($this->_entityTable, $condition);

        return $this;
    }


    /**
     * Import data rows
     *
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _importData()
    {
        if (\Magento\ImportExport\Model\Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            $this->deleteAdvancedPricing();
        } elseif (\Magento\ImportExport\Model\Import::BEHAVIOR_REPLACE == $this->getBehavior()) {
            $this->replaceAdvancedPricing();
        } elseif (\Magento\ImportExport\Model\Import::BEHAVIOR_APPEND == $this->getBehavior()) {
            $this->saveSuburbs();
        }
    }


    /**
     * Save advanced pricing
     *
     * @return $this
     */
    public function saveSuburbs()
    {
        $this->saveSuburbsData();
        return $this;
    }
    
    /**
    * Save and replace advanced prices
    *
    * @return $this
    * @SuppressWarnings(PHPMD.CyclomaticComplexity)
    * @SuppressWarnings(PHPMD.NPathComplexity)
    */
    public function saveSuburbsData(){
        $behavior = $this->getBehavior();
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    $this->addRowError(ERROR_INVALID_DATA, $rowNum);
                    continue;
                }
                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                }
                if (\Magento\ImportExport\Model\Import::BEHAVIOR_REPLACE == $behavior) {

                } elseif (\Magento\ImportExport\Model\Import::BEHAVIOR_APPEND == $behavior) {
                    $this->saveSuburb($rowData);
                }
            }
        }
    }
    
    
    public function saveSuburb($rowData){
        if( $rowData ) {
            $tableName = $this->_resourceFactory->create()->getTable( self::TABLE_SUBURBS );
            $this->_connection->insertOnDuplicate($tableName, $rowData);
        }
    }
    /**
     * Validate row data for add/update behaviour
     *
     * @param array $rowData
     * @param int $rowNumber
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _validateRowForUpdate(array $rowData, $rowNumber)
    {
        if ($this->_checkUniqueKey($rowData, $rowNumber)) {
            $code = $rowData[self::COLUMN_CODE];
            $name = ucwords($rowData[self::COLUMN_NAME]);
            $this->_newSuburbs[$code][$name] = false;
        }
    }

    /**
     * Validate row data for delete behaviour
     *
     * @param array $rowData
     * @param int $rowNumber
     * @return void
     */
    protected function _validateRowForDelete(array $rowData, $rowNumber)
    {
        if ($this->_checkUniqueKey($rowData, $rowNumber)) {
            if (!$this->_getSuburbId($rowData[self::COLUMN_CODE], $rowData[self::COLUMN_NAME])) {
                $this->addRowError(self::ERROR_SUBURB_NOT_FOUND, $rowNumber);
            }
        }
    }


    /**
     * @inheritDoc
     */
    public function getValidColumnNames()
    {
        $this->validColumnNames = array_merge(
            $this->validColumnNames,
            $this->suburbFields
        );

        return $this->validColumnNames;
    }
    

    /**
     * General check of unique key
     *
     * @param array $rowData
     * @param int $rowNumber
     * @return bool
     */
    protected function _checkUniqueKey(array $rowData, $rowNumber)
    {
        if (empty($rowData[static::COLUMN_CODE])) {
            $this->addRowError(static::ERROR_CODE_IS_EMPTY, $rowNumber, static::COLUMN_CODE);
        } elseif (empty($rowData[static::COLUMN_NAME])) {
            $this->addRowError(static::ERROR_NAME_IS_EMPTY, $rowNumber, static::COLUMN_NAME);
        } else {
            $code = strtolower($rowData[static::COLUMN_CODE]);
            $name = $rowData[static::COLUMN_NAME];
        }
        return !$this->getErrorAggregator()->isRowInvalid($rowNumber);
    }
}
