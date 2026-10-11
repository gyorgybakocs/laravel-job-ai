<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGithubCredentialsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GithubController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Github', [
            'githubUsername' => $request->user()->github_username,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(UpdateGithubCredentialsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! $request->filled('github_token')) {
            unset($data['github_token']);
        }

        $request->user()->update($data);

        return to_route('github.edit')->with('status', trans('github.saved'));
    }
}
