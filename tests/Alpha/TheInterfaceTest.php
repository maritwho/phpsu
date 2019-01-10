<?php
declare(strict_types=1);

namespace PHPSu\Tests\Alpha;

use PHPSu\Alpha\AppInstance;
use PHPSu\Alpha\Database;
use PHPSu\Alpha\FileSystem;
use PHPSu\Alpha\GlobalConfig;
use PHPSu\Alpha\SshConnection;
use PHPSu\Alpha\TheInterface;
use PHPUnit\Framework\TestCase;

class TheInterfaceTest extends TestCase
{
    private static function getGlobalConfig(): GlobalConfig
    {
        $global = new GlobalConfig();
        $global->addFilesystemObject((new FileSystem())->setName('fileadmin')->setPath('fileadmin'));
        $global->addFilesystemObject((new FileSystem())->setName('uploads')->setPath('uploads'));
        $global->addDatabaseObject((new Database())->setName('app')->setUrl('mysql://user:pw@host:3307/database'));
        $global->addSshConnectionObject((new SshConnection())->setHost('serverEu')->setUrl('user@server.eu'));
        $global->addSshConnectionObject((new SshConnection())->setHost('stagingServer')->setUrl('staging@stagingServer.server.eu'));
        $global->addAppInstanceObject((new AppInstance())->setName('production')->setHost('serverEu')->setPath('/var/www/production')->addFilesystemObject(
            (new FileSystem())->setName('fileadmin')->setPath('fileadmin2')
        )->addDatabaseObject(
            (new Database())->setName('app')->setUrl('mysql://root:root@appHost/appDatabase')
        ));
        $global->addAppInstanceObject((new AppInstance())->setName('staging')->setHost('stagingServer')->setPath('/var/www/staging'));
        $global->addAppInstanceObject((new AppInstance())->setName('testing')->setHost('serverEu')->setPath('/var/www/testing'));
        $global->addAppInstanceObject((new AppInstance())->setName('local')->setHost('')->setPath('./'));
        $global->addAppInstanceObject((new AppInstance())->setName('local2')->setHost('')->setPath('../local2'));
        return $global;
    }

    public function testProductionToLocalFromAnyThere(): void
    {
        $interface = new TheInterface();
        $interface->setFile($file = new \SplTempFileObject());
        $global = static::getGlobalConfig();

        $result = $interface->getCommands($global, 'production', 'local', '');
        $this->assertSame([
            'filesystem:fileadmin' => 'rsync -avz -e "ssh -F php://temp" serverEu:/var/www/production/fileadmin2/* ./fileadmin/',
            'filesystem:uploads' => 'rsync -avz -e "ssh -F php://temp" serverEu:/var/www/production/uploads/* ./uploads/',
            'database:app' => 'ssh -F php://temp serverEu -C "mysqldump --skip-comments --extended-insert -happHost -P3306 -uroot -proot appDatabase" | mysql -hhost -P3307 -uuser -ppw database',
        ], $result);
        $expectedSshConfigString = <<<'SSH_CONFIG'
Host serverEu
  HostName server.eu
  User user

Host stagingServer
  HostName stagingServer.server.eu
  User staging


SSH_CONFIG;
        $this->assertSame($expectedSshConfigString, implode('', iterator_to_array($file)));
    }

    public function testProductionToTestingFromAnyThere(): void
    {
        $interface = new TheInterface();
        $interface->setFile($file = new \SplTempFileObject());
        $global = static::getGlobalConfig();

        $result = $interface->getCommands($global, 'production', 'testing', '');
        $this->assertSame([
            'filesystem:fileadmin' => 'ssh -F php://temp serverEu -C "rsync -avz /var/www/production/fileadmin2/* /var/www/testing/fileadmin/"',
            'filesystem:uploads' => 'ssh -F php://temp serverEu -C "rsync -avz /var/www/production/uploads/* /var/www/testing/uploads/"',
            'database:app' => 'ssh -F php://temp serverEu -C "mysqldump --skip-comments --extended-insert -happHost -P3306 -uroot -proot appDatabase | mysql -hhost -P3307 -uuser -ppw database"',
        ], $result);
        $expectedSshConfigString = <<<'SSH_CONFIG'
Host serverEu
  HostName server.eu
  User user

Host stagingServer
  HostName stagingServer.server.eu
  User staging


SSH_CONFIG;
        $this->assertSame($expectedSshConfigString, implode('', iterator_to_array($file)));
    }

