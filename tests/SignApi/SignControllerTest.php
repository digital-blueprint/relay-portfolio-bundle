<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests\SignApi;

use Dbp\Relay\PortfolioBundle\SignApi\SignController;
use Dbp\Relay\PortfolioBundle\SignApi\SignCredentials;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobState;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobStateResponse;
use Dbp\Relay\PortfolioBundle\SignApi\SignService;
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

    protected function setUp(): void
    {
        parent::setUp();

        $service = $this->container->get(SignService::class);
        $credentials = $this->container->get(SignCredentials::class);
        $this->controller = new SignController($service, $credentials);
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

        $response = $this->controller->getJobState('pi-1', 'EMAIL', $request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('state', $payload);
        $this->assertSame(SignService::DEFAULT_JOB_STATE->value, $payload['state']);
    }

    #[DataProvider('validNameClassifierProvider')]
    public function testGetJobStateAcceptsSupportedClassifiers(string $nameClassifier): void
    {
        $request = new Request();
        $this->applyAuth($request);

        $response = $this->controller->getJobState('pi-1', $nameClassifier, $request);
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

        $response = $this->controller->getJobState('pi-1', 'NOPE', $request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    // -- getDocument -------------------------------------------------------

    public function testGetDocumentReturnsPdf(): void
    {
        $request = new Request();
        $this->applyAuth($request);

        $response = $this->controller->getDocument('pi-1', $request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function testGetDocumentReturns404WhenNoDocument(): void
    {
        // Service that reports "no document available" by returning null.
        $service = new class extends SignService {
            public function getDocument(string $processInstanceId): ?string
            {
                return null;
            }
        };
        $controller = new SignController($service, $this->container->get(SignCredentials::class));

        $request = new Request();
        $this->applyAuth($request);

        $response = $controller->getDocument('pi-unknown', $request);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    public function testServiceExceptionIsMappedToItsStatus(): void
    {
        // Service that raises a SignException with a specific status.
        $service = new class extends SignService {
            public function getJobState(string $processInstanceId, string $nameClassifier): SignJobStateResponse
            {
                throw SignException::jobNotFound($processInstanceId);
            }
        };
        $controller = new SignController($service, $this->container->get(SignCredentials::class));

        $request = new Request();
        $this->applyAuth($request);

        $response = $controller->getJobState('pi-unknown', 'EMAIL', $request);
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

        $response = $this->controller->cancelJob('pi-1', $request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(SignJobState::FINISHED_WF_CANCELLED->value, $payload['state']);
    }

    public function testCancelJobMissingBodyIsError(): void
    {
        $request = new Request();
        $this->applyAuth($request);

        $response = $this->controller->cancelJob('pi-1', $request);
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

        $response = $this->controller->cancelJob('pi-1', $request);
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

        $response = $this->controller->cancelJob('pi-1', $request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('"error":', (string) $response->getContent());
    }

    // -- auth --------------------------------------------------------------

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
}
