<?php
/**
 *
 * Copyright © 2016 Stepzero.Solutions. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Forgeonline\Unittesting\Controller\Adminhtml\Suburbs;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\Export\ConvertToCsv;
use Magento\Framework\App\Response\Http\FileFactory;

class MassExport extends \Magento\Backend\App\Action
{
	 /**
     * Massactions filter
     *
     * @var Filter
     */
    protected $filter;
	
    /**
     * @var MetadataProvider
     */
    protected $metadataProvider;

    /**
     * @var WriteInterface
     */
    protected $directory;

    /**
     * @var ConvertToCsv
     */
    protected $converter;

    /**
     * @var FileFactory
     */
    protected $fileFactory;

   /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
	Context $context,
	Filter $filter, 
	Filesystem $filesystem,
    ConvertToCsv $converter,
    FileFactory $fileFactory,
	\Magento\Ui\Model\Export\MetadataProvider $metadataProvider,
	\Forgeonline\Unittesting\Model\ResourceModel\Suburbs $resource	)
    {
		$this->resources = $resource;
        $this->filter = $filter;
		$this->_connection = $this->resources->getConnection();
		$this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
		$this->metadataProvider = $metadataProvider;
        $this->converter = $converter;
        $this->fileFactory = $fileFactory;
        parent::__construct($context);
    }

    /**
     * Export selected data.
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $component = $this->filter->getComponent();
        $this->filter->prepareComponent($component);
        $dataProvider = $component->getContext()->getDataProvider();
        $dataProvider->setLimit(0, false);
        $ids = [];
        foreach ($dataProvider->getSearchResult()->getItems() as $document) {
            $ids[] = (int)$document->getId();
        }//die(var_dump( $ids ));
		$searchResult = $component->getContext()->getDataProvider()->getSearchResult();
		$fields = $this->metadataProvider->getFields($component);
		$options = $this->metadataProvider->getOptions();

        $name = md5(microtime());
        $file = 'export/'. $component->getName() . $name . '.csv';
		$this->directory->create('export');
        $stream = $this->directory->openFile($file, 'w+');
        $stream->lock();
        $stream->writeCsv($this->metadataProvider->getHeaders($component));
        foreach ($searchResult->getItems() as $document) {
			if( in_array( $document->getId(), $ids ) ) {
    	        $this->metadataProvider->convertDate($document, $component->getName());
	            $stream->writeCsv($this->metadataProvider->getRowData($document, $fields, $options));
			}
        }
        $stream->unlock();
        $stream->close();
        return $this->fileFactory->create('export.csv', [
            'type' => 'filename',
            'value' => $file,
            'rm' => true  // can delete file after use
        ], 'var');
    }
}
