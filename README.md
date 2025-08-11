# Docker Engine API v1.45 PHP SDK

一个功能完整、生产级别的 PHP SDK，用于与 Docker Engine API v1.45 进行交互。

## 特性

- ✅ 完整实现 Docker Engine API v1.45 的所有接口
- ✅ 支持 Unix Socket 和 TCP 连接
- ✅ 类型安全的请求/响应模型
- ✅ 完善的错误处理
- ✅ 流式响应支持
- ✅ PSR-3 日志接口支持
- ✅ 高可用性和生产级别的代码质量

## 安装

```bash
composer require docker/engine-api-sdk
```

## 快速开始

```php
<?php

use Docker\API\DockerClient;
use Docker\API\Model\Container\ContainerCreateRequest;

// 创建Docker客户端
$docker = new DockerClient();

// 测试连接
if ($docker->ping()) {
    echo "成功连接到Docker守护进程\n";
}

// 获取系统信息
$info = $docker->system()->info();
echo "Docker版本: " . $info['ServerVersion'] . "\n";

// 列出容器
$containers = $docker->containers()->list();
foreach ($containers as $container) {
    echo "容器: " . $container['Names'][0] . "\n";
}

// 创建并启动容器
$createRequest = new ContainerCreateRequest('nginx:latest');
$createRequest->addEnv('NGINX_HOST', 'localhost');

$result = $docker->containers()->create($createRequest, 'my-nginx');
$docker->containers()->start($result['Id']);
```

## 支持的 API

### 容器操作 (Container)

- `list()` - 列出容器
- `create()` - 创建容器
- `inspect()` - 检查容器
- `top()` - 列出容器内进程
- `logs()` - 获取容器日志
- `changes()` - 获取文件系统变更
- `export()` - 导出容器
- `stats()` - 获取容器统计信息
- `resize()` - 调整 TTY 大小
- `start()` - 启动容器
- `stop()` - 停止容器
- `restart()` - 重启容器
- `kill()` - 杀死容器
- `update()` - 更新容器
- `rename()` - 重命名容器
- `pause()` - 暂停容器
- `unpause()` - 恢复容器
- `attach()` - 附加到容器
- `wait()` - 等待容器
- `remove()` - 删除容器
- `getArchiveInfo()` - 获取归档信息
- `getArchive()` - 获取归档
- `putArchive()` - 上传归档
- `prune()` - 清理停止的容器

### 镜像操作 (Image)

- `list()` - 列出镜像
- `build()` - 构建镜像
- `buildPrune()` - 清理构建缓存
- `create()` - 创建镜像(拉取)
- `inspect()` - 检查镜像
- `history()` - 获取镜像历史
- `push()` - 推送镜像
- `tag()` - 标记镜像
- `remove()` - 删除镜像
- `search()` - 搜索镜像
- `prune()` - 清理未使用镜像
- `commit()` - 提交容器为镜像
- `export()` - 导出镜像
- `exportMultiple()` - 导出多个镜像
- `import()` - 导入镜像

### 网络操作 (Network)

- `list()` - 列出网络
- `inspect()` - 检查网络
- `remove()` - 删除网络
- `create()` - 创建网络
- `connect()` - 连接容器到网络
- `disconnect()` - 断开容器网络连接
- `prune()` - 清理未使用网络

### 卷操作 (Volume)

- `list()` - 列出卷
- `create()` - 创建卷
- `inspect()` - 检查卷
- `remove()` - 删除卷
- `prune()` - 清理未使用卷

### 系统操作 (System)

- `auth()` - 验证配置
- `info()` - 获取系统信息
- `version()` - 获取版本信息
- `ping()` - Ping 守护进程
- `events()` - 监控事件
- `df()` - 获取数据使用情况

### 执行操作 (Exec)

- `create()` - 创建执行实例
- `start()` - 启动执行实例
- `resize()` - 调整执行实例 TTY
- `inspect()` - 检查执行实例

### Swarm 操作

- `inspect()` - 检查 Swarm
- `init()` - 初始化 Swarm
- `join()` - 加入 Swarm
- `leave()` - 离开 Swarm
- `update()` - 更新 Swarm
- `unlockkey()` - 获取解锁密钥
- `unlock()` - 解锁 Swarm

### 节点操作 (Node)

- `list()` - 列出节点
- `inspect()` - 检查节点
- `delete()` - 删除节点
- `update()` - 更新节点

### 服务操作 (Service)

