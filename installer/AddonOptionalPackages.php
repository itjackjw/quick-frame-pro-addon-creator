<?php

namespace Installer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\BasePackage;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Seld\JsonLint\ParsingException;

class AddonOptionalPackages
{

    public const PACKAGE_REGEX = '/^(?P<name>[^:]+\/[^:]+)([:]*)(?P<version>.*)$/';

    public IOInterface $io;

    public Composer $composer;

    private array $addonOption;

    private array $config;

    /**
     * 配置文件
     * @var JsonFile
     */
    private JsonFile $composerJson;


    private string $projectRoot;


    private RootPackageInterface $rootPackage;


    private array $stabilityFlags;


    private array $composerRequires;


    private array $composerDevRequires;

    /**
     * @var string
     */
    private string $installerAddonSource;

    private array $assetsToRemove = [
        'composer.lock'
    ];

    /**
     * 开发依赖
     * @var array|string[]
     */
    private array $devDependencies = [
        'composer/composer',
    ];

    public function __construct(IOInterface $io, Composer $composer, string $projectRoot = null)
    {
        $this->io = $io;
        $this->composer = $composer;
        $composerFile = Factory::getComposerFile();
        $this->projectRoot = $projectRoot ?: realpath(dirname($composerFile));
        $this->projectRoot = rtrim($this->projectRoot, '/\\') . '/';
        $this->parseComposerDefinition($this->composer, $composerFile);
        $this->config = $this->AddonConfiguration();
        $this->installerAddonSource = realpath(__DIR__) . '/';
    }

    /**
     * 创建插件
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 15:43
     */
    public function setAddonComposerJson(): void
    {
        $this->createAddonName();
        $this->setAddonDescription();
        $this->setAddonNameSpace();
        $this->setAddonLicense();
        $this->setAddonType();
    }

    /**
     * 设置插件类型
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 16:15
     */
    public function setAddonType(): void
    {
        $this->addonOption['type'] = 'library';
    }

    /**
     * 设置插件描述
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 15:46
     */
    public function setAddonDescription(): void
    {
        $this->addonOption['description'] = $this->io->ask('<info>请输入您的插件描述：</info>', '');
    }


    /**
     * 设置插件名称
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 15:22
     */
    public function createAddonName(): void
    {
        $addonName = $this->io->ask("<info>请输入您的插件名称(quick-frame/demo)：</info>", 'quick-frame/demo');
        $addonName = trim(str_replace('\\', '/', $addonName), '/');
        $this->addonOption['name'] = $addonName;
    }


    /**
     * 设置插件命名空间
     * @param string $addonName
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 16:11
     */
    public function setAddonNameSpace(): void
    {
        $defaultNameSpace = $this->fetchNameSpace($this->addonOption['name']);
        $nameSpace = $this->io->ask("<info>请输入插件名称空间（{$defaultNameSpace}）：</info>", $defaultNameSpace);
        $nameSpace = trim(str_replace('/', '\\', $nameSpace), '\\');
        $ConfigProviderContent = file_get_contents(__DIR__ . '/resources/ConfigProvider.stub');
        $ConfigProviderContent = str_replace('%NAMESPACE%', $nameSpace, $ConfigProviderContent);
        file_put_contents(__DIR__ . '/../ConfigProvider.php', $ConfigProviderContent);
        //创建插件
        $AddonContent = file_get_contents(__DIR__ . '/resources/Addon.stub');
        $AddonContent = str_replace('%NAMESPACE%', $nameSpace, $AddonContent);
        file_put_contents(__DIR__ . '/../Addon.php', $AddonContent);
        //创建应用配置
        $configName = 'addon-'.str_replace('/', '-', $this->addonOption['name']);
        $AddonConfigContent = file_get_contents(__DIR__ . '/resources/AddonConfig.stub');
        file_put_contents(__DIR__ . '/../Config/autoload/'.$configName.'.php', $AddonConfigContent);
        @unlink(__DIR__ . '/../.gitkeep');
        $this->addonOption['autoload']['psr-4'][$nameSpace . '\\'] = '/';
        $this->addonOption['extra']['hyperf']['config'] = $nameSpace . '\\ConfigProvider';
    }

