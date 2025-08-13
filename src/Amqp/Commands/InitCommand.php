<?php

namespace Riven\Amqp\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use JetBrains\PhpStorm\NoReturn;
use Riven\Amqp\AmqpManager;
use Riven\Amqp\Enum\AmqpRedisKey;
use Throwable;

class InitCommand extends Command
{
    protected $signature   = 'amqp:init';
    protected $description = '清除AMQP缓存，初始化RabbitMQ交换机和队列';

    public function __construct(private readonly AmqpManager $amqpManager)
    {
        parent::__construct();
    }

    #[NoReturn] public function handle(): void
    {
        $appName = env('APP_NAME');
        dump(Cache::forget(AmqpRedisKey::Impl->spr($appName)));
        dump(Cache::forget(AmqpRedisKey::Callee->spr($appName)));
        dump(Cache::forget(AmqpRedisKey::Amqp->spr($appName)));
        dump(Redis::del(AmqpRedisKey::AmqpDeclaredExchange->value));
        dump(Redis::del(AmqpRedisKey::AmqpDeclaredQueue->value));
        $this->info('AMQP 清除缓存 成功');

        try {
            $this->amqpManager->init();
            $this->info('AMQP 初始化交换机和队列 成功');
        } catch (Throwable $e) {
            $this->error('AMQP 初始化交换机和队列 失败：' . $e->getMessage());
        }
    }
}
