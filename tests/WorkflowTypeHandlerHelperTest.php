<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WorkflowTypeHandlerHelperTest extends TestCase
{
    private UriSigner $uriSigner;
    private WorkflowTypeHandlerHelper $helper;

    protected function setUp(): void
    {
        $this->uriSigner = new UriSigner('test-secret');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            fn (string $route, array $params) => 'https://example.com/portfolio/tasks/'.$params['identifier']
        );

        $this->helper = new WorkflowTypeHandlerHelper($urlGenerator, $this->uriSigner);
    }

    public function testGetTaskUrl(): void
    {
        $this->assertSame(
            'https://example.com/portfolio/tasks/my-task',
            $this->helper->getTaskUrl('my-task')
        );
    }

    public function testGetSignedTaskUrlIsVerifiableByUriSigner(): void
    {
        $signed = $this->helper->getSignedTaskUrl('my-task');

        $this->assertTrue($this->uriSigner->check($signed));
    }

    public function testGetSignedTaskUrlContainsTaskId(): void
    {
        $signed = $this->helper->getSignedTaskUrl('my-task');

        $this->assertStringContainsString('/portfolio/tasks/my-task', $signed);
    }

    public function testGetSignedTaskUrlContainsExpiration(): void
    {
        $before = time();
        $signed = $this->helper->getSignedTaskUrl('my-task', 3600);
        $after = time();

        parse_str((string) parse_url($signed, PHP_URL_QUERY), $params);
        $expiration = (int) $params['_expiration'];

        $this->assertGreaterThanOrEqual($before + 3600, $expiration);
        $this->assertLessThanOrEqual($after + 3600, $expiration);
    }

    public function testGetSignedTaskUrlRespectsTtl(): void
    {
        $before = time();
        $signed60 = $this->helper->getSignedTaskUrl('my-task', 60);
        $signed7200 = $this->helper->getSignedTaskUrl('my-task', 7200);

        parse_str((string) parse_url($signed60, PHP_URL_QUERY), $params60);
        parse_str((string) parse_url($signed7200, PHP_URL_QUERY), $params7200);

        $exp60 = (int) $params60['_expiration'];
        $exp7200 = (int) $params7200['_expiration'];

        $this->assertGreaterThanOrEqual($before + 60, $exp60);
        $this->assertGreaterThanOrEqual($before + 7200, $exp7200);
        $this->assertGreaterThan($exp60, $exp7200);
    }

    public function testSignedUrlWithWrongSecretFailsCheck(): void
    {
        $signed = $this->helper->getSignedTaskUrl('my-task');

        $wrongSigner = new UriSigner('wrong-secret');
        $this->assertFalse($wrongSigner->check($signed));
    }

    public function testTamperedTaskIdFailsCheck(): void
    {
        $signed = $this->helper->getSignedTaskUrl('my-task');

        // Replace the task ID in the path — signature should no longer match
        $tampered = str_replace('/tasks/my-task', '/tasks/other-task', $signed);
        $this->assertFalse($this->uriSigner->check($tampered));
    }
}
