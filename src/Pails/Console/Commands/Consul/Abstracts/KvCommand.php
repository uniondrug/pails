<?php
/**
 * @author wsfuyibing<websearch@163.com>
 * @date   2019-05-22
 */
namespace Pails\Console\Commands\Consul\Abstracts;

use Pails\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * KvCommand
 * @package Pails\Console\Commands\Consul\Abstracts
 */
abstract class KvCommand extends Command
{
    private $_consulHost = '127.0.0.1:8500';
    private $_keyCatalog = 'tm';

    /**
     * 按路径名计算项目名
     * 注意: 项目名务必与coding上的项目名称保持一致, 否则将计算出错
     * @return string
     * @throws \Exception
     */
    public function getAppName()
    {
        // 1. 来自`--name`设置
        $name = trim((string) $this->input->getOption('name'));
        if ($name !== "") {
            return $name;
        }
        // 2. 自动计算
        $path = $this->di->basePath();
        if (preg_match("/([_a-z\-]+)$/", $path, $m) > 0) {
            $name = strtolower($m[1]);
            return preg_replace("/^tm[_\-]/", "", $name);
        }
        throw new \Exception("计算项目名称失败");
    }

    public function getAppCatalog()
    {
        return $this->_keyCatalog;
    }

    /**
     * 计算Consul地址
     * @return string
     */
    public function getConsulHost()
    {
        // 1. 来自`--name`设置
        $name = trim((string) $this->input->getOption('consul'));
        if ($name !== "") {
            return $name;
        }
        return $this->_consulHost;
    }

    /**
     * 合并配置文件
     * @param array $data
     */
    public function mergeConsulKey($data)
    {
        // 1. path check
        $path = $this->di->basePath().'/config';
        if (!is_dir($path)) {
            $this->error("[ERROR] 目录{{$path}}不合法");
            return;
        }
        // 2. loop directory
        $this->info("[INFO] 合并{KV}配置到配置文件");
        $d = dir($path);
        while (false !== ($e = $d->read())) {
            // 3. ignore dir
            if (preg_match("/^\./", $e) > 0) {
                continue;
            }
            // 4. ignore for not in follow
            if (preg_match("/(\S+)\.(php|yml)$/i", $e, $m) === 0) {
                continue;
            }
            switch ($m[2]) {
                // 5. wirte to php file
                case 'php' :
                    $this->phpWriter($data, $m[1], $e, $path.'/'.$e);
                    break;
                // 6. write to yml file
                case 'yml' :
                    $this->ymlWriter($data, $m[1], $e, $path.'/'.$e);
                    break;
            }
        }
        $d->close();
        $this->info("[INFO] 合并完成");
    }

