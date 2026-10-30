<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListClientsRequest;
use App\Services\Admin\ClientService;
use Illuminate\Http\Request;

/**
 * The platform's clients - everyone who signed up on the website - and, on
 * each one's page, the businesses they own and the plan each is on.
 */
class ClientController extends Controller
{
    public function __construct(
        protected ClientService $clientService,
    ) {}

    public function index(ListClientsRequest $request)
    {
        try {
            $clients  = $this->clientService->paginate(
                trim((string) $request->validated('search')),
                $request->validated('package'),
            );

            $packages = $this->clientService->packages();

            return view('admin.clients.index', compact('clients', 'packages'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(int $id)
    {
        try {
            $client   = $this->clientService->findWithBusinessesOrFail($id);
            $totals   = $this->clientService->totals($client);
            $packages = $this->clientService->packages();

            return view('admin.clients.show', compact('client', 'totals', 'packages'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Opens the client's dashboard on the website, signed in as them, in the
     * tab the button opened. Refused while the client could not sign in
     * themselves.
     */
    public function loginAs(Request $request, int $id)
    {
        try {
            $client = $this->clientService->findOrFail($id);

            if ($problem = $this->clientService->loginAsProblem($client)) {
                return $this->adminFailure($request, $problem, 422);
            }

            return redirect()->away($this->clientService->loginAsLink($client, $request->user()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
