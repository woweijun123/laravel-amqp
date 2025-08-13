<?php

namespace Riven\Amqp\Commands;

use Illuminate\Console\Command;
use JetBrains\PhpStorm\NoReturn;
use Riven\Amqp\AmqpManager;
use Riven\Providers\AmqpProvider;
use Throwable;

class InitCommand extends Command
{
    protected $signature = 'amqp:init';
    protected $description = '清除AMQP缓存，初始化RabbitMQ交换机和队列';

    public function __construct(private readonly AmqpManager $amqpManager)
    {
        parent::__construct();
    }

    #[NoReturn] public function handle(): void
    {
        if (is_file($implPath = AmqpProvider::getCachedPath('cache/impl.php'))) {
            @unlink($implPath);
        }
        if (is_file($calleePath = AmqpProvider::getCachedPath('cache/callee.php'))) {
            @unlink($calleePath);
        }
        if (is_file($amqpPath = AmqpProvider::getCachedPath('cache/amqp.php'))) {
            @unlink($amqpPath);
        }
        $this->info('impl callee amqp 清除缓存 成功');
        try {
            $this->amqpManager->init();
            $this->info('AMQP 初始化交换机和队列 成功');
        } catch (Throwable $e) {
            $this->error('AMQP 初始化交换机和队列 失败：' . $e->getMessage());
        }
    }
}
