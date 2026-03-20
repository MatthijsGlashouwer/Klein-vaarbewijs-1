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
     * @var array<int, string|array<int, string>>
     */
    private const IMAGE_URLS = [
        2 => 'https://www.cbr.nl/upload/e94776e2-cc48-416b-9bcf-50454b8ab108_image12779757616292015284.jpeg',
        5 => 'https://www.cbr.nl/upload/d322d38e-b9eb-4350-bb3a-36c6c774c285_vraag5.jpg',
        7 => 'https://www.cbr.nl/upload/8b5e785a-abdc-450c-8a78-8b46e4e78fa8_image9587512985988798585.png',
        9 => 'https://www.cbr.nl/upload/a9ce701d-5fbc-413a-bbe5-73d73e189c37_image15932805479821047379.png',
        10 => 'https://www.cbr.nl/upload/72ae5038-b630-4ffa-8719-8e8ed72af564_image311926536123105092.png',
        11 => 'https://www.cbr.nl/upload/37dbec62-7fa7-4032-a550-5f04f02d8989_k1111V_V2.jpg',
        17 => 'https://www.cbr.nl/upload/dc91cc3a-d9a7-48f1-ae9a-632a2be7209c_image11989864004680204890.jpeg',
        24 => 'https://www.cbr.nl/upload/55473719-b01d-4f56-922f-26fe8d3d9b04_image12862543411078347022.jpeg',
        26 => 'https://www.cbr.nl/upload/f0469426-f6ed-4234-bb7b-c2edc076da38_image17022819764617001985.jpeg',
        27 => 'https://www.cbr.nl/upload/80059315-f16f-4f38-8426-9ab2023f9942_image6023544700875225381.jpeg',
        31 => [
            'https://www.cbr.nl/upload/ffadcdaa-92bf-45a1-9e35-999e2f778125_image16616626647042290214.png',
            'https://www.cbr.nl/upload/51574c4f-2fc7-4f2a-95d1-b7231d9b3381_image45094639788572509.png',
        ],
        32 => 'https://www.cbr.nl/upload/f5065b0b-1e0f-44e1-83ae-cb6b27482c68_image14680098661472120457.png',
        35 => 'https://www.cbr.nl/upload/18bf4d82-b04e-4182-8dc1-a10616dee647_image18193239761225312987.png',
        38 => 'https://www.cbr.nl/upload/3950501a-73ae-4e43-9cb6-f4d3ae1dfe90_image73356010018584339.jpeg',
        39 => 'https://www.cbr.nl/upload/607accbc-d658-4be4-90e6-3dac4ed2d146_image1611557220440132604.png',
        40 => 'https://www.cbr.nl/upload/2730b37d-c301-485d-9528-b8a97b8e2659_image16335964899091121054.png',
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
        $datasetDirectory = storage_path('app/private/kvb1');
        $datasetPath = $datasetDirectory.'/questions.json';

        if (! is_dir($renderDirectory)) {
            mkdir($renderDirectory, 0777, true);
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

        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("KVB1-vragenbank aangemaakt: {$datasetPath}");

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

        $images = self::IMAGE_URLS[$number] ?? null;

        $question = [
            'id' => sprintf('kvb1-%02d', $number),
            'nummer' => $number,
            'punten' => $answer['points'],
            'uitleg' => $answer['explanation'] !== '' ? $answer['explanation'] : null,
            'afbeelding' => is_string($images) ? $images : null,
            'afbeeldingen' => is_array($images) ? $images : [],
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
}
