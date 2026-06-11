<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CacheSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = __DIR__ . '/data/channels_raw.txt';
        
        if (file_exists($filePath)) {
            $value = file_get_contents($filePath);
            
            // Set expiration to 1 year in the future
            $expiration = time() + 31536000;
            
            DB::table('cache')->updateOrInsert(
                ['key' => 'laravel-cache-iptv_channels_raw'],
                [
                    'value' => $value,
                    'expiration' => $expiration
                ]
            );
            
            $this->command->info('Successfully seeded IPTV channels cache (1224 channels).');
        } else {
            $this->command->error('IPTV channels raw data file not found at: ' . $filePath);
        }
    }
}
