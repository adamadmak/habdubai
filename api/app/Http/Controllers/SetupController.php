<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\RuntimeEnv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * First-run and upgrade steps for a host with no shell: run the migrations,
 * and create the first administrator while there is none. Guarded by
 * SETUP_TOKEN, which must be set and at least 32 characters; with no token
 * the routes answer 404 as if they did not exist.
 */
class SetupController extends Controller
{
    public function migrate(Request $request): JsonResponse
    {
        $this->authorise($request);

        Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());

        if (Schema::hasTable('audit_log')) {
            AuditLog::record(null, 'setup.migrate', null, null, null, ['output' => str($output)->limit(2000)->toString()]);
        }

        // Which database actually answered: on this host a missing build-time variable silently means SQLite.
        return response()->json(['ok' => true, 'driver' => \Illuminate\Support\Facades\DB::connection()->getDriverName(), 'output' => $output]);
    }

    /** Creates the first admin; refuses once any administrator exists. */
    public function admin(Request $request): JsonResponse
    {
        $this->authorise($request);

        if (User::query()->exists()) {
            return response()->json(['message' => 'An administrator already exists. Use the admin:create command or an existing admin.'], 409);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:10'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'] ?? 'Admin',
            'email' => strtolower($data['email']),
            'password' => $data['password'],
        ]);

        AuditLog::record($user->id, 'setup.first_admin', 'user', $user->id);

        return response()->json(['ok' => true, 'user' => ['id' => $user->id, 'email' => $user->email]], 201);
    }

    private function authorise(Request $request): void
    {
        $token = RuntimeEnv::get('services.host.setup_token', null, 'SETUP_TOKEN');
        $provided = (string) $request->header('X-Setup-Token', '');

        if (strlen($token) < 32 || ! hash_equals($token, $provided)) {
            abort(404);
        }
    }
}
