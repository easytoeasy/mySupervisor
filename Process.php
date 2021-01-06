<?php


require_once __DIR__ . '/Consumer.php';
require_once __DIR__ . '/StreamConnection.php';
require_once __DIR__ . '/Http.php';

class Process
{
    /** 
     * 待启动的消费者数组
     * @var array(Consumer)
     */
    protected $consumers = array();
    protected $childPids = array();

    const PPID_FILE = __DIR__ . '/process';


    protected $serializerConsumer;

    public function __construct()
    {
        $this->consumers = $this->getConsumers();
    }

    public function getConsumers()
    {
        $consumer = new Consumer([
            'program' => 'test',
            'command' => '/usr/bin/php test.php',
            'directory' => __DIR__,
            'logfile' => __DIR__ . '/test.log',
            'uniqid' => uniqid(),
            'auto_restart' => false,
        ]);
        return [
            $consumer->uniqid => $consumer,
        ];
    }

    public function run()
    {
        if (empty($this->consumers)) {
            // consumer empty
            return;
        }
        if ($this->_notifyMaster()) {
            // master alive
            return;
        }

        $pid = pcntl_fork();
        if ($pid < 0) {
            exit;
        } elseif ($pid > 0) {
            exit;
        }
        if (!posix_setsid()) {
            exit;
        }

        $stream = new StreamConnection('tcp://0.0.0.0:7865');
        @cli_set_process_title('AMQP Master Process');
        // 将主进程ID写入文件
        file_put_contents(self::PPID_FILE, getmypid());
        // master进程继续
        while (true) {
            $this->init();
            pcntl_signal_dispatch();
            $this->waitpid();
            // if (empty($this->childPids)) {
            //     $stream->close($stream->getSocket());
            //     break;
            // }
            $stream->accept(function ($uniqid, $action) {
                $this->handle($uniqid, $action);
                return $this->display();
            });
        }
    }

    protected function init()
    {
        foreach ($this->consumers as &$c) {
            switch ($c->state) {
                case Consumer::RUNNING:
                case Consumer::STOP:
                    break;
                case Consumer::NOMINAL:
                case Consumer::STARTING:
                    $this->fork($c);
                    break;
                case Consumer::STOPING:
                    if ($c->pid && posix_kill($c->pid, SIGTERM)) {
                        $this->reset($c, Consumer::STOP);
                    }
                    break;
                case Consumer::RESTART:
                    if (empty($c->pid)) {
                        $this->fork($c);
                        break;
                    }
                    if (posix_kill($c->pid, SIGTERM)) {
                        $this->reset($c, Consumer::STOP);
                        $this->fork($c);
                    }
                    break;
                default:
                    break;
            }
        }
    }

    protected function reset(Consumer $c, $state)
    {
        $c->pid = '';
        $c->uptime = '';
        $c->state = $state;
        $c->process = null;
    }



    /**
     * reload 被杀死的子进程
     *
     * @return void
     */
    protected function waitpid()
    {
        foreach ($this->childPids as $uniqid => $pid) {
            $result = pcntl_waitpid($pid, $status, WNOHANG);
            if ($result == $pid || $result == -1) {
                unset($this->childPids[$uniqid]);
                $c = &$this->consumers[$uniqid];
                $state = pcntl_wifexited($status) ? Consumer::EXITED : Consumer::STOP;
                $this->reset($c, $state);
            }
        }
    }


    /**
     * 父进程存活情况下，只会通知父进程信息，否则可能产生多个守护进程
     *
     * @return bool 父进程是否健在
     */
    private function _notifyMaster()
    {
        $ppid = file_get_contents(self::PPID_FILE );
        $isAlive = $this->checkProcessAlive($ppid);
        if (!$isAlive) return false;
        return true;
    }

    public function checkProcessAlive($pid)
    {
        if (empty($pid)) return false;
        $pidinfo = `ps co pid {$pid} | xargs`;
        $pidinfo = trim($pidinfo);
        $pattern = "/.*?PID.*?(\d+).*?/";
        preg_match($pattern, $pidinfo, $matches);
        return empty($matches) ? false : ($matches[1] == $pid ? true : false);
    }

    /**
     * fork一个新的子进程
     *
     * @param string $queueName
     * @param integer $qos
     * @return Consumer
     */
    protected function fork(Consumer $c)
    {
        $descriptorspec = [2 => ['file', $c->logfile, 'a'],];
        $process = proc_open('exec ' . $c->command, $descriptorspec, $pipes, $c->directory);
        if ($process) {
            $ret = proc_get_status($process);
            if ($ret['running']) {
                $c->state = Consumer::RUNNING;
                $c->pid = $ret['pid'];
                $c->process = $process;
                $c->uptime = date('m-d H:i');
                $this->childPids[$c->uniqid] = $ret['pid'];
            } else {
                $c->state = Consumer::EXITED;
                proc_close($process);
            }
        } else {
            $c->state = Consumer::ERROR;
        }
        return $c;
    }

    public function display()
    {
        $location = 'http://127.0.0.1:7865';
        $basePath = Http::$basePath;
        $scriptName = isset($_SERVER['SCRIPT_NAME']) &&
            !empty($_SERVER['SCRIPT_NAME']) &&
            $_SERVER['SCRIPT_NAME'] != '/' ? $_SERVER['SCRIPT_NAME'] : '/index.php';
        if ($scriptName == '/index.html') {
            return Http::status_301($location);
        }

        $sourcePath = $basePath . $scriptName;
        if (!is_file($sourcePath)) {
            return Http::status_404();
        }

        ob_start();
        include $sourcePath;
        $response = ob_get_contents();
        ob_clean();

        return Http::status_200($response);
    }




    public function handle($uniqid, $action)
    {
        if (!empty($uniqid) && !isset($this->consumers[$uniqid])) {
            return;
        }
        switch ($action) {
            case 'refresh':
                break;
            case 'restartall':
                $this->killall(true);
                break;
            case 'stopall':
                $this->killall();
                break;
            case 'stop':
                $c = &$this->consumers[$uniqid];
                if ($c->state != Consumer::RUNNING) break;
                $c->state = Consumer::STOPING;
                break;
            case 'start':
                $c = &$this->consumers[$uniqid];
                if ($c->state == Consumer::RUNNING) break;
                $c->state = Consumer::STARTING;
                break;
            case 'restart':
                $c = &$this->consumers[$uniqid];
                $c->state = Consumer::RESTART;
                break;
            case 'copy':
                $c = $this->consumers[$uniqid];
                $newC = clone $c;
                $newC->uniqid = uniqid('C');
                $newC->state = Consumer::NOMINAL;
                $newC->pid = '';
                $this->consumers[$newC->uniqid] = $newC;
                break;
            default:
                break;
        }
    }

    protected function killall($restart = false)
    {
        foreach ($this->consumers as &$c) {
            $c->state = $restart ? Consumer::RESTART : Consumer::STOPING;
        }
    }
}

$cli = new Process();
$cli->run();