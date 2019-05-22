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
class KvUploadCommand extends Abstracts\KvCommand
{
    /**
     * @var string
     */
    protected $signature = 'consul:kv:upload
                            {--name= : 在Consul中注册的KV名称, 默认为当前目录名称}
                            {--consul=127.0.0.1:8500 : Consul地址}';
    /**
     * @var string
     */
    protected $description = '扫描config目录, 将配置同步到将Consul KV中(一次性)';

    /**
     * 导入过程
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
        // 2. 扫描配置
        $this->info("[INFO] 上传{KV}初始配置到Consul服务器{{$host}}上");
        $data = $this->scanner();
        if (is_array($data)) {
            $this->sendConsulKey($host, $name, $data);
        }
    }
}
