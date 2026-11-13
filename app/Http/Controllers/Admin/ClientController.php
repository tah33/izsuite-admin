<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListClientsRequest;
use App\Services\Admin\ClientService;

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
            $clients  = $this->clientService->paginate($request->filters());
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

            return view('admin.clients.show', [...compact('client', 'totals', 'packages'), ...$this->clientService->security($client)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
