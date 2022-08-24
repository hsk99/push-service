<?php

namespace app\task\stop;

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
            Task::updatePushData();
            Task::updateSubscriptionData();
        }
    }
}
