<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use GuzzleHttp\Psr7\Request;
use Php\Pie\DependencyResolver\Package;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

use function file_exists;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class UnixDownloadAndExtract implements DownloadAndExtract
{
    /** @psalm-api */
    public function __construct(
        private readonly DownloadZip $downloadZip,
        private readonly ExtractZip $extractZip,
        private readonly AuthHelper $authHelper,
    ) {
    }

    public function __invoke(Package $package): DownloadedPackage
    {
        // @todo extract to a static util
        $localTempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_downloader_', true);
        if (! file_exists($localTempPath)) {
            mkdir($localTempPath, recursive: true);
        }

        $tmpZipFile = $this->downloadZip->downloadZipAndReturnLocalPath(
            $this->createRequestForUnixDownloadUrl($package),
            $localTempPath,
        );

        $extractedPath = $this->extractZip->to($tmpZipFile, $localTempPath);

        return DownloadedPackage::fromPackageAndExtractedPath($package, $extractedPath);
    }

    private function createRequestForUnixDownloadUrl(Package $package): RequestInterface
    {
        if ($package->downloadUrl === null) {
            throw new RuntimeException(sprintf('The package %s does not have a download URL', $package->name));
        }

        $request = new Request('GET', $package->downloadUrl);

        return AddAuthenticationHeader::withAuthHeaderFromComposer($request, $package, $this->authHelper);
    }
}
