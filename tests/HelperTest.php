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
     * 相对路径测试
     */
    public function testRelativeLink()
    {
        // case
//        $val_1 = $this->helper->formatUrl('abs/cde/efg.html', 'https://www.beanbun.org');
//        $this->assertEquals($val_1, 'https://www.beanbun.org/abs/cde/efg.html');
        // case
        $val_1 = $this->helper->formatUrl('abs/cde/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/abs/cde/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('./efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('./ced/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('../efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('../ced/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('../abs/ced/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/ced/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('../../abs/ced/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/ced/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('../../abs/ced/../efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/ced/efg.html');
    }

    /**
     * 绝对路径测试
     */
    public function testAbsoluteLink()
    {
        // case
        $val_1 = $this->helper->formatUrl('/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('/abs/cde/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/abs/cde/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('//efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('//cde/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/cde/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('//cde/../efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/cde/efg.html');
    }

    /**
     * javascript:void(0)测试
     */
    public function testJavascriptVoid()
    {
        // case
        $val_1 = $this->helper->formatUrl('javascript:void(0);', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, false);
    }
}