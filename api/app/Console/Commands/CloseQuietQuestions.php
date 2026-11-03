<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;

/** Closes question topics that have been quiet for seven days, solved or not. */
class CloseQuietQuestions extends Command
{
    protected $signature = 'questions:close-quiet';

    protected $description = 'Close question topics with no activity for seven days';

    public function handle(TelegramBotService $telegram): int
    {
        $quiet = Question::query()->whereIn('status', ['open', 'solved'])
            ->where('last_activity_at', '<', now()->subDays(Question::QUIET_DAYS))->get();

        foreach ($quiet as $question) {
            if ($question->forum_topic_id && $question->group_chat_id) {
                $telegram->closeForumTopic((string) $question->group_chat_id, (int) $question->forum_topic_id);
            }
            $question->forceFill(['status' => $question->status === 'solved' ? 'solved' : 'closed'])->save();
            // A solved question stays "solved" in our records; its topic is closed all the same.
            $question->forceFill(['last_activity_at' => null])->save();
        }

        $this->info('Closed '.$quiet->count().' quiet question topic(s).');

        return self::SUCCESS;
    }
}
