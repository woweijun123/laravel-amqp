<?php

namespace Riven\Amqp\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use JetBrains\PhpStorm\NoReturn;
use Riven\Amqp\AmqpManager;
use Throwable;

class ConsumeCommand extends Command
{
    protected $signature   = 'amqp:consume {queue}';
    protected $description = '从RabbitMQ队列中消费消息';

    public function __construct(private readonly AmqpManager $amqpManager)
    {
        parent::__construct();
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function handle(): void
    {
        $date = date('Y-m-d H:i:s');
        $this->info("开始消费, 请勿关闭窗口, time: $date");

        // 注册信号处理器
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, [$this, 'gracefulShutdown']);
        pcntl_signal(SIGINT, [$this, 'gracefulShutdown']);
        pcntl_signal(SIGHUP, [$this, 'gracefulShutdown']);

        // 启动消费者
        $this->amqpManager->consume($this->argument('queue'));
    }

    /**
     * @param int $signo
     * @return void
     */
    #[NoReturn] public function gracefulShutdown(int $signo): void
    {
        Log::info("Received $signo, shutting down gracefully...");
        $this->amqpManager->shutdown();
        exit(0);
    }
}