    /**
     * 设置插件协议
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 16:13
     */
    public function setAddonLicense(): void
    {
        $this->addonOption['license'] = 'MIT';
    }

    /**
     * 获取格式化后的插件命名空间
     * @param string $nameSpace
     * @return string
     * @author itjack
     * @date 2023/2/6 16:01
     * @link 1092428238@qq.com
     */
    public function fetchNameSpace(string $nameSpace): string
    {
        if (empty($nameSpace)) return '';
        $nameSpaceBySplit = explode("/", $nameSpace);
        foreach ($nameSpaceBySplit as $key => $item) {
            $formatItemName = ucwords(str_replace(['_', '-'], '', $item));
            $nameSpaceBySplit[$key] = str_replace(' ', '', $formatItemName);
        }
        return implode('\\', $nameSpaceBySplit);
    }

    /**
     * 获取插件配置
     * @return array
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 15:34
     */
    public
    function AddonConfiguration(): array
    {
        if (file_exists($file = __DIR__ . '/config.php')) {
            return require $file;
        }
        return [];
    }


    /**
     * 删除依赖的开发项
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 16:38
     */
    public function removeDevDependencies(): void
    {
        $this->io->info('正在删除安装程序开发依赖项');
        foreach ($this->devDependencies as $devDependency) {
            unset($this->stabilityFlags[$devDependency], $this->composerDevRequires[$devDependency],
                $this->addonOption['require-dev'][$devDependency]);
        }
    }


    /**
     * 创建插件目录
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 15:38
     */
    public
    function makeAddonDirectory(): void
    {
        if ($this->config && !empty($directory = $this->config['directory'])) {
            foreach ($directory as $item) {
                @mkdir(__DIR__ . '/../' . $item);
            }
        }
    }

    /**
     * 获取插件解析包内容
     * @param Composer $composer
     * @param string $composerFile
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 16:32
     */
    public function parseComposerDefinition(Composer $composer, string $composerFile): void
    {
        $this->composerJson = new JsonFile($composerFile);
        try {
            $this->addonOption = $this->composerJson->read();
            $this->rootPackage = $composer->getPackage();
            $this->composerRequires = $this->rootPackage->getRequires();
            $this->composerDevRequires = $this->rootPackage->getDevRequires();
            $this->stabilityFlags = $this->rootPackage->getStabilityFlags();
        } catch (ParsingException $e) {
        }
    }

    /**
     * 安装扩展包
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 18:40
     */
    public function confirmForOptionalPackages(): void
    {
        foreach ($this->config['questions'] as $questionName => $question) {
            $this->confirmForOptionalItem($questionName, $question);
        }
    }

    /**
     * @param string $questionName
     * @param array $question
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 18:18
     */
    public function confirmForOptionalItem(string $questionName, array $question): void
    {
        try {
            $this->composerJson->write($this->addonOption);
            $defaultOption = $question['default'] ?? 1;
            if (isset($this->addonOption['extra']['optional-packages'][$questionName])) {
                return;
            }
            $answer = $this->askQuestion($question, $defaultOption);
            $this->processAnswer($question, $answer);
            $this->addonOption['extra']['optional-packages'][$questionName] = $answer;
        } catch (\Exception $e) {
        }
    }

