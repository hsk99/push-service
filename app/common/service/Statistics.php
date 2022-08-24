<?php

namespace app\common\service;

use think\facade\Db;

class Statistics
{
    /**
     * 总统计数据
     *
     * @author HSK
     * @date 2022-08-23 15:43:44
     *
     * @param string|null $data
     * @param int|null $project
     * @param string|null $channel
     *
     * @return array
     */
    public static function totalData(string $date = null, int $project = null, string $channel = null): array
    {
        try {
            $where = [];

            if (!empty($date)) {
                $date = date('Ymd', strtotime($date));
            } else {
                $date = date('Ymd');
            }
            $where[] = ['day', '=', $date];

            if (!empty($project)) {
                $where[] = ['project', '=', $project];
            }

            if (!empty($channel)) {
                $where[] = ['channel', '=', $channel];
            }

            $data = Db::name('statistics')
                ->field('COUNT(DISTINCT project) AS project, COUNT(channel) AS channel, SUM(subscription) AS subscription, SUM(push) AS push')
                ->where($where)
                ->find();

            return $data ?? [];
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return [];
        }
    }

    /**
     * 统计图表数据
     *
     * @author HSK
     * @date 2022-08-23 16:56:19
     *
     * @param string|null $start_date
     * @param string|null $end_date
     * @param integer|null $project
     * @param string|null $channel
     *
     * @return array
     */
    public static function chartData(string $start_date = null, string $end_date = null, int $project = null, string $channel = null): array
    {
        try {
            $where = [];

            switch (true) {
                case is_null($start_date) && is_null($end_date):
                    $start_date = date('Y-m-d', time() - 86400 * 30);
                    $end_date   = date('Y-m-d');
                    break;
                case !empty($start_date) && is_null($end_date):
                    $end_date = date('Y-m-d');
                    break;
                case is_null($start_date) && !empty($end_date):
                    $start_date = date('Y-m-d', strtotime($end_date) - 86400 * 30);
                    break;
            }
            $startDate = date('Y-m-d 00:00:00', strtotime($start_date));
            $endDate   = date('Y-m-d 23:59:59', strtotime($end_date));

            $where[] = ['create_time', 'between', [$startDate, $endDate]];

            if (!empty($project)) {
                $where[] = ['project', '=', $project];
            }

            if (!empty($channel)) {
                $where[] = ['channel', '=', $channel];
            }

            $data = Db::name('statistics')
                ->field('day, COUNT(DISTINCT project) AS project, COUNT(channel) AS channel, SUM(subscription) AS subscription, SUM(push) AS push')
                ->where($where)
                ->group('day')
                ->order('day', 'asc')
                ->select()
                ->toArray();
            $data = array_column($data, null, 'day');

            $chartList = [];
            $time = strtotime($start_date);
            $intervalCount = (strtotime($end_date) - $time) / 86400;
            for ($i = 0; $i <= $intervalCount; $i++) {
                $interval = date('Ymd', $time + $i * 86400);

                if (empty($data[$interval])) {
                    $chartList['day'][$interval]          = date('Y-m-d', $time + $i * 86400);
                    $chartList['project'][$interval]      = 0;
                    $chartList['channel'][$interval]      = 0;
                    $chartList['subscription'][$interval] = 0;
                    $chartList['push'][$interval]         = 0;
                } else {
                    $chartList['day'][$interval]          = date('Y-m-d', $time + $i * 86400);
                    $chartList['project'][$interval]      = $data[$interval]['project'];
                    $chartList['channel'][$interval]      = $data[$interval]['channel'];
                    $chartList['subscription'][$interval] = $data[$interval]['subscription'];
                    $chartList['push'][$interval]         = $data[$interval]['push'];
                }
            }
            $chartList['day']          = array_values($chartList['day'] ?? []);
            $chartList['project']      = array_values($chartList['project'] ?? []);
            $chartList['channel']      = array_values($chartList['channel'] ?? []);
            $chartList['subscription'] = array_values($chartList['subscription'] ?? []);
            $chartList['push']         = array_values($chartList['push'] ?? []);

            return $chartList;
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return [];
        }
    }

    /**
     * 日统计图表数据
     *
     * @author HSK
     * @date 2022-08-24 09:35:12
     *
     * @param string|null $data
     * @param integer|null $project
     * @param string|null $channel
     *
     * @return array
     */
    public static function dailyChartData(string $date = null, int $project = null, string $channel = null): array
    {
        try {
            $where = [];

            if (!empty($date)) {
                $date = date('Ymd', strtotime($date));
            } else {
                $date = date('Ymd');
            }
            $where[] = ['day', '=', $date];

            if (!empty($project)) {
                $where[] = ['project', '=', $project];
            }

            if (!empty($channel)) {
                $where[] = ['channel', '=', $channel];
            }

            $data = Db::name('record')
                ->field('DATE_FORMAT(create_time, "%Y-%m-%d %H:%i") AS time, COUNT(channel) AS channel, COUNT(event) AS event, COUNT(id) AS push')
                ->where($where)
                ->group('time')
                ->order('time', 'asc')
                ->select()
                ->toArray();
            $data = array_column($data, null, 'time');

            $chartList = [];
            $time = strtotime($date);
            $intervalCount = 1440;
            for ($i = 0; $i <= $intervalCount; $i++) {
                $interval = date('Y-m-d H:i', $time + $i * 60);

                if (empty($data[$interval])) {
                    $chartList['time'][$interval]    = $interval;
                    $chartList['channel'][$interval] = 0;
                    $chartList['event'][$interval]   = 0;
                    $chartList['push'][$interval]    = 0;
                } else {
                    $chartList['time'][$interval]    = $interval;
                    $chartList['channel'][$interval] = $data[$interval]['channel'];
                    $chartList['event'][$interval]   = $data[$interval]['event'];
                    $chartList['push'][$interval]    = $data[$interval]['push'];
                }
            }
            $chartList['time']    = array_values($chartList['time'] ?? []);
            $chartList['channel'] = array_values($chartList['channel'] ?? []);
            $chartList['event']   = array_values($chartList['event'] ?? []);
            $chartList['push']    = array_values($chartList['push'] ?? []);

            return $chartList;
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return [];
        }
    }
}
