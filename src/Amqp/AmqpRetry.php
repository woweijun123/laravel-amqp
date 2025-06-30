<?php

namespace Riven\Amqp;

use App\Enums\RedisKey;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Riven\Amqp\Enum\AmqpRedisKey;
use Throwable;

class AmqpRetry
{
    /**
     * 获取消息的重试次数
     *
     * @param string $messageId
     * @return int
     */
    public function getRetryCount(string $messageId): int
    {
        try {
            return (int) Redis::get($this->getKey($messageId)) ?: 0;
        } catch (Throwable $e) {
            Log::error("getRetryCount error: " . $e->getMessage(), ['code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            // 可记录日志或上报监控
            return 0;
        }
    }

    /**
     * 增加重试次数
     *
     * @param string $messageId
     * @param int    $retryCount
     * @return void
     */
    public function setRetryCount(string $messageId, int $retryCount): void
    {
        try {
            // 在 Redis 中存储重试次数，TTL 24 小时
            Redis::setex($this->getKey($messageId), 86400, $retryCount);
        } catch (Throwable $e) {
            // 可记录日志或上报监控
            Log::error("setRetryCount error: " . $e->getMessage(), ['code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * 清除重试计数
     *
     * @param string $messageId
     * @return void
     */
    public function clearRetryCount(string $messageId): void
    {
        try {
            Redis::del($this->getKey($messageId));
        } catch (Throwable $e) {
            // 可记录日志或上报监控
            Log::error("clearRetryCount error: " . $e->getMessage(), ['code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * 构造 Redis 键名
     *
     * @param string $messageId
     * @return string
     */
    protected function getKey(string $messageId): string
    {
        return AmqpRedisKey::AmqpRetryCount->spr($messageId);
    }
}
