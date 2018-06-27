<?php
namespace Beanbun\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Helper类方法测试用例
 * @package Beanbun\Tests
 */
class FormatUrlTest extends TestCase
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
     * 完整路径测试
     */
    public function testFullLink()
    {
        // case
        $val = $this->helper->formatUrl('http://www.beanbun.org/', 'https://www.baidu.com/');
        $this->assertEquals($val, 'http://www.beanbun.org/');

        // case
        $val = $this->helper->formatUrl('https://www.beanbun.org/', 'http://baidu.com/');
        $this->assertEquals($val, 'https://www.beanbun.org/');

        // case
        $val = $this->helper->formatUrl('htps://www.beanbun.org/', 'http://baidu.com/');
        $this->assertEquals($val, 'htps://www.beanbun.org/');
    }

    /**
     * 相对路径测试
     */
    public function testRelativeLink()
    {
        // case
        $val_1 = $this->helper->formatUrl('abs/cde/efg.html', 'https://www.beanbun.org');
        $this->assertEquals($val_1, 'https://www.beanbun.org/abs/cde/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('abs/cde/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/abs/cde/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('abs/cde/efg.html', 'https://www.beanbun.org/ok');
        $this->assertEquals($val_1, 'https://www.beanbun.org/ok/abs/cde/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('./efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('./ced/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/ced/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('../efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('../ced/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/ced/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('../abs/ced/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/abs/ced/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('../../abs/ced/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/abs/ced/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('../../abs/ced/../efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/abs/efg.html');
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
        $val_1 = $this->helper->formatUrl('/abs/cde/../../efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('//efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('//cde/efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/cde/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('//cde/../efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/efg.html');

        // case
        $val_1 = $this->helper->formatUrl('//abs/cde/../efg.html', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'https://www.beanbun.org/abs/efg.html');
    }

    /**
     * 路由参数测试
     */
    public function testRouteParams()
    {
        $val = $this->helper->formatUrl('/efg.html#user/a', 'http://www.beanbun.org/');
        $this->assertEquals($val, 'http://www.beanbun.org/efg.html#user/a');

        $val = $this->helper->formatUrl('/efg.html#user/../a', 'http://www.beanbun.org/');
        $this->assertEquals($val, 'http://www.beanbun.org/efg.html#user/../a');

        $val = $this->helper->formatUrl('./abs/efg.html#user/../a', 'http://www.beanbun.org/');
        $this->assertEquals($val, 'http://www.beanbun.org/abs/efg.html#user/../a');

        $val = $this->helper->formatUrl('./abs/efg.html#user/../a', 'http://www.beanbun.org/are/you/ok');
        $this->assertEquals($val, 'http://www.beanbun.org/are/you/ok/abs/efg.html#user/../a');

        $val = $this->helper->formatUrl('/abs/efg?page=1&limit=20#user/a', 'http://www.beanbun.org/are/you/ok');
        $this->assertEquals($val, 'http://www.beanbun.org/abs/efg?page=1&limit=20#user/a');
    }

    /**
     * javascript:void(0)测试
     */
    public function testJavascriptVoid()
    {
        // case
        $val_1 = $this->helper->formatUrl('javascript:void(0);', 'https://www.beanbun.org/');
        $this->assertEquals($val_1, 'javascript:void(0);');
    }
}
