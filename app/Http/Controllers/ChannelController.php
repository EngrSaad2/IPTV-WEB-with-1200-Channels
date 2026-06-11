<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ChannelController extends Controller
{
    // Channel logic with secure stream prioritization
    private const FEED_URL = 'https://cdn.jsdelivr.net/gh/foridul422/IPTV-@main/channels.json';
    private const DEFAULT_FALLBACK_LOGO = 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1770377900139.png';

    public function index(Request $request)
    {
        $category = $request->query('category', 'all');
        $channels = $this->fetchAndFilterChannels($category);
        return response()->json($channels);
    }

    private function fetchAndFilterChannels(string $category): array
    {
        $cacheKey = "iptv_channels_raw";
        $rawChannels = Cache::remember($cacheKey, 3600, function () {
            try {
                $response = $this->adaptiveGet(self::FEED_URL);
                if ($response->successful()) {
                    $json = $response->json();
                    if (!empty($json)) {
                        return $json;
                    }
                }
            } catch (\Exception $e) {
                // Fail silently
            }

            // Fallback to local channels.json
            $localPath = resource_path('json/channels.json');
            if (file_exists($localPath)) {
                return json_decode(file_get_contents($localPath), true) ?? [];
            }

            return [];
        });


        $all = [];
        // Add custom movie channels
        $all = array_merge($all, $this->getCustomMovieChannels());
        // Add FIFA / Sports channels
        $all = array_merge($all, $this->getFifaSportsChannels());

        foreach ($rawChannels as $c) {
            if (empty($c['name']) || empty($c['url']) || !str_starts_with($c['url'], 'http')) {
                continue;
            }

            $logo = $this->resolveLogoByName($c['name']);
            if ($logo === self::DEFAULT_FALLBACK_LOGO && !empty($c['logo']) && str_starts_with($c['logo'], 'http')) {
                $logo = $c['logo'];
            }

            $newChannel = [
                'name' => $c['name'],
                'logo' => $logo,
                'group' => $c['group'] ?? 'Sports',
                'url' => $c['url']
            ];

            if (str_contains(strtolower($c['name']), 't sports')) {
                $newChannel['alternates'] = [
                    'https://tvsen7.aynaott.com/tsports-hd/index.m3u8?e=1780662761&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=755de1abb34402b6543ac8e82a34488d',
                    'https://tvsen7.aynaott.com/tsports-hd/index.m3u8?e=1779283784&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=3b4c5a2cfa872fa7f91ffbfb4aa0f658'
                ];
            }

            $this->addOrUpgradeChannel($all, $newChannel);
        }

        $all = $this->prioritizeSports($all);

        return $this->filterByCategory($all, $category);
    }

    private function isAlreadyAdded(array $list, string $name): bool
    {
        $nameNormalized = preg_replace('/[^a-z0-9]/', '', strtolower($name));
        foreach ($list as $item) {
            $itemNameNormalized = preg_replace('/[^a-z0-9]/', '', strtolower($item['name']));
            if ($itemNameNormalized === $nameNormalized) {
                return true;
            }
        }
        return false;
    }

    private function addOrUpgradeChannel(array &$list, array $newChannel): void
    {
        $newNormalized = preg_replace('/[^a-z0-9]/', '', strtolower($newChannel['name']));
        $newIsHttps = str_starts_with(strtolower($newChannel['url']), 'https');

        foreach ($list as $index => $existing) {
            $existingNormalized = preg_replace('/[^a-z0-9]/', '', strtolower($existing['name']));
            if ($existingNormalized === $newNormalized) {
                $existingIsHttps = str_starts_with(strtolower($existing['url']), 'https');
                
                // Upgrade from HTTP to HTTPS if dynamic version is secure
                if (!$existingIsHttps && $newIsHttps) {
                    $list[$index]['url'] = $newChannel['url'];
                    if (!empty($newChannel['logo']) && $newChannel['logo'] !== self::DEFAULT_FALLBACK_LOGO) {
                        $list[$index]['logo'] = $newChannel['logo'];
                    }
                }
                
                // Copy alternates if present
                if (isset($newChannel['alternates'])) {
                    $list[$index]['alternates'] = $newChannel['alternates'];
                }
                return;
            }
        }

        $list[] = $newChannel;
    }

    private function prioritizeSports(array $channels): array
    {
        $prioritized = [];
        $ptv = null;
        $asports = null;
        $tsports = null;
        $ddsports = null;
        $others = [];

        foreach ($channels as $item) {
            $nameNormalized = preg_replace('/[^a-z0-9]/', '', strtolower($item['name']));
            
            if ($nameNormalized === 'tsportshd' || $nameNormalized === 'tsports') {
                if (!$tsports) $tsports = $item;
                else $others[] = $item;
            } elseif (str_contains($nameNormalized, 'ptvsports')) {
                if (!$ptv) $ptv = $item;
                else $others[] = $item;
            } elseif (str_contains($nameNormalized, 'asports')) {
                if (!$asports) $asports = $item;
                else $others[] = $item;
            } elseif ($nameNormalized === 'ddsports') {
                if (!$ddsports) $ddsports = $item;
                else $others[] = $item;
            } else {
                $others[] = $item;
            }
        }

        if ($tsports) $prioritized[] = $tsports;
        if ($ptv) $prioritized[] = $ptv;
        if ($asports) $prioritized[] = $asports;
        if ($ddsports) $prioritized[] = $ddsports;

        return array_merge($prioritized, $others);
    }

    private function filterByCategory(array $channels, string $category): array
    {
        $filtered = [];
        $categoryLower = strtolower($category);

        foreach ($channels as $item) {
            $nameLower = strtolower($item['name']);

            // Explicit global exclusions matching Kotlin, keeping requested channels
            $isBeinMaxOrSports = (str_contains($nameLower, 'bein sports') && (str_contains($nameLower, 'max') || $nameLower === 'bein sports'));
            $isFoxAllowed = (str_contains($nameLower, 'fox sports') && (str_contains($nameLower, '1') || str_contains($nameLower, '2') || $nameLower === 'fox sports'));

            if ((str_contains($nameLower, 'bein sports') && !$isBeinMaxOrSports) ||
                str_contains($nameLower, 'sky sports') ||
                str_contains($nameLower, 'tnt sports') ||
                str_contains($nameLower, 'espn') ||
                (str_contains($nameLower, 'fox sports') && !$isFoxAllowed) ||
                str_contains($nameLower, 'supersport')) {
                continue;
            }

            if ($categoryLower === 'all') {
                $filtered[] = $item;
                continue;
            }

            $group = $item['group'] ?? '';
            $groupLower = strtolower($group);
            $urlLower = strtolower($item['url']);

            $isIslamic = ($groupLower === 'religious' || $groupLower === 'islamic');
            $isMovie = (
                str_contains($groupLower, 'movie') ||
                str_contains($groupLower, 'cinema') ||
                str_contains($groupLower, 'film') ||
                str_contains($nameLower, 'movie') ||
                str_contains($nameLower, 'cinema') ||
                str_contains($nameLower, 'film') ||
                str_contains($nameLower, 'hbo') ||
                str_contains($nameLower, 'cinemax') ||
                str_contains($nameLower, 'sony pix') ||
                str_contains($nameLower, 'movies now') ||
                str_contains($nameLower, 'cineedge') ||
                str_contains($nameLower, 'uniques') ||
                str_contains($nameLower, 'superrix') ||
                str_contains($nameLower, 'screem') ||
                str_contains($nameLower, 'crimes') ||
                str_contains($nameLower, 'true stories') ||
                str_contains($nameLower, 'intelligence') ||
                str_contains($nameLower, 'originals') ||
                str_contains($nameLower, 'sony aath')
            );

            // Bangladeshi check matching Android Room rules
            $isBd = ($groupLower === 'bangla' || $groupLower === 'bangladesh');
            if (!$isBd) {
                if (
                    str_contains($nameLower, 'bangla') ||
                    str_contains($nameLower, ' bd') ||
                    str_contains($nameLower, 'bd ') ||
                    str_contains($nameLower, 'dhaka') ||
                    str_ends_with($nameLower, ' bd') ||
                    str_contains($urlLower, 'tsports') ||
                    str_contains($urlLower, 'somoy') ||
                    str_contains($urlLower, 'btv') ||
                    str_contains($urlLower, 'ekattor') ||
                    str_contains($urlLower, 'jamuna') ||
                    str_contains($urlLower, 'dbc') ||
                    str_contains($urlLower, 'ekhon') ||
                    str_contains($urlLower, 'news24') ||
                    str_contains($urlLower, 'atn') ||
                    str_contains($urlLower, 'rtv') ||
                    str_contains($urlLower, 'channeli') ||
                    str_contains($urlLower, 'channel9') ||
                    str_contains($urlLower, 'boishakhi') ||
                    str_contains($urlLower, 'desh') ||
                    str_contains($urlLower, 'anandatv') ||
                    str_contains($urlLower, 'duronto') ||
                    str_contains($urlLower, 'satv') ||
                    str_contains($urlLower, 'etv') ||
                    str_contains($urlLower, 'bijoy') ||
                    str_contains($urlLower, 'mytv') ||
                    str_contains($urlLower, 'mohona') ||
                    str_contains($urlLower, 'maasranga') ||
                    str_contains($urlLower, 'nexus') ||
                    str_contains($urlLower, 'asian') ||
                    str_contains($urlLower, 'ntv') ||
                    str_contains($urlLower, 'gazi') ||
                    str_contains($urlLower, 'banglavision') ||
                    str_contains($urlLower, 'anb')
                ) {
                    $isBd = true;
                }
            }

            // Exclude foreign duplicates from BD
            if ($isBd) {
                if (
                    str_contains($nameLower, 'republic') ||
                    str_contains($nameLower, 'sangeet') ||
                    str_contains($nameLower, 'dd bangla') ||
                    str_contains($nameLower, 'enter 10') ||
                    str_contains($nameLower, 'enter10') ||
                    str_contains($nameLower, 'khushboo') ||
                    str_contains($nameLower, 'colors') ||
                    str_contains($nameLower, 'zee') ||
                    str_contains($nameLower, 'star') ||
                    str_contains($nameLower, 'jalsha') ||
                    str_contains($nameLower, 'rupashi') ||
                    str_contains($nameLower, 'aakash') ||
                    str_contains($nameLower, 'news18') ||
                    str_contains($nameLower, 'tv9') ||
                    str_contains($nameLower, 'r. bangla') ||
                    str_contains($nameLower, 'manoranjan') ||
                    str_contains($nameLower, 'world war') ||
                    str_contains($nameLower, 'travel') ||
                    str_contains($nameLower, 'power turk') ||
                    str_contains($nameLower, 'azstar') ||
                    str_contains($nameLower, 'tn live') ||
                    str_contains($nameLower, 'center tv') ||
                    str_contains($nameLower, 'cnn indonesia') ||
                    str_contains($nameLower, 'discovery') ||
                    str_contains($nameLower, 'rtv 38') ||
                    str_contains($nameLower, 'rtp3') ||
                    str_contains($groupLower, 'india') ||
                    str_contains($groupLower, 'hindi') ||
                    str_contains($groupLower, 'italy') ||
                    str_contains($groupLower, 'greece') ||
                    str_contains($groupLower, 'ukraine') ||
                    str_contains($groupLower, 'russia') ||
                    str_contains($groupLower, 'turkey')
                ) {
                    $isBd = false;
                }
            }

            if ($categoryLower === 'bangladeshi') {
                if ($isBd && !$isIslamic && !$isMovie) {
                    $filtered[] = $item;
                }
            } elseif ($categoryLower === 'islamic') {
                if ($isIslamic) {
                    $filtered[] = $item;
                }
            } elseif ($categoryLower === 'movies') {
                if ($nameLower === 'movies now' || str_contains($nameLower, 'sony pix') || str_contains($nameLower, 'cinemax')) {
                    continue;
                }
                if ($isMovie && !$isIslamic) {
                    $filtered[] = $item;
                }
            } elseif ($categoryLower === 'global') {
                if (
                    str_contains($nameLower, 'bbc') ||
                    str_contains($nameLower, 'reuters') ||
                    str_contains($nameLower, 'bloomberg') ||
                    str_contains($nameLower, 'fox news') ||
                    str_contains($nameLower, 'france 24') ||
                    str_contains($nameLower, 'euronews')
                ) {
                    continue;
                }
                if (!$isBd && !$isIslamic && !$isMovie && !str_contains($nameLower, 'bangla') && !str_contains($groupLower, 'bangla')) {
                    $filtered[] = $item;
                }
            }
        }

        // Apply prioritization overrides matching Android
        if ($categoryLower === 'islamic') {
            return $this->prioritizeIslamic($filtered);
        }

        if ($categoryLower === 'global') {
            return $this->prioritizeGlobal($filtered);
        }

        if ($categoryLower === 'movies') {
            return $this->prioritizeMovies($filtered);
        }

        return $filtered;
    }

    private function prioritizeIslamic(array $channels): array
    {
        $prioritized = [];
        $wazTv = null;
        $islamicTv = null;
        $peaceTv = null;
        $quranTv = null;
        $others = [];

        foreach ($channels as $item) {
            $name = strtoupper(trim($item['name']));
            if ($name === 'WAZ TV' && !$wazTv) {
                $wazTv = $item;
            } elseif ($name === 'ISLAMIC TV' && !$islamicTv) {
                $islamicTv = $item;
            } elseif (($name === 'PEACE TV BANGLA' || $name === 'PEACE TV') && !$peaceTv) {
                $peaceTv = $item;
            } elseif ($name === 'QURAN TV' && !$quranTv) {
                $quranTv = $item;
                $quranTv['logo'] = 'https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEjleKdDnG77fWUg5ZKAV8JKa9MFOH8iq7q0lmMTtzF75qsAZfxfxzTzbdVhhHhCUbCszQ9CZqNDkslVA_OWboGPZARGQFVXHb9cKBUB1FlG8KcqQffWWdXKSNBWJ_2Dd-YFGqjia2ujeIOinC_Ilkb83kZbJE07IMPjnF06dUlSn_LhN-OwahHc43cESw/s600/Nur_20251107_152155_0000.png';
            } else {
                $others[] = $item;
            }
        }

        if ($wazTv) $prioritized[] = $wazTv;
        if ($islamicTv) $prioritized[] = $islamicTv;
        if ($peaceTv) $prioritized[] = $peaceTv;
        if ($quranTv) $prioritized[] = $quranTv;

        return array_merge($prioritized, $others);
    }

    private function prioritizeGlobal(array $channels): array
    {
        $prioritized = [];
        $cnn = null;
        $aljazeera = null;
        $sky = null;
        $cnbc = null;
        $others = [];

        foreach ($channels as $item) {
            $name = strtolower(trim($item['name']));

            if ($name === 'cnn' || str_contains($name, 'cnn international')) {
                if (!$cnn) {
                    $cnn = $item;
                    $cnn['logo'] = 'https://raw.githubusercontent.com/tv-logo/tv-logos/main/countries/united-states/cnn-us.png';
                }
            } elseif ($name === 'al jazeera' || $name === 'al jazeera english' || str_contains($name, 'al jazeera')) {
                if (!$aljazeera) {
                    $aljazeera = $item;
                    $aljazeera['logo'] = 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Al%20Jazeera%20.png';
                }
            } elseif ($name === 'sky news' || str_contains($name, 'sky news (uk)') || str_contains($name, 'sky news')) {
                if (!$sky) {
                    $sky = $item;
                    $sky['logo'] = 'https://raw.githubusercontent.com/tv-logo/tv-logos/main/countries/united-kingdom/sky-news-uk.png';
                }
            } elseif (str_contains($name, 'cnbc') && !str_contains($name, 'indonesia')) {
                if (!$cnbc) {
                    $cnbc = $item;
                    $cnbc['logo'] = 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/CNBC.png';
                }
            } else {
                $others[] = $item;
            }
        }

        if ($cnn) $prioritized[] = $cnn;
        if ($aljazeera) $prioritized[] = $aljazeera;
        if ($sky) $prioritized[] = $sky;
        if ($cnbc) $prioritized[] = $cnbc;

        return array_merge($prioritized, $others);
    }

    private function prioritizeMovies(array $channels): array
    {
        $prioritized = [];
        $starMovies = null;
        $hbo = null;
        $cineedge = null;
        $sonyAath = null;
        $others = [];

        foreach ($channels as $item) {
            $name = strtolower(trim($item['name']));

            if (($name === 'star movies' || str_contains($name, 'star movies')) && !$starMovies) {
                $starMovies = $item;
            } elseif (str_contains($name, 'hbo') && !$hbo) {
                $hbo = $item;
                $hbo['logo'] = asset('images/hbo.png');
            } elseif (str_contains($name, 'cineedge') && !$cineedge) {
                $cineedge = $item;
            } elseif (str_contains($name, 'sony aath') && !$sonyAath) {
                $sonyAath = $item;
                $sonyAath['logo'] = 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Sony%20Aath.jpeg';
            } else {
                $others[] = $item;
            }
        }

        if ($starMovies) $prioritized[] = $starMovies;
        if ($hbo) $prioritized[] = $hbo;
        if ($cineedge) $prioritized[] = $cineedge;
        if ($sonyAath) $prioritized[] = $sonyAath;

        return array_merge($prioritized, $others);
    }

    private function getCustomMovieChannels(): array
    {
        return [
            ['name' => 'Star Movies', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Movies.png', 'url' => 'http://198.195.239.50:8095/StarMovies/index.m3u8', 'group' => 'Movies'],
            ['name' => 'Cineedge HD', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1770347851305.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/cineedge/playlist.m3u8', 'group' => 'Movies'],
            ['name' => 'Uniques HD', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1770347327658.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/uniques/playlist.m3u8', 'group' => 'Movies'],
            ['name' => 'Superrix HD', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1770348388925.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/superrix/playlist.m3u8', 'group' => 'Movies'],
            ['name' => 'Screem', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1770312098339.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/screem/playlist.m3u8', 'group' => 'Movies'],
            ['name' => 'Crimes', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1770380126540.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/crimes/playlist.m3u8', 'group' => 'Movies'],
            ['name' => 'True Stories', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1770380306806.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/truestories/playlist.m3u8', 'group' => 'Movies'],
            ['name' => 'Intelligence', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1770380460488.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/intelligence/playlist.m3u8', 'group' => 'Movies'],
            ['name' => 'Originals', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1778085327477.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/originals/playlist.m3u8', 'group' => 'Movies'],
            ['name' => 'Hindi Movie Classic 24', 'logo' => 'https://s3.aynaott.com/storage/3132515182ec50091b496fe515564084', 'url' => 'https://vods2.aynaott.com/hindimovies/index.m3u8', 'group' => 'Movies'],
            ['name' => 'Action Hollywood Movies', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Movies.png', 'url' => 'https://amg01076-lightningintern-actionhollywood-samsungnz-82rry.amagi.tv/playlist/amg01076-lightningintern-actionhollywood-samsungnz/playlist.m3u8', 'group' => 'Movies']
        ];
    }

    private function getFifaSportsChannels(): array
    {
        return [
            ['name' => 'B TV', 'logo' => 'https://s3.aynaott.com/storage/00da8a07fb26b2fb79359ee535e4c7bc', 'url' => 'https://tvsen6.aynaott.com/btvctg/index.m3u8?e=1779283747&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=9bca925fbdfe526b29d41ab7802348ec', 'group' => 'Sports'],
            ['name' => 'Somoy TV', 'logo' => 'https://s3.aynaott.com/storage/ece71c1163a377fbe2d93f9d28c34f60', 'url' => 'https://tvsen6.aynaott.com/somoytv/index.m3u8?e=1779283766&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=269246b8a31fb3a656624d71e10e447d', 'group' => 'Sports'],
            ['name' => 'beIN Sports', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Bein%20Sports%201.jpeg', 'url' => 'http://145.239.5.177:80/559/index.m3u8', 'group' => 'Sports'],
            ['name' => 'beIN Sports Max 1', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Bein%20Sports%201.jpeg', 'url' => 'http://145.239.5.177:80/559/index.m3u8', 'group' => 'Sports'],
            ['name' => 'beIN Sports Max 2', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Bein%20Sports%201.jpeg', 'url' => 'http://145.239.5.177:80/559/index.m3u8', 'group' => 'Sports'],
            ['name' => 'beIN Sports Max 3', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Bein%20Sports%201.jpeg', 'url' => 'http://145.239.5.177:80/559/index.m3u8', 'group' => 'Sports'],
            ['name' => 'beIN Sports Max 4', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Bein%20Sports%201.jpeg', 'url' => 'http://145.239.5.177:80/559/index.m3u8', 'group' => 'Sports'],
            ['name' => 'ESPN', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/ESPN.png', 'url' => 'https://tvsen5.aynaott.com/espn/index.m3u8?e=1779283793&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=cf2b4cb8b6c96ab86daee4299c792295', 'group' => 'Sports'],
            ['name' => 'Fox Sports 1', 'logo' => 'https://i.imgur.com/O9BapV9.png', 'url' => 'https://cors-proxy.cooks.fyi/http://190.11.225.124:5000/live/fs1_hd/playlist.m3u8', 'group' => 'Sports'],
            ['name' => 'Fox Sports 2', 'logo' => 'https://s3.aynaott.com/storage/da4282cd107cc3d40efadae488b187e5', 'url' => 'https://tvsen7.aynaott.com/foxsports2/index.m3u8?e=1779283790&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=cbb7f40b4af7be51a91e0629a5ac7238', 'group' => 'Sports'],
            ['name' => 'BBC One', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8b/BBC_One_logo_2021.svg/960px-BBC_One_logo_2021.svg.png', 'url' => 'http://92.114.85.72:8000/play/a0mp', 'group' => 'Sports'],
            ['name' => 'ITV1', 'logo' => 'https://i.imgur.com/xwPekCF.png', 'url' => 'http://80.194.62.172:50002/stream/channelid/95929545', 'group' => 'Sports'],
            ['name' => 'TSN 1', 'logo' => 'https://s3.aynaott.com/storage/59fe7ff434fed04ecec29b4d737ebc95', 'url' => 'https://tvsen7.aynaott.com/tsn1/index.m3u8?e=1779283805&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=e5ce886378c54bd381b9833b5d57649a', 'group' => 'Sports'],
            ['name' => 'TSN 2', 'logo' => 'https://s3.aynaott.com/storage/17642cb60c2af7fc36ca1e08cc54fdae', 'url' => 'https://tvsen7.aynaott.com/tsn2/index.m3u8?e=1779283793&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=636d9b8b83d4316193c2d1c9aad8951c', 'group' => 'Sports'],
            ['name' => 'TSN 3', 'logo' => 'https://s3.aynaott.com/storage/1cb10107a47db353e35ad78d3160eda7', 'url' => 'https://tvsen7.aynaott.com/tsn3/index.m3u8?e=1779283805&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=fd3b5d71227f183da51caba4325cee10', 'group' => 'Sports'],
            ['name' => 'Unite8 Sports 1', 'logo' => 'https://static.wikia.nocookie.net/logopedia/images/a/a2/Zee_Bihar_Jharkhand_2025_Logo.png', 'url' => 'https://cdn-uw2-prod.tsv2.amagi.tv/linear/amg01412-xiaomiasia-zeebihar-xiaomi/playlist.m3u8', 'group' => 'Sports'],
            ['name' => 'Unite8 Sports 2', 'logo' => 'https://static.wikia.nocookie.net/logopedia/images/a/a2/Zee_Bihar_Jharkhand_2025_Logo.png', 'url' => 'https://cdn-uw2-prod.tsv2.amagi.tv/linear/amg01412-xiaomiasia-zee24ghantaa-xiaomi/playlist.m3u8', 'group' => 'Sports'],
            ['name' => 'Thunder Er', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1770380791303.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/thunder/playlist.m3u8', 'group' => 'Sports'],
            ['name' => 'Fighters', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1770380942670.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/fighter/playlist.m3u8', 'group' => 'Sports'],
            ['name' => 'Crazy Ex', 'logo' => 'https://tstatic.akash-go.com/cms-ui/images/custom-content/1778085745609.png', 'url' => 'https://nomawnoijl.gpcdn.net/akash/crazy_ex/playlist.m3u8', 'group' => 'Sports'],
            ['name' => 'PTV Sports HD (Pakistan)', 'logo' => 'https://s3.aynaott.com/storage/9d9d7cbfba5a8ceea648bbd963ad1014', 'url' => 'https://tvsen5.aynaott.com/PtvSports/index.m3u8?e=1780662761&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=b714d4f0812496defe4be81125c560aa', 'group' => 'Sports'],
            ['name' => 'A Sports HD (Pakistan)', 'logo' => 'https://s3.aynaott.com/storage/64de30d2df9b2a888cb73f17614a9a8b', 'url' => 'https://tvsen6.aynaott.com/asports/index.m3u8?e=1780662762&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=79cb2b10ec3a06c91dc483a6f1a04f36', 'group' => 'Sports'],
            ['name' => 'Cricket Gold', 'logo' => 'https://s3.aynaott.com/storage/7d20b575edc4e4b5276faa8c246e72a4', 'url' => 'https://tvsen6.aynaott.com/CricketGold/index.m3u8?e=1780662762&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=c1ffa5e779430e350c5cc5401c9b9bdc', 'group' => 'Sports'],
            ['name' => 'Willow TV', 'logo' => 'https://s3.aynaott.com/storage/94a778ec3219f7eb54bdf1ee07a95788', 'url' => 'https://tvsen5.aynaott.com/willowhd/index.m3u8?e=1780662762&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=7ff3de9f9a286f0a6df46787e8abd8fb', 'group' => 'Sports'],
            ['name' => 'DD Sports', 'logo' => 'https://s3.aynaott.com/storage/188500190395c4de0e506d518925dcc4', 'url' => 'https://cdn-6.pishow.tv/live/13/master.m3u8', 'group' => 'Sports'],
            ['name' => 'STAR SPORTS 1', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Sports%201.png', 'url' => 'https://starsportshindiii.pages.dev/720p.m3u8', 'group' => 'Sports'],
            ['name' => 'STAR SPORTS SELECT 1', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Sports%20Select%201%40.jpeg', 'url' => 'http://198.195.239.50:8095/StarSportsSelect1/tracks-v1a1/mono.m3u8', 'group' => 'Sports'],
            ['name' => 'STAR SPORTS SELECT 2', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Sports%20Select%202.png', 'url' => 'http://198.195.239.50:8095/StarSportsSelect2/tracks-v1a1/mono.m3u8', 'group' => 'Sports'],
            ['name' => 'EURO SPORTS', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Eurosport.png', 'url' => 'http://198.195.239.50:8095/Eurosport/index.m3u8', 'group' => 'Sports'],
            ['name' => 'SONY TEN SPORTS 2', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Sony%20Sports%20Ten%202.png', 'url' => 'http://198.195.239.50:8095/SonyTenSports2/index.m3u8', 'group' => 'Sports'],
            ['name' => 'SONY TEN SPORTS 5', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Sony%20Sports%20Ten%205.png', 'url' => 'http://198.195.239.50:8095/SonyTenSports5/index.m3u8', 'group' => 'Sports'],
            ['name' => 'WILLOW SPORTS', 'logo' => 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Willow%20TV.jpeg', 'url' => 'https://tvsen5.aynaott.com/willowhd/tracks-v1a1/mono.ts.m3u8', 'group' => 'Sports'],
            ['name' => 'T Sports HD', 'logo' => 'https://s3.aynaott.com/storage/dbc585f70a60b9855b6e13a8ce4cb6f4', 'url' => 'https://tvsen7.aynaott.com/tsports-hd/index.m3u8?e=1780662761&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=755de1abb34402b6543ac8e82a34488d', 'group' => 'Sports', 'alternates' => [
                'https://tvsen7.aynaott.com/tsports-hd/index.m3u8?e=1779283784&u=78be6644-0a65-48ec-81a4-089ac65a2619&token=3b4c5a2cfa872fa7f91ffbfb4aa0f658'
            ]]
        ];
    }

    private function resolveLogoByName(string $name): string
    {
        $n = strtoupper(trim($name));

        if (str_contains($n, 'TSPORTS') || str_contains($n, 'T SPORTS')) {
            return 'https://s3.aynaott.com/storage/dbc585f70a60b9855b6e13a8ce4cb6f4';
        }
        if (str_contains($n, 'STAR SPORTS SELECT 1') || str_contains($n, 'STAR SPORTS SELECT1')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Sports%20Select%201%40.jpeg';
        }
        if (str_contains($n, 'STAR SPORTS SELECT 2') || str_contains($n, 'STAR SPORTS SELECT2')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Sports%20Select%202.png';
        }
        if (str_contains($n, 'STAR SPORTS 1') || str_contains($n, 'STAR SPORTS1')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Sports%201.png';
        }
        if (str_contains($n, 'STAR SPORTS 2') || str_contains($n, 'STAR SPORTS2')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Sports%202.png';
        }
        if (str_contains($n, 'STAR PLUS')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Plus.png';
        }
        if (str_contains($n, 'STAR GOLD')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Gold.png';
        }
        if (str_contains($n, 'STAR MOVIES')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Star%20Movies.png';
        }
        if (str_contains($n, 'SONY SPORTS 2') || str_contains($n, 'SONY TEN SPORTS 2') || str_contains($n, 'SONY TEN 2')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Sony%20Sports%20Ten%202.png';
        }
        if (str_contains($n, 'SONY SPORTS 5') || str_contains($n, 'SONY TEN SPORTS 5') || str_contains($n, 'SONY TEN 5')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Sony%20Sports%20Ten%205.png';
        }
        if (str_contains($n, 'SONY TV')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Sony%20Tv.png';
        }
        if (str_contains($n, 'SONY MAX')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Sony%20Max.png';
        }
        if (str_contains($n, 'SONY AATH')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Sony%20Aath.jpeg';
        }
        if (str_contains($n, 'EURO SPORTS') || str_contains($n, 'EUROSPORT')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Eurosport.png';
        }
        if (str_contains($n, 'JALSHA MOVIES')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Jalshamovieshd.jpg';
        }
        if (str_contains($n, 'HBO')) {
            return asset('images/hbo.png');
        }
        if (str_contains($n, 'ZEE BANGLA CINEMA') || str_contains($n, 'ZEE BANGLA CHINEMA')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Zee%20Bangla%20Cinema.png';
        }
        if (str_contains($n, 'ZEE TV')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Zee%20TV.png';
        }
        if (str_contains($n, 'DISCOVERY KIDS')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Discovery%20Kids.png';
        }
        if (str_contains($n, 'DISCOVERY')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Discovery.png';
        }
        if (str_contains($n, 'NATIONAL GEOGRAPHIC')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/National%20Geographic.png';
        }
        if (str_contains($n, 'CARTOON NETWORK')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Cartoon%20Network.png';
        }
        if (str_contains($n, 'NAGORIK')) {
            return 'https://upload.wikimedia.org/wikipedia/commons/e/e0/Nagorik_TV_logo.png';
        }
        if (str_contains($n, 'NEWS 24') || str_contains($n, 'NEWS24')) {
            return 'https://upload.wikimedia.org/wikipedia/commons/7/77/News24_logo.png';
        }
        if (str_contains($n, 'COLORS BANGLA') || str_contains($n, 'COLOR BANGLA')) {
            return 'https://upload.wikimedia.org/wikipedia/commons/6/6f/Colors_Bangla_logo.png';
        }
        if (str_contains($n, 'SKY SPORT')) {
            return 'https://raw.githubusercontent.com/subirkumarpaul/Logo/main/Sky%20Sports.png';
        }
        if (str_contains($n, 'BBC ONE')) {
            return 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8b/BBC_One_logo_2021.svg/960px-BBC_One_logo_2021.svg.png';
        }
        if (str_contains($n, 'ITV1')) {
            return 'https://i.imgur.com/xwPekCF.png';
        }
        if (str_contains($n, 'FOX SPORTS 1') || str_contains($n, 'FS1')) {
            return 'https://i.imgur.com/O9BapV9.png';
        }
        if (str_contains($n, 'FOX SPORTS 2') || str_contains($n, 'FS2')) {
            return 'https://s3.aynaott.com/storage/da4282cd107cc3d40efadae488b187e5';
        }
        if (str_contains($n, 'TSN 1')) {
            return 'https://s3.aynaott.com/storage/59fe7ff434fed04ecec29b4d737ebc95';
        }
        if (str_contains($n, 'TSN 2')) {
            return 'https://s3.aynaott.com/storage/17642cb60c2af7fc36ca1e08cc54fdae';
        }
        if (str_contains($n, 'TSN 3')) {
            return 'https://s3.aynaott.com/storage/1cb10107a47db353e35ad78d3160eda7';
        }
        if (str_contains($n, 'UNITE8 SPORTS') || str_contains($n, 'ZEE5')) {
            return 'https://static.wikia.nocookie.net/logopedia/images/a/a2/Zee_Bihar_Jharkhand_2025_Logo.png';
        }

        return self::DEFAULT_FALLBACK_LOGO;
    }
}
