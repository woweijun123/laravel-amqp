<?php

return [
    // RabbitMQ 服务器的主机地址
    'host'     => env('RABBITMQ_HOST', 'localhost'),
    // RabbitMQ 服务器的端口
    'port'     => env('RABBITMQ_PORT', 5672),
    // 连接 RabbitMQ 的用户名
    'user'     => env('RABBITMQ_USER', 'guest'),
    // 连接 RabbitMQ 的密码
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    // 连接 RabbitMQ 的虚拟主机
    'vhost'    => env('RABBITMQ_VHOST', '/'),
    // 心跳：对于短生命周期的 PHP-FPM 请求，心跳意义不大，甚至可能引入不必要的开销。
    // 建议值：0（禁用心跳）。
    'heartbeat' => env('RABBITMQ_PUBLISHER_HEARTBEAT', 0),
    // TCP Keepalive：通常由操作系统控制，不推荐在应用层强制开启。
    // 建议值：false。
    'keepalive' => env('RABBITMQ_PUBLISHER_KEEPALIVE', false),
    // TCP 连接超时：短生命周期，快速失败，避免长时间阻塞 Web 请求
    // 建议值：5 秒。太短可能因为网络抖动失败，太长阻塞 Web。
    'connection_timeout' => env('RABBITMQ_PUBLISHER_CONNECTION_TIMEOUT', 5),
    // 读写超时：发送和接收数据包的超时时间。
    // 对于生产者，主要影响发送消息时的等待。
    // 建议值：5-10 秒。根据网络质量调整。
    'read_write_timeout' => env('RABBITMQ_PUBLISHER_READ_WRITE_TIMEOUT', 10),
    // --- 独立消费者进程 (消费者) 配置推荐 ---
    'consumer' => [
        // 心跳：对于长生命周期的消费者至关重要，用于检测死连接。
        // RabbitMQ 默认心跳是 60 秒，客户端通常设置其三分之一。
        // 建议值：10-20 秒。
        'heartbeat' => env('RABBITMQ_CONSUMER_HEARTBEAT', 10),
        // TCP Keepalive：同样，通常由操作系统控制。
        // 建议值：false。
        'keepalive' => env('RABBITMQ_CONSUMER_KEEPALIVE', true),
        // TCP 连接超时：消费者启动时连接 Broker 的超时时间。
        // 建议值：根据网络稳定性和 RabbitMQ 启动时间适当延长，例如 10-30 秒。
        'connection_timeout' => env('RABBITMQ_CONSUMER_CONNECTION_TIMEOUT', 5),
        // 读写超时：消费者与 Broker 之间维持通信和收发消息的超时时间。
        // 必须大于心跳间隔，否则心跳会超时。
        // 建议值：心跳的1~1.5倍，例如 10-15 秒。
        'read_write_timeout' => env('RABBITMQ_CONSUMER_READ_WRITE_TIMEOUT', 12),
    ],
    // AMQP 消息重试机制的配置
    'retry'    => [
        // 是否启用消息重试机制
        'enabled'      => env('RABBITMQ_RETRY_ENABLED', true),
        // 消息最大重试次数
        'max_attempts' => env('RABBITMQ_RETRY_MAX_ATTEMPTS', 5),
        // 重试间隔的退避基数（单位：秒）。
        // 例如，如果基数为 2，则第一次重试等待 2^1=2 秒，第二次等待 2^2=4 秒，以此类推。
        'backoff_base' => env('RABBITMQ_RETRY_BACKOFF_BASE', 2),
    ],
];
