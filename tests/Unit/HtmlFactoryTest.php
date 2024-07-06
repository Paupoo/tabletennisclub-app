<?php

namespace Tests\Unit;

use App\Classes\HtmlFactory;
use PHPUnit\Framework\TestCase;

class HtmlFactoryTest extends TestCase
{
    /**
     * check <option> tag maker.
     */

    public function test_that_one_parameter_returns_same_value_and_text():void
    {
        $this->assertEquals('<option value="test">test</option>' . PHP_EOL, HtmlFactory::wrapIntoOptionTag('test'));
    }

    public function test_that_first_parameter_is_value_and_second_parameter_is_text(): void
    {
        $this->assertEquals('<option value="test1">test2</option>' . PHP_EOL, HtmlFactory::wrapIntoOptionTag('test1', 'test2'));
    }
}
