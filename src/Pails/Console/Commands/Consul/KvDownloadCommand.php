<?php
/**
 * @author wsfuyibing<websearch@163.com>
 * @date   2019-05-22
 */
namespace Pails\Console\Commands\Consul;

/**
 * 导出KV
 * @package Pails\Console\Commands\Consul
 */
class KvDownloadCommand extends Abstracts\KvCommand
{
    /**
     * @var string
     */
    protected $signature = 'consul:kv:download
                            {--name= : 在Consul中注册的KV名称, 默认为当前目录名称}
                            {--consul=127.0.0.1:8500 : Consul地址}';
    /**
     * @var string
     */
    protected $description = '将Consul KV配置导出, 覆盖到config目录';

    /**
     * 导出过程
     * @return void
     */
    public function handle()
    {
        // 1. 运行参数
        //    a) consul host
        //    b) kv name
        //    c) kv catalog
        $host = $this->getConsulHost();
        $name = $this->getAppName();
        $cata = $this->getAppCatalog();
        if ($cata !== '') {
            $name = $cata.'/'.$name;
        }
        // 2. 读取KV配置
        $this->info("[INFO] 从服务器{{$host}}下载{KV}配置");
        $data = $this->openConsulKey($host, $name);
        if ($data !== false) {
            // 3. 合并配置
            $this->mergeConsulKey($data);
        }
    }
}
