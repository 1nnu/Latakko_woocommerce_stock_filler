<?php

function log_event($message)
{
    $string = strval($message);
    $logfile = fopen("log.txt", 'a');
    fwrite($logfile, "$string at ".date('m/d/Y h:i:s a', time()). "\n");
    fclose($logfile);
}