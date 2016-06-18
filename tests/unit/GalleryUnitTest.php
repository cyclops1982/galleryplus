<?php
/**
 * ownCloud - galleryplus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Olivier Paroz <owncloud@interfasys.ch>
 *
 * @copyright Olivier Paroz 2016
 */

namespace Test;

use OCP\ILogger;
use OCP\Files\File;
use OCP\Files\Folder;

use OCA\GalleryPlus\Environment\Environment;
use OCA\GalleryPlus\Service\ServiceException;

/**
 * Class GalleryUnitTest
 *
 * @package OCA\GalleryPlus
 */
abstract class GalleryUnitTest extends \Test\TestCase {

	/** @var string */
	protected $appName = 'galleryplus';
	/** @var Environment */
	protected $environment;
	/** @var ILogger */
	protected $logger;

	/**
	 * Test set up
	 */
	protected function setUp() {
		parent::setUp();

		$this->environment = $this->getMockBuilder('\OCA\GalleryPlus\Environment\Environment')
								  ->disableOriginalConstructor()
								  ->getMock();
		$this->logger = $this->getMockBuilder('\OCP\ILogger')
							 ->disableOriginalConstructor()
							 ->getMock();
	}

	/**
	 * Mocks Object->getFile
	 *
	 * Needs to pass a mock of a File or Folder
	 *
	 * @param object $mockedObject
	 * @param int $fileId
	 * @param File|Folder $answer
	 */
	protected function mockGetFile($mockedObject, $fileId, $answer) {
		$mockedObject->expects($this->once())
					 ->method('getFile')
					 ->with($this->equalTo($fileId))
					 ->willReturn($answer);
	}

	/**
	 * Mocks Object->getFile with a bad Id
	 *
	 * Needs to pass a mock of a File or Folder
	 *
	 * @param \PHPUnit_Framework_MockObject_MockObject $mockedObject
	 * @param int $fileId
	 * @param \Exception $exception
	 */
	protected function mockGetFileWithBadFile($mockedObject, $fileId, $exception) {
		$mockedObject->expects($this->once())
					 ->method('getFile')
					 ->with($this->equalTo($fileId))
					 ->willThrowException($exception);
	}

	/**
	 * Mocks OCP\Files\File
	 *
	 * Duplicate of PreviewControllerTest->mockFile
	 *
	 * Contains a JPG
	 *
	 * @param int $fileId
	 * @param string $storageId
	 * @param bool $isReadable
	 * @param string $path
	 * @param string $etag
	 * @param int $size
	 * @param bool $isShared
	 * @param null|object $owner
	 * @param int $permissions
	 *
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 */
	protected function mockFile(
		$fileId,
		$storageId = 'home::user',
		$isReadable = true,
		$path = '',
		$etag = "8603c11cd6c5d739f2c156c38b8db8c4",
		$size = 1024,
		$isShared = false,
		$owner = null,
		$permissions = 31
	) {
		$storage = $this->mockGetStorage($storageId);
		$file = $this->getMockBuilder('OCP\Files\File')
					 ->disableOriginalConstructor()
					 ->getMock();
		$file->method('getId')
			 ->willReturn($fileId);
		$file->method('getType')
			 ->willReturn('file');
		$file->method('getStorage')
			 ->willReturn($storage);
		$file->method('getOwner')
			 ->willReturn($owner);
		$file->method('getPermissions')
			 ->willReturn($permissions);
		$file->method('isReadable')
			 ->willReturn($isReadable);
		$file->method('getPath')
			 ->willReturn($path);
		$file->method('getEtag')
			 ->willReturn($etag);
		$file->method('getSize')
			 ->willReturn($size);
		$file->method('isShared')
			 ->willReturn($isShared);

		return $file;
	}

	protected function mockJpgFile(
		$fileId,
		$storageId = 'home::user',
		$isReadable = true,
		$path = '',
		$etag = "8603c11cd6c5d739f2c156c38b8db8c4",
		$size = 1024,
		$isShared = false,
		$owner = null,
		$permissions = 31
	) {
		$file = $this->mockFile(
			$fileId, $storageId, $isReadable, $path, $etag, $size, $isShared, $owner, $permissions
		);
		$this->mockJpgFileMethods($file);

		return $file;
	}

	protected function mockSvgFile($fileId) {
		$file = $this->mockFile($fileId);
		$this->mockSvgFileMethods($file);

		return $file;
	}

	protected function mockAnimatedGifFile($fileId) {
		$file = $this->mockFile($fileId);
		$this->mockAnimatedGifFileMethods($file);

		return $file;
	}

	protected function mockNoMediaFile($fileId) {
		$file = $this->mockFile($fileId);
		$this->mockNoMediaFileMethods($file);

		return $file;
	}

