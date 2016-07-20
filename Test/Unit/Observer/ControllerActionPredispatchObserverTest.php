<?php
/**
 * Copyright © 2016 Forgeonline. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Forgeonline\Unittesting\Test\Unit\Observer;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class ControllerActionPredispatchObserverTest extends \PHPUnit_Framework_TestCase
{
	/** @var \Magento\Framework\App\RequestInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $request;
	
    /**
     * @var CmsPageRenderObserver
     */
    protected $model;

    protected function setUp()
    {
		$helper = new ObjectManager($this);
		$this->request = $this->getMockBuilder('Magento\Framework\App\RequestInterface')
		->disableOriginalConstructor()->getMock();
		
		$this->model = $helper->getObject(
			'Forgeonline\Unittesting\Observer\ControllerActionPredispatchObserver',
			[
				'request' => $this->request
			]
        );
    }
	
	public function testGetTextTo(){
		$this->model->getText();
	}
	
	public function testMainSpace(){
		echo "A";
	}
}
