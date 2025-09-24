<?php declare(strict_types=1);

use Tests\BaseTestCase;

final class ExampleTest extends BaseTestCase
{
    public function testTrueIsTrue(): void
    {
        $this->assertTrue(true);
    }

    public function testArrayOperations(): void
    {
        $array = ['key' => 'value'];
        $this->assertIsArray($array);
        $this->assertArrayHasKey('key', $array);
        $this->assertSame('value', $array['key']);
    }

    public function testStringOperations(): void
    {
        $string = 'Axytos Payment Plugin';
        $this->assertIsString($string);
        $this->assertStringContainsString('Axytos', $string);
        $this->assertStringStartsWith('Axytos', $string);
        $this->assertStringEndsWith('Plugin', $string);
    }
}
