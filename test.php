<?php


while(true) {
    file_put_contents(__DIR__ . '/test.log', date('Y-m-d H:i:s'));
    sleep(1);
}