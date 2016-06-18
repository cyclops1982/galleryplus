<?php
/**
 * ownCloud - galleryplus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Olivier Paroz <owncloud@interfasys.ch>
 *
 * @copyright Olivier Paroz 2015
 */

namespace OCA\GalleryPlus\Middleware;

use OC\AppFramework\Utility\ControllerMethodReflector;

use OCP\IRequest;
use OCP\Notification\IManager;
use OCP\Security\IHasher;
use OCP\ISession;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\Share;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;

use OCA\GalleryPlus\Environment\Environment;
use OCA\GalleryPlus\Environment\EnvironmentException;

/**
 * @package OCA\GalleryPlus\Middleware\EnvCheckMiddlewareTest
 */
class EnvCheckMiddlewareTest extends \Codeception\TestCase\Test {

	/** @var CoreTestCase */
	private $coreTestCase;

	/** @var string */
	private $appName = 'galleryplus';
	/** @var IRequest */
	private $request;
	/** @var IHasher */
	private $hasher;
	/** @var ISession */
	private $session;
	/** @var Environment */
	private $environment;
	/** @var IControllerMethodReflector */
	protected $reflector;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var ILogger */
	protected $logger;
	/** @var Controller */
	private $controller;
	/** @var SharingCheckMiddleware */
	private $middleware;

	/** @var string */
	public $sharedFolderToken;
	/** @var string */
	public $passwordForFolderShare;
	/** @var OCP\Share|IManager */
	private $shareManager;

