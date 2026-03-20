<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

#[Signature('app:import-kvb1-exam {source? : Pad naar de KVB1-PDF}')]
#[Description('Importeer het voorbeeldexamen KVB1 uit een PDF naar een lokale JSON-vragenbank met afbeeldingen.')]
class ImportKvb1Exam extends Command
{
    /**
     * @var array<int, array{page: int, height: int, width: int, y: int, x: int}>
     */
    private const IMAGE_CROPS = [
        2 => ['page' => 2, 'height' => 330, 'width' => 480, 'y' => 230, 'x' => 650],
        5 => ['page' => 3, 'height' => 260, 'width' => 480, 'y' => 70, 'x' => 650],
        7 => ['page' => 3, 'height' => 250, 'width' => 470, 'y' => 500, 'x' => 650],
        9 => ['page' => 3, 'height' => 270, 'width' => 480, 'y' => 1120, 'x' => 650],
        10 => ['page' => 4, 'height' => 280, 'width' => 460, 'y' => 70, 'x' => 730],
        11 => ['page' => 4, 'height' => 480, 'width' => 620, 'y' => 430, 'x' => 0],
        17 => ['page' => 5, 'height' => 260, 'width' => 480, 'y' => 760, 'x' => 650],
        24 => ['page' => 7, 'height' => 360, 'width' => 450, 'y' => 100, 'x' => 0],
        26 => ['page' => 7, 'height' => 400, 'width' => 350, 'y' => 560, 'x' => 780],
        27 => ['page' => 7, 'height' => 470, 'width' => 360, 'y' => 960, 'x' => 780],
        31 => ['page' => 8, 'height' => 220, 'width' => 360, 'y' => 940, 'x' => 780],
        32 => ['page' => 8, 'height' => 300, 'width' => 400, 'y' => 1100, 'x' => 760],
        35 => ['page' => 9, 'height' => 340, 'width' => 560, 'y' => 590, 'x' => 660],
        38 => ['page' => 10, 'height' => 320, 'width' => 420, 'y' => 100, 'x' => 760],
        39 => ['page' => 10, 'height' => 300, 'width' => 520, 'y' => 620, 'x' => 700],
        40 => ['page' => 10, 'height' => 360, 'width' => 520, 'y' => 900, 'x' => 700],
    ];

