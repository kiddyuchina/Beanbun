<?php
namespace Beanbun\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Helper类方法测试用例
 * @package Beanbun\Tests
 */
class HelperTest extends TestCase
{
    /**
     * @var \Beanbun\Lib\Helper;
     */
    private $helper;

    public function setUp()/* The :void return type declaration that should be here would cause a BC issue */
    {
        $this->helper = new \Beanbun\Lib\Helper();
    }

    public function tearDown()/* The :void return type declaration that should be here would cause a BC issue */
    {
        unset($this->helper);
    }

    /**
     * return array
     */
    public function testAbsoluteLink()
    {

    }

    /**
     * @depends testExampleLinksData
     * @param $links
     */
    public function testFormatUrl($links)
    {

    }
}