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
                    'eyebrow' => 'Creator marketplace × full-service agency',
                    'title_line_1' => 'Trusted', 'title_line_2' => 'creators.',
                    'headlines' => [['line_1' => 'Flexible', 'line_2' => 'terms.'], ['line_1' => 'Faster', 'line_2' => 'deals.']],
                    'subtitle' => 'Their community.', 'subtitle_accent' => 'Your next big move.',
                    'body' => 'We connect betting, casino, and gaming brands with vetted streamers and creators who turn audience attention into action. The right talent. The right markets. Campaigns built for growth.',
                    'primary_label' => 'Start a campaign', 'primary_url' => '/talk-to-us?type=brand',
                    'secondary_label' => 'Explore creators', 'secondary_url' => '#platform',
                    'footnote' => 'Vetted creator partnerships · Global GEO coverage',
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
                    'countries' => [
                        ['code' => 'DE', 'name' => 'Germany'], ['code' => 'PT', 'name' => 'Portugal'],
                        ['code' => 'CA', 'name' => 'Canada'], ['code' => 'PL', 'name' => 'Poland'],
                        ['code' => 'CH', 'name' => 'Switzerland'], ['code' => 'BR', 'name' => 'Brazil'],
                        ['code' => 'AR', 'name' => 'Argentina'], ['code' => 'NZ', 'name' => 'New Zealand'],
                        ['code' => 'HU', 'name' => 'Hungary'], ['code' => 'US', 'name' => 'United States'],
                        ['code' => 'AT', 'name' => 'Austria'], ['code' => 'SE', 'name' => 'Sweden'],
                        ['code' => 'NO', 'name' => 'Norway'], ['code' => 'FI', 'name' => 'Finland'],
                        ['code' => 'KG', 'name' => 'Kyrgyzstan'],
                    ],
                    'end_label' => 'YOUR NEXT AUDIENCE',
                ]],
                ['type' => 'audiences', 'data' => [
                    'enabled' => true, 'eyebrow' => 'One ecosystem. Every side of the stream.', 'title' => 'Your growth. Our game plan.',
                    'description' => "Great partnerships take the right people.\nWe bring every side together.",
                    'cards' => [
                        ['icon' => 'target', 'title' => 'For brands', 'description' => 'Reach the right communities. We handle creator matching, campaign delivery, and reporting around your brief.', 'label' => 'Build your campaign', 'url' => '/talk-to-us?type=brand', 'image_path' => null, 'image_alt' => ''],
                        ['icon' => 'layers', 'title' => 'For agencies', 'description' => 'Extend your team. Bring your client brief; we coordinate the creator partnership from start to finish.', 'label' => 'Let’s work together', 'url' => '/talk-to-us?type=agency', 'image_path' => null, 'image_alt' => ''],
                        ['icon' => 'users', 'title' => 'For creators', 'description' => 'Keep creating. Find brand opportunities aligned with your content, your audience, and the markets you know best.', 'label' => 'Join the creator network', 'url' => '/talk-to-us?type=creator', 'image_path' => null, 'image_alt' => ''],
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
                    'label' => 'Let’s build your campaign', 'url' => '/talk-to-us?type=brand',
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
                    'enabled' => true, 'eyebrow' => 'A little more clarity', 'title_line_1' => 'Frequently Asked', 'title_line_2' => 'Questions',
                    'label' => 'Talk to us', 'url' => '/talk-to-us',
                    'items' => [
                        ['question' => 'Which markets do you work with?', 'answer' => 'We work globally, matching brands and agencies with creators based on audience GEO, language, and campaign goals. Share your target markets so we can find the right fit.'],
                        ['question' => 'How are campaign terms agreed?', 'answer' => 'Each proposal sets out the stream package, deliverables, pricing, payment schedule, tracking, and any performance commitments. Fixed-fee, CPA, revenue share, or hybrid terms depend on the creator and the campaign.'],
                        ['question' => 'Can I choose creators myself?', 'answer' => 'You can explore anonymous profiles and build a shortlist. Send your brief and we will confirm the fit, availability, and verified audience information before an introduction.'],
                        ['question' => 'How do you measure performance?', 'answer' => 'We agree on tracking and reporting before launch. Depending on the campaign, this can include clicks, registrations, qualified first-time depositors, and total deposits. Measurement windows and any make-up arrangements are agreed in the contract.'],
                        ['question' => 'How can I join?', 'answer' => 'Whether you’re a brand, agency, or creator, click Talk to us, select your category, and complete the relevant form. Our team will review your details and get in touch about the next steps.'],
                    ],
                ]],
                ['type' => 'cta', 'data' => [
                    'enabled' => true, 'title' => 'Ready to go all in?', 'description' => 'Your audience is out there. Let’s find your people.',
                    'primary_label' => 'Start a campaign', 'primary_url' => '/talk-to-us?type=brand',
                    'secondary_label' => 'Join as a creator', 'secondary_url' => '/talk-to-us?type=creator',
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
            'navigation' => [['label' => 'Platform', 'url' => '#platform'], ['label' => 'How it works', 'url' => '#how-it-works'], ['label' => 'Creator network', 'url' => '#creators'], ['label' => 'About us', 'url' => '#about']],
            'platform_menu' => [
                ['label' => 'For brands', 'description' => 'Find creators for your next campaign', 'url' => '/talk-to-us?type=brand'],
                ['label' => 'For agencies', 'description' => 'Expand your creator partnerships', 'url' => '/talk-to-us?type=agency'],
                ['label' => 'For creators', 'description' => 'Join our creator network', 'url' => '/talk-to-us?type=creator'],
            ],
            'header' => ['sign_in_label' => 'Partner sign in', 'workspace_label' => 'Your workspace', 'cta_label' => "Let's talk", 'cta_url' => '/talk-to-us'],
            'footer' => ['tagline' => 'Creator partnerships. Managed end to end.', 'copyright' => 'All rights reserved.', 'sign_in_label' => 'Partner sign in', 'privacy_label' => 'Privacy policy', 'terms_label' => 'Terms & conditions'],
            'network_stats' => [
                'enabled' => true, 'eyebrow' => 'THE NETWORK BEHIND THE STREAM',
                'title' => 'Be part of a growing network.',
                'description' => 'Unlock unlimited possibilities. Creators, agencies, and brands. Connected by one dedicated team.',
                'is_demo' => true, 'sample_note' => 'Illustrative network figures for this demo.',
                'cards' => [
                    ['icon'=>'users','count'=>120,'suffix'=>'+','label'=>'Creators','description'=>'Streamers & influencers'],
                    ['icon'=>'layers','count'=>18,'suffix'=>'+','label'=>'Agencies','description'=>'Talent & campaign partners'],
                    ['icon'=>'globe','count'=>24,'suffix'=>'','label'=>'Countries','description'=>'Local voices. Global reach.'],
                    ['icon'=>'briefcase','count'=>60,'suffix'=>'+','label'=>'Companies','description'=>'Brands building connections'],
                ],
            ],
            'about' => [
                'enabled' => true, 'eyebrow' => 'ALL IN, TOGETHER', 'title' => 'Real people. Shared ambition.',
                'description' => 'All In Streams connects brands, agencies and creators through one dedicated team. We bring the people, process and performance together, from the first conversation to the final report.',
            ],
            'creator_network' => [
                'enabled' => true, 'eyebrow' => 'DISCOVER YOUR NEXT MATCH',
                'title_line_1' => 'Different voices.', 'title_line_2' => 'The right influence.',
                'description' => 'Discover streamers and influencers by audience region. Compare their audiences, build your shortlist, and send us your brief to find the right match.',
                'request_label' => 'Request more profiles', 'request_url' => '/talk-to-us?type=brand',
            ],
            'enquiry_entry' => [
                'eyebrow' => 'LET’S MAKE IT HAPPEN', 'title' => 'Let’s find your right match.',
                'description' => 'Choose how you would like to work with All In Streams.',
                'cards' => [
                    ['icon' => 'target', 'title' => 'Brand', 'description' => 'Find creators for your next campaign.', 'url' => '/talk-to-us?type=brand'],
                    ['icon' => 'layers', 'title' => 'Agency', 'description' => 'Expand your creator partnerships.', 'url' => '/talk-to-us?type=agency'],
                    ['icon' => 'users', 'title' => 'Creator', 'description' => 'Join our creator network.', 'url' => '/talk-to-us?type=creator'],
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
