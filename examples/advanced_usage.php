<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Docker\API\DockerClient;
use Docker\API\Model\Container\ContainerCreateRequest;
use Docker\API\Exception\DockerException;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

try {
    // 创建带日志的Docker客户端
    $logger = new Logger('docker');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    
    $docker = new DockerClient('unix:///var/run/docker.sock', [
        'timeout' => 120,
        'connect_timeout' => 30,
    ], $logger);
    
    echo "=== Docker Engine API v1.45 PHP SDK 高级示例 ===\n\n";
    
    // 1. 系统信息
    echo "1. 获取系统信息\n";
    $info = $docker->system()->info();
    echo "Docker版本: {$info['ServerVersion']}\n";
    echo "操作系统: {$info['OperatingSystem']}\n";
    echo "架构: {$info['Architecture']}\n";
    echo "CPU数量: {$info['NCPU']}\n";
    echo "内存: " . round($info['MemTotal'] / 1024 / 1024 / 1024, 2) . "GB\n\n";
    
    // 2. 拉取镜像
    echo "2. 拉取nginx镜像\n";
    $pullStream = $docker->images()->create('nginx:alpine');
    while (!$pullStream->eof()) {
        $line = $pullStream->read(1024);
        if ($line) {
            $data = json_decode(trim($line), true);
            if ($data && isset($data['status'])) {
                echo "拉取状态: {$data['status']}\n";
            }
        }
    }
    echo "\n";
    
    // 3. 创建网络
    echo "3. 创建自定义网络\n";
    $networkConfig = [
        'Name' => 'my-app-network',
        'Driver' => 'bridge',
        'IPAM' => [
            'Config' => [
                [
                    'Subnet' => '172.20.0.0/16',
                    'Gateway' => '172.20.0.1'
                ]
            ]
        ],
        'Labels' => [
            'app' => 'demo',
            'environment' => 'development'
        ]
    ];
    
    try {
        $network = $docker->networks()->create($networkConfig);
        echo "网络已创建: {$network['Id']}\n";
    } catch (DockerException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "网络已存在\n";
        } else {
            throw $e;
        }
    }
    echo "\n";
    
    // 4. 创建卷
    echo "4. 创建数据卷\n";
    $volumeConfig = [
        'Name' => 'my-app-data',
        'Driver' => 'local',
        'Labels' => [
            'app' => 'demo',
            'type' => 'data'
        ]
    ];
    
    try {
        $volume = $docker->volumes()->create($volumeConfig);
        echo "卷已创建: {$volume['Name']}\n";
    } catch (DockerException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "卷已存在\n";
        } else {
            throw $e;
        }
    }
    echo "\n";
    
    // 5. 创建复杂的容器配置
    echo "5. 创建Web服务器容器\n";
    $containerConfig = new ContainerCreateRequest('nginx:alpine');
    $containerConfig
        ->setHostname('web-server')
        ->addEnv('NGINX_HOST', 'localhost')
        ->addEnv('NGINX_PORT', '80')
        ->setExposedPorts(['80/tcp'])
        ->addLabel('app', 'web-server')
        ->addLabel('version', '1.0')
        ->addLabel('environment', 'development')
        ->setWorkingDir('/usr/share/nginx/html')
        ->setCmd(['nginx', '-g', 'daemon off;'])
        ->setHostConfig([
            'PortBindings' => [
                '80/tcp' => [
                    ['HostPort' => '8080']
                ]
            ],
            'Mounts' => [
                [
                    'Type' => 'volume',
                    'Source' => 'my-app-data',
                    'Target' => '/usr/share/nginx/html',
                    'ReadOnly' => false
                ]
            ],
            'RestartPolicy' => [
                'Name' => 'unless-stopped'
            ],
            'Memory' => 128 * 1024 * 1024, // 128MB
            'CpuShares' => 512
        ])
        ->setNetworkingConfig([
            'EndpointsConfig' => [
                'my-app-network' => [
                    'IPAMConfig' => [
                        'IPv4Address' => '172.20.0.10'
                    ]
                ]
            ]
        ]);
    
    $containerName = 'my-web-server';
    
    // 检查容器是否已存在
    try {
        $existingContainer = $docker->containers()->inspect($containerName);
        echo "删除现有容器...\n";
        $docker->containers()->stop($containerName);
        $docker->containers()->remove($containerName, true);
    } catch (DockerException $e) {
        // 容器不存在，继续
    }
    
    $result = $docker->containers()->create($containerConfig, $containerName);
    echo "容器已创建: {$result['Id']}\n";
    
    // 6. 启动容器
    echo "6. 启动容器\n";
    $docker->containers()->start($result['Id']);
    echo "容器已启动\n\n";
    
    // 7. 检查容器状态
    echo "7. 检查容器状态\n";
    $containerInfo = $docker->containers()->inspect($result['Id']);
    echo "容器状态: {$containerInfo['State']['Status']}\n";
    echo "容器IP: {$containerInfo['NetworkSettings']['Networks']['my-app-network']['IPAddress']}\n";
    echo "端口映射: localhost:8080 -> 80\n\n";
    
    // 8. 在容器中执行命令
    echo "8. 在容器中执行命令\n";
    $execConfig = [
        'AttachStdout' => true,
        'AttachStderr' => true,
        'Cmd' => ['ls', '-la', '/usr/share/nginx/html']
    ];
    
    $execResult = $docker->exec()->create($result['Id'], $execConfig);
    $execOutput = $docker->exec()->start($execResult['Id'], ['Detach' => false]);
    
    echo "执行结果:\n";
    echo $execOutput->getContents() . "\n";
    
    // 9. 获取容器日志
    echo "9. 获取容器日志\n";
    $logs = $docker->containers()->logs($result['Id'], [
        'stdout' => true,
        'stderr' => true,
        'tail' => 10
    ]);
    echo "最近10行日志:\n";
    echo $logs . "\n";
    
    // 10. 获取容器统计信息
    echo "10. 获取容器统计信息\n";
    $stats = $docker->containers()->stats($result['Id'], false, true);
    $memoryUsage = round($stats['memory_stats']['usage'] / 1024 / 1024, 2);
    echo "内存使用: {$memoryUsage}MB\n";
    
    if (isset($stats['cpu_stats']['cpu_usage']['total_usage'])) {
        echo "CPU使用: {$stats['cpu_stats']['cpu_usage']['total_usage']} nanoseconds\n";
    }
    echo "\n";
    
    // 11. 创建镜像快照
    echo "11. 创建容器快照\n";
    $commitResult = $docker->images()->commit($result['Id'], [
        'repo' => 'my-web-server',
        'tag' => 'snapshot',
        'comment' => 'Web server snapshot',
        'author' => 'Docker PHP SDK'
    ]);
    echo "快照已创建: {$commitResult['Id']}\n\n";
    
    // 12. 监控事件 (异步)
    echo "12. 监控Docker事件 (5秒)\n";
    $eventStream = $docker->system()->events([
        'since' => time(),
        'filters' => json_encode([
            'container' => [$containerName]
        ])
    ]);
    
    $startTime = time();
    while (time() - $startTime < 5 && !$eventStream->eof()) {
        $line = $eventStream->read(1024);
        if ($line) {
            $event = json_decode(trim($line), true);
            if ($event) {
                echo "事件: {$event['Action']} - {$event['Actor']['Attributes']['name']}\n";
            }
        }
        usleep(100000); // 100ms
    }
    echo "\n";
    
    echo "=== 演示完成 ===\n";
    echo "Web服务器正在运行在 http://localhost:8080\n";
    echo "容器名称: {$containerName}\n";
    echo "网络: my-app-network\n";
    echo "数据卷: my-app-data\n\n";
    
    echo "清理资源? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) === 'y') {
        echo "\n清理资源...\n";
        
        // 停止并删除容器
        $docker->containers()->stop($result['Id']);
        $docker->containers()->remove($result['Id'], true);
        echo "容器已删除\n";
        
        // 删除镜像快照
        $docker->images()->remove('my-web-server:snapshot');
        echo "快照镜像已删除\n";
        
        // 删除网络
        $docker->networks()->remove('my-app-network');
        echo "网络已删除\n";
        
        // 删除卷
        $docker->volumes()->remove('my-app-data');
        echo "卷已删除\n";
        
        echo "清理完成\n";
    } else {
        echo "\n资源保留，您可以手动清理:\n";
        echo "docker stop {$containerName}\n";
        echo "docker rm {$containerName}\n";
        echo "docker rmi my-web-server:snapshot\n";
        echo "docker network rm my-app-network\n";
        echo "docker volume rm my-app-data\n";
    }
    
} catch (DockerException $e) {
    echo "Docker API错误: " . $e->getMessage() . "\n";
    echo "错误代码: " . $e->getCode() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}