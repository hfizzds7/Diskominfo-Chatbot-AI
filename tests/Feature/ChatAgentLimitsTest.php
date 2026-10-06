<?php

namespace Tests\Feature;

use App\Ai\Agents\ChatAgent;
use App\Models\KnowledgeBase;
use Tests\TestCase;

class ChatAgentLimitsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        KnowledgeBase::query()->delete();
    }

    public function test_instructions_keep_context_under_a_safe_size(): void
    {
        KnowledgeBase::query()->insert([
            [
                'cluster' => 'A',
                'topic' => 'Topik 1',
                'content' => str_repeat('X', 12000),
                'keywords' => 'a',
            ],
            [
                'cluster' => 'A',
                'topic' => 'Topik 2',
                'content' => str_repeat('Y', 12000),
                'keywords' => 'b',
            ],
            [
                'cluster' => 'B',
                'topic' => 'Topik 3',
                'content' => str_repeat('Z', 12000),
                'keywords' => 'c',
            ],
        ]);

        $instructions = (new ChatAgent())->instructions();

        $this->assertIsString($instructions);
        $this->assertLessThan(20000, mb_strlen($instructions));
        $this->assertStringContainsString('DATA REFERENSI RESMI', $instructions);
    }
}
