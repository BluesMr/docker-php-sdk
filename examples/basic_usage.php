<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Docker\API\DockerClient;
use Docker\API\Model\Container\ContainerCreateRequest;
use Docker\API\Exception\DockerException;

try {
    // 创建Docker客户端
    // 默认连接到 unix:///var/run/docker.sock
    $docker = new DockerClient();
    
    // 或者连接到TCP端点
    // $docker = new DockerClient('http://localhost:2376');
    
    // 测试连接
    if (!$docker->ping()) {
        echo "无法连接到Docker守护进程\n";
        exit(1);
    }
    
    echo "成功连接到Docker守护进程\n";
    
    // 获取系统信息
    $info = $docker->system()->info();
    echo "Docker版本: " . $info['ServerVersion'] . "\n";
    echo "容器数量: " . $info['Containers'] . "\n";
    echo "镜像数量: " . $info['Images'] . "\n";
    
    // 列出所有容器
    $containers = $docker->containers()->list(['all' => true]);
    echo "找到 " . count($containers) . " 个容器\n";
    
    foreach ($containers as $container) {
        echo "- " . $container['Names'][0] . " (" . $container['State'] . ")\n";
    }
    
    // 列出所有镜像
    $images = $docker->images()->list();
    echo "找到 " . count($images) . " 个镜像\n";
    
    foreach ($images as $image) {
        if (!empty($image['RepoTags'])) {
            echo "- " . $image['RepoTags'][0] . "\n";
        }
    }
    
    // 创建容器示例
    $createRequest = new ContainerCreateRequest('nginx:latest');
    $createRequest
        ->setCmd(['nginx', '-g', 'daemon off;'])
        ->addEnv('NGINX_HOST', 'localhost')
        ->addEnv('NGINX_PORT', '80')
        ->setExposedPorts(['80/tcp'])
        ->addLabel('app', 'web-server')
        ->addLabel('version', '1.0');
    
    // 创建容器
    $result = $docker->containers()->create($createRequest, 'my-nginx-container');
    echo "创建容器: " . $result['Id'] . "\n";
    
    // 启动容器
    $docker->containers()->start($result['Id']);
    echo "容器已启动\n";
    
    // 获取容器详细信息
    $containerInfo = $docker->containers()->inspect($result['Id']);
    echo "容器状态: " . $containerInfo['State']['Status'] . "\n";
    
    // 停止容器
    $docker->containers()->stop($result['Id']);
    echo "容器已停止\n";
    
    // 删除容器
    $docker->containers()->remove($result['Id']);
    echo "容器已删除\n";
    
} catch (DockerException $e) {
    echo "Docker API错误: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}