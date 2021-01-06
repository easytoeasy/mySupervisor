<?php

require_once __DIR__ . '/BaseObject.php';

class Consumer extends BaseObject
{
    /** 开启多少个消费者 */
    public $numprocs = 1;
    /** 当前配置的唯一标志 */
    public $program;
    /** 执行的命令 */
    public $command;
    /** 开启消费者副本的数量 */
    public $duplicate = 1;
    /** 当前工作的目录 */
    public $directory;

    /** 程序执行日志记录 */
    public $logfile = '';
    /** 消费进程的唯一ID */
    public $uniqid;
    /** 进程IDpid */
    public $pid;
    /** 进程状态 */
    public $state = self::NOMINAL;
    /** 自启动 */
    public $auto_restart = false;

    public $process;
    /** 启动时间 */
    public $uptime;

    const RUNNING = 'running';
    const STOP = 'stoped';
    const NOMINAL = 'nominal';
    const RESTART = 'restart';
    const STOPING = 'stoping';
    const STARTING = 'stating';
    const ERROR = 'error';
    const BLOCKED = 'blocked';
    const EXITED = 'exited';
    const FATEL = 'fatel';

    
}
