<?php

namespace Tests;

use ManaPHP\Di\FactoryDefault;
use ManaPHP\Message\Queue\Adapter\Redis;
use PHPUnit\Framework\TestCase;

class MessageQueueTest extends TestCase
{
    public function test_push()
    {
        $di = new FactoryDefault();
        $messageQueue = $di->getShared(Redis::class);

        $messageQueue->do_delete('test');
        $messageQueue->do_push('test', 'manaphp');
        $this->assertEquals('manaphp', $messageQueue->do_pop('test'));
    }

    public function test_pop()
    {

        $di = new FactoryDefault();
        $messageQueue = $di->getShared(Redis::class);

        $messageQueue->do_delete('test');

        $this->assertFalse($messageQueue->do_pop('test', 0));

        $this->assertFalse($messageQueue->do_pop('test', 1));

        $messageQueue->do_push('test', 'manaphp');
        $this->assertEquals('manaphp', $messageQueue->do_pop('test'));
        $this->assertFalse($messageQueue->do_pop('test', 0));
        $this->assertFalse($messageQueue->do_pop('test', 1));
    }

    public function test_delete()
    {
        $di = new FactoryDefault();
        $messageQueue = $di->getShared(Redis::class);

        $this->assertEquals(0, $messageQueue->do_length('test'));
        $messageQueue->do_delete('test');

        $messageQueue->do_push('test', 'manaphp');
        $this->assertEquals(1, $messageQueue->do_length('test'));
        $messageQueue->do_delete('test');
        $this->assertEquals(0, $messageQueue->do_length('test'));
    }

    public function test_length()
    {
        $di = new FactoryDefault();
        $messageQueue = $di->getShared(Redis::class);

        $messageQueue->do_delete('test');

        $this->assertEquals(0, $messageQueue->do_length('test'));

        $messageQueue->do_push('test', 'manaphp');
        $this->assertEquals(1, $messageQueue->do_length('test'));
    }
}