    public function handle(): int
    {
        $pdfPath = $this->argument('source') ?: '/Users/mglashouwer/Downloads/Voorbeeldexamen KVB1 (2).pdf';

        if (! is_string($pdfPath) || ! is_file($pdfPath)) {
            $this->error("PDF niet gevonden: {$pdfPath}");

            return self::FAILURE;
        }

        $tempDirectory = storage_path('app/private/kvb1/import');
        $renderDirectory = $tempDirectory.'/pages';
        $outputDirectory = public_path('images/kvb1');
        $datasetDirectory = storage_path('app/private/kvb1');
        $datasetPath = $datasetDirectory.'/questions.json';

        if (! is_dir($renderDirectory)) {
            mkdir($renderDirectory, 0777, true);
        }

        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0777, true);
        }

        if (! is_dir($datasetDirectory)) {
            mkdir($datasetDirectory, 0777, true);
        }

        try {
            $pages = $this->extractPdf($pdfPath, $renderDirectory);
            $questions = $this->buildQuestionBank($pages);

            file_put_contents(
                $datasetPath,
                json_encode($questions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );

            $this->cropQuestionImages($renderDirectory, $outputDirectory);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("KVB1-vragenbank aangemaakt: {$datasetPath}");
        $this->info("Afbeeldingen geëxporteerd naar: {$outputDirectory}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{page: int, text: string}>
     */
    private function extractPdf(string $pdfPath, string $renderDirectory): array
    {
        $binaryPath = storage_path('app/private/kvb1/import/pdfkit-extract');
        $swiftScript = base_path('resources/kvb1/pdfkit_extract.swift');
        $environment = [
            'SWIFT_MODULECACHE_PATH' => sys_get_temp_dir().'/swift-module-cache',
            'CLANG_MODULE_CACHE_PATH' => sys_get_temp_dir().'/clang-module-cache',
            'TMPDIR' => sys_get_temp_dir().'/swift-tmp',
        ];

        foreach ($environment as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        $compile = Process::path(base_path())
            ->env($environment)
            ->run([
                'swiftc',
                $swiftScript,
                '-o',
                $binaryPath,
            ]);

        if ($compile->failed()) {
            throw new RuntimeException('Swift-helper kon niet gecompileerd worden: '.$compile->errorOutput());
        }

        $extract = Process::path(base_path())
            ->env($environment)
            ->run([
                $binaryPath,
                $pdfPath,
                $renderDirectory,
            ]);

        if ($extract->failed()) {
            throw new RuntimeException('PDF kon niet worden uitgelezen: '.$extract->errorOutput());
        }

        $payload = json_decode($extract->output(), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload) || ! isset($payload['pages']) || ! is_array($payload['pages'])) {
            throw new RuntimeException('Onverwachte output van de PDF-helper.');
        }

        return $payload['pages'];
    }

    /**
     * @param  array<int, array{page: int, text: string}>  $pages
     * @return array<int, array<string, mixed>>
     */
    private function buildQuestionBank(array $pages): array
    {
        $pageTexts = [];

        foreach ($pages as $page) {
            $pageTexts[(int) $page['page']] = trim($page['text']);
        }

        $questionText = collect(range(2, 10))
            ->map(function (int $page) use ($pageTexts): string {
                $text = $pageTexts[$page] ?? '';
                $position = strpos($text, 'Vraag ');

                return $position === false ? '' : trim(substr($text, $position));
            })
            ->implode("\n");

        $questionBlocks = preg_split('/(?=Vraag \d+\. ?\*?)/u', $questionText, -1, PREG_SPLIT_NO_EMPTY);

        if ($questionBlocks === false || count($questionBlocks) !== 40) {
            throw new RuntimeException('Niet alle 40 vragen konden uit de PDF worden gehaald.');
        }

        $answers = $this->parseAnswers(($pageTexts[11] ?? '')."\n".($pageTexts[12] ?? ''));
        $questions = [];

        foreach ($questionBlocks as $block) {
            $number = $this->extractQuestionNumber($block);
            $questions[] = $this->buildQuestion($number, $block, $answers[$number] ?? null);
        }

        return $questions;
    }

    /**
     * @return array<int, array{answer: string|null, points: int, explanation: string}>
     */
    private function parseAnswers(string $text): array
    {
        $entries = [];
        $currentQuestion = null;

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' ||
                str_starts_with($line, 'Antwoorden bij') ||
                str_starts_with($line, 'Vraagnr.') ||
                $line === 'Juiste' ||
                $line === 'antwoord' ||
                $line === 'Aantal' ||
                $line === 'punten' ||
                $line === 'Toelichting') {
                continue;
            }

            if (preg_match('/^(\d+)\s+([a-d])\s+(\d+)\s*(.*)$/u', $line, $matches) === 1) {
                $currentQuestion = (int) $matches[1];
                $entries[$currentQuestion] = [
                    'answer' => $matches[2],
                    'points' => (int) $matches[3],
                    'explanation' => trim($matches[4]),
                ];

                continue;
            }

            if (preg_match('/^(\d+)\s+(\d+)\s*(.*)$/u', $line, $matches) === 1) {
                $currentQuestion = (int) $matches[1];
                $entries[$currentQuestion] = [
                    'answer' => null,
                    'points' => (int) $matches[2],
                    'explanation' => trim($matches[3]),
                ];

                continue;
            }

            if ($currentQuestion !== null) {
                $entries[$currentQuestion]['explanation'] = trim(
                    $entries[$currentQuestion]['explanation'].' '.$line,
                );
            }
        }

        $entries[13] = [
            'answer' => null,
            'points' => 2,
            'explanation' => 'Artikel 6.29 lid 3 BPR. Antwoord I: Nee. Artikel 4.06 lid 2 BPR. Antwoord II: Ja.',
        ];

        return $entries;
    }

    /**
     * @param  array{answer: string|null, points: int, explanation: string}|null  $answer
     * @return array<string, mixed>
     */
    private function buildQuestion(int $number, string $block, ?array $answer): array
    {
        if ($answer === null) {
            throw new RuntimeException("Antwoord voor vraag {$number} ontbreekt.");
        }

        $normalizedBlock = $this->normalizeBlock($block);
        $normalizedBlock = str_replace('EINDE VOORBEELDEXAMEN', '', $normalizedBlock);

        preg_match('/^Vraag\s+\d+\.\s*(?:\*\s*)?(?:\((\d+)\s+punt(?:en)?\))?\s*(.*)$/us', $normalizedBlock, $matches);
        $body = trim($matches[2] ?? '');

        $question = [
            'id' => sprintf('kvb1-%02d', $number),
            'nummer' => $number,
            'punten' => $answer['points'],
            'uitleg' => $answer['explanation'] !== '' ? $answer['explanation'] : null,
            'afbeelding' => isset(self::IMAGE_CROPS[$number])
                ? sprintf('/images/kvb1/question-%02d.png', $number)
                : null,
        ];

        if ($number === 11) {
            return $question + [
                'type' => 'ordering',
                'vraag' => $body,
                'items' => ['Nel', 'Kim', 'Han'],
                'antwoord' => ['Nel', 'Kim', 'Han'],
            ];
        }

        if ($number === 13) {
            return $question + [
                'type' => 'boolean',
                'vraag' => 'Hier volgen twee vragen over het varen bij slecht zicht op een vaarweg waar het Binnenvaartpolitiereglement (BPR) geldt.',
                'stellingen' => [
                    [
                        'id' => 'i',
                        'tekst' => 'Mag een klein schip bij slecht zicht op ALLE BPR-vaarwegen zonder radar varen?',
                        'antwoord' => 'nee',
                        'punten' => 1,
                    ],
                    [
                        'id' => 'ii',
                        'tekst' => 'Moet een klein schip, dat op radar vaart, volgens het BPR zijn uitgerust met een marifoon?',
                        'antwoord' => 'ja',
                        'punten' => 1,
                    ],
                ],
            ];
        }

        if ($number === 24) {
            return $question + [
                'type' => 'mcq',
                'vraag' => $body,
                'opties' => [
                    ['id' => 'a', 'tekst' => 'Linksboven'],
                    ['id' => 'b', 'tekst' => 'Rechtsboven'],
                    ['id' => 'c', 'tekst' => 'Linksonder'],
                    ['id' => 'd', 'tekst' => 'Rechtsonder'],
                ],
                'juiste_antwoord' => 'd',
            ];
        }

        $options = [];
        preg_match_all('/(?:^|\n)([a-d])\.\s+(.*?)(?=\n[a-d]\.\s+|$)/us', "\n".$body, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $text = preg_replace('/\s+/u', ' ', trim($match[2])) ?? trim($match[2]);
            $text = str_replace(' Teken B.11', '', $text);

            $options[] = [
                'id' => $match[1],
                'tekst' => trim($text),
            ];
        }

        $questionText = preg_split('/\n[a-d]\.\s+/u', $body, 2)[0] ?? $body;
        $questionText = trim(str_replace(' Teken B.11', '', $questionText));

        return $question + [
            'type' => 'mcq',
            'vraag' => $questionText,
            'opties' => $options,
            'juiste_antwoord' => $answer['answer'],
        ];
    }

    private function extractQuestionNumber(string $block): int
    {
        if (preg_match('/Vraag\s+(\d+)\./u', $block, $matches) !== 1) {
            throw new RuntimeException('Vraagnummer kon niet worden bepaald.');
        }

        return (int) $matches[1];
    }

    private function normalizeBlock(string $block): string
    {
        $lines = array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\R/u', $block) ?: []),
            static fn (string $line): bool => $line !== '',
        ));

        $normalized = [];

        foreach ($lines as $line) {
            if ($normalized !== [] &&
                preg_match('/^(Vraag\s+[IVX]+:|[a-d]\.|Antwoord met|Teken\s|EINDE)/u', $line) !== 1) {
                $normalized[array_key_last($normalized)] .= ' '.$line;
            } else {
                $normalized[] = $line;
            }
        }

        return implode("\n", $normalized);
    }

    private function cropQuestionImages(string $renderDirectory, string $outputDirectory): void
    {
        foreach (self::IMAGE_CROPS as $number => $crop) {
            $source = sprintf('%s/page-%02d.png', $renderDirectory, $crop['page']);
            $target = sprintf('%s/question-%02d.png', $outputDirectory, $number);

            $result = Process::path(base_path())->run([
                'sips',
                '-c',
                (string) $crop['height'],
                (string) $crop['width'],
                '--cropOffset',
                (string) $crop['y'],
                (string) $crop['x'],
                $source,
                '--out',
                $target,
            ]);

            if ($result->failed()) {
                throw new RuntimeException("Afbeelding voor vraag {$number} kon niet worden uitgesneden.");
            }
        }
    }
}
