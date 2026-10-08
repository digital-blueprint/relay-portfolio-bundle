<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests\SignApi;

use Dbp\Relay\PortfolioBundle\SignApi\SignController;
use Dbp\Relay\PortfolioBundle\SignApi\SignCredentials;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobDescription;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobState;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobStateResponse;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceInterface;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceRegistry;
use Dbp\Relay\PortfolioBundle\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exercises the four Sign endpoints the way the signature client
 * calls them.
 */
class SignControllerTest extends AbstractTestCase
{
    private const USER = 'svc_user';
    private const PASSWORD = 'svc_pass';
    private const USER_CLASS = 'com.example.api.User';
    private const EXTERNAL_USER_CLASS = 'com.example.api.ExternalUser';

    private SignController $controller;
    private string $processInstanceId;

    protected function setUp(): void
    {
        parent::setUp();

        $registry = $this->container->get(SignServiceRegistry::class);
        $credentials = $this->container->get(SignCredentials::class);
        $this->controller = new SignController($registry, $credentials);
        $service = $registry->getService('test_process');
        $this->processInstanceId = $service->startProcess('test_process', SignJobDescription::fromArray($this->jobDescription()), '%PDF', []);
    }

    private function jobDescription(): array
    {
        return [
            'constituent' => [
                'classifier' => 'EMAIL',
                'name' => 'owner@example.com',
                '@class' => self::USER_CLASS,
            ],
            'positionType' => 'SIGNATURE_PAGE',
            'metaData' => [
                'referenceId' => 'CASE-123',
            ],
            'iterationData' => [
                [
                    'invitees' => [[
                        '@class' => self::USER_CLASS,
                        'classifier' => 'EMAIL',
                        'name' => 'approver@example.com',
                        'roleName' => 'signer',
                    ]],
                    'category' => 'APPROVAL',
                    'iterationNumber' => 0,
                ],
                [
                    'invitees' => [[
                        '@class' => self::EXTERNAL_USER_CLASS,
                        'classifier' => 'EMAIL',
                        'name' => 'external@example.com',
                        'externalUserName' => 'New Employee',
                        'locale' => 'de',
                        'roleName' => 'Extern',
                    ]],
                    'category' => 'EXTERNAL_APPROVAL',
                    'iterationNumber' => 1,
                ],
            ],
        ];
    }