    /**
     * @param array $question
     * @param $defaultOption
     * @return int|string|bool
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 16:47
     */
    private function askQuestion(array $question, $defaultOption): int|string|bool
    {
        $ask = [
            sprintf("\n  <question>%s</question>\n", $question['question']),
        ];
        $defaultText = $defaultOption;
        foreach ($question['options'] as $key => $option) {
            $defaultText = ($key === $defaultOption) ? $option['name'] : $defaultText;
            $ask[] = sprintf("  [<comment>%d</comment>] %s\n", $key, $option['name']);
        }
        if ($question['required'] !== true) {
            $ask[] = "  [<comment>n</comment>] None of the above\n";
        }
        $ask[] = ($question['custom-package'] === true)
            ? sprintf(
                '  选择或输入 composer 软件包名称和版本 <comment>(%s)</comment>: ',
                $defaultText
            )
            : sprintf('  请选择 <comment>(%s)</comment>: ', $defaultText);
        while (true) {
            $answer = $this->io->ask(implode($ask), (string)$defaultOption);
            if ($answer === 'n' && $question['required'] !== true) {
                return 'n';
            }
            if (is_numeric($answer) && isset($question['options'][(int)$answer])) {
                return (int)$answer;
            }
            if ($question['custom-package'] === true && preg_match(self::PACKAGE_REGEX, $answer, $match)) {
                $packageName = $match['name'];
                $packageVersion = $match['version'];
                if (!$packageVersion) {
                    $this->io->write('<error>未指定包版本</error>');
                    continue;
                }
                $this->io->write(sprintf('  - 搜索 <info>%s:%s</info>', $packageName, $packageVersion));
                $optionalPackage = $this->composer->getRepositoryManager()->findPackage($packageName, $packageVersion);
                if ($optionalPackage === null) {
                    $this->io->write(sprintf('<error>找不到包 %s:%s</error>', $packageName, $packageVersion));
                    continue;
                }
                return sprintf('%s:%s', $packageName, $packageVersion);
            }
            $this->io->write('<error>无效答案</error>');
        }
    }

    /**
     * 安装包文件
     * @param string $packageName
     * @param string $packageVersion
     * @param array $whiteList
     * @return void
     * @author itjack
     * @date 2023/2/6 18:12
     * @link 1092428238@qq.com
     */
    public function addPackage(string $packageName, string $packageVersion, array $whiteList = array()): void
    {
        $this->io->write(sprintf('正在安装包<info>%s</info>(<comment>%s</comment>)', $packageName, $packageVersion));
        $versionParser = new VersionParser();
        $constraint = $versionParser->parseConstraints($packageVersion);
        $link = new Link('__root__', $packageName, $constraint, 'requires', $packageVersion);
        if (in_array($packageName, $this->config['require-dev'], true)) {
            unset($this->addonOption['require'][$packageName], $this->composerRequires[$packageName]);
            $this->addonOption['require'][$packageName] = $packageVersion;
            $this->composerDevRequires[$packageName] = $link;
        } else {
            unset($this->addonOption['require-dev'][$packageName], $this->composerDevRequires[$packageName]);
            $this->addonOption['require'][$packageName] = $packageVersion;
            $this->composerRequires[$packageName] = $link;
        }
        switch (VersionParser::parseStability($packageVersion)) {
            case 'dev':
                $this->stabilityFlags[$packageName] = BasePackage::STABILITY_DEV;
                break;
            case 'alpha':
                $this->stabilityFlags[$packageName] = BasePackage::STABILITY_ALPHA;
                break;
            case 'beta':
                $this->stabilityFlags[$packageName] = BasePackage::STABILITY_BETA;
                break;
            case 'RC':
                $this->stabilityFlags[$packageName] = BasePackage::STABILITY_RC;
                break;
        }
        foreach ($whiteList as $package) {
            if (!in_array($package, $this->addonOption['extra']['zf']['component-whitelist'], true)) {
                $this->addonOption['extra']['zf']['component-whitelist'][] = $package;
                $this->io->write(sprintf('  - Whitelist package <info>%s</info>', $package));
            }
        }
    }