    public function testLocalToLocal2FromAnyThere(): void
    {
        $interface = new TheInterface();
        $interface->setFile($file = new \SplTempFileObject());
        $global = static::getGlobalConfig();

        $result = $interface->getCommands($global, 'local', 'local2', '');
        $this->assertSame([
            'filesystem:fileadmin' => 'rsync -avz ./fileadmin/* ../local2/fileadmin/',
            'filesystem:uploads' => 'rsync -avz ./uploads/* ../local2/uploads/',
            'database:app' => 'mysqldump --skip-comments --extended-insert -hhost -P3307 -uuser -ppw database | mysql -hhost -P3307 -uuser -ppw database',
        ], $result);
        $expectedSshConfigString = <<<'SSH_CONFIG'
Host serverEu
  HostName server.eu
  User user

Host stagingServer
  HostName stagingServer.server.eu
  User staging


SSH_CONFIG;
        $this->assertSame($expectedSshConfigString, implode('', iterator_to_array($file)));
    }

    public function testProductionToStagingFromAnyThere(): void
    {
        $interface = new TheInterface();
        $interface->setFile($file = new \SplTempFileObject());
        $global = static::getGlobalConfig();

        $result = $interface->getCommands($global, 'production', 'staging', '');
        $this->assertSame([
            'filesystem:fileadmin' => 'rsync -avz -e "ssh -F php://temp" serverEu:/var/www/production/fileadmin2/* stagingServer:/var/www/staging/fileadmin/',
            'filesystem:uploads' => 'rsync -avz -e "ssh -F php://temp" serverEu:/var/www/production/uploads/* stagingServer:/var/www/staging/uploads/',
            'database:app' => 'ssh -F php://temp serverEu -C "mysqldump --skip-comments --extended-insert -happHost -P3306 -uroot -proot appDatabase" | ssh -F php://temp stagingServer -C "mysql -hhost -P3307 -uuser -ppw database"',
        ], $result);
        $expectedSshConfigString = <<<'SSH_CONFIG'
Host serverEu
  HostName server.eu
  User user

Host stagingServer
  HostName stagingServer.server.eu
  User staging


SSH_CONFIG;
        $this->assertSame($expectedSshConfigString, implode('', iterator_to_array($file)));
    }

    public function testProductionToStagingFromStaging(): void
    {
        $interface = new TheInterface();
        $interface->setFile($file = new \SplTempFileObject());
        $global = static::getGlobalConfig();

        $result = $interface->getCommands($global, 'production', 'staging', 'stagingServer');
        $this->assertSame([
            'filesystem:fileadmin' => 'rsync -avz -e "ssh -F php://temp" serverEu:/var/www/production/fileadmin2/* /var/www/staging/fileadmin/',
            'filesystem:uploads' => 'rsync -avz -e "ssh -F php://temp" serverEu:/var/www/production/uploads/* /var/www/staging/uploads/',
            'database:app' => 'ssh -F php://temp serverEu -C "mysqldump --skip-comments --extended-insert -happHost -P3306 -uroot -proot appDatabase" | mysql -hhost -P3307 -uuser -ppw database',
        ], $result);
        $expectedSshConfigString = <<<'SSH_CONFIG'
Host serverEu
  HostName server.eu
  User user


SSH_CONFIG;
        $this->assertSame($expectedSshConfigString, implode('', iterator_to_array($file)));
    }

    public function testStagingToProductionFromStaging(): void
    {
        $interface = new TheInterface();
        $interface->setFile($file = new \SplTempFileObject());
        $global = static::getGlobalConfig();

        $result = $interface->getCommands($global, 'staging', 'production', 'stagingServer');
        $this->assertSame([
            'filesystem:fileadmin' => 'rsync -avz -e "ssh -F php://temp" /var/www/staging/fileadmin/* serverEu:/var/www/production/fileadmin2/',
            'filesystem:uploads' => 'rsync -avz -e "ssh -F php://temp" /var/www/staging/uploads/* serverEu:/var/www/production/uploads/',
            'database:app' => 'mysqldump --skip-comments --extended-insert -hhost -P3307 -uuser -ppw database | ssh -F php://temp serverEu -C "mysql -happHost -P3306 -uroot -proot appDatabase"',
        ], $result);
        $expectedSshConfigString = <<<'SSH_CONFIG'
Host serverEu
  HostName server.eu
  User user


SSH_CONFIG;
        $this->assertSame($expectedSshConfigString, implode('', iterator_to_array($file)));
    }
}
