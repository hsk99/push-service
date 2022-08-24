<?php

namespace app\task\start;

use app\common\service\Task;

class UpdateStatistics
{
    /**
     * @author HSK
     * @date 2022-08-18 09:49:44
     *
     * @param \Workerman\Worker $worker
     */
    public function __construct(\Workerman\Worker $worker)
    {
        if (0 === $worker->id) {
            \Workerman\Timer::add(30, function () {
                try {
                    Task::updatePushData();
                    Task::updateSubscriptionData();
                } catch (\Throwable $th) {
                    \Hsk99\WebmanException\RunException::report($th);
                }
            });

            new \Workerman\Crontab\Crontab('1 1 0 * * *', function () {
                Task::updatePushData(date('Ymd', time() - 300));
                Task::updateSubscriptionData(date('Ymd', time() - 300));
            });
        }
    }
}
