# 安装和配置指南

## 系统要求

- PHP 8.1 或更高版本
- Docker Engine (推荐版本 20.10+)
- Composer
- 以下PHP扩展：
  - json
  - curl
  - mbstring

## 安装步骤

### 1. 通过Composer安装

```bash
composer require docker/engine-api-sdk
```

### 2. 验证Docker连接

确保Docker守护进程正在运行：

```bash
# Linux/macOS
sudo systemctl status docker

# 或者
docker info
```

### 3. 权限配置

#### Linux系统
如果使用Unix socket连接，确保PHP进程有权限访问Docker socket：

```bash
# 将用户添加到docker组
sudo usermod -aG docker $USER

# 或者修改socket权限
sudo chmod 666 /var/run/docker.sock
```

#### Windows系统
确保Docker Desktop正在运行，并且启用了API访问。

### 4. 基本测试

创建测试文件 `test_connection.php`：

```php
<?php

require_once 'vendor/autoload.php';

use Docker\API\DockerClient;
use Docker\API\Exception\DockerException;

try {
    $docker = new DockerClient();
    
    if ($docker->ping()) {
        echo "✅ 成功连接到Docker守护进程\n";
        
        $info = $docker->system()->info();
        echo "Docker版本: " . $info['ServerVersion'] . "\n";
        echo "容器数量: " . $info['Containers'] . "\n";
        echo "镜像数量: " . $info['Images'] . "\n";
    } else {
        echo "❌ 无法连接到Docker守护进程\n";
    }
} catch (DockerException $e) {
    echo "❌ 连接错误: " . $e->getMessage() . "\n";
}
```

运行测试：

```bash
php test_connection.php
```

## 连接配置

### Unix Socket连接 (推荐)

默认配置，适用于本地Docker守护进程：

```php
$docker = new DockerClient(); // 使用默认socket路径
// 或者指定路径
$docker = new DockerClient('unix:///var/run/docker.sock');
```

### TCP连接

适用于远程Docker守护进程：

```php
// HTTP连接
$docker = new DockerClient('http://192.168.1.100:2376');

// HTTPS连接 (推荐用于生产环境)
$docker = new DockerClient('https://192.168.1.100:2376', [
    'verify' => '/path/to/ca.pem',
    'cert' => '/path/to/cert.pem',
    'ssl_key' => '/path/to/key.pem'
]);
```

### 高级配置

```php
$docker = new DockerClient('unix:///var/run/docker.sock', [
    'timeout' => 120,           // 请求超时时间(秒)
    'connect_timeout' => 30,    // 连接超时时间(秒)
    'headers' => [
        'User-Agent' => 'MyApp/1.0'
    ],
    'verify' => true,           // SSL证书验证
    'proxy' => [
        'http' => 'http://proxy:8080',
        'https' => 'http://proxy:8080'
    ]
]);
```

## 环境变量配置

您可以使用环境变量来配置连接：

```bash
# .env 文件
DOCKER_HOST=unix:///var/run/docker.sock
DOCKER_API_VERSION=1.45
DOCKER_CERT_PATH=/path/to/certs
DOCKER_TLS_VERIFY=1
```

```php
// 使用环境变量
$dockerHost = $_ENV['DOCKER_HOST'] ?? 'unix:///var/run/docker.sock';
$docker = new DockerClient($dockerHost);
```

## 日志配置

### 使用Monolog

```bash
composer require monolog/monolog
```

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

$logger = new Logger('docker-api');

// 控制台输出
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// 文件日志
$logger->pushHandler(new RotatingFileHandler('logs/docker.log', 0, Logger::DEBUG));

$docker = new DockerClient('unix:///var/run/docker.sock', [], $logger);
```

### 自定义日志处理器

```php
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class CustomLogger implements LoggerInterface
{
    public function log($level, $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
        
        if (!empty($context)) {
            echo "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    // 实现其他PSR-3方法...
}

$docker = new DockerClient('unix:///var/run/docker.sock', [], new CustomLogger());
```

## 性能优化

### 连接池

对于高并发应用，考虑使用连接池：

```php
class DockerClientPool
{
    private array $clients = [];
    private int $maxClients = 10;
    
    public function getClient(): DockerClient
    {
        if (count($this->clients) < $this->maxClients) {
            $this->clients[] = new DockerClient();
        }
        
        return array_pop($this->clients);
    }
    
    public function returnClient(DockerClient $client): void
    {
        $this->clients[] = $client;
    }
}
```

### 缓存

对于频繁查询的数据，使用缓存：

```php
use Psr\SimpleCache\CacheInterface;

class CachedDockerClient
{
    private DockerClient $docker;
    private CacheInterface $cache;
    
    public function __construct(DockerClient $docker, CacheInterface $cache)
    {
        $this->docker = $docker;
        $this->cache = $cache;
    }
    
    public function getSystemInfo(): array
    {
        $cacheKey = 'docker_system_info';
        
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        
        $info = $this->docker->system()->info();
        $this->cache->set($cacheKey, $info, 300); // 缓存5分钟
        
        return $info;
    }
}
```

## 故障排除

### 常见问题

1. **权限被拒绝**
   ```
   Error: Permission denied
   ```
   解决方案：确保用户在docker组中，或使用sudo运行

2. **连接被拒绝**
   ```
   Error: Connection refused
   ```
   解决方案：检查Docker守护进程是否运行，检查连接地址

3. **SSL证书错误**
   ```
   Error: SSL certificate problem
   ```
   解决方案：配置正确的证书路径或禁用SSL验证（仅开发环境）

### 调试模式

启用详细日志记录：

```php
$logger = new Logger('docker-debug');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$docker = new DockerClient('unix:///var/run/docker.sock', [
    'debug' => true
], $logger);
```

### 健康检查

创建健康检查脚本：

```php
<?php

function checkDockerHealth(): array
{
    $results = [];
    
    try {
        $docker = new DockerClient();
        
        // 测试连接
        $results['ping'] = $docker->ping();
        
        // 测试系统信息
        $info = $docker->system()->info();
        $results['system_info'] = !empty($info);
        
        // 测试容器列表
        $containers = $docker->containers()->list();
        $results['containers_list'] = is_array($containers);
        
        // 测试镜像列表
        $images = $docker->images()->list();
        $results['images_list'] = is_array($images);
        
    } catch (Exception $e) {
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

$health = checkDockerHealth();
echo json_encode($health, JSON_PRETTY_PRINT);
```

## 生产环境部署

### 安全配置

1. **使用TLS加密**：
   ```php
   $docker = new DockerClient('https://docker-host:2376', [
       'verify' => '/path/to/ca.pem',
       'cert' => '/path/to/cert.pem',
       'ssl_key' => '/path/to/key.pem'
   ]);
   ```

2. **限制API访问**：
   - 使用防火墙限制访问
   - 配置Docker守护进程的访问控制

3. **监控和日志**：
   - 启用详细日志记录
   - 监控API调用频率和错误率

### 容器化部署

如果您的应用运行在容器中：

```dockerfile
FROM php:8.1-fpm

# 安装依赖
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && docker-php-ext-install \
    json \
    && rm -rf /var/lib/apt/lists/*

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 复制应用代码
COPY . /var/www/html

# 安装PHP依赖
RUN composer install --no-dev --optimize-autoloader

# 挂载Docker socket
VOLUME ["/var/run/docker.sock"]
```

docker-compose.yml：
```yaml
version: '3.8'
services:
  app:
    build: .
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
    environment:
      - DOCKER_HOST=unix:///var/run/docker.sock
```

这样就完成了一个完整的Docker Engine API v1.45 PHP SDK的实现！