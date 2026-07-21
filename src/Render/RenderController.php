<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Render;

use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Serves handler-generated content (e.g. HTML for embedding in an iframe) for a
 * workflow. Access is authorized solely by a valid signature on the request URL
 * (see WorkflowTypeHandlerHelper::getSignedRenderUrl()), not by a bearer token —
 * this is what allows the URL to be loaded directly as an iframe src.
 */
#[AsController]
class RenderController
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly UriSigner $uriSigner,
        private readonly Locale $locale,
    ) {
    }

    #[Route(path: '/portfolio/_render', name: 'dbp_relay_portfolio_render', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        if (!$this->uriSigner->checkRequest($request)) {
            throw new AccessDeniedHttpException('Invalid or missing signature.');
        }

        $workflowId = $request->query->get('workflowId');
        $renderId = $request->query->get('renderId');

        if ($workflowId === null) {
            throw new BadRequestHttpException('The workflowId parameter is required.');
        }
        if ($renderId === null) {
            throw new BadRequestHttpException('The renderId parameter is required.');
        }

        $workflow = $this->workflowService->getWorkflow($workflowId);
        if ($workflow === null) {
            throw new NotFoundHttpException(sprintf("Workflow '%s' not found.", $workflowId));
        }

        $result = $this->workflowService->getRenderResponse(
            $workflow,
            $renderId,
            $this->locale->getCurrentPrimaryLanguage(),
        );

        return new Response(
            $result->getHtml(),
            Response::HTTP_OK,
            ['Content-Type' => $result->getContentType()],
        );
    }
}
