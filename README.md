# mySupervisor

## run
php Process.php

## 思路
模拟supervisor对进程管理（简易实现）

1、在主进程循环内启动子进程执行命令
2、在web输入 http://127.0.0.1:7865 获取子进程状态
3、接收请求消息，并且执行相应操作
4、回收僵尸进程
