<?php

namespace Riven\Amqp\Invoke;

use Illuminate\Database\DatabaseTransactionsManager;

/**
 * 数据库事务管理器解析器。
 */
trait TransactionManagerResolver
{
    /**
     * 数据库事务管理器解析器实例
     * @var callable
     */
    public $transactionManagerResolver;

    /**
     * 解析数据库事务管理器
     * @return DatabaseTransactionsManager|null
     */
    protected function resolveTransactionManager(): ?DatabaseTransactionsManager
    {
        return call_user_func($this->transactionManagerResolver);
    }

    /**
     * 设置数据库事务管理器解析器
     * @param  callable  $resolver
     * @return $this
     */
    public function setTransactionManagerResolver(callable $resolver): static
    {
        $this->transactionManagerResolver = $resolver;
        return $this;
    }
}
