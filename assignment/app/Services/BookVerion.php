<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookVersion;
use App\Models\User;

class BookVersionService
{
    public function createSnapshot(Book $book, User $creator, ?string $label = null): BookVersion
    {
        $snapshot = $this->buildSnapshot($book);

        return BookVersion::create([
            'book_id'        => $book->id,
            'version_number' => $book->nextVersionNumber(),
            'label'          => $label ?? ('Version ' . $book->nextVersionNumber()),
            'snapshot'       => $snapshot,
            'created_by'     => $creator->id,
        ]);
    }

    public function buildSnapshot(Book $book): array
    {
        $book->load(['chapters.pages']);

        $chapters = [];
        foreach ($book->chapters as $chapter) {
            $pages = [];
            foreach ($chapter->pages as $page) {
                $pages[] = [
                    'id'      => $page->id,
                    'content' => $page->content,
                    'order'   => $page->order,
                ];
            }
            $chapters[] = [
                'id'    => $chapter->id,
                'title' => $chapter->title,
                'order' => $chapter->order,
                'pages' => $pages,
            ];
        }

        return [
            'metadata' => [
                'title'       => $book->title,
                'description' => $book->description,
                'genre'       => $book->genre,
                'status'      => $book->status,
                'author_id'   => $book->author_id,
            ],
            'chapters' => $chapters,
        ];
    }

    public function restoreFromSnapshot(Book $book, BookVersion $version): void
    {
        $snapshot = $version->snapshot;

        $book->update([
            'title'       => $snapshot['metadata']['title'],
            'description' => $snapshot['metadata']['description'],
            'genre'       => $snapshot['metadata']['genre'],
        ]);

        $book->chapters()->delete();

        foreach ($snapshot['chapters'] as $chapterData) {
            $chapter = $book->chapters()->create([
                'title' => $chapterData['title'],
                'order' => $chapterData['order'],
            ]);

            foreach ($chapterData['pages'] as $pageData) {
                $chapter->pages()->create([
                    'content' => $pageData['content'],
                    'order'   => $pageData['order'],
                ]);
            }
        }
    }
}