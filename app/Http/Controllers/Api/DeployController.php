<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DeployController extends Controller
{
    public function migrate(Request $request): JsonResponse
    {
        $token = (string) config('services.deploy.token');

        if ($token === '' || ! hash_equals($token, (string) $request->header('X-Deploy-Token'))) {
            abort(403);
        }

        Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = Artisan::output();

        // Ensure the public storage symlink exists on every deploy so uploaded
        // experience photos remain reachable from storage/app/public. Use
        // --force to recreate the link if the target is missing or stale.
        Artisan::call('storage:link', ['--force' => true]);
        $storageLinkOutput = Artisan::output();

        return response()->json([
            'output' => $migrateOutput.$storageLinkOutput,
        ]);
    }
}
