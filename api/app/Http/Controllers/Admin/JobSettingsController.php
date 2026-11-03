<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Support\JobSettings;
use App\Support\RuntimeEnv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Jobs pipeline settings. The Anthropic key is write-only: a blank field
 * keeps the stored key, and the page only ever learns whether one is set and
 * its last four characters.
 */
class JobSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $key = JobSettings::anthropicKey();

        return response()->json([
            'settings' => [
                'job_channel_id' => JobSettings::channel(),
                'job_review_threshold' => JobSettings::reviewThreshold(),
                'job_warning_threshold' => JobSettings::warningThreshold(),
                'job_auto_publish' => JobSettings::autoPublish(),
                'job_card_languages' => implode(',', JobSettings::cardLanguages()),
                'job_scam_phrases' => (string) PlatformSetting::valueFor('job_scam_phrases', ''),
                'job_blocked_companies' => (string) PlatformSetting::valueFor('job_blocked_companies', ''),
                'anthropic_model' => JobSettings::model(),
            ],
            'anthropic' => [
                'configured' => $key !== '',
                'hint' => $key !== '' ? '…'.substr($key, -4) : null,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'job_channel_id' => ['nullable', 'string', 'max:64', 'regex:/^(@[A-Za-z][A-Za-z0-9_]{4,31}|-?\d{5,20})$/'],
            'job_review_threshold' => ['required', 'integer', 'min:1', 'max:100'],
            'job_warning_threshold' => ['required', 'integer', 'min:1', 'max:100'],
            'job_auto_publish' => ['required', 'boolean'],
            'job_card_languages' => ['required', 'string', 'regex:/^(am|ti|en|om)(,(am|ti|en|om))*$/'],
            'job_scam_phrases' => ['nullable', 'string', 'max:5000'],
            'job_blocked_companies' => ['nullable', 'string', 'max:10000'],
            'anthropic_model' => ['required', 'string', 'max:64', 'regex:/^claude-[a-z0-9.-]+$/'],
            'anthropic_api_key' => ['nullable', 'string', 'regex:/^sk-ant-[A-Za-z0-9_-]{20,}$/'],
        ], [
            'anthropic_api_key.regex' => 'That does not look like an Anthropic API key (sk-ant-…).',
            'job_channel_id.regex' => 'Use the channel @username or its numeric id (-100…).',
        ]);

        $before = [];
        foreach ($data as $key => $value) {
            if ($key === 'anthropic_api_key') {
                continue;
            }
            $before[$key] = RuntimeEnv::get(null, $key, null);
            PlatformSetting::put($key, is_bool($value) ? ($value ? '1' : '0') : trim((string) $value));
        }

        $keyChanged = false;
        if (filled($data['anthropic_api_key'] ?? null)) {
            JobSettings::setAnthropicKey($data['anthropic_api_key']);
            $keyChanged = true;
        }

        unset($data['anthropic_api_key']);
        AuditLog::record($request->user()->id, 'jobs.settings_updated', 'settings', null, $before, $data + ['anthropic_api_key_changed' => $keyChanged]);

        return $this->show();
    }
}
