<?php

namespace Riven\Amqp;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPIOException;
use Riven\Amqp\Exception\AMQPConnectionException;
use Throwable;

class AMQPConnection extends AMQPStreamConnection
{
    private static AMQPConnection $instance; // AMQP 连接实例
    private static AMQPChannel $channel; // AMQP 通道
    private static AMQPChannel $confirmChannel; // AMQP 发布确认通道

    /**
     * 构造函数
     * @param bool $isConsumer
     * @throws AMQPConnectionException
     */
    private function __construct(bool $isConsumer)
    {
        // 从配置文件中获取AMQP连接配置
        $config = config('amqp');
        // 消费者模式配置
        if ($isConsumer) {
            $config = array_merge($config, config('amqp.consumer'));
        }
        try {
            parent::__construct(
                $config['host'], // RabbitMQ 服务器地址
                $config['port'], // RabbitMQ 端口，默认为 5672
                $config['user'], // 连接用户名
                $config['password'], // 连接密码
                $config['vhost'], // 虚拟主机（Virtual Host），默认为 '/'
                false, // 是否开启 insist 模式。如果为 true，客户端会在连接失败时尝试重连（通常不推荐在构造函数直接设置）
                'AMQPLAIN', // 认证机制，默认为 'AMQPLAIN'
                null, // 客户端属性数组，可用于发送自定义连接属性给 Broker
                'en_US', // Locale，语言/地区设置
                $config['connection_timeout'], // TCP 连接超时时间（秒）
                $config['read_write_timeout'], // 读写操作超时时间（秒）
                null, // 回调函数，用于处理连接异常（不常用）
                $config['keepalive'], // TCP Keepalive 模式是否开启（true/false），用于维护 TCP 连接活力
                $config['heartbeat'] // 心跳间隔（秒）。客户端和服务器之间定期发送心跳帧，用于检测死连接。
            );
            $this->heartbeat();
        } catch (Throwable $e) {
            throw new AMQPConnectionException('AMQP 连接异常：' . $e->getMessage());
        }
    }

    /**
     * 创建连接
     * @param bool $isConsumer 是否为消费者
     * @return AMQPConnection
     * @throws AMQPConnectionException
     */
    public static function getInstance(bool $isConsumer = false): AMQPConnection
    {
        if (!isset(self::$instance) || !self::$instance->isConnected()) {
            self::$instance = new self($isConsumer);
        }
        return self::$instance;
    }

    /**
     * 获取通道
     * @return AMQPChannel
     */
    public function getChannel(): AMQPChannel
    {
        if (!isset(self::$channel) || !self::$channel->is_open()) {
            self::$channel = $this->channel();
        }
        return self::$channel;
    }

    /**
     * 获取发布确认通道
     * @return AMQPChannel
     */
    public function getConfirmChannel(): AMQPChannel
    {
        if (!isset(self::$confirmChannel) || !self::$confirmChannel->is_open()) {
            self::$confirmChannel = $this->channel();
            self::$confirmChannel->confirm_select();
        }
        return self::$confirmChannel;
    }

    /**
     * 保持心跳「时钟信号」
     * @return void
     * @throws AMQPIOException
     */
    public function heartbeat(): void
    {
        if (isset($this->heartbeat) && $this->heartbeat > 0) {
            $heartbeat = ceil($this->heartbeat / 4);
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, function () use ($heartbeat) {
                $this->checkHeartBeat(); // 手动触发心跳响应
                pcntl_alarm($heartbeat);
            });
            pcntl_alarm($heartbeat);
        }
    }

    /**
     * 关闭 AMQP 连接和通道
     * @return void
     */
    public static function shutdown(): void
    {
        if (isset(self::$channel) && self::$channel->is_open()) {
            try {
                self::$channel->close();
            } catch (Throwable $e) {
                Log::error("关闭 AMQP channel 失败", ['exception' => $e]);
            }
        }
        if (isset(self::$confirmChannel) && self::$confirmChannel->is_open()) {
            try {
                self::$confirmChannel->close();
            } catch (Throwable $e) {
                Log::error("关闭 AMQP confirmChannel 失败", ['exception' => $e]);
            }
        }
        if (isset(self::$instance) && self::$instance->isConnected()) {
            try {
                self::$instance->close();
            } catch (Throwable $e) {
                Log::error("关闭 AMQP 连接 失败", ['exception' => $e]);
            }
        }
    }
}
