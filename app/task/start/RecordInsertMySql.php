<?php

namespace app\task\start;

use app\common\service\Task;

class RecordInsertMySql
{
    /**
     * @author HSK
     * @date 2022-08-25 11:01:36
     *
     * @param \Workerman\Worker $worker
     */
    public function __construct(\Workerman\Worker $worker)
    {
        if (0 === $worker->id) {
            \Workerman\Timer::add(10, function () {
                try {
                    Task::recordInsertMySql();
                } catch (\Throwable $th) {
                    \Hsk99\WebmanException\RunException::report($th);
                }
            });
        }
    }
}
