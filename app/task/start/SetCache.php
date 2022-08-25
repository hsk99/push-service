<?php

namespace app\task\start;

use app\common\service\Task;

class SetCache
{
    /**
     * @author HSK
     * @date 2022-08-25 10:11:31
     *
     * @param \Workerman\Worker $worker
     */
    public function __construct(\Workerman\Worker $worker)
    {
        if (0 === $worker->id) {
            \Workerman\Timer::add(1, function () {
                try {
                    Task::setCache();
                } catch (\Throwable $th) {
                    \Hsk99\WebmanException\RunException::report($th);
                }
            }, '', false);

            new \Workerman\Crontab\Crontab('1 5 0 * * *', function () {
                Task::setCache();
            });
        }
    }
}
