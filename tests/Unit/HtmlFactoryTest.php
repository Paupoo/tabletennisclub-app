<?php

namespace Tests\Unit;

use App\Classes\HtmlFactory;
use PHPUnit\Framework\TestCase;

class HtmlFactoryTest extends TestCase
{
    /**
     * check <option> tag maker.
     */
    public function test_generic_builder_from_array_to_options_tags(): void
    {
        $associative_array = [
            '1' => 'apples',
            '2' => 'grapes',
            '3' => 'bananas',
        ];

        $list = [
            'apples',
            'grapes',
            'bananas',
        ];

        $this->assertEquals('<option value="1">apples</option>'. PHP_EOL . '<option value="2">grapes</option>'. PHP_EOL . '<option value="3">bananas</option>'. PHP_EOL, HtmlFactory::arrayInHtmlList($associative_array));
        $this->assertEquals('<option value="apples">apples</option>'. PHP_EOL . '<option value="grapes">grapes</option>'. PHP_EOL . '<option value="bananas">bananas</option>'. PHP_EOL, HtmlFactory::arrayInHtmlList($list));
    }

    public function test_one_parameter_returns_same_value_and_text():void
    {
        $this->assertEquals('<option value="test">test</option>' . PHP_EOL, HtmlFactory::wrapIntoOptionTag('test'));
    }

    public function test_first_parameter_is_value_and_second_parameter_is_text(): void
    {
        $this->assertEquals('<option value="test1">test2</option>' . PHP_EOL, HtmlFactory::wrapIntoOptionTag('test1', 'test2'));
    }
}