    /**
     * 复制资源文件
     * @param string $resource
     * @param string $target
     * @param bool $force
     * @return void
     * @author itjack
     * @date 2023/2/6 18:14
     * @link 1092428238@qq.com
     */
    public function copyResource(string $resource, string $target, bool $force = false): void
    {
        // Copy file
        if ($force === false && is_file($this->projectRoot . $target)) {
            return;
        }
        $destinationPath = dirname($this->projectRoot . $target);
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0775, true);
        }
        $this->io->write(sprintf('  - 复制中 <info>%s</info>', $target));
        copy($this->installerAddonSource . $resource, $this->projectRoot . $target);
    }

    /**
     * @param array $question
     * @param $answer
     * @return bool
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 18:16
     */
    public function processAnswer(array $question, $answer): bool
    {
        if (is_numeric($answer) && isset($question['options'][$answer])) {
            if (isset($question['options'][$answer]['packages'])) {
                foreach ($question['options'][$answer]['packages'] as $packageName) {
                    $packageData = $this->config['packages'][$packageName];
                    $this->addPackage($packageName, $packageData['version'], $packageData['whitelist'] ?? []);
                }
            }
            if (isset($question['options'][$answer])) {
                $force = !empty($question['force']);
                foreach ($question['options'][$answer]['resources'] ?? [] as $resource => $target) {
                    $this->copyResource($resource, $target, $force);
                }
            }
            return true;
        }
        if ($question['custom-package'] === true && preg_match(self::PACKAGE_REGEX, (string)$answer, $match)) {
            $this->addPackage($match['name'], $match['version'], []);
            if (isset($question['custom-package-warning'])) {
                $this->io->write(sprintf('  <warning>%s</warning>', $question['custom-package-warning']));
            }
            return true;
        }
        return false;
    }

    /**
     * 根据当前状态更新根包
     */
    public function updateAddonRootPackage(): void
    {
        $this->rootPackage->setRequires($this->composerRequires);
        $this->rootPackage->setDevRequires($this->composerDevRequires);
        $this->rootPackage->setStabilityFlags($this->stabilityFlags);
        $this->rootPackage->setAutoload($this->addonOption['autoload']);
        $this->rootPackage->setDevAutoload($this->addonOption['autoload-dev']);
        $this->rootPackage->setExtra($this->composerDefinition['extra'] ?? []);
    }

    /**
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 18:46
     */
    public function removeAddonInstallerFromDefinition(): void
    {
        $this->io->write('<info>正在删除安装器</info>');
        unset(
            $this->addonOption['autoload']['psr-4']['Installer\\'],
            $this->addonOption['autoload-dev']['psr-4']['InstallerTest\\'],
            $this->addonOption['extra']['branch-alias'],
            $this->addonOption['extra']['optional-packages'],
            $this->addonOption['scripts']['pre-update-cmd'],
            $this->addonOption['scripts']['pre-install-cmd'],
            $this->addonOption['scripts']['post-install-cmd'],
            $this->addonOption['scripts']['post-create-project-cmd']
        );
    }

    /**
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 22:39
     */
    public function finalizePackage(): void
    {
        try {
            $this->composerJson->write($this->addonOption);
            $this->io->info('删除安装程序类、配置、测试和文档');
            $this->recursiveAddonRemoveDirectory($this->installerAddonSource);
        } catch (\Exception $e) {
        }
    }

    /**
     * @param string $directory
     * @return void
     * @author itjack
     * @date 2023/2/6 22:36
     * @link 1092428238@qq.com
     */
    public function recursiveAddonRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) return;
        $recursiveDirectoryIterator = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
        $recursiveIteratorIterator = new RecursiveIteratorIterator($recursiveDirectoryIterator, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($recursiveIteratorIterator as $fileName => $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileName);
                continue;
            }
            unlink($fileName);
        }
        rmdir($directory);
    }

    /**
     * 清理安装程序
     * @return void
     * @link 1092428238@qq.com
     * @author itjack
     * @date 2023/2/6 22:45
     */
    public function cleanInstall(): void
    {
        foreach ($this->assetsToRemove as $item) {
            $item = $this->projectRoot . $item;
            if (file_exists($item)) {
                unlink($item);
            }
        }
        $this->recursiveAddonRemoveDirectory($this->projectRoot . 'vendor');
    }
}