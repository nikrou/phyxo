<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\DataMapper\UserMapper;
use App\Repository\ThemeRepository;
use App\Utils\DirectoryManager;
use Exception;
use InvalidArgumentException;
use Phyxo\Theme\Themes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'phyxo:assets:install',
    description: 'Install themes web assets under a public directory',
)]
class AssetsInstallCommand extends Command
{
    public function __construct(
        private readonly string $rootProjectDir,
        private readonly string $themesDir,
        private readonly ThemeRepository $themeRepository,
        private readonly UserMapper $userMapper,
        private readonly DirectoryManager $directoryManager,
        private readonly Filesystem $fs
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(file_get_contents(__DIR__ . '/../Resources/help/AssetsInstallCommand.txt'))
            ->addArgument('target', InputArgument::OPTIONAL, 'The target directory', null)
            ->addArgument('theme', InputArgument::OPTIONAL, 'Apply only for that theme', null)
            ->addOption('symlink', null, InputOption::VALUE_NONE, 'Symlink the assets instead of copying them')
            ->addOption('relative', null, InputOption::VALUE_NONE, 'Make relative symlinks');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetArg = rtrim($input->getArgument('target') ?? '', '/');
        if ($targetArg === '') {
            $targetArg = $this->rootProjectDir . '/public';
        }

        if (!is_dir($targetArg)) {
            $targetArg = $this->rootProjectDir . '/' . $targetArg;

            if (!is_dir($targetArg)) {
                throw new InvalidArgumentException(sprintf('The target directory "%s" does not exist.', $targetArg));
            }
        }

        $targetDir = $targetArg . '/themes';

        $io = new SymfonyStyle($input, $output);
        $io->newLine();

        if ($input->getOption('relative')) {
            $expectedMethod = DirectoryManager::METHOD_RELATIVE_SYMLINK;
            $io->text('Trying to install assets as <info>relative symbolic links</info>.');
        } elseif ($input->getOption('symlink')) {
            $expectedMethod = DirectoryManager::METHOD_ABSOLUTE_SYMLINK;
            $io->text('Trying to install assets as <info>absolute symbolic links</info>.');
        } else {
            $expectedMethod = DirectoryManager::METHOD_COPY;
            $io->text('Installing assets as <info>hard copies</info>.');
        }

        $rows = [];
        $copyUsed = false;
        $exitCode = 0;
        $validAssetDirs = [];

        $themes = new Themes($this->themeRepository, $this->userMapper);
        $themes->setRootPath($this->themesDir);
        foreach ($themes->getFsThemes() as $theme) {
            if ($input->getArgument('theme') && $theme['id'] !== $input->getArgument('theme')) {
                continue;
            }

            if (!is_dir($originDir = $this->themesDir . '/' . $theme['id'] . '/build')) {
                continue;
            }

            $assetDir = $theme['id'];
            $targetDir = $targetDir . '/' . $assetDir;
            $validAssetDirs[] = $assetDir;

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $message = sprintf("%s\n-> %s", $theme['name'], $targetDir);
            } else {
                $message = $theme['name'];
            }

            try {
                $this->fs->remove($targetDir);

                if ($expectedMethod === DirectoryManager::METHOD_RELATIVE_SYMLINK) {
                    $method = $this->directoryManager->relativeSymlinkWithFallback($originDir, $targetDir);
                } elseif ($expectedMethod === DirectoryManager::METHOD_ABSOLUTE_SYMLINK) {
                    $method = $this->directoryManager->absoluteSymlinkWithFallback($originDir, $targetDir);
                } else {
                    $method = $this->directoryManager->hardCopy($originDir, $targetDir);
                }

                if ($method === DirectoryManager::METHOD_COPY) {
                    $copyUsed = true;
                }

                if ($method === $expectedMethod) {
                    $rows[] = [sprintf('<fg=green;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'OK' : "\xE2\x9C\x94" /* HEAVY CHECK MARK (U+2714) */), $message, $method];
                } else {
                    $rows[] = [sprintf('<fg=yellow;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'WARNING' : '!'), $message, $method];
                }
            } catch (Exception $e) {
                $exitCode = Command::FAILURE;
                $rows[] = [sprintf('<fg=red;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'ERROR' : "\xE2\x9C\x98" /* HEAVY BALLOT X (U+2718) */), $message, $e->getMessage()];
            }
        }

        $io->newLine();

        if ($rows !== []) {
            $io->table(['', 'Theme', 'Method / Error'], $rows);
        }

        if ($exitCode !== Command::SUCCESS) {
            $io->error('Some errors occurred while installing assets.');
        } else {
            if ($copyUsed) {
                $io->note('Some assets were installed via copy. If you make changes to these assets you have to run this command again.');
            }

            $io->success($rows !== [] ? 'All assets were successfully installed.' : 'No assets were provided by any themes.');
        }

        return (int) $exitCode;
    }
}