	/**
	 * Test set up
	 */
	protected function setUp() {
		parent::setUp();

		$this->request = $this->getMockBuilder('\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();
		$this->hasher = $this->getMockBuilder('\OCP\Security\IHasher')
			->disableOriginalConstructor()
			->getMock();
		$this->session = $this->getMockBuilder('\OCP\ISession')
							  ->disableOriginalConstructor()
							  ->getMock();
		$this->environment = $this->getMockBuilder('\OCA\GalleryPlus\Environment\Environment')
								  ->disableOriginalConstructor()
								  ->getMock();
		// We need to use a real reflector to be able to test our custom notation
		$this->reflector = new ControllerMethodReflector();
		$this->urlGenerator = $this->getMockBuilder('\OCP\IURLGenerator')
			->disableOriginalConstructor()
			->getMock();
		$this->logger = $this->getMockBuilder('\OCP\ILogger')
			->disableOriginalConstructor()
			->getMock();
		$this->controller = $this->getMockBuilder('OCP\AppFramework\Controller')
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager = $this->getMockBuilder('\OCP\Share\IManager')
			->disableOriginalConstructor()
			->getMock();

		$this->middleware = new EnvCheckMiddleware(
			$this->appName,
			$this->request,
			$this->hasher,
			$this->session,
			$this->environment,
			$this->reflector,
			$this->urlGenerator,
			$this->logger,
			$this->shareManager
		);

		/**
		 * Injects objects we need to bypass the static methods
		 *
		 * CODECEPTION SPECIFIC
		 */
		$setupData = $this->getModule('\Helper\DataSetup');
		$this->sharedFolderToken = $setupData->sharedFolderToken;
		$this->passwordForFolderShare = $setupData->passwordForFolderShare;
		$this->coreTestCase = $setupData->coreTestCase;
	}

	/**
	 * Invokes private methods
	 *
	 * CODECEPTION SPECIFIC
	 * This is from the core TestCase
	 *
	 * @param $object
	 * @param $methodName
	 * @param array $parameters
	 *
	 * @return mixed
	 */
	public function invokePrivate($object, $methodName, array $parameters = []) {
		return $this->coreTestCase->invokePrivate($object, $methodName, $parameters);
	}


	/**
	 * @todo Mock an environment response
	 */
	public function testBeforeControllerWithoutNotation() {
		$this->reflector->reflect(__CLASS__, __FUNCTION__);
		$this->middleware->beforeController(__CLASS__, __FUNCTION__);
	}

	/**
	 * @PublicPage
	 *
	 * @expectedException \OCP\Files\NotFoundException
	 */
	public function testBeforeControllerWithPublicNotationAndInvalidToken() {
		$this->reflector->reflect(__CLASS__, __FUNCTION__);

		$token = 'aaaabbbbccccdddd';
		$this->mockGetTokenParam($token);

		$share = $this->newShare();
		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with($token)
			->willReturn($share);

		$this->middleware->beforeController(__CLASS__, __FUNCTION__);
	}

	/**
	 * @PublicPage
	 *
	 * Because the method tested is static, we need to load our test environment \Helper\DataSetup
	 */
	public function testBeforeControllerWithPublicNotationAndToken() {
		$this->reflector->reflect(__CLASS__, __FUNCTION__);

		$this->mockGetTokenAndPasswordParams(
			$this->sharedFolderToken, $this->passwordForFolderShare
		);

		$share = $this->newShare();
		$share->setId(12345)
			->setNodeType('folder')
			->setShareOwner('test')
			->setTarget('folder1')
			->setShareType(Share::SHARE_TYPE_LINK)
			->setSharedWith('validpassword');
		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with($this->sharedFolderToken)
			->willReturn($share);

		$this->shareManager
			->expects($this->once())
			->method('checkPassword')
			->with($share, $this->passwordForFolderShare)
			->willReturn(true);

		$this->middleware->beforeController(__CLASS__, __FUNCTION__);
	}

	/**
	 * @PublicPage
	 *
	 * @expectedException \OCA\Gallery\Middleware\CheckException
	 */
	public function testBeforeControllerWithPublicNotationAndNoToken() {
		$this->reflector->reflect(__CLASS__, __FUNCTION__);

		$token = null;
		$this->mockGetTokenParam($token);
		$this->middleware->beforeController(__CLASS__, __FUNCTION__);
	}

	/**
	 * @@Guest
	 */
	public function testBeforeControllerWithGuestNotation() {
		$this->reflector->reflect(__CLASS__, __FUNCTION__);

		$this->middleware->beforeController(__CLASS__, __FUNCTION__);
	}

	public function testCheckSessionAfterPasswordEntry() {
		$share = $this->newShare();
		$share->setId(12345);
		$this->mockSessionExists($share->getId());
		$this->mockSessionWithLinkItemId($share->getId());

		self::invokePrivate($this->middleware, 'checkSession', [$share]);
	}

	/**
	 * @expectedException \OCA\GalleryPlus\Middleware\CheckException
	 */
	public function testCheckSessionBeforePasswordEntry() {
		$share = $this->newShare();
		$share->setId(12345);
		$this->mockSessionExists(false);

		self::invokePrivate($this->middleware, 'checkSession', [$share]);
	}

	/**
	 * Ids of linkItem do not match
	 *
	 * @expectedException \OCA\GalleryPlus\Middleware\CheckException
	 */
	public function testCheckSessionWithWrongSession() {
		$share = $this->newShare();
		$share->setId(12345);
		$this->mockSessionExists(true);
		$this->mockSessionWithLinkItemId(99999);

		self::invokePrivate($this->middleware, 'checkSession', [$share]);
	}

	public function testCheckPasswordAfterValidPasswordEntry() {
		$password = 'Je suis une pipe';
		$share = $this->newShare();
		$share->setId(12345)
			->setSharedWith($password);
		$this->shareManager
			->expects($this->once())
			->method('checkPassword')
			->with($share, $password)
			->willReturn(true);

		self::invokePrivate($this->middleware, 'checkPassword', [$share, $password]);
	}

	/**
	 * Given password and token password don't match
	 *
	 * @expectedException \OCA\GalleryPlus\Middleware\CheckException
	 */
	public function testCheckPasswordAfterInvalidPasswordEntry() {
		$password = 'Je suis une pipe';
		$share = $this->newShare();
		$share->setId(12345)
			->setSharedWith('Empyrion Galactic Survival');
		$this->shareManager
			->expects($this->once())
			->method('checkPassword')
			->with($share, $password)
			->willReturn(false);

		self::invokePrivate($this->middleware, 'checkPassword', [$share, $password]);
	}

	public function testAuthenticateAfterValidPasswordEntry() {
		$password = 'Je suis une pipe';
		$share = $this->newShare();
		$share->setId(12345)
			->setSharedWith($password)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK);
		$this->shareManager
			->expects($this->once())
			->method('checkPassword')
			->with($share, $password)
			->willReturn(true);

		$this->assertTrue(
			self::invokePrivate($this->middleware, 'authenticate', [$share, $password])
		);
	}

	/**
	 * Given password and token password don't match
	 *
	 * @expectedException \OCA\GalleryPlus\Middleware\CheckException
	 */
	public function testAuthenticateAfterInvalidPasswordEntry() {
		$password = 'Je suis une pipe';
		$share = $this->newShare();
		$share->setId(12345)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setSharedWith('Empyrion Galactic Survival');
		$this->shareManager
			->expects($this->once())
			->method('checkPassword')
			->with($share, $password)
			->willReturn(false);

		self::invokePrivate($this->middleware, 'authenticate', [$share, $password]);
	}

