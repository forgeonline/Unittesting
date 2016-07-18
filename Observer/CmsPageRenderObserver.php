<?php
/**
 * Copyright © 2016 Forgeonline. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Forgeonline\Unittesting\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CmsPageRenderObserver implements ObserverInterface
{
	protected $logger;
	protected $scopeConfig;
	
    public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Psr\Log\LoggerInterface $logger //log injection
    ) {
        $this->logger = $logger;
		$this->scopeConfig = $scopeConfig;
    }
	

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
		$this->logger->debug( $this->getText() );
	}
	

	public function getText(){
		return "Controller action";
	}
}