    /**
     * @param string $host
     * @param string $name
     * @return false|array
     */
    public function openConsulKey(string $host, string $name)
    {
        $this->info("[INFO] 读取顶级JSON配置结构");
        // 1. block key
        if (false === ($text = $this->readConsulKey($host, $name))) {
            return false;
        }
        // 2. json required
        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $this->error("[ERROR] 一级KV结构不是有效的JSON字符串");
            return false;
        }
        // 3. nest manager
        $this->parseData($this, $host, $data);
        return $data;
    }

    /**
     * @param string $host
     * @param string $name
     * @return false|string
     */
    public function readConsulKey(string $host, string $name)
    {
        $this->output->writeln("       读取{{$name}}配置");
        try {
            // 1. get response text
            $url = "http://{$host}/v1/kv/{$name}";
            $text = $this->httpClient->get($url)->getBody()->getContents();
            // 2. decode
            $json = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($json) || !count($json)) {
                throw new \RuntimeException('json decode failure '.json_last_error_msg(), 500);
            }
            // 3. base64 decode
            return (string) base64_decode($json[0]['Value']);
        } catch(\Throwable $e) {
            $this->error("[ERROR] 读取{{$name}}失败 - {$e->getMessage()}");
        }
        return false;
    }

    /**
     * @param KvCommand $cmd
     * @param string    $host
     * @param array     $data
     * @return void
     */
    public function parseData($cmd, $host, & $data)
    {
        // 1. not array
        if (!is_array($data)) {
            return;
        }
        foreach ($data as & $value) {
            // 2. nest
            if (is_array($value)) {
                $this->parseData($cmd, $host, $value);
                continue;
            }
            // 3. not string
            if (!is_string($value)) {
                continue;
            }
            // 4. kv nest
            if (preg_match("/^kv:\/\/(\S+)$/", $value, $m) > 0) {
                $value = $this->parserNest($this, $host, $m[1]);
                continue;
            }
            // 5. string checker
            $lower = strtolower($value);
            if ($lower === "true") {
                // 5.1 bool:true
                $value = true;
            } else if ($lower === "false") {
                // 5.2 bool:false
                $value = false;
            } else {
                // 5.3 nest string
                $value = $this->parserText($this, $host, $value);
            }
        }
    }

    /**
     * 解析字符串
     * @param KvCommand $cmd
     * @param string    $host
     * @param string    $name
     * @return array|string
     */
    public function parserNest($cmd, string $host, string $name)
    {
        // 1. false
        if (false === ($text = $this->readConsulKey($host, $name))) {
            return "";
        }
        // 2. json
        $json = json_decode($text, true);
        if (is_array($json)) {
            $this->parseData($cmd, $host, $json);
            return $json;
        }
        // 3. string
        return $this->parserText($cmd, $host, $text);
    }

    /**
     * 解析字符串
     * @param KvCommand $cmd
     * @param string    $host
     * @param string    $text
     * @return mixed
     */
    public function parserText($cmd, string $host, string $text)
    {
        return preg_replace_callback("/kv:\/\/([_a-zA-Z0-9\-\/]+)/", function($a) use ($host, $cmd){
            if (false === ($temp = $cmd->readConsulKey($host, $a[1]))) {
                return "";
            }
            return $temp;
        }, $text);
    }

    public function parserYml($data, $prefix = "")
    {
        $content = "";
        $eol = "\n";
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $content .= $prefix."{$key}: ".$eol;
                $content .= $this->parserYml($value, $prefix."\t");
            } else {
                $content .= $prefix."{$key}: {$value}".$eol;
            }
        }
        return $content;
    }

    /**
     * 扫描配置
     * 遍历配置文件目录config下的全部php/yml文件, 并将内容
     * 合并成一个完整的数组
     * @return array|false
     */
    public function scanner()
    {
        // 1. directory validator
        $path = $this->di->basePath().'/config';
        if (!is_dir($path)) {
            $this->error("[ERROR] - 目录{{$path}}不合法");
            return false;
        }
        // 2. loop config directory
        $this->info("[INFO] 遍历{{$path}}目录下的配置文件");
        $d = dir($path);
        $data = [];
        while (false !== ($e = $d->read())) {
            // 3. ignore directory
            if (preg_match("/^\./", $e) > 0) {
                continue;
            }
            // 4. ignore file which not php/yml
            if (preg_match("/(\S+)\.(php|yml)$/i", $e, $m) === 0) {
                continue;
            }
            // 5. read config contents
            $this->output->writeln("       发现{{$e}}文件");
            switch ($m[2]) {
                case 'php' :
                    $this->phpScanner($data, $m[1], $path.'/'.$e);
                    break;
                case 'yml' :
                    $this->ymlScanner($data, $m[1], $path.'/'.$e);
                    break;
            }
        }
        $d->close();
        ksort($data);
        reset($data);
        return $data;
    }

    /**
     * @param string $host
     * @param string $name
     * @param array  $data
     * @return bool|string
     */
    public function sendConsulKey(string $host, string $name, array $data)
    {
        try {
            // 1. send consul
            $url = "http://{$host}/v1/kv/{$name}?cas=0";
            $this->info("[INFO] HttpClient以{PUT}到{{$url}}接口");
            $this->httpClient->put($url, [
                'body' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ])->getBody()->getContents();
            return true;
        } catch(\Throwable $e) {
            $this->error("[ERROR] 上传失败 - {$e->getMessage()}");
        } finally {
            $this->info("[INFO] 上传完成");
        }
        return false;
    }

    /**
     * @param $data
     * @param $name
     * @param $filename
     * @param $filepath
     */
    private function phpWriter($data, $name, $filename, $filepath)
    {
        // 1. make array
        $conf = include($filepath);
        $conf = is_array($conf) ? $conf : [];
        // 2. merge
        if (isset($data[$name], $data[$name]['value']) && is_array($data[$name]['value'])) {
            $conf = array_replace_recursive($conf, $data[$name]['value']);
        }
        // 3. generate contents
        $contents = "<?php\n";
        $contents .= "return unserialize('".serialize($conf)."');\n";
        // 4. check directory
        $target = $this->di->tmpPath().'/config';
        if (!is_dir($target)) {
            @mkdir($target, 0777, true);
        }
        // 5. save target file
        $targetFile = $target.'/'.$filename;
        $this->output->writeln("       写入{{$filename}}文件");
        try {
            file_put_contents($targetFile, $contents);
        } catch(\Throwable $e) {
            $this->error("[ERROR] 写入{{$targetFile}}失败 - {{$e->getMessage()}}");
        }
    }

    /**
     * @param $data
     * @param $name
     * @param $filename
     * @param $filepath
     */
    private function ymlWriter($data, $name, $filename, $filepath)
    {
        // 1. make array
        $conf = Yaml::parse(file_get_contents($filepath));
        $conf = is_array($conf) ? $conf : [];
        // 2. merge
        if (isset($data[$name], $data[$name]['value']) && is_array($data[$name]['value'])) {
            $conf = array_replace_recursive($conf, $data[$name]['value']);
        }
        // 3. generate contents
        $contents = $this->parserYml($conf);
        // 4. check directory
        $target = $this->di->tmpPath().'/config';
        if (!is_dir($target)) {
            @mkdir($target, 0777, true);
        }
        // 5. save target file
        $targetFile = $target.'/'.$filename;
        $this->output->writeln("       写入{{$filename}}文件");
        try {
            file_put_contents($targetFile, $contents);
        } catch(\Throwable $e) {
            $this->error("[ERROR] 写入失败 - {{$e->getMessage()}}");
        }
    }

    /**
     * 解析PHP文件
     * @param array  $data
     * @param string $name
     * @param string $file
     */
    private function phpScanner(& $data, $name, $file)
    {
        // 1. open default
        $conf = include($file);
        $conf = is_array($conf) ? $conf : [];
        // 2. parser environment
        if (isset($conf['development']) && isset($conf['production'])) {
            $env = $this->di->environment();
            if ($conf[$env]) {
                foreach ($conf as $key => $value) {
                    if ($key !== $env) {
                        unset($conf[$key]);
                    }
                }
            }
        }
        // 3. return
        $data[$name] = [
            'type' => 'php',
            'value' => $conf
        ];
    }

    /**
     * 解析YML文件
     * @param array  $data
     * @param string $name
     * @param string $file
     */
    private function ymlScanner(& $data, $name, $file)
    {
        $data[$name] = [
            'type' => 'yml',
            'value' => Yaml::parse(file_get_contents($file))
        ];
    }
}
