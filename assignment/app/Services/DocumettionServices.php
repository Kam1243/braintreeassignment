<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;

class DocumentService
{
    private const MAX_WORDS_PER_PAGE = 300;

    public function processUpload(Book $book, UploadedFile $file): array
    {
        $path     = $file->store('manuscripts', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $htmlPages = $this->convertToHtmlPages($fullPath);
            $chapter   = $this->createChapterFromPages($book, $htmlPages, $file->getClientOriginalName());

            return [
                'chapter_id'    => $chapter->id,
                'pages_created' => $chapter->pages()->count(),
            ];
        } catch (\Throwable $e) {
            Log::error('Document conversion failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to process document: ' . $e->getMessage());
        }
    }

    public function processFromPath(Book $book, string $filePath, string $originalName): array
    {
        $htmlPages = $this->convertToHtmlPages(storage_path('app/' . $filePath));
        $chapter   = $this->createChapterFromPages($book, $htmlPages, $originalName);

        return [
            'chapter_id'    => $chapter->id,
            'pages_created' => $chapter->pages()->count(),
        ];
    }

    private function convertToHtmlPages(string $filePath): array
    {
        $phpWord  = IOFactory::load($filePath);
        $sections = $phpWord->getSections();
        $allHtmlBlocks = [];

        foreach ($sections as $section) {
            foreach ($section->getElements() as $element) {
                $html = $this->elementToHtml($element);
                if (!empty(trim(strip_tags($html)))) {
                    $allHtmlBlocks[] = $html;
                }
            }
        }

        return $this->paginateHtmlBlocks($allHtmlBlocks);
    }

    private function elementToHtml($element): string
    {
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            $text = '';
            foreach ($element->getElements() as $child) {
                if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                    $text .= htmlspecialchars($child->getText());
                }
            }
            return '<p>' . $text . '</p>';
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            return '<p>' . htmlspecialchars($element->getText()) . '</p>';
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Title) {
            $level = $element->getDepth() ?: 1;
            $level = min($level, 6);
            return '<h' . $level . '>' . htmlspecialchars($element->getText()) . '</h' . $level . '>';
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\ListItem) {
            return '<li>' . htmlspecialchars($element->getTextObject()->getText()) . '</li>';
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            return $this->tableToHtml($element);
        }

        return '';
    }

    private function tableToHtml(\PhpOffice\PhpWord\Element\Table $table): string
    {
        $html = '<table border="1" cellpadding="4" cellspacing="0">';
        foreach ($table->getRows() as $row) {
            $html .= '<tr>';
            foreach ($row->getCells() as $cell) {
                $cellText = '';
                foreach ($cell->getElements() as $el) {
                    $cellText .= strip_tags($this->elementToHtml($el)) . ' ';
                }
                $html .= '<td>' . htmlspecialchars(trim($cellText)) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private function paginateHtmlBlocks(array $blocks): array
    {
        $pages       = [];
        $currentPage = '';
        $wordCount   = 0;

        foreach ($blocks as $block) {
            $blockWordCount = str_word_count(strip_tags($block));

            if ($wordCount + $blockWordCount > self::MAX_WORDS_PER_PAGE && !empty($currentPage)) {
                $pages[]     = $currentPage;
                $currentPage = $block;
                $wordCount   = $blockWordCount;
            } else {
                $currentPage .= $block;
                $wordCount   += $blockWordCount;
            }
        }

        if (!empty($currentPage)) {
            $pages[] = $currentPage;
        }

        return $pages ?: ['<p>No content found in document.</p>'];
    }

    private function createChapterFromPages(Book $book, array $htmlPages, string $filename): Chapter
    {
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $order = $book->chapters()->max('order') ?? 0;

        $chapter = $book->chapters()->create([
            'title' => $title,
            'order' => $order + 1,
        ]);

        foreach ($htmlPages as $index => $html) {
            $chapter->pages()->create([
                'content' => $html,
                'order'   => $index + 1,
            ]);
        }

        return $chapter;
    }
}