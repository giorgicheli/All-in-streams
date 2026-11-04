<?php

namespace App\Support;

use InvalidArgumentException;

class SiteContentDefaults
{
    public const BLOCK_TYPES = ['hero', 'geo_strip', 'audiences', 'industries', 'process', 'results', 'faq', 'cta'];
    public const ICONS = ['arrow', 'right', 'left', 'grid', 'briefcase', 'file', 'users', 'user', 'shield', 'check', 'plus', 'close', 'globe', 'game', 'trophy', 'target', 'layers', 'clock', 'message', 'chart', 'wallet', 'star', 'logout', 'menu', 'lock', 'chevron', 'download', 'spark', 'mail', 'eye', 'calendar'];

    public static function for(string $slug): array
    {
        return match ($slug) {
            'home' => self::home(),
            'settings' => self::settings(),
            'privacy' => self::privacy(),
            'terms' => self::terms(),
            default => throw new InvalidArgumentException('Unknown website content page.'),
        };
    }

    public static function home(): array
    {
        return [
            'seo' => [
                'title' => 'Creator partnerships. All in.',
                'description' => 'All In Streams connects brands with gaming creators. From your first brief to the final campaign report, managed end to end.',
            ],
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'enabled' => true,
                    'eyebrow' => 'Creator partnerships. All in.',
                    'title_line_1' => 'Outplay your', 'title_line_2' => 'competition.',
                    'subtitle' => 'Their community.', 'subtitle_accent' => 'Your next big move.',
                    'body' => 'We connect brands with creators who fit their audience, markets, and ambition. Then we manage every step, from the first brief to the final report.',
                    'primary_label' => 'Submit a brief', 'primary_url' => '/briefs/create',
                    'secondary_label' => 'Join as a creator', 'secondary_url' => '/register?role=creator',
                    'footnote' => 'Real connections. Managed end to end.',
                    'preview_label' => 'PARTNERSHIP PREVIEW',
                    'preview_title_1' => 'Your brand.', 'preview_title_2' => 'Their world.', 'preview_highlight' => 'The right fit.',
                    'preview_pill' => 'Audience × Market × Creative', 'preview_caption' => 'Local voices. Lasting connections.',
                    'preview_countries' => [['code' => 'DE', 'name' => 'Germany'], ['code' => 'FR', 'name' => 'France'], ['code' => 'BR', 'name' => 'Brazil']],
                    'float_top' => 'Creator × Community', 'float_bottom' => 'From first brief to final report.',
                    'vertical_label' => 'BUILT AROUND YOUR NEXT MOVE',
                    'bottom_statement' => 'BUILT FOR GAMING CULTURE. MANAGED FOR YOUR GOALS.', 'scroll_label' => 'SCROLL TO EXPLORE',
                    'image_path' => null, 'image_alt' => 'Brand and creator partnership preview',
                ]],
                ['type' => 'geo_strip', 'data' => [
                    'enabled' => true, 'label' => 'MATCH BY AUDIENCE GEO',
                    'countries' => [['code' => 'DE', 'name' => 'Germany'], ['code' => 'FR', 'name' => 'France'], ['code' => 'PL', 'name' => 'Poland'], ['code' => 'BR', 'name' => 'Brazil'], ['code' => 'CA', 'name' => 'Canada'], ['code' => 'MX', 'name' => 'Mexico']],
                    'end_label' => 'YOUR NEXT AUDIENCE',
                ]],
                ['type' => 'audiences', 'data' => [
                    'enabled' => true, 'eyebrow' => 'One ecosystem. Every side of the stream.', 'title' => 'Your next move starts here.',
                    'description' => "Great partnerships take the right people.\nWe bring every side together.",
                    'cards' => [
                        ['icon' => 'target', 'title' => 'For businesses', 'description' => 'Reach the right communities. We handle creator matching, campaign delivery, and reporting around your brief.', 'label' => 'Build your campaign', 'url' => '/register', 'image_path' => null, 'image_alt' => ''],
                        ['icon' => 'layers', 'title' => 'For agencies', 'description' => 'Extend your team. Bring your client brief; we coordinate the creator partnership from start to finish.', 'label' => 'Let’s work together', 'url' => '/register?account_type=agency', 'image_path' => null, 'image_alt' => ''],
                        ['icon' => 'users', 'title' => 'For creators', 'description' => 'Keep creating. Find brand opportunities aligned with your content, your audience, and the markets you know best.', 'label' => 'Join the creator network', 'url' => '/register?role=creator', 'image_path' => null, 'image_alt' => ''],
                    ],
                ]],
                ['type' => 'industries', 'data' => [
                    'enabled' => true, 'eyebrow' => 'We speak your language', 'title' => 'Native to your world.',
                    'description' => 'Made for the spaces where communities play, compete, and connect.',
                    'cards' => [
                        ['icon' => 'globe', 'title' => 'iGaming', 'description' => 'Market-aware partnerships for adult audiences. GEO, eligibility, and responsible promotion considered from the brief.', 'image_path' => null, 'image_alt' => ''],
                        ['icon' => 'game', 'title' => 'Gaming', 'description' => 'From first discovery to the next play session. Put your game inside the communities that live for it.', 'image_path' => null, 'image_alt' => ''],
                        ['icon' => 'trophy', 'title' => 'Esports', 'description' => 'Connect with competitive culture through creators, tournament moments, and communities that follow every play.', 'image_path' => null, 'image_alt' => ''],
                    ],
                ]],
                ['type' => 'process', 'data' => [
                    'enabled' => true, 'eyebrow' => 'The All In approach', 'title_line_1' => 'You set the goal.', 'title_line_2' => 'We run the play.',
                    'description' => 'One team, every step. A clear process that keeps your campaign moving and everyone on the same page.',
                    'label' => 'Let’s build your campaign', 'url' => '/briefs/create',
                    'steps' => [
                        ['title' => 'Tell us the mission.', 'description' => 'Share your audience, markets, goals, budget, and timing.', 'tag' => 'BRIEF'],
                        ['title' => 'Find your people.', 'description' => 'Review creators selected for audience fit and market relevance.', 'tag' => 'MATCH'],
                        ['title' => 'Bring it to life.', 'description' => 'We coordinate invitations, deliverables, approvals, and campaign rollout.', 'tag' => 'ACTIVATE'],
                        ['title' => 'Know what landed.', 'description' => 'Get campaign reporting tied to the objectives agreed in your brief.', 'tag' => 'REPORT'],
                    ],
                ]],
                ['type' => 'results', 'data' => [
                    'enabled' => true, 'eyebrow' => 'Across communities. Across borders.', 'title' => 'Local audiences. Shared ambition.',
                    'description' => 'A place for approved campaign stories, published when results are ready to share.',
                    'regions' => [['label' => 'All GEOs'], ['label' => 'Europe'], ['label' => 'LATAM'], ['label' => 'North America']],
                    'empty_title' => 'Our next story is in the making.', 'empty_description' => 'No approved campaign results published yet.',
                    'privacy_note' => 'Creator applications and private performance data are never added to this public gallery.',
                ]],
                ['type' => 'faq', 'data' => [
                    'enabled' => true, 'eyebrow' => 'A little more clarity', 'title_line_1' => 'Good questions.', 'title_line_2' => 'Clear answers.',
                    'items' => [
                        ['question' => 'How does a partnership start?', 'answer' => 'Create a business account and send us your campaign brief. Our team reviews your objectives, then identifies suitable creators and prepares a shortlist for your review.'],
                        ['question' => 'Can I contact creators directly?', 'answer' => 'All introductions and campaign requests go through All In Streams. We share creator identities when an introduction has been approved by our team.'],
                        ['question' => 'Does submitting a brief commit me to a campaign?', 'answer' => 'No. Your brief starts a conversation. Your campaign launches only after the scope, agreements, and payment readiness have been confirmed.'],
                        ['question' => 'How do creators join?', 'answer' => 'Create a creator account and complete the five-step application. Tell us about your channels, audience, commercial preferences, and availability. Our team reviews your profile before matching you to opportunities.'],
                    ],
                ]],
                ['type' => 'cta', 'data' => [
                    'enabled' => true, 'title' => 'Ready to go all in?', 'description' => 'Your audience is out there. Let’s find your people.',
                    'primary_label' => 'Submit a brief', 'primary_url' => '/briefs/create',
                    'secondary_label' => 'Join as a creator', 'secondary_url' => '/register?role=creator',
                    'image_path' => null, 'image_alt' => '',
                ]],
            ],
        ];
    }

    public static function settings(): array
    {
        return [
            'brand_name' => 'All In Streams', 'brand_tagline' => 'Creator partnerships', 'logo_path' => null,
            'site_description' => 'All In Streams connects brands with gaming creators. From your first brief to the final campaign report, managed end to end.',
            'navigation' => [['label' => 'Platform', 'url' => '#platform'], ['label' => 'Industries', 'url' => '#industries'], ['label' => 'How it works', 'url' => '#how-it-works']],
            'header' => ['sign_in_label' => 'Partner sign in', 'workspace_label' => 'Your workspace', 'cta_label' => 'Get started', 'cta_url' => '/register'],
            'footer' => ['tagline' => 'Creator partnerships. Managed end to end.', 'copyright' => 'All rights reserved.', 'sign_in_label' => 'Partner sign in', 'privacy_label' => 'Privacy policy', 'terms_label' => 'Terms & conditions'],
            'network_stats' => [
                'enabled' => true, 'eyebrow' => 'THE NETWORK BEHIND THE STREAM',
                'title' => 'Great people. Growing possibilities.',
                'description' => 'Creators, agencies and brands. Connected by one team.',
                'is_demo' => true, 'sample_note' => 'Illustrative network figures for this demo.',
                'cards' => [
                    ['icon'=>'users','count'=>120,'suffix'=>'+','label'=>'Creators','description'=>'Streamers & influencers'],
                    ['icon'=>'layers','count'=>18,'suffix'=>'+','label'=>'Agencies','description'=>'Talent & campaign partners'],
                    ['icon'=>'globe','count'=>24,'suffix'=>'','label'=>'Countries','description'=>'Local voices. Global reach.'],
                    ['icon'=>'briefcase','count'=>60,'suffix'=>'+','label'=>'Companies','description'=>'Brands building connections'],
                ],
            ],
            'auth_story' => [
                'eyebrow' => 'Creator partnerships. All in.', 'title_line_1' => 'Great people.', 'title_line_2' => 'Real connections.', 'highlight' => 'All in.',
                'description' => 'The right creators. The right communities. One team to bring it all together.',
                'perks' => [['icon' => 'target', 'text' => 'Matched to your audience and ambition'], ['icon' => 'layers', 'text' => 'Every partnership, managed end to end'], ['icon' => 'shield', 'text' => 'A private space for every partner']],
            ],
            'auth' => [
                'back_label' => 'Back to All In Streams',
                'login' => ['page_title' => 'Partner sign in', 'eyebrow' => 'YOUR NEXT MOVE STARTS HERE', 'title' => 'Welcome back.', 'description' => 'Sign in to your partner workspace.', 'submit_label' => 'Sign in', 'remember_label' => 'Keep me signed in', 'register_prompt' => 'New to All In Streams?', 'register_label' => 'Create an account'],
                'register' => ['page_title' => 'Join All In Streams', 'eyebrow' => 'LET’S MAKE YOUR NEXT MOVE', 'title' => 'Let’s go all in.', 'description' => 'A new partnership starts with you.', 'submit_label' => 'Create account', 'login_prompt' => 'Already a partner?', 'login_label' => 'Sign in', 'channel_help' => 'Add at least one channel. Stream on both? Add both links. Your channel details stay private until our team authorizes an introduction.', 'password_help' => 'Use at least 10 characters, including letters and a number.'],
                'labels' => ['name' => 'Your name', 'company' => 'Company name', 'agency' => 'Agency name', 'email' => 'Email address', 'password' => 'Password', 'password_confirmation' => 'Confirm password', 'twitch' => 'Twitch channel', 'kick' => 'Kick channel', 'company_choice' => 'Company', 'agency_choice' => 'Agency', 'creator_choice' => 'Creator'],
            ],
        ];
    }

    public static function privacy(): array
    {
        return [
            'seo' => ['title' => 'Privacy policy', 'description' => 'Privacy policy for All In Streams.'],
            'title' => 'Privacy policy',
            'body' => ['type' => 'doc', 'content' => []],
        ];
    }

    public static function terms(): array
    {
        return [
            'seo' => ['title' => 'Terms & conditions', 'description' => 'Terms and conditions for All In Streams.'],
            'title' => 'Terms & conditions',
            'body' => ['type' => 'doc', 'content' => []],
        ];
    }
}
