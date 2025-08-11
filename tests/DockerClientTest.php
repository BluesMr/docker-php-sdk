<?php

declare(strict_types=1);

namespace Docker\API\Tests;

use Docker\API\DockerClient;
use Docker\API\Exception\DockerException;
use PHPUnit\Framework\TestCase;

class DockerClientTest extends TestCase
{
    private DockerClient $docker;

    protected function setUp(): void
    {
        $this->docker = new DockerClient();
    }

    public function testPing(): void
    {
        $this->assertTrue($this->docker->ping());
    }

    public function testSystemInfo(): void
    {
        $info = $this->docker->system()->info();
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('ServerVersion', $info);
        $this->assertArrayHasKey('Containers', $info);
        $this->assertArrayHasKey('Images', $info);
    }

    public function testVersion(): void
    {
        $version = $this->docker->system()->version();
        
        $this->assertIsArray($version);
        $this->assertArrayHasKey('Version', $version);
        $this->assertArrayHasKey('ApiVersion', $version);
    }

    public function testListContainers(): void
    {
        $containers = $this->docker->containers()->list();
        
        $this->assertIsArray($containers);
    }

    public function testListImages(): void
    {
        $images = $this->docker->images()->list();
        
        $this->assertIsArray($images);
    }

    public function testListNetworks(): void
    {
        $networks = $this->docker->networks()->list();
        
        $this->assertIsArray($networks);
        $this->assertNotEmpty($networks); // Should have at least bridge network
    }

    public function testListVolumes(): void
    {
        $volumes = $this->docker->volumes()->list();
        
        $this->assertIsArray($volumes);
        $this->assertArrayHasKey('Volumes', $volumes);
    }

    public function testInvalidContainerInspect(): void
    {
        $this->expectException(DockerException::class);
        
        $this->docker->containers()->inspect('non-existent-container');
    }
}