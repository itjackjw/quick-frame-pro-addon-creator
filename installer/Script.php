<?php
declare(strict_types=1);

namespace Installer;

use Composer\Script\Event;

class Script
{
    /**
     * @param Event $event
     * @return void
     * @author itjack
     * @date 2023/2/6 18:44
     * @link 1092428238@qq.com
     */
    public static function install(Event $event): void
    {
        $addonInstaller = new AddonOptionalPackages($event->getIO(), $event->getComposer());
        $addonInstaller->io->write('<info>正在创建 QuickFrame 插件，请根据以下提示进行配置</info>');
        $addonInstaller->setAddonComposerJson();
        $addonInstaller->makeAddonDirectory();
        $addonInstaller->removeDevDependencies();
        $addonInstaller->confirmForOptionalPackages();
        $addonInstaller->updateAddonRootPackage();
        $addonInstaller->removeAddonInstallerFromDefinition();
        $addonInstaller->finalizePackage();
    }

    /**
     * cleanInstall
     * @author itjack
     * @date 2023/2/6 22:47
     * @link 1092428238@qq.com
     * @param Event $event
     * @return void
     */
    public static function cleanInstall(Event $event): void
    {
        $addonInstaller = new AddonOptionalPackages($event->getIO(), $event->getComposer());
        $addonInstaller->cleanInstall();
        $addonInstaller->io->write('<info>恭喜，QuickFrame 插件创建成功！</info>');
    }
}