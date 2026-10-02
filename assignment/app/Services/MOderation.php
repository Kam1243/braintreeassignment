<?php

namespace App\Services;

use App\Models\Book;
use App\Models\ModerationLog;

class ModerationService
{
    private array $profanityList = [
        'fuck', 'shit', 'ass', 'bitch', 'bastard', 'damn', 'crap',
        'piss', 'cock', 'dick', 'pussy', 'cunt', 'whore', 'slut',
    ];

    private array $restrictedWords = [
        'kill all', 'bomb making', 'how to hack', 'child abuse',
        'terrorism', 'drug synthesis', 'suicide instructions',
    ];

    public function moderate(Book $book): ModerationLog
    {
        $book->load(['chapters.pages']);

        $fullText = $this->extractText($book);
        $flags    = [];

        $profanityFlags = $this->checkProfanity($fullText);
        if (!empty($profanityFlags)) {
            $flags['profanity'] = $profanityFlags;
        }

        $restrictedFlags = $this->checkRestrictedWords($fullText);
        if (!empty($restrictedFlags)) {
            $flags['restricted_words'] = $restrictedFlags;
        }

        $passed = empty($flags);

        return ModerationLog::create([
            'book_id' => $book->id,
            'passed'  => $passed,
            'flags'   => $flags ?: null,
        ]);
    }

    private function extractText(Book $book): string
    {
        $parts = [$book->title, $book->description ?? ''];

        foreach ($book->chapters as $chapter) {
            $parts[] = $chapter->title;
            foreach ($chapter->pages as $page) {
                $parts[] = strip_tags($page->content ?? '');
            }
        }

        return strtolower(implode(' ', $parts));
    }

    private function checkProfanity(string $text): array
    {
        $found = [];
        foreach ($this->profanityList as $word) {
            if (strpos($text, $word) !== false) {
                $found[] = $word;
            }
        }
        return $found;
    }

    private function checkRestrictedWords(string $text): array
    {
        $found = [];
        foreach ($this->restrictedWords as $phrase) {
            if (strpos($text, strtolower($phrase)) !== false) {
                $found[] = $phrase;
            }
        }
        return $found;
    }
}