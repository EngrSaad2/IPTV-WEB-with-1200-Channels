<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateChannels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-channels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch working channels from M3U playlist and save them to channels.json';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $m3uUrl = 'https://raw.githubusercontent.com/bugsfreeweb/LiveTVCollector/refs/heads/main/LiveTV/Bangladesh/LiveTV.m3u';
        $this->info("Fetching M3U from {$m3uUrl}...");
        
        $content = @file_get_contents($m3uUrl);
        if ($content === false) {
            $this->error("Failed to fetch M3U file.");
            return 1;
        }

        $lines = explode("\n", $content);
        $channels = [];
        $currentChannel = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (str_starts_with($line, '#EXTINF:')) {
                $commaPos = strrpos($line, ',');
                $name = ($commaPos !== false) ? trim(substr($line, $commaPos + 1)) : 'Unknown';
                
                $logo = '';
                if (preg_match('/tvg-logo="([^"]*)"/i', $line, $matches)) {
                    $logo = $matches[1];
                }
                
                $group = '';
                if (preg_match('/group-title="([^"]*)"/i', $line, $matches)) {
                    $group = $matches[1];
                }
                
                $currentChannel = [
                    'name' => $name,
                    'logo' => $logo,
                    'group' => $group
                ];
            } elseif (!str_starts_with($line, '#') && $currentChannel !== null) {
                $currentChannel['url'] = $line;
                $channels[] = $currentChannel;
                $currentChannel = null;
            }
        }

        $totalParsed = count($channels);
        $this->info("Parsed {$totalParsed} channels.");

        // Filter unique URLs to avoid double-checking
        $uniqueChannels = [];
        $seenUrls = [];
        foreach ($channels as $c) {
            if (empty($c['url']) || !str_starts_with($c['url'], 'http')) continue;
            if (!isset($seenUrls[$c['url']])) {
                $seenUrls[$c['url']] = true;
                $uniqueChannels[] = $c;
            }
        }

        $totalUnique = count($uniqueChannels);
        $this->info("Unique URLs to check: {$totalUnique}");

        $workingChannels = [];
        $batchSize = 250;
        $batches = array_chunk($uniqueChannels, $batchSize);
        $checkedCount = 0;
        $workingCount = 0;

        $this->info("Checking channel availability in parallel...");
        $progressBar = $this->output->createProgressBar($totalUnique);
        $progressBar->start();

        foreach ($batches as $batch) {
            $mh = curl_multi_init();
            $curls = [];

            foreach ($batch as $i => $c) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $c['url']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // Use GET with Range: 0-100 to avoid HEAD issues on some stream servers
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Range: bytes=0-100',
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                
                curl_multi_add_handle($mh, $ch);
                $curls[$i] = $ch;
            }

            $active = null;
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

            while ($active && $mrc == CURLM_OK) {
                if (curl_multi_select($mh) != -1) {
                    do {
                        $mrc = curl_multi_exec($mh, $active);
                    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
                }
            }

            foreach ($curls as $i => $ch) {
                $info = curl_getinfo($ch);
                $httpCode = $info['http_code'];
                
                if ($httpCode >= 200 && $httpCode < 400) {
                    $workingChannels[] = $batch[$i];
                    $workingCount++;
                }
                
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            
            curl_multi_close($mh);
            
            $checkedCount += count($batch);
            $progressBar->advance(count($batch));
        }

        $progressBar->finish();
        $this->newLine();
        
        $this->info("Completed checks. Working: {$workingCount} / {$totalUnique}");

        // Save to resources/json/channels.json
        $outputPath = resource_path('json/channels.json');
        file_put_contents($outputPath, json_encode($workingChannels, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Saved to {$outputPath}.");

        // Clear Laravel cache for raw channels
        \Illuminate\Support\Facades\Cache::forget('iptv_channels_raw');
        $this->info("Cleared cache.");

        return 0;
    }
}
