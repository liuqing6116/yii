<?php
/**
 * Defined the consts for swoole client and server
 * Create by SamuelYu at 2015-11-09
 */
class Swoole_Server_Const
{
    const MEMORY_SIZE = '200M';
    const SERVER_HOST = '0.0.0.0';
    const SERVER_PORT = 9501;
    //最大一次并发请求数
    const MAX_PARR = 10;

    const SW_SYNC_SINGLE = 'SS';
    const SW_RSYNC_SINGLE = 'RS';

    const SW_SYNC_MULTI = 'SM';
    const SW_RSYNC_MULTI = 'RM';

    const SW_CTRL_CMD = 'CC';

    const USE_SWOOLE_TALBE = false;
    const TABLE_MAX_LINE = 65536;
    const PROC_MAX_SHAIR = 1024; //4M
    const DATA_SPLIT_LEN = 1000;

    const SW_lOG_FILE = '/tmp/swoole_server.log';
}