    private function makePdf(string $content = "%PDF-1.4\n%mock\n%%EOF"): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'signapi_').'.pdf';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'contract.pdf', 'application/pdf', null, true);
    }

    /**
     * Builds a startProcess request with valid auth, the jobDescription part and
     * a documentToSign PDF part.
     */
    private function startProcessRequest(bool $auth = true): Request
    {
        $request = new Request(
            request: ['jobDescription' => json_encode($this->jobDescription())],
            files: ['documentToSign' => $this->makePdf()],
        );
        if ($auth) {
            $this->applyAuth($request);
        }

        return $request;
    }

    private function applyAuth(Request $request, string $user = self::USER, string $password = self::PASSWORD): void
    {
        $request->headers->set('Authorization', 'Basic '.base64_encode($user.':'.$password));
        $request->server->set('PHP_AUTH_USER', $user);
        $request->server->set('PHP_AUTH_PW', $password);
    }

    private function controllerWithService(TestSignService $service): SignController
    {
        $registry = new SignServiceRegistry();
        $registry->addService('test_process', $service);
        $this->processInstanceId = $service->startProcess('test_process', SignJobDescription::fromArray($this->jobDescription()), '%PDF', []);

        return new SignController($registry, $this->container->get(SignCredentials::class));
    }

    // -- startProcess ------------------------------------------------------

    public function testStartProcessReturnsBareStringId(): void
    {
        $response = $this->controller->startProcess('foobar42', $this->startProcessRequest());

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        // Bare string, not JSON: no quotes, no braces.
        $this->assertStringNotContainsString('"', $body);
        $this->assertStringNotContainsString('{', $body);
        $this->assertNotEmpty(trim($body));
        // Never contains the connector's error-detection substrings.
        $this->assertStringNotContainsString('"error":', $body);
        $this->assertStringNotContainsString('"ErrorCode":', $body);
    }

    public function testStartProcessWithAttachments(): void
    {
        $request = new Request(
            request: ['jobDescription' => json_encode($this->jobDescription())],
            files: [
                'documentToSign' => $this->makePdf(),
                'attachments' => [$this->makePdf('%PDF-1.4 att1'), $this->makePdf('%PDF-1.4 att2')],
            ],
        );
        $this->applyAuth($request);

        $response = $this->controller->startProcess('foobar42', $request);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertNotEmpty(trim((string) $response->getContent()));
    }

    /**
     * The client sends attachments as `attachments[]`, so a single attachment
     * still arrives as a one-element array of UploadedFile.
     */
    public function testStartProcessWithSingleAttachment(): void
    {
        $request = new Request(
            request: ['jobDescription' => json_encode($this->jobDescription())],
            files: [
                'documentToSign' => $this->makePdf(),
                'attachments' => [$this->makePdf('%PDF-1.4 only')],
            ],
        );
        $this->applyAuth($request);

        $response = $this->controller->startProcess('foobar42', $request);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertNotEmpty(trim((string) $response->getContent()));
    }

    public function testStartProcessMissingJobDescriptionIsError(): void
    {
        $request = new Request(files: ['documentToSign' => $this->makePdf()]);
        $this->applyAuth($request);

        $response = $this->controller->startProcess('foobar42', $request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    public function testStartProcessMissingDocumentIsError(): void
    {
        $request = new Request(request: ['jobDescription' => json_encode($this->jobDescription())]);
        $this->applyAuth($request);

        $response = $this->controller->startProcess('foobar42', $request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    // -- getJobState -------------------------------------------------------

    public function testGetJobStateReturnsState(): void
    {
        $request = new Request();
        $this->applyAuth($request);

        $response = $this->controller->getJobState($this->processInstanceId, 'EMAIL', $request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('state', $payload);
        $this->assertSame(SignJobState::ACTIVE->value, $payload['state']);
    }

    #[DataProvider('validNameClassifierProvider')]
    public function testGetJobStateAcceptsSupportedClassifiers(string $nameClassifier): void
    {
        $request = new Request();
        $this->applyAuth($request);

        $response = $this->controller->getJobState($this->processInstanceId, $nameClassifier, $request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function validNameClassifierProvider(): iterable
    {
        yield 'EMAIL' => ['EMAIL'];
        yield 'ID' => ['ID'];
        yield 'UPN' => ['UPN'];
    }

    public function testGetJobStateRejectsUnknownClassifier(): void
    {
        $request = new Request();
        $this->applyAuth($request);

        $response = $this->controller->getJobState($this->processInstanceId, 'NOPE', $request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    // -- getDocument -------------------------------------------------------

    public function testGetDocumentReturnsPdf(): void
    {
        $request = new Request();
        $this->applyAuth($request);

        $response = $this->controller->getDocument($this->processInstanceId, $request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function testGetDocumentReturns404WhenNoDocument(): void
    {
        // Service that reports "no document available" by returning null.
        $service = new class extends TestSignService {
            public function getDocument(string $processInstanceId): ?string
            {
                return null;
            }
        };
        $controller = $this->controllerWithService($service);

        $request = new Request();
        $this->applyAuth($request);

        $response = $controller->getDocument($this->processInstanceId, $request);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    public function testServiceExceptionIsMappedToItsStatus(): void
    {
        // Service that raises a SignException with a specific status.
        $service = new class extends TestSignService {
            public function getJobState(string $processInstanceId, string $nameClassifier): SignJobStateResponse
            {
                throw SignException::jobNotFound($processInstanceId);
            }
        };
        $controller = $this->controllerWithService($service);

        $request = new Request();
        $this->applyAuth($request);

        $response = $controller->getJobState($this->processInstanceId, 'EMAIL', $request);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    // -- cancelJob ---------------------------------------------------------

    public function testCancelJobReturnsCancelledState(): void
    {
        $request = new Request(content: json_encode([
            '@class' => self::USER_CLASS,
            'classifier' => 'EMAIL',
            'name' => ' owner@example.com',
        ]));
        $this->applyAuth($request);

        $response = $this->controller->cancelJob($this->processInstanceId, $request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(SignJobState::FINISHED_WF_CANCELLED->value, $payload['state']);
    }

    public function testCancelJobMissingBodyIsError(): void
    {
        $request = new Request();
        $this->applyAuth($request);

        $response = $this->controller->cancelJob($this->processInstanceId, $request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    public function testCancelJobEmptyClassIsError(): void
    {
        $request = new Request(content: json_encode([
            '@class' => '',
            'classifier' => 'EMAIL',
            'name' => 'owner@example.com',
        ]));
        $this->applyAuth($request);

        $response = $this->controller->cancelJob($this->processInstanceId, $request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    public function testCancelJobMissingNameIsError(): void
    {
        $request = new Request(content: json_encode([
            '@class' => self::USER_CLASS,
            'classifier' => 'EMAIL',
        ]));
        $this->applyAuth($request);

        $response = $this->controller->cancelJob($this->processInstanceId, $request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    // -- auth --------------------------------------------------------------

    #[DataProvider('unauthenticatedCredentialsProvider')]
    public function testUnauthenticatedRequestsDoNotResolveProcesses(?string $username, ?string $password): void
    {
        $service = $this->createMock(SignServiceInterface::class);
        $service->expects($this->never())->method('resolveProcessId');
        $registry = new SignServiceRegistry();
        $registry->addService('test_process', $service);
        $controller = new SignController($registry, $this->container->get(SignCredentials::class));
        $request = new Request();
        if ($username !== null && $password !== null) {
            $this->applyAuth($request, $username, $password);
        }

        $responses = [
            $controller->startProcess('test_process', $request),
            $controller->getJobState('instance', 'EMAIL', $request),
            $controller->getDocument('instance', $request),
            $controller->cancelJob('instance', $request),
        ];
        foreach ($responses as $response) {
            $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
            $this->assertSame('Basic realm="Sign API"', $response->headers->get('WWW-Authenticate'));
        }
    }

    public static function unauthenticatedCredentialsProvider(): iterable
    {
        yield 'missing credentials' => [null, null];
        yield 'unknown user' => ['unknown_user', 'password'];
        yield 'wrong password' => [self::USER, 'wrong_password'];
    }

    public function testStartProcessMissingAuthIsUnauthorized(): void
    {
        $response = $this->controller->startProcess('foobar42', new Request());
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testStartProcessBadAuthIsUnauthorized(): void
    {
        $request = new Request();
        $this->applyAuth($request, 'nope', 'nope');

        $response = $this->controller->startProcess('foobar42', $request);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetJobStateMissingAuthIsUnauthorized(): void
    {
        $response = $this->controller->getJobState('pi-1', 'EMAIL', new Request());
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetJobStateBadAuthIsUnauthorized(): void
    {
        $request = new Request();
        $this->applyAuth($request, 'nope', 'nope');

        $response = $this->controller->getJobState('pi-1', 'EMAIL', $request);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetDocumentMissingAuthIsUnauthorized(): void
    {
        $response = $this->controller->getDocument('pi-1', new Request());
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetDocumentBadAuthIsUnauthorized(): void
    {
        $request = new Request();
        $this->applyAuth($request, 'nope', 'nope');

        $response = $this->controller->getDocument('pi-1', $request);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCancelJobMissingAuthIsUnauthorized(): void
    {
        $response = $this->controller->cancelJob('pi-1', new Request());
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCancelJobBadAuthIsUnauthorized(): void
    {
        $request = new Request();
        $this->applyAuth($request, 'nope', 'nope');

        $response = $this->controller->cancelJob('pi-1', $request);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    // -- per-process access control ----------------------------------------

    public function testResolvesProcessOnlyOnce(): void
    {
        $service = $this->createMock(SignServiceInterface::class);
        $service->expects($this->once())->method('resolveProcessId')->with('instance')->willReturn('test_process');
        $service->method('getJobState')->willReturn(new SignJobStateResponse(SignJobState::ACTIVE));
        $registry = new SignServiceRegistry();
        $registry->addService('test_process', $service);
        $controller = new SignController($registry, $this->container->get(SignCredentials::class));
        $request = new Request();
        $this->applyAuth($request);

        $this->assertSame(Response::HTTP_OK, $controller->getJobState('instance', 'EMAIL', $request)->getStatusCode());
    }

    public function testConfiguredProcessWithoutImplementationReturns404(): void
    {
        $controller = new SignController(new SignServiceRegistry(), $this->container->get(SignCredentials::class));
        $response = $controller->startProcess('foobar42', $this->startProcessRequest());

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('No signing service registered', (string) $response->getContent());
    }

    public function testUnknownInstanceIsForbiddenForAllEndpoints(): void
    {
        $request = new Request();
        $this->applyAuth($request);

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller->getJobState('unknown', 'EMAIL', $request)->getStatusCode());
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller->getDocument('unknown', $request)->getStatusCode());
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->controller->cancelJob('unknown', $request)->getStatusCode());
    }

    public function testStartProcessValidUserNotAdminIsForbidden(): void
    {
        // other_user is a valid api_user but not an admin of foobar42.
        $request = $this->startProcessRequest(auth: false);
        $this->applyAuth($request, 'other_user', 'other_pass');

        $response = $this->controller->startProcess('foobar42', $request);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertFalse($response->headers->has('WWW-Authenticate'));
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    public function testStartProcessUnknownProcessIsForbidden(): void
    {
        $response = $this->controller->startProcess('unknown_process', $this->startProcessRequest());
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    public function testGetJobStateValidUserNotAdminIsForbidden(): void
    {
        // The instance belongs to test_process, of which
        // other_user is not an admin.
        $request = new Request();
        $this->applyAuth($request, 'other_user', 'other_pass');

        $response = $this->controller->getJobState($this->processInstanceId, 'EMAIL', $request);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    public function testGetDocumentValidUserNotAdminIsForbidden(): void
    {
        $request = new Request();
        $this->applyAuth($request, 'other_user', 'other_pass');

        $response = $this->controller->getDocument($this->processInstanceId, $request);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    public function testCancelJobValidUserNotAdminIsForbidden(): void
    {
        $request = new Request(content: json_encode([
            '@class' => self::USER_CLASS,
            'classifier' => 'EMAIL',
            'name' => 'owner@example.com',
        ]));
        $this->applyAuth($request, 'other_user', 'other_pass');

        $response = $this->controller->cancelJob($this->processInstanceId, $request);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }
}
