<?php

namespace Riven\Amqp\Enum;

trait SprintfVal
{
    /**
     * 获取格式化后的键
     * @param ...$args
     * @return string
     */
    public function spr(...$args): string
    {
        if (!$args) {
            return $this->value;
        }
        // 处理带 * 的情况：替换 * 为 参数
        $value = $this->value;
        if (str_contains($value, '*')) {
            $i     = 0;
            $value = preg_replace_callback('/\*/', function () use (&$i, &$args) {
                $tmp = $args[$i] ?? '*';
                unset($args[$i]); // 删除已使用的参数
                $i++;

                return $tmp; // 替换 * 为 索引参数
            }, $value);
        }
        // 处理带 % 的情况：替换 %s 为 参数
        if (str_contains($value, '%')) {
            $count = substr_count($value, '%');
            // 参数足够才处理
            if (count($args) >= $count) {
                $value = vsprintf($value, array_splice($args, 0, $count));
            }
        }

        return $value;
    }
}
