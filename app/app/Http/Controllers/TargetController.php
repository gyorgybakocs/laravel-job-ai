<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTargetRequest;
use App\Src\Github\GithubClientFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TargetController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('targets/Index');
    }

    public function store(StoreTargetRequest $request, GithubClientFactory $factory): RedirectResponse
    {
        $data = $request->validated();
        $client = $factory->make($request->user()->github_token);

        try {
            $client->getRepositories($data['name'], $data['type']);
            $message = 'target_found';
        } catch (ConnectionException) {
            $message = 'request_failed';
        } catch (RequestException $exception) {
            $message = match ($exception->response->status()) {
                404 => 'target_not_found',
                401 => 'invalid_token',
                403, 429 => 'access_restricted',
                default => 'request_failed',
            };
        }

        return to_route('targets.index')->with('target_result', trans("targets.{$message}", [
            'target' => $data['name'],
        ]));
    }
}
