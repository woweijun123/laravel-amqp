<?php

namespace Riven\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Riven\Amqp\Enum\AmqpRedisKey;

class InitCommand extends Command
{
    protected $signature   = 'clean:cache';
    protected $description = '清除框架自定义缓存';

    public function handle(): void
    {
        Cache::forget(AmqpRedisKey::Impl->value);
        Cache::forget(AmqpRedisKey::Callee->value);
        Cache::forget(AmqpRedisKey::Amqp->value);
        $this->info('Clearing successfully');
    }
}
