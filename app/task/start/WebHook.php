<?php

namespace app\task\start;

use app\common\service\Task;

class WebHook
{
    /**
     * @author HSK
     * @date 2022-08-19 11:39:46
     *
     * @param \Workerman\Worker $worker
     */
    public function __construct(\Workerman\Worker $worker)
    {
        if (0 === $worker->id) {
            \Workerman\Timer::add(5, function () {
                try {
                    Task::webHook();
                } catch (\Throwable $th) {
                    \Hsk99\WebmanException\RunException::report($th);
                }
            });
        }
    }
}
