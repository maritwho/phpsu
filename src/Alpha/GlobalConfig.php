<?php
declare(strict_types=1);

namespace PHPSu\Alpha;

final class GlobalConfig
{
    use AddFilesystemTrait;
    use AddDatabaseTrait;

    /** @var SshConnections */
    private $sshConnections;

    /** @var AppInstances */
    private $appInstances;

    public function __construct()
    {
        $this->sshConnections = new SshConnections();
        $this->appInstances = new AppInstances();
        $this->fileSystems = new FileSystems();
        $this->databases = new Databases();
    }

    public function addSshConnectionObject(SshConnection $sshConnection): GlobalConfig
    {
        $this->sshConnections->add($sshConnection);
        return $this;
    }

    public function addSshConnection(string $host, string $url, array $options = []): SshConnection
    {
        $sshConnection = new SshConnection();
        $sshConnection->setHost($host)->setUrl($url)->setOptions($options);
        $this->sshConnections->add($sshConnection);
        return $sshConnection;
    }

    public function addAppInstanceObject(AppInstance $appInstance): GlobalConfig
    {
        $this->appInstances->add($appInstance);
        return $this;
    }

    public function addAppInstance(string $name, string $host = '', string $path = ''): AppInstance
    {
        $appInstance = new AppInstance();
        $appInstance->setName($name)->setHost($host)->setPath($path);
        $this->appInstances->add($appInstance);
        return $appInstance;
    }

    public function getSshConnections(): SshConnections
    {
        return $this->sshConnections;
    }

    /**
     * @return FileSystem[]
     */
    public function getFileSystems(): array
    {
        return $this->fileSystems->getAll();
    }

    /**
     * @return Database[]
     */
    public function getDatabases(): array
    {
        return $this->databases->getAll();
    }

    /**
     * @return AppInstance[]
     */
    public function getAppInstances(): array
    {
        return $this->appInstances->getAll();
    }

    /**
     * @param string $host
     * @throws \Exception
     */
    public function validateConnectionToHost(string $host): void
    {
        $this->sshConnections->getPossibilities($host);
    }

    public function getAppInstance(string $appName): AppInstance
    {
        return $this->appInstances->get($appName);
    }

    public function getHostName(string $connectionName): string
    {
        if ($this->appInstances->has($connectionName)) {
            return $this->getAppInstance($connectionName)->getHost();
        }
        return $connectionName;
    }
}
