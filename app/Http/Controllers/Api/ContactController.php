<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Contacts\ListContactRequest;
use App\Http\Requests\Api\Contacts\SaveContactRequest;
use App\Http\Resources\Contacts\ContactResource;
use App\Services\Api\Contacts\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(
        protected ContactService $contactService,
    ) {}

    /**
     * The business's contacts, a page at a time - a business can have
     * thousands - alphabetical, optionally searched and filtered.
     */
    public function index(ListContactRequest $request): JsonResponse
    {
        try {
            $contacts = $this->contactService->list(
                $this->currentWorkspace($request),
                $request->only(['search', 'role', 'type', 'status', 'tax_treatment', 'for_sale', 'for_purchase']),
                requested_per_page(),
            );

            return response()->json([
                'data'       => ContactResource::collection($contacts->items()),
                'pagination' => slim_pagination($contacts),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** One contact, whole - for its profile and its edit form. */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new ContactResource($this->contactService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        try {
            return $this->setActive($request, $id, true);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        try {
            return $this->setActive($request, $id, false);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveContactRequest $request): JsonResponse
    {
        try {
            $contact = $this->contactService->create($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Contact added successfully.',
                'data'    => new ContactResource($contact),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveContactRequest $request, int $id): JsonResponse
    {
        try {
            $contact = $this->contactService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json([
                'message' => 'Contact updated successfully.',
                'data'    => new ContactResource($contact),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->contactService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Contact deleted successfully.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The answer for activate() and deactivate(), which catch around it. */
    private function setActive(Request $request, int $id, bool $active): JsonResponse
    {
        $contact = $this->contactService->setActive($this->currentWorkspace($request), $id, $active);

        return response()->json([
            'message' => $active ? 'Contact activated.' : 'Contact deactivated.',
            'data'    => new ContactResource($contact),
        ]);
    }
}
