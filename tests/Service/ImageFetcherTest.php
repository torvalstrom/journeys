<?php
namespace OCA\Journeys\Tests\Service;

use OCA\Journeys\Service\ImageFetcher;
use OCA\Journeys\Model\Image;
use PHPUnit\Framework\TestCase;

class ImageFetcherTest extends TestCase {
    public function testFetchImagesForUserReturnsTypedImages() {
        // ImageFetcher queries oc_memories/oc_filecache through an injected
        // IDBConnection, so this is an integration test, not a unit test: it needs
        // a live Nextcloud DB with indexed photos, which the tests/Service suite
        // (plain Composer autoload, no server) does not have. Kept as executable
        // documentation of the expected row shape; run it against a real instance
        // by wiring the two constructor dependencies.
        $this->markTestSkipped('needs a live Nextcloud DB (FacePresenceProvider + IDBConnection)');
        /** @phpstan-ignore-next-line unreachable while skipped */
        $fetcher = new ImageFetcher();
        $images = $fetcher->fetchImagesForUser('admin'); // Use a test user with known images

        $this->assertIsArray($images);
        foreach ($images as $image) {
            $this->assertInstanceOf(Image::class, $image);
            $this->assertIsInt($image->fileid);
            $this->assertIsString($image->path);
            $this->assertIsString($image->datetaken);
            $this->assertTrue(is_null($image->lat) || is_string($image->lat));
            $this->assertTrue(is_null($image->lon) || is_string($image->lon));
        }
    }
}
