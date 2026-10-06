<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncKnowledge extends Command
{
    protected $signature = 'knowledge:sync';
    protected $description = 'Menyinkronkan knowledge dari tabel admin ke knowledge_bases';

    public function handle(): int
    {
        $sourceRecords = DB::table('knowledge')->get();
        $synced = 0;

        foreach ($sourceRecords as $record) {
            $sourceMarker = 'source_id:' . $record->id;
            $keywords = $sourceMarker;

            if (! empty($record->source_url)) {
                $keywords .= ' source:' . trim($record->source_url);
            }

            $target = DB::table('knowledge_bases')
                ->where('keywords', 'like', $sourceMarker . '%')
                ->first();

            $values = [
                'cluster' => 'Umum',
                'topic' => $record->title ?: 'Informasi Umum',
                'content' => $record->content,
                'keywords' => $keywords,
                'updated_at' => now(),
            ];

            if ($target) {
                DB::table('knowledge_bases')->where('id', $target->id)->update($values);
            } else {
                DB::table('knowledge_bases')->insert($values + [
                    'created_at' => $record->created_at ?? now(),
                ]);
            }

            $synced++;
        }

        $this->info("{$synced} data knowledge berhasil disinkronkan.");

        return self::SUCCESS;
    }
}