	/**
	 * @expectedException \OCA\GalleryPlus\Middleware\CheckException
	 */
	public function testAuthenticateWithWrongLinkType() {
		$password = 'Je suis une pipe';
		$share = $this->newShare();
		$share->setId(12345)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setSharedWith('tester');
		$this->shareManager
			->expects($this->once())
			->method('checkPassword')
			->with($share, $password)
			->willReturn(false);

		self::invokePrivate($this->middleware, 'authenticate', [$share, $password]);
	}

	public function testCheckAuthorisationAfterValidPasswordEntry() {
		$password = 'Je suis une pipe';
		$share = $this->newShare();
		$share->setId(12345)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setSharedWith($password);
		$this->shareManager
			->expects($this->once())
			->method('checkPassword')
			->with($share, $password)
			->willReturn(true);

		self::invokePrivate($this->middleware, 'checkAuthorisation', [$share, $password]);
	}

	/**
	 * Given password and token password don't match
	 *
	 * @expectedException \OCA\GalleryPlus\Middleware\CheckException
	 */
	public function testCheckAuthorisationAfterInvalidPasswordEntry() {
		$password = 'Je suis une pipe';
		$share = $this->newShare();
		$share->setId(12345)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setSharedWith('Empyrion Galactic Survival');
		$this->shareManager
			->expects($this->once())
			->method('checkPassword')
			->with($share, $password)
			->willReturn(false);

		self::invokePrivate($this->middleware, 'checkAuthorisation', [$share, $password]);
	}

	/**
	 * It will use the session, wich is a valid one in this case
	 * Other cases are in the checkSession tests
	 */
	public function testCheckAuthorisationWithNoPassword() {
		$password = null;
		$share = $this->newShare();
		$share->setId(12345)
			->setSharedWith('Empyrion Galactic Survival');
		$this->mockSessionExists($share->getId());
		$this->mockSessionWithLinkItemId($share->getId());
		self::invokePrivate($this->middleware, 'checkAuthorisation', [$share, $password]);
	}

	public function testCheckItemTypeWithItemTypeSet() {
		$share = $this->newShare();
		$share->setId(12345)
			->setNodeType('folder');

		self::invokePrivate($this->middleware, 'checkItemType', [$share]);
	}

	/**
	 * @expectedException \OCP\Files\NotFoundException
	 */
	public function testCheckItemTypeWithItemTypeNotSet() {
		$share = $this->newShare();
		$share->setId(12345);

		self::invokePrivate($this->middleware, 'checkItemType', [$share]);
	}

	public function testCheckLinkItemIsValidWithValidLinkItem() {
		$share = $this->newShare();
		$share->setId(12345)
			->setShareOwner('tester')
			->setTarget('folder1');
		$token = 'aaaabbbbccccdddd';

		self::invokePrivate($this->middleware, 'checkLinkItemIsValid', [$share, $token]);
	}

	/**
	 * @expectedException \OCA\GalleryPlus\Middleware\CheckException
	 */
	public function testCheckLinkItemIsValidWithMissingOwner() {
		$share = $this->newShare();
		$share->setId(12345)
			->setTarget('folder1');
		$token = 'aaaabbbbccccdddd';

		self::invokePrivate($this->middleware, 'checkLinkItemIsValid', [$share, $token]);
	}

	/**
	 * @expectedException \OCA\GalleryPlus\Middleware\CheckException
	 */
	public function testCheckLinkItemIsValidWithMissingSource() {
		$share = $this->newShare();
		$share->setId(12345)
			->setShareOwner('tester');
		$token = 'aaaabbbbccccdddd';

		self::invokePrivate($this->middleware, 'checkLinkItemIsValid', [$share, $token]);
	}

	/**
	 * @return array
	 */
	public function providesItemTypes() {
		return [
			['file'],
			['folder']
		];
	}

	/**
	 * @dataProvider providesItemTypes
	 *
	 * @param string $type
	 */
	public function testCheckLinkItemExistsWithValidLinkItem($type) {
		$share = $this->newShare();
		$share->setNodeType($type);

		self::invokePrivate($this->middleware, 'checkLinkItemExists', [$share]);
	}

