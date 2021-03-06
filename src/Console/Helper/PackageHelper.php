<?php
namespace Pickle\Console\Helper;

use Composer\Config;
use Composer\Downloader\GitDownloader;
use Composer\IO\ConsoleIO;
use Composer\Package\PackageInterface;
use Pickle\Downloader\PECLDownloader;
use Pickle\Package;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PackageHelper extends Helper
{
    const RE_PECL_PACKAGE = '#^
        (?:pecl/)?
        (?<package>\w+)
        (?:
            \-(?<stability>beta|stable|alpha)
            |@(?<version>(?:\d+(?:\.\d+){1,2})|(?:[1-2]\d{3}[0-1]\d[0-3]\d{1}))
        )?
    $#x';

    const RE_GIT_PACKAGE = '#^
        (?:git|https?)://.*?/
        (?P<package>\w+)
        (?:
            (?:\.git|)
            (?:\#(?P<reference>.*?)|)
        )?
    $#x';

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     * @api
     */
    public function getName()
    {
        return 'package';
    }

    public function showInfo(OutputInterface $output, PackageInterface $package)
    {
        $table = new Table($output);
        $table
            ->setRows([
                ['<info>Package name</info>', $package->getPrettyName()],
                ['<info>Package version (current release)</info>', $package->getPrettyVersion()],
                ['<info>Package status</info>', $package->getStability()]
            ])
            ->render();
    }

    public function showOptions(OutputInterface $output, Package $package)
    {
        $table = new Table($output);
        $table->setHeaders(['Type', 'Description', 'Default']);

        foreach ($package->getConfigureOptions() as $option) {
            $default = $option->default;

            if ($option->type === 'enable') {
                $option->type = '<fg=yellow>' . $option->type . '</fg=yellow>';
                $default = $default ? '<fg=green>yes</fg=green>' : '<fg=red>no</fg=red>';
            }

            $table->addRow([
                $option->type,
                wordwrap($option->prompt, 40, PHP_EOL),
                $default
            ]);
        }

        $table->render();
    }

    public function download(InputInterface $input, OutputInterface $output, $url, $path)
    {
        $package = null;
        $downloader = null;
        $io = new ConsoleIO($input, $output, $this->getHelperSet());

        if (preg_match(self::RE_PECL_PACKAGE, $url, $matches) > 0) {
            $url = 'http://pecl.php.net/get/' . $matches['package'];

            if (isset($matches['stability']) && '' !== $matches['stability']) {
                $url .= '-' . $matches['stability'];
            } else {
                $matches['stability'] = 'stable';
            }

            if (isset($matches['version']) && '' !== $matches['version']) {
                $url .= '/' . $matches['version'];
                $prettyVersion = $matches['version'];
            } else {
                $matches['version'] = 'latest';
                $prettyVersion = 'latest-' . $matches['stability'];
            }

            $package = new Package($matches['package'], $matches['version'], $prettyVersion);
            $package->setDistUrl($url);

            $downloader = new PECLDownloader($io, new Config());
        }

        if (null === $package && preg_match(self::RE_GIT_PACKAGE, $url, $matches) > 0) {
            $version = isset($matches['reference']) ? $matches['reference'] : 'master';

            $package = new Package($matches['package'], $version, $version);
            $package->setSourceUrl(preg_replace('/#.*$/', '', $url));
            $package->setSourceType('git');
            $package->setSourceReference($version);

            $downloader = new GitDownloader($io, new Config());
        }

        if (null !== $downloader) {
            $downloader->download($package, $path . DIRECTORY_SEPARATOR . $matches['package']);
        }

        return $package;
    }
}