	private function mockJpgFileMethods($file) {
		$filename = 'testimage.jpg';
		$file->method('getContent')
			 ->willReturn(file_get_contents(__DIR__ . '/../_data/' . $filename));
		$file->method('getName')
			 ->willReturn($filename);
		$file->method('getMimeType')
			 ->willReturn('image/jpeg');
	}

	private function mockSvgFileMethods($file) {
		$filename = 'testimagelarge.svg';
		$file->method('getContent')
			 ->willReturn(file_get_contents(__DIR__ . '/../_data/' . $filename));
		$file->method('getName')
			 ->willReturn($filename);
		$file->method('getMimeType')
			 ->willReturn('image/svg+xml');
	}

	private function mockAnimatedGifFileMethods($file) {
		$filename = 'animated.gif';
		$file->method('getContent')
			 ->willReturn(file_get_contents(__DIR__ . '/../_data/' . $filename));
		$file->method('getName')
			 ->willReturn($filename);
		$file->method('getMimeType')
			 ->willReturn('image/gif');
		$file->method('fopen')
			 ->with('rb')
			 ->willReturn(fopen(__DIR__ . '/../_data/' . $filename, 'rb'));;
	}

	private function mockNoMediaFileMethods($file) {
		$filename = '.nomedia';
		$file->method('getContent')
			 ->willReturn(file_get_contents(__DIR__ . '/../_data/' . $filename));
		$file->method('getName')
			 ->willReturn($filename);
		$file->method('getMimeType')
			 ->willReturn('image/jpeg');
	}

	protected function mockBadFile() {
		$exception = new ServiceException("Can't read file");
		$file = $this->getMockBuilder('OCP\Files\File')
					 ->disableOriginalConstructor()
					 ->getMock();
		$file->method('getId')
			 ->willThrowException($exception);
		$file->method('getType')
			 ->willThrowException($exception);
		$file->method('getPath')
			 ->willThrowException($exception);
		$file->method('getContent')
			 ->willThrowException($exception);

		return $file;
	}

	/**
	 * @param string $storageId
	 * @param int $nodeId
	 * @param array $files
	 * @param bool $isReadable
	 * @param bool $mounted
	 * @param null $mount
	 * @param string $query
	 * @param bool $queryResult
	 * @param bool $sharedWithUser
	 * @param string $etag
	 * @param int $size
	 * @param string $path
	 * @param null|object $owner
	 * @param int $permissions
	 * @param int $freeSpace
	 *
	 * @return mixed|object|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function mockFolder(
		$storageId,
		$nodeId,
		$files,
		$isReadable = true,
		$mounted = false,
		$mount = null,
		$query = '',
		$queryResult = false,
		$sharedWithUser = false,
		$etag = "etag303",
		$size = 4096,
		$path = "not/important",
		$owner = null,
		$permissions = 31,
		$freeSpace = 999999999
	) {
		$storage = $this->mockGetStorage($storageId);
		$folder = $this->getMockBuilder('OCP\Files\Folder')
					   ->disableOriginalConstructor()
					   ->getMock();
		$folder->method('getType')
			   ->willReturn('dir');
		$folder->method('getId')
			   ->willReturn($nodeId);
		$folder->method('getEtag')
			   ->willReturn($etag);
		$folder->method('getSize')
			   ->willReturn($size);
		$folder->method('getPath')
			   ->willReturn($path);
		$folder->method('getFreeSpace')
			   ->willReturn($freeSpace);
		$folder->method('getDirectoryListing')
			   ->willReturn($files);
		$folder->method('getStorage')
			   ->willReturn($storage);
		$folder->method('getOwner')
			   ->willReturn($owner);
		$folder->method('getPermissions')
			   ->willReturn($permissions);
		$folder->method('isReadable')
			   ->willReturn($isReadable);
		$folder->method('isShared')
			   ->willReturn($sharedWithUser);
		$folder->method('isMounted')
			   ->willReturn($mounted);
		$folder->method('getMountPoint')
			   ->willReturn($mount);
		$folder->method('nodeExists')
			   ->with($query)
			   ->willReturn($queryResult);

		return $folder;
	}

	protected function mockGetStorage($storageId) {
		$storage = $this->getMockBuilder('OCP\Files\Storage')
						->disableOriginalConstructor()
						->getMock();
		$storage->method('getId')
				->willReturn($storageId);

		return $storage;
	}

	protected function mockGetFileNodeFromVirtualRoot($location, $file) {
		$this->environment->expects($this->any())
						  ->method('getNodeFromVirtualRoot')
						  ->with($location)
						  ->willReturn($file);
	}

	protected function mockGetPathFromVirtualRoot($node, $path) {
		$this->environment->expects($this->any())
						  ->method('getPathFromVirtualRoot')
						  ->with($node)
						  ->willReturn($path);
	}

	protected function mockGetResourceFromId($nodeId, $node) {
		$this->environment->expects($this->any())
						  ->method('getResourceFromId')
						  ->with($nodeId)
						  ->willReturn($node);
	}

}