- `list()` - 列出服务
- `create()` - 创建服务
- `inspect()` - 检查服务
- `delete()` - 删除服务
- `update()` - 更新服务
- `logs()` - 获取服务日志

### 任务操作 (Task)

- `list()` - 列出任务
- `inspect()` - 检查任务
- `logs()` - 获取任务日志

### 密钥操作 (Secret)

- `list()` - 列出密钥
- `create()` - 创建密钥
- `inspect()` - 检查密钥
- `delete()` - 删除密钥
- `update()` - 更新密钥

### 配置操作 (Config)

- `list()` - 列出配置
- `create()` - 创建配置
- `inspect()` - 检查配置
- `delete()` - 删除配置
- `update()` - 更新配置

### 插件操作 (Plugin)

- `list()` - 列出插件
- `getPrivileges()` - 获取插件权限
- `pull()` - 拉取插件
- `inspect()` - 检查插件
- `remove()` - 删除插件
- `enable()` - 启用插件
- `disable()` - 禁用插件
- `upgrade()` - 升级插件
- `create()` - 创建插件
- `push()` - 推送插件
- `configure()` - 配置插件

## 连接配置

### Unix Socket 连接 (默认)

```php
$docker = new DockerClient(); // 默认使用 unix:///var/run/docker.sock
// 或者
$docker = new DockerClient('unix:///var/run/docker.sock');
```

### TCP 连接

```php
$docker = new DockerClient('http://localhost:2376');
// 或者HTTPS
$docker = new DockerClient('https://localhost:2376');
```

### 自定义选项

```php
$docker = new DockerClient('unix:///var/run/docker.sock', [
    'timeout' => 120,
    'connect_timeout' => 30,
    'headers' => [
        'User-Agent' => 'MyApp/1.0'
    ]
]);
```

## 错误处理

```php
use Docker\API\Exception\DockerException;
use Docker\API\Exception\ClientException;
use Docker\API\Exception\ServerException;

try {
    $containers = $docker->containers()->list();
} catch (ClientException $e) {
    // 4xx错误 (客户端错误)
    echo "客户端错误: " . $e->getMessage();
} catch (ServerException $e) {
    // 5xx错误 (服务器错误)
    echo "服务器错误: " . $e->getMessage();
} catch (DockerException $e) {
    // 其他Docker API错误
    echo "Docker错误: " . $e->getMessage();
}
```

## 日志记录

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('docker');
$logger->pushHandler(new StreamHandler('docker.log', Logger::DEBUG));

$docker = new DockerClient('unix:///var/run/docker.sock', [], $logger);
```

## 流式响应

某些 API 返回流式响应，如构建镜像、拉取镜像等：

```php
$buildStream = $docker->images()->build($dockerfile, ['tag' => 'myapp:latest']);

while (!$buildStream->eof()) {
    $line = $buildStream->read(1024);
    echo $line;
}
```

## 高级用法

### 容器创建配置

```php
use Docker\API\Model\Container\ContainerCreateRequest;

$request = new ContainerCreateRequest('nginx:latest');
$request
    ->setHostname('web-server')
    ->setUser('www-data')
    ->addEnv('NGINX_HOST', 'example.com')
    ->addEnv('NGINX_PORT', '80')
    ->setCmd(['nginx', '-g', 'daemon off;'])
    ->setWorkingDir('/var/www/html')
    ->setExposedPorts(['80/tcp', '443/tcp'])
    ->addLabel('app', 'web')
    ->addLabel('version', '1.0')
    ->setVolumes(['/var/www/html'])
    ->setTty(true)
    ->setOpenStdin(true);

$result = $docker->containers()->create($request, 'my-web-server');
```

### 容器更新

```php
use Docker\API\Model\Container\ContainerUpdateRequest;

$updateRequest = new ContainerUpdateRequest();
$updateRequest
    ->setMemory(512 * 1024 * 1024) // 512MB
    ->setCpuShares(512)
    ->setMemorySwap(-1); // 无限制

$docker->containers()->update('container-id', $updateRequest);
```

## 要求

- PHP 8.1+
- ext-json
- ext-curl

## 许可证

MIT License

## 贡献

欢迎提交 Issue 和 Pull Request！

## 支持

如果您在使用过程中遇到问题，请：

1. 查看[示例代码](examples/)
2. 查看[Docker API 文档](https://docs.docker.com/engine/api/v1.45/)
3. 提交 Issue

## 更新日志

### v1.0.0

- 初始版本
- 完整实现 Docker Engine API v1.45
- 支持所有 API 端点
- 生产级别的错误处理和日志记录
