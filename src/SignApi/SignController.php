<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Signature webservice.
 *
 * Only provides a limited subset of the API.
 *
 * Auth is HTTP Basic and is checked manually in each handler.
 */
#[AsController]
class SignController
{
    private const BASE_PATH = '/portfolio/_signapi/webservices/rest/api/layer2/v1.0';

    public function __construct(
        private readonly SignService $service,
        private readonly SignCredentials $credentials,
    ) {
    }

    #[Route(path: self::BASE_PATH.'/startProcess/{processId}', name: 'dbp_relay_portfolio_signapi_start_process', methods: ['POST'])]
    public function startProcess(string $processId, Request $request): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }

        $jobDescriptionRaw = $request->request->get('jobDescription');
        if (!is_string($jobDescriptionRaw) || $jobDescriptionRaw === '') {
            return $this->error('The jobDescription part is required.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $jobDescriptionData = json_decode($jobDescriptionRaw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->error('The jobDescription part is not valid JSON.', Response::HTTP_BAD_REQUEST);
        }
        if (!is_array($jobDescriptionData)) {
            return $this->error('The jobDescription part must be a JSON object.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $jobDescription = SignJobDescription::fromArray($jobDescriptionData);
        } catch (SignException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }

        $documentToSign = $request->files->get('documentToSign');
        if ($documentToSign === null) {
            return $this->error('The documentToSign part is required.', Response::HTTP_BAD_REQUEST);
        }
        $documentBytes = file_get_contents($documentToSign->getPathname());
        if ($documentBytes === false) {
            return $this->error('The documentToSign part could not be read.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $attachmentBytes = [];
        foreach ($request->files->all('attachments') as $attachment) {
            if ($attachment instanceof UploadedFile) {
                $bytes = file_get_contents($attachment->getPathname());
                if ($bytes === false) {
                    return $this->error('An attachment could not be read.', Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $attachmentBytes[] = $bytes;
            }
        }

        try {
            $processInstanceId = $this->service->startProcess($processId, $jobDescription, $documentBytes, $attachmentBytes);
        } catch (SignException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }

        return new Response($processInstanceId, Response::HTTP_CREATED, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    #[Route(path: self::BASE_PATH.'/getJobState/{processInstanceId}/{nameClassifier}', name: 'dbp_relay_portfolio_signapi_get_job_state', methods: ['GET'])]
    public function getJobState(string $processInstanceId, string $nameClassifier, Request $request): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }

        if (SignClassifier::tryFrom($nameClassifier) === null) {
            $valid = array_map(static fn (SignClassifier $c): string => $c->value, SignClassifier::cases());

            return $this->error(
                sprintf('The "nameClassifier" must be one of %s.', implode(', ', $valid)),
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $jobState = $this->service->getJobState($processInstanceId, $nameClassifier);
        } catch (SignException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }

        return new JsonResponse($jobState->toArray());
    }

    #[Route(path: self::BASE_PATH.'/getDocument/{processInstanceId}', name: 'dbp_relay_portfolio_signapi_get_document', methods: ['GET'])]
    public function getDocument(string $processInstanceId, Request $request): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }

        $pdf = $this->service->getDocument($processInstanceId);
        if ($pdf === null) {
            return $this->error(
                sprintf("No document is available for job '%s'.", $processInstanceId),
                Response::HTTP_NOT_FOUND,
            );
        }

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    #[Route(path: self::BASE_PATH.'/cancelJob/{processInstanceId}', name: 'dbp_relay_portfolio_signapi_cancel_job', methods: ['PUT'])]
    public function cancelJob(string $processInstanceId, Request $request): Response
    {
        if (($deny = $this->guard($request)) !== null) {
            return $deny;
        }

        $content = $request->getContent();
        if ($content === '') {
            return $this->error('The request body is required.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->error('The request body is not valid JSON.', Response::HTTP_BAD_REQUEST);
        }
        if (!is_array($data)) {
            return $this->error('The request body must be a JSON object.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = SignUser::fromArray($data);
        } catch (SignException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }

        try {
            $state = $this->service->cancelJob($processInstanceId, $user);
        } catch (SignException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }

        return new JsonResponse(['state' => $state->value]);
    }

    /**
     * Enforces HTTP Basic auth. Returns a 401 error Response when the request is
     * unauthenticated, or null when the credentials are valid.
     */
    private function guard(Request $request): ?Response
    {
        [$user, $password] = $this->extractBasicAuth($request);

        if (!$this->credentials->check($user, $password)) {
            return $this->error('Unauthorized.', Response::HTTP_UNAUTHORIZED);
        }

        return null;
    }

    /**
     * Extracts the HTTP Basic username/password from the request by decoding the
     * `Authorization: Basic` header directly.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function extractBasicAuth(Request $request): array
    {
        $header = $request->headers->get('Authorization', '');
        if (stripos($header, 'Basic ') === 0) {
            $decoded = base64_decode(substr($header, 6), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$user, $password] = explode(':', $decoded, 2);

                return [$user, $password];
            }
        }

        return [null, null];
    }

    /**
     * Builds a JSON error response. The body deliberately contains the `"error":`
     * substring so the signature client reliably detects the failure.
     */
    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
