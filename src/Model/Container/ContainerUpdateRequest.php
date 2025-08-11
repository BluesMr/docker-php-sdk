<?php

declare(strict_types=1);

namespace Docker\API\Model\Container;

/**
 * Container update request model
 */
class ContainerUpdateRequest
{
    private array $config = [];

    public function setCpuShares(int $cpuShares): self
    {
        $this->config['CpuShares'] = $cpuShares;
        return $this;
    }

    public function setMemory(int $memory): self
    {
        $this->config['Memory'] = $memory;
        return $this;
    }

    public function setCgroupParent(string $cgroupParent): self
    {
        $this->config['CgroupParent'] = $cgroupParent;
        return $this;
    }

    public function setBlkioWeight(int $blkioWeight): self
    {
        $this->config['BlkioWeight'] = $blkioWeight;
        return $this;
    }

    public function setCpuPeriod(int $cpuPeriod): self
    {
        $this->config['CpuPeriod'] = $cpuPeriod;
        return $this;
    }

    public function setCpuQuota(int $cpuQuota): self
    {
        $this->config['CpuQuota'] = $cpuQuota;
        return $this;
    }

    public function setCpuRealtimePeriod(int $cpuRealtimePeriod): self
    {
        $this->config['CpuRealtimePeriod'] = $cpuRealtimePeriod;
        return $this;
    }

    public function setCpuRealtimeRuntime(int $cpuRealtimeRuntime): self
    {
        $this->config['CpuRealtimeRuntime'] = $cpuRealtimeRuntime;
        return $this;
    }

    public function setCpusetCpus(string $cpusetCpus): self
    {
        $this->config['CpusetCpus'] = $cpusetCpus;
        return $this;
    }

    public function setCpusetMems(string $cpusetMems): self
    {
        $this->config['CpusetMems'] = $cpusetMems;
        return $this;
    }

    public function setMemoryReservation(int $memoryReservation): self
    {
        $this->config['MemoryReservation'] = $memoryReservation;
        return $this;
    }

    public function setMemorySwap(int $memorySwap): self
    {
        $this->config['MemorySwap'] = $memorySwap;
        return $this;
    }

    public function setMemorySwappiness(int $memorySwappiness): self
    {
        $this->config['MemorySwappiness'] = $memorySwappiness;
        return $this;
    }

    public function setNanoCpus(int $nanoCpus): self
    {
        $this->config['NanoCpus'] = $nanoCpus;
        return $this;
    }

    public function setOomKillDisable(bool $oomKillDisable): self
    {
        $this->config['OomKillDisable'] = $oomKillDisable;
        return $this;
    }

    public function setInit(bool $init): self
    {
        $this->config['Init'] = $init;
        return $this;
    }

    public function setPidsLimit(int $pidsLimit): self
    {
        $this->config['PidsLimit'] = $pidsLimit;
        return $this;
    }

    public function setRestartPolicy(array $restartPolicy): self
    {
        $this->config['RestartPolicy'] = $restartPolicy;
        return $this;
    }

    public function toArray(): array
    {
        return $this->config;
    }
}