	/**
	 * @expectedException \OCA\GalleryPlus\Middleware\CheckException
	 */
	public function testCheckLinkItemExistsWithEmptyLinkItem() {
		$share = false;

		self::invokePrivate($this->middleware, 'checkLinkItemExists', [$share]);
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testCheckLinkItemExistsWithWeirdLinkItem() {
		$share = $this->newShare();
		$share->setNodeType('cheese');

		self::invokePrivate($this->middleware, 'checkLinkItemExists', [$share]);
	}

	public function testAfterExceptionWithCheckExceptionAndHtmlAcceptAnd401Code() {
		$message = 'fail';
		$code = Http::STATUS_UNAUTHORIZED;
		$exception = new CheckException($message, $code);

		$template = $this->mockHtml401Response();

		$response =
			$this->middleware->afterException($this->controller, 'checkSession', $exception);

		$this->assertEquals($template, $response);
	}

	public function testAfterExceptionWithCheckExceptionAndHtmlAcceptAnd404Code() {
		$message = 'fail';
		$code = Http::STATUS_NOT_FOUND;
		$exception = new CheckException($message, $code);

		$redirectUrl = '/app/error/route';
		$this->mockHtml404Response($redirectUrl, $code);

		$response =
			$this->middleware->afterException($this->controller, 'authenticate', $exception);

		$this->assertEquals($redirectUrl, $response->getRedirectURL());
		$this->assertEquals(Http::STATUS_SEE_OTHER, $response->getStatus());
		$this->assertEquals($message, $response->getCookies()['galleryErrorMessage']['value']);
	}

	public function testAfterExceptionWithCheckExceptionAndJsonAccept() {
		$message = 'fail';
		$code = Http::STATUS_NOT_FOUND;
		$exception = new CheckException($message, $code);

		$template = $this->mockJsonResponse($message, $code);

		$response =
			$this->middleware->afterException(
				$this->controller, 'checkLinkItemIsValid', $exception
			);

		$this->assertEquals($template, $response);
	}

	/**
	 * @expectedException \OCA\GalleryPlus\Environment\EnvironmentException
	 */
	public function testAfterExceptionWithNonCheckException() {
		$message = 'fail';
		$code = Http::STATUS_NOT_FOUND;
		$exception = new EnvironmentException($message, $code);

		$this->middleware->afterException($this->controller, 'checkLinkItemIsValid', $exception);
	}

	/**
	 * Mocks ISession->exists('public_link_authenticated')
	 *
	 * @param int $linkItemId
	 */
	private function mockSessionExists($linkItemId) {
		$this->session->expects($this->once())
			->method('exists')
			->with('public_link_authenticated')
			->willReturn($linkItemId);
	}

	/**
	 * Mocks ISession->get('public_link_authenticated')
	 *
	 * @param int $linkItemId
	 */
	private function mockSessionWithLinkItemId($linkItemId) {
		$this->session->expects($this->once())
			->method('get')
			->with('public_link_authenticated')
			->willReturn($linkItemId);
	}

	private function mockHtml401Response() {
		$this->mockAcceptHeader('html');
		$this->mockGetParams();

		return new TemplateResponse($this->appName, 'authenticate', [], 'guest');
	}

	private function mockHtml404Response($redirectUrl, $code) {
		$this->mockAcceptHeader('html');
		$this->mockUrlToErrorPage($code, $redirectUrl);
	}

	private function mockJsonResponse($message, $code) {
		$this->mockAcceptHeader('json');
		$jsonData = [
			'message' => $message,
			'success' => false
		];

		return new JSONResponse($jsonData, $code);
	}

	/**
	 * Mocks IRequest->getHeader('Accept')
	 *
	 * @param string $type
	 */
	private function mockAcceptHeader($type) {
		$this->request->expects($this->once())
			->method('getHeader')
			->with('Accept')
			->willReturn($type);
	}

	/**
	 * Mocks IRequest->getParams()
	 */
	private function mockGetParams() {
		$this->request->expects($this->once())
			->method('getParams')
			->willReturn([]);
	}

	/**
	 * Mocks IURLGenerator->linkToRoute()
	 *
	 * @param int $code
	 * @param string $url
	 */
	private function mockUrlToErrorPage($code, $url) {
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with($this->appName . '.page.error_page', ['code' => $code])
			->willReturn($url);
	}

	/**
	 * Mocks IRequest->getParam()
	 *
	 * @param $token
	 * @param $password
	 */
	private function mockGetTokenAndPasswordParams($token, $password = null) {
		$this->request->expects($this->at(0))
			->method('getParam')
			->with('token')
			->willReturn($token);
		$this->request->expects($this->at(1))
			->method('getParam')
			->with('password')
			->willReturn($password);
	}

	/**
	 * Mocks IRequest->getParam('token')
	 */
	private function mockGetTokenParam($token) {
		$this->request->expects($this->any())
			->method('getParam')
			->with('token')
			->willReturn($token);
	}

	private function newShare(){
		return \OC::$server->getShareManager()->newShare();
	}

}
