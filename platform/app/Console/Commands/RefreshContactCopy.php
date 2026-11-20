<?php

namespace App\Console\Commands;

use App\Models\SitePage;
use App\Models\User;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Apply the requested contact copy without publishing unrelated editor work. */
class RefreshContactCopy extends Command
{
    protected $signature = 'site:refresh-contact-copy
        {--publish : Publish the updated contact copy; otherwise show a read-only plan}
        {--actor= : Administrator ID to record in the publication audit}';

    protected $description = 'Refresh only the contact heading, description and form labels from the site defaults';

    private const FIELDS = ['eyebrow', 'title', 'description', 'name_label', 'email_field_label', 'message_label', 'submit_label'];

    public function handle(SiteContent $content): int
    {
        try {
            $plan = $this->plan($content);
            $this->table(['Contact copy', 'Publication'], [
                ['Published settings', $plan['publishedChanged'] ? 'Update required' : 'Already current'],
                ['Pending draft settings', $plan['draftChanged'] ? 'Update required' : 'Already current'],
            ]);
            if (! $this->option('publish')) {
                $this->info('Dry run only. Use --publish to refresh contact copy. Other content and contact email addresses are preserved.');
                return self::SUCCESS;
            }

            $actor = $this->actor();
            if (! $actor) {
                $this->error('An existing administrator is required. Choose one with --actor=<id>.');
                return self::FAILURE;
            }

            [$published, $drafts] = DB::transaction(function () use ($content, $actor): array {
                SitePage::where('slug', 'settings')->lockForUpdate()->first();
                $plan = $this->plan($content);
                if ($plan['publishedChanged']) {
                    $content->publish($actor, 'settings', $plan['published']);
                    // Publishing normally aligns the draft. Restore any distinct
                    // editor work while applying only these same seven copy fields.
                    if ($plan['draft'] !== $plan['published']) {
                        $content->saveDraft($actor, 'settings', $plan['draft']);
                    }
                } elseif ($plan['draftChanged']) {
                    $content->saveDraft($actor, 'settings', $plan['draft']);
                }

                return [(int) $plan['publishedChanged'], (int) $plan['draftChanged']];
            });

            $this->info($published.' contact copy publication(s), '.$drafts.' draft refresh(es). Other settings and unpublished editor work were preserved.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function plan(SiteContent $content): array
    {
        $copy = Arr::only(SiteContentDefaults::settings()['contact'], self::FIELDS);
        $published = $content->getPublished('settings');
        $draft = $content->getDraft('settings');
        $nextPublished = $published;
        $nextDraft = $draft;
        $nextPublished['contact'] = array_replace($published['contact'], $copy);
        $nextDraft['contact'] = array_replace($draft['contact'], $copy);
        $nextPublished = $content->validate('settings', $nextPublished);
        $nextDraft = $content->validate('settings', $nextDraft);

        return [
            'published' => $nextPublished, 'draft' => $nextDraft,
            'publishedChanged' => $nextPublished !== $published,
            'draftChanged' => $nextDraft !== $draft,
        ];
    }

    private function actor(): ?User
    {
        $id = $this->option('actor');
        if ($id !== null && ((! is_string($id) && ! is_int($id)) || ! ctype_digit((string) $id) || (int) $id < 1)) {
            throw new LogicException('The administrator ID must be a positive integer.');
        }
        $query = User::where('role', 'admin');

        return $id !== null ? $query->whereKey($id)->first() : $query->where('is_demo', false)->orderBy('id')->first();
    }
}
