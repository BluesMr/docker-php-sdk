<?php

declare(strict_types=1);

namespace Docker\API\Model\Container;

/**
 * Container creation request model
 */
class ContainerCreateRequest
{
    private array $config = [];

    public function __construct(string $image)
    {
        $this->config['Image'] = $image;
    }

    public function setHostname(string $hostname): self
    {
        $this->config['Hostname'] = $hostname;
        return $this;
    }

    public function setDomainname(string $domainname): self
    {
        $this->config['Domainname'] = $domainname;
        return $this;
    }

    public function setUser(string $user): self
    {
        $this->config['User'] = $user;
        return $this;
    }

    public function setAttachStdin(bool $attach): self
    {
        $this->config['AttachStdin'] = $attach;
        return $this;
    }

    public function setAttachStdout(bool $attach): self
    {
        $this->config['AttachStdout'] = $attach;
        return $this;
    }

    public function setAttachStderr(bool $attach): self
    {
        $this->config['AttachStderr'] = $attach;
        return $this;
    }

    public function setTty(bool $tty): self
    {
        $this->config['Tty'] = $tty;
        return $this;
    }

    public function setOpenStdin(bool $open): self
    {
        $this->config['OpenStdin'] = $open;
        return $this;
    }

    public function setStdinOnce(bool $once): self
    {
        $this->config['StdinOnce'] = $once;
        return $this;
    }

    public function setEnv(array $env): self
    {
        $this->config['Env'] = $env;
        return $this;
    }

    public function addEnv(string $key, string $value): self
    {
        if (!isset($this->config['Env'])) {
            $this->config['Env'] = [];
        }
        $this->config['Env'][] = "{$key}={$value}";
        return $this;
    }

    public function setCmd(array $cmd): self
    {
        $this->config['Cmd'] = $cmd;
        return $this;
    }

    public function setEntrypoint(array $entrypoint): self
    {
        $this->config['Entrypoint'] = $entrypoint;
        return $this;
    }

    public function setWorkingDir(string $workingDir): self
    {
        $this->config['WorkingDir'] = $workingDir;
        return $this;
    }

    public function setLabels(array $labels): self
    {
        $this->config['Labels'] = $labels;
        return $this;
    }

    public function addLabel(string $key, string $value): self
    {
        if (!isset($this->config['Labels'])) {
            $this->config['Labels'] = [];
        }
        $this->config['Labels'][$key] = $value;
        return $this;
    }

    public function setExposedPorts(array $ports): self
    {
        $exposedPorts = [];
        foreach ($ports as $port) {
            $exposedPorts[$port] = new \stdClass();
        }
        $this->config['ExposedPorts'] = $exposedPorts;
        return $this;
    }

    public function setVolumes(array $volumes): self
    {
        $volumeConfig = [];
        foreach ($volumes as $volume) {
            $volumeConfig[$volume] = new \stdClass();
        }
        $this->config['Volumes'] = $volumeConfig;
        return $this;
    }

    public function setNetworkDisabled(bool $disabled): self
    {
        $this->config['NetworkDisabled'] = $disabled;
        return $this;
    }

    public function setMacAddress(string $macAddress): self
    {
        $this->config['MacAddress'] = $macAddress;
        return $this;
    }

    public function setOnBuild(array $onBuild): self
    {
        $this->config['OnBuild'] = $onBuild;
        return $this;
    }

    public function setStopSignal(string $signal): self
    {
        $this->config['StopSignal'] = $signal;
        return $this;
    }

    public function setStopTimeout(int $timeout): self
    {
        $this->config['StopTimeout'] = $timeout;
        return $this;
    }

    public function setShell(array $shell): self
    {
        $this->config['Shell'] = $shell;
        return $this;
    }

    public function setHostConfig(array $hostConfig): self
    {
        $this->config['HostConfig'] = $hostConfig;
        return $this;
    }

    public function setNetworkingConfig(array $networkingConfig): self
    {
        $this->config['NetworkingConfig'] = $networkingConfig;
        return $this;
    }

    public function toArray(): array
    {
        return $this->config;
    }
}