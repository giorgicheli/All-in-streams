<?php

namespace App\Console\Commands;

use App\Models\SitePage;
use App\Models\User;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Publish the two owner-supplied documents through the normal CMS revision flow. */
class ImportLegalDocuments extends Command
{
    protected $signature = 'website:import-legal-documents
        {--publish : Publish the supplied documents; otherwise show a read-only plan}
        {--replace : Permit replacing an existing non-empty published policy}
        {--admin= : Administrator ID to record in the publication audit}';

    protected $description = 'Import the supplied privacy policy and terms without changing other website content';

    public function handle(SiteContent $content): int
    {
        try {
            $plans = $this->plans($content);
            $this->table(['Page', 'Document title', 'Body blocks', 'Publication'], array_map(fn (array $plan): array => [
                $plan['slug'], $plan['content']['title'], count($plan['content']['body']['content']),
                $plan['changed'] ? 'Update required' : 'Already current',
            ], $plans));

            if (! $this->option('publish')) {
                $this->info('Dry run only. Use --publish to import, with --replace if a published policy already has content.');
                return self::SUCCESS;
            }

            $actor = $this->actor();
            if (! $actor) {
                $this->error('An existing administrator is required. Choose one with --admin=<id>.');
                return self::FAILURE;
            }

            $updated = DB::transaction(function () use ($content, $actor): int {
                SitePage::whereIn('slug', ['privacy', 'terms'])->lockForUpdate()->get();
                $plans = $this->plans($content);

                // Check both policies before publishing either of them.
                foreach ($plans as $plan) {
                    if ($plan['changed'] && $plan['hasPublishedBody'] && ! $this->option('replace')) {
                        throw new LogicException('Existing '.$plan['slug'].' content was preserved. Use --replace to explicitly replace it.');
                    }
                }

                $updated = 0;
                foreach ($plans as $plan) {
                    if (! $plan['changed']) continue;
                    $content->publish($actor, $plan['slug'], $plan['content']);
                    if ($plan['separateDraft'] !== null) {
                        $content->saveDraft($actor, $plan['slug'], $plan['separateDraft']);
                        $this->line('Preserved the separate unpublished '.$plan['slug'].' draft.');
                    }
                    ++$updated;
                }

                return $updated;
            });

            $this->info($updated.' legal page(s) published. Other pages, site settings and separate unpublished drafts were preserved.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function plans(SiteContent $content): array
    {
        $plans = [];
        foreach (['privacy', 'terms'] as $slug) {
            $source = json_decode(file_get_contents(resource_path('legal/'.$slug.'.json')), true, flags: JSON_THROW_ON_ERROR);
            $published = $content->getPublished($slug);
            $import = $published;
            $import['title'] = $source['title'];
            $import['seo']['title'] = $source['title'];
            $import['body'] = $source['body'];
            $import = $content->validate($slug, $import);
            $page = SitePage::where('slug', $slug)->first();
            $baseline = $page?->published_content ?? SiteContentDefaults::for($slug);
            $draft = $page?->draft_content;
            $plans[] = [
                'slug' => $slug, 'content' => $import,
                'changed' => $published !== $import,
                'hasPublishedBody' => ! empty($published['body']['content']),
                'separateDraft' => $draft !== null && $draft !== $baseline ? $draft : null,
            ];
        }
        return $plans;
    }

    private function actor(): ?User
    {
        $actorId = $this->option('admin');
        if ($actorId !== null && ((! is_string($actorId) && ! is_int($actorId)) || ! ctype_digit((string) $actorId) || (int) $actorId < 1)) {
            throw new LogicException('The administrator ID must be a positive integer.');
        }
        $query = User::where('role', 'admin');
        if ($actorId !== null) return $query->whereKey($actorId)->first();
        return $query->orderBy('is_demo')->orderBy('id')->first();
    }
}
