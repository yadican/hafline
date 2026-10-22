<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OperationException;
use App\Exceptions\OperationRejectedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\HaulScanRequest;
use App\Http\Requests\Api\HaulStoreRequest;
use App\Http\Resources\Api\HaulPrintResource;
use App\Models\Haul;
use App\Services\Api\FieldHaulService;
use App\Services\Api\SecureImageIngest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HaulController extends Controller
{
    public function __construct(
        private FieldHaulService $fieldHauls,
        private SecureImageIngest $images,
    ) {}

    #[OA\Get(
        path: '/api/v1/hauls/options',
        operationId: 'haulsOptions',
        summary: 'Sites and shifts for a field session',
        security: [['bearerAuth' => []]],
        tags: ['Hauls'],
        responses: [
            new OA\Response(response: 200, description: 'Session option lists'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function options(): JsonResponse
    {
        $this->authorize('view-haul');

        return response()->json($this->fieldHauls->options());
    }

    #[OA\Get(
        path: '/api/v1/hauls/dump-sites',
        operationId: 'haulsDumpSites',
        summary: 'Dump sites with an active route for the given site',
        security: [['bearerAuth' => []]],
        tags: ['Hauls'],
        parameters: [
            new OA\Parameter(name: 'site_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dump site list'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function dumpSites(Request $request): JsonResponse
    {
        $this->authorize('view-haul');

        $validated = $request->validate([
            'site_id' => ['required', 'uuid'],
        ]);

        return response()->json([
            'dump_sites' => $this->fieldHauls->dumpSites($validated['site_id']),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/hauls/scan',
        operationId: 'haulsScan',
        summary: 'Upload a plate photo, store it privately, and run OCR',
        security: [['bearerAuth' => []]],
        tags: ['Hauls'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        required: ['file', 'field_session_id'],
                        properties: [
                            new OA\Property(property: 'file', type: 'string', format: 'binary'),
                            new OA\Property(property: 'field_session_id', type: 'string', format: 'uuid', description: 'Active field session id'),
                        ]
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 200, description: 'Scan result'),
            new OA\Response(response: 422, description: 'Invalid image or missing session'),
            new OA\Response(response: 429, description: 'Too many requests'),
            new OA\Response(response: 502, description: 'OCR unavailable'),
        ]
    )]
    public function scan(HaulScanRequest $request): JsonResponse
    {
        try {
            return response()->json(
                $this->fieldHauls->scan(
                    $request->file('file'),
                    $request->string('field_session_id')->value(),
                    $request->user(),
                )
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }

    #[OA\Post(
        path: '/api/v1/hauls',
        operationId: 'haulsStore',
        summary: 'Create a haul from a confirmed plate scan',
        description: 'Site/dump/shift come from the locked field session. Send only plate, scan_id and field_session_id.',
        security: [['bearerAuth' => []]],
        tags: ['Hauls'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['plate', 'scan_id', 'field_session_id'],
                properties: [
                    new OA\Property(property: 'plate', type: 'string', example: '34 ABC 123'),
                    new OA\Property(property: 'scan_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'field_session_id', type: 'string', format: 'uuid', description: 'id from POST /field-sessions'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Haul created with print payload'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function store(HaulStoreRequest $request): JsonResponse
    {
        try {
            $payload = $this->fieldHauls->store($request->validated(), $request->user());
        } catch (OperationRejectedException $exception) {
            throw ValidationException::withMessages(['plate' => $exception->getMessage()]);
        } catch (OperationException $exception) {
            throw ValidationException::withMessages(['plate' => $exception->getMessage()]);
        }

        return (new HaulPrintResource($payload))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/v1/hauls/session',
        operationId: 'haulsSession',
        summary: 'Today hauls for the locked site / dump / shift session',
        description: 'Requires an open field session. List is filtered automatically by that session site_id, dump_site_id and shift.',
        security: [['bearerAuth' => []]],
        tags: ['Hauls'],
        responses: [
            new OA\Response(response: 200, description: 'Session context + haul list'),
            new OA\Response(response: 422, description: 'No active field session'),
        ]
    )]
    public function session(Request $request): JsonResponse
    {
        $this->authorize('view-haul');

        return response()->json(
            $this->fieldHauls->sessionHauls($request->user())
        );
    }

    #[OA\Get(
        path: '/api/v1/hauls/{haul}',
        operationId: 'haulsShow',
        summary: 'Print DTO for a haul',
        security: [['bearerAuth' => []]],
        tags: ['Hauls'],
        parameters: [
            new OA\Parameter(name: 'haul', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Print payload'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Request $request, Haul $haul): HaulPrintResource
    {
        $this->authorize('view-haul');

        return new HaulPrintResource($this->fieldHauls->showFor($request->user(), $haul));
    }

    #[OA\Get(
        path: '/api/v1/hauls/{haul}/photo',
        operationId: 'haulsPhoto',
        summary: 'Private haul photo (signed URL)',
        tags: ['Hauls'],
        parameters: [
            new OA\Parameter(name: 'haul', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'JPEG image'),
            new OA\Response(response: 403, description: 'Invalid signature'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function photo(Haul $haul): BinaryFileResponse
    {
        $path = $this->fieldHauls->photoPathFor($haul);

        return response()->file($path, [
            'Content-Type' => $this->images->mimeForPath($path),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
