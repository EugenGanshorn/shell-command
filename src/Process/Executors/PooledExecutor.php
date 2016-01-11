<?php

namespace Process\Executors;

use LogicException;
use Process\Output\OutputHandler;


/**
 * Class PooledExecutor
 */
class PooledExecutor implements ExecutorInterface
{
    /**
     * @var Process[]
     */
    protected $processes;

    /**
     * @var OutputHandler
     */
    protected $handler;

    /**
     * @var int
     */
    protected $poolSize;

    /**
     * PooledExecutor constructor.
     *
     * @param int $poolSize
     * @param OutputHandler $handler
     */
    public function __construct($poolSize = -1, OutputHandler $handler = null)
    {
        $this->processes = [];
        $this->handler = $handler;
        $this->poolSize = $poolSize;
    }

    /**
     * Manage a selected process.
     *
     * @param Process[] $processes
     * @return PooledExecutor
     */
    public function manage(Process ...$processes)
    {
        $unbounded = ($this->poolSize < 0);

        foreach ($processes as $process) {
            if (!$unbounded && (--$this->poolSize < 0)) {
                throw new LogicException("PooledExecutor pool size [{$this->poolSize}] exceeded.");
            }

            $this->processes[$process->getPid()] = $process;
        }

        return $this;
    }

    /**
     * Run each managed process.
     *
     * @param OutputHandler $handler
     * @return void
     */
    public function start(OutputHandler $handler = null)
    {
        foreach ($this->processes as $pid => $process) {
            if (!$process->isStarted()) {
                $process->runAsync($handler ?: $this->handler);
            }
        }
    }

    /**
     * Block execution until each managed process has completed.
     *
     * @return void
     */
    public function join()
    {
        while (true) {
            $finished = true;
            foreach ($this->processes as $pid => $process) {
                if ($process->isAlive()) {
                    $finished = false;
                    break;
                }
            }

            if ($finished) {
                break;
            }

            unset($finished);
            usleep(10000);
        }
    }

    /**
     * Check if any managed processes are still running.
     *
     * @return bool
     */
    public function hasAlive()
    {
        foreach ($this->processes as $pid => $process) {
            if ($process->isAlive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Abandon the currently managed processes.
     *
     * @return void
     */
    public function abandon()
    {
        foreach ($this->processes as $index => $process) {
            $process->kill();

            unset($process[$index]);
        }

        unset($this->processes);
    }
}
