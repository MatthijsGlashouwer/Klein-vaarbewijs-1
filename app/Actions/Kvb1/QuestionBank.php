<?php

namespace App\Actions\Kvb1;

use Illuminate\Support\Collection;
use RuntimeException;

class QuestionBank
{
    /**
     * @var array<int, array{
     *     topic: string,
     *     chapter: string,
     *     section: string,
     *     title: string
     * }>
     */
    private const STUDY_REFERENCES = [
        1 => ['topic' => 'Alcohol op het water', 'chapter' => 'Hoofdstuk 3', 'section' => '3.1.1', 'title' => 'Wetten en reglementen'],
        2 => ['topic' => 'Werkingsgebied van BPR en RPR', 'chapter' => 'Hoofdstuk 3', 'section' => '3.1.1', 'title' => 'Wetten en reglementen'],
        3 => ['topic' => 'Documenten aan boord en marifoonplicht', 'chapter' => 'Hoofdstuk 3', 'section' => '3.1.3', 'title' => 'Leeftijd en marifoon'],
        4 => ['topic' => 'Navigatielichten van kleine schepen in het donker', 'chapter' => 'Hoofdstuk 3', 'section' => '3.2.4', 'title' => "Lichten 's nachts"],
        5 => ['topic' => 'Dagmerken van baggerwerktuigen', 'chapter' => 'Hoofdstuk 3', 'section' => '3.2.3', 'title' => 'Extra lichten en dagmerken grote schepen vervolg'],
        6 => ['topic' => 'Geluidsseinen bij bruggen', 'chapter' => 'Hoofdstuk 3', 'section' => '3.3.1', 'title' => 'Geluidsseinen'],
        7 => ['topic' => 'Marifoonregels op klein schip', 'chapter' => 'Hoofdstuk 3', 'section' => '3.1.3', 'title' => 'Leeftijd en marifoon'],
        8 => ['topic' => 'Voorrang tussen snel schip en andere schepen', 'chapter' => 'Hoofdstuk 4', 'section' => '4.2.3', 'title' => 'Groot-klein en klein onderling'],
        9 => ['topic' => 'Varen door engtes', 'chapter' => 'Hoofdstuk 4', 'section' => '4.3.1', 'title' => 'Engtes'],
        10 => ['topic' => 'Samenkomst hoofdvaarwater en nevenvaarwater', 'chapter' => 'Hoofdstuk 4', 'section' => '4.2.1', 'title' => 'Hoofdvaarwater - nevenvaarwater'],
        11 => ['topic' => 'Basisvoorrang tussen kleine schepen', 'chapter' => 'Hoofdstuk 4', 'section' => '4.1.1', 'title' => 'Basisregels kleine schepen onderling'],
        12 => ['topic' => 'Gedrag in de sluis', 'chapter' => 'Hoofdstuk 4', 'section' => '4.4.1', 'title' => 'Oversteken en sluis'],
        13 => ['topic' => 'Varen bij slecht zicht', 'chapter' => 'Hoofdstuk 4', 'section' => '4.4.2', 'title' => 'Bijzondere bepalingen'],
        14 => ['topic' => 'Definitie van stilliggen', 'chapter' => 'Hoofdstuk 3', 'section' => '3.1.2', 'title' => 'Definities, BPR'],
        15 => ['topic' => 'Waterskiën en snelvaren', 'chapter' => 'Hoofdstuk 4', 'section' => '4.4.2', 'title' => 'Bijzondere bepalingen'],
        16 => ['topic' => 'Plaats in het vaarwater op RPR-water', 'chapter' => 'Hoofdstuk 4', 'section' => '4.6.1', 'title' => 'Verschillen BPR - RPR'],
        17 => ['topic' => 'Lichten van klein zeilschip op RPR-water', 'chapter' => 'Hoofdstuk 4', 'section' => '4.6.2', 'title' => 'Vervolg BPR - RPR'],
        18 => ['topic' => 'Blauw bord en wit flikkerlicht', 'chapter' => 'Hoofdstuk 4', 'section' => '4.6.2', 'title' => 'Vervolg BPR - RPR'],
        19 => ['topic' => 'Wierfilter en koelwater', 'chapter' => 'Hoofdstuk 1', 'section' => '1.4.1', 'title' => 'Motortechniek'],
        20 => ['topic' => 'Brand- en explosieveilig starten', 'chapter' => 'Hoofdstuk 1', 'section' => '1.3.1', 'title' => 'Veiligheid, brand'],
        21 => ['topic' => 'Reddingsvesten aan boord', 'chapter' => 'Hoofdstuk 1', 'section' => '1.3.3', 'title' => 'Voorkomen van verdrinking'],
        22 => ['topic' => 'Gasdetector en sensoren', 'chapter' => 'Hoofdstuk 1', 'section' => '1.3.1', 'title' => 'Veiligheid, brand'],
        23 => ['topic' => 'Maatregelen tegen overboord vallen', 'chapter' => 'Hoofdstuk 1', 'section' => '1.2.1', 'title' => 'Uitrusting'],
        24 => ['topic' => 'Laterale betonning en scheidingstonnen', 'chapter' => 'Hoofdstuk 2', 'section' => '2.4.2', 'title' => 'Laterale betonning, vervolg'],
        25 => ['topic' => 'Karakter van betonningslichten', 'chapter' => 'Hoofdstuk 2', 'section' => '2.6.1', 'title' => 'Betonningslichten'],
        26 => ['topic' => 'Hoogteschaal bij bruggen lezen', 'chapter' => 'Hoofdstuk 1', 'section' => '1.6.1', 'title' => 'Waterkaarten'],
        27 => ['topic' => 'Peilschaal en waterstand aflezen', 'chapter' => 'Hoofdstuk 1', 'section' => '1.6.1', 'title' => 'Waterkaarten'],
        28 => ['topic' => 'Brughoogte en kanaalpeil berekenen', 'chapter' => 'Hoofdstuk 1', 'section' => '1.6.1', 'title' => 'Waterkaarten'],
        29 => ['topic' => 'Windkracht en Beaufort', 'chapter' => 'Hoofdstuk 1', 'section' => '1.7.1', 'title' => 'Meteorologie'],
        30 => ['topic' => 'Isobaren en weersverwachting', 'chapter' => 'Hoofdstuk 1', 'section' => '1.7.1', 'title' => 'Meteorologie'],
        31 => ['topic' => 'Verbods- en gebodstekens voor zeilvaart', 'chapter' => 'Hoofdstuk 3', 'section' => '3.4.1', 'title' => 'Verbods- en gebodstekens'],
        32 => ['topic' => 'Verkeerstekens voor stilliggen en ankeren', 'chapter' => 'Hoofdstuk 3', 'section' => '3.4.1', 'title' => 'Verbods- en gebodstekens'],
        33 => ['topic' => 'Schroefwerking en roergedrag', 'chapter' => 'Hoofdstuk 2', 'section' => '2.1.1', 'title' => 'Varen'],
        34 => ['topic' => 'Ankeren bij veranderende omstandigheden', 'chapter' => 'Hoofdstuk 2', 'section' => '2.3.1', 'title' => 'Begrippen, waterbewegingen en ankeren'],
        35 => ['topic' => 'Zuiging en oevereffect', 'chapter' => 'Hoofdstuk 2', 'section' => '2.3.1', 'title' => 'Begrippen, waterbewegingen en ankeren'],
        36 => ['topic' => 'Dode hoek van een schip', 'chapter' => 'Hoofdstuk 2', 'section' => '2.1.1', 'title' => 'Varen'],
        37 => ['topic' => 'Slepen van een schip met motorstoring', 'chapter' => 'Hoofdstuk 2', 'section' => '2.3.2', 'title' => 'Reddingsacties'],
        38 => ['topic' => 'Goed afmeren met landvasten', 'chapter' => 'Hoofdstuk 2', 'section' => '2.2.1', 'title' => 'Aankomen'],
        39 => ['topic' => 'Afmeren met schroefwerking en wind', 'chapter' => 'Hoofdstuk 2', 'section' => '2.2.1', 'title' => 'Aankomen'],
        40 => ['topic' => 'Keren met boegschroef en schroefwerking', 'chapter' => 'Hoofdstuk 2', 'section' => '2.1.2', 'title' => 'Varen met boeg- en hekschroef en hekaandrijving'],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $path = storage_path('app/private/kvb1/questions.json');

        if (! is_file($path)) {
            throw new RuntimeException('KVB1-vragenbank niet gevonden. Draai eerst php artisan app:import-kvb1-exam.');
        }

        $questions = json_decode((string) file_get_contents($path), true);

        if (! is_array($questions)) {
            throw new RuntimeException('KVB1-vragenbank kon niet worden geladen.');
        }

        return $questions;
    }

    /**
     * @return array{
     *     question_count: int,
     *     total_points: int,
     *     passing_score: int,
     *     passing_percentage: int
     * }
     */
    public function meta(): array
    {
        $questions = $this->all();
        $totalPoints = array_sum(array_map(
            static fn (array $question): int => (int) $question['punten'],
            $questions,
        ));

        return [
            'question_count' => count($questions),
            'total_points' => $totalPoints,
            'passing_score' => 56,
            'passing_percentage' => (int) round((56 / max($totalPoints, 1)) * 100),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function practiceSet(?int $limit = null): array
    {
        $questions = Collection::make($this->all())->shuffle()->values();

        if ($limit !== null && $limit > 0) {
            $questions = $questions->take($limit)->values();
        }

        return $questions
            ->map(fn (array $question): array => $this->sanitizeQuestion($question))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{
     *     score: int,
     *     total_points: int,
     *     percentage: int,
     *     passing_score: int,
     *     passed: bool,
     *     results: array<int, array<string, mixed>>,
     *     study_advice: array<int, array<string, mixed>>
     * }
     */
    public function grade(array $answers): array
    {
        $questions = $this->all();
        $meta = $this->meta();
        $results = [];
        $score = 0;

        foreach ($questions as $question) {
            $submitted = $answers[$question['id']] ?? null;
            $result = match ($question['type']) {
                'boolean' => $this->gradeBoolean($question, $submitted),
                'ordering' => $this->gradeOrdering($question, $submitted),
                default => $this->gradeMcq($question, $submitted),
            };

            $score += $result['score'];
            $results[] = $result;
        }

        return [
            'score' => $score,
            'total_points' => $meta['total_points'],
            'percentage' => (int) round(($score / max($meta['total_points'], 1)) * 100),
            'passing_score' => $meta['passing_score'],
            'passed' => $score >= $meta['passing_score'],
            'results' => $results,
            'study_advice' => $this->buildStudyAdvice($results),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function sanitizeQuestion(array $question): array
    {
        if ($question['type'] === 'mcq') {
            $question['opties'] = Collection::make($question['opties'])->shuffle()->values()->all();
            unset($question['juiste_antwoord']);
        }

        if ($question['type'] === 'boolean') {
            foreach ($question['stellingen'] as $index => $statement) {
                unset($question['stellingen'][$index]['antwoord']);
            }
        }

        if ($question['type'] === 'ordering') {
            unset($question['antwoord']);
        }

        return $question;
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function gradeMcq(array $question, mixed $submitted): array
    {
        $selected = is_string($submitted) ? $submitted : null;
        $correct = (string) $question['juiste_antwoord'];
        $score = $selected === $correct ? (int) $question['punten'] : 0;

        return [
            'id' => $question['id'],
            'nummer' => $question['nummer'],
            'type' => $question['type'],
            'vraag' => $question['vraag'],
            'punten' => (int) $question['punten'],
            'score' => $score,
            'afbeelding' => $question['afbeelding'],
            'uitleg' => $question['uitleg'],
            'selected_option' => $selected,
            'correct_option' => $correct,
            'correct_option_text' => $this->findOptionText($question, $correct),
            'study_reference' => $this->studyReference((int) $question['nummer']),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function gradeBoolean(array $question, mixed $submitted): array
    {
        $submittedAnswers = is_array($submitted) ? $submitted : [];
        $score = 0;
        $results = [];

        foreach ($question['stellingen'] as $statement) {
            $selected = $submittedAnswers[$statement['id']] ?? null;
            $earned = $selected === $statement['antwoord'] ? (int) $statement['punten'] : 0;
            $score += $earned;

            $results[] = [
                'id' => $statement['id'],
                'tekst' => $statement['tekst'],
                'punten' => (int) $statement['punten'],
                'score' => $earned,
                'selected_answer' => $selected,
                'correct_answer' => $statement['antwoord'],
            ];
        }

        return [
            'id' => $question['id'],
            'nummer' => $question['nummer'],
            'type' => $question['type'],
            'vraag' => $question['vraag'],
            'punten' => (int) $question['punten'],
            'score' => $score,
            'afbeelding' => $question['afbeelding'],
            'uitleg' => $question['uitleg'],
            'stellingen' => $results,
            'study_reference' => $this->studyReference((int) $question['nummer']),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function gradeOrdering(array $question, mixed $submitted): array
    {
        $submittedPositions = is_array($submitted) ? $submitted : [];
        $score = 0;
        $results = [];

        foreach ($question['items'] as $index => $item) {
            $expectedPosition = $index + 1;
            $selectedPosition = isset($submittedPositions[$item]) ? (int) $submittedPositions[$item] : null;
            $earned = $selectedPosition === $expectedPosition ? 1 : 0;
            $score += $earned;

            $results[] = [
                'item' => $item,
                'expected_position' => $expectedPosition,
                'selected_position' => $selectedPosition,
                'score' => $earned,
            ];
        }

        return [
            'id' => $question['id'],
            'nummer' => $question['nummer'],
            'type' => $question['type'],
            'vraag' => $question['vraag'],
            'punten' => (int) $question['punten'],
            'score' => $score,
            'afbeelding' => $question['afbeelding'],
            'uitleg' => $question['uitleg'],
            'items' => $results,
            'study_reference' => $this->studyReference((int) $question['nummer']),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function findOptionText(array $question, string $optionId): ?string
    {
        foreach ($question['opties'] as $option) {
            if ($option['id'] === $optionId) {
                return $option['tekst'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function buildStudyAdvice(array $results): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $missedPoints = (int) $result['punten'] - (int) $result['score'];

            if ($missedPoints <= 0) {
                continue;
            }

            $reference = $result['study_reference'] ?? null;

            if (! is_array($reference)) {
                continue;
            }

            $key = (string) $reference['section'];

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'topic' => $reference['topic'],
                    'chapter' => $reference['chapter'],
                    'section' => $reference['section'],
                    'title' => $reference['title'],
                    'wrong_count' => 0,
                    'missed_points' => 0,
                    'questions' => [],
                ];
            }

            $grouped[$key]['wrong_count']++;
            $grouped[$key]['missed_points'] += $missedPoints;
            $grouped[$key]['questions'][] = (int) $result['nummer'];
        }

        $advice = array_values($grouped);

        usort($advice, function (array $left, array $right): int {
            return [$right['missed_points'], $right['wrong_count'], $left['section']]
                <=> [$left['missed_points'], $left['wrong_count'], $right['section']];
        });

        return array_map(function (array $item): array {
            sort($item['questions']);
            $item['advice'] = sprintf(
                'Herhaal %s, subhoofdstuk %s (%s) over %s en oefen daarna vraag %s opnieuw.',
                $item['chapter'],
                $item['section'],
                $item['title'],
                mb_strtolower($item['topic']),
                implode(', ', $item['questions']),
            );

            return $item;
        }, array_slice($advice, 0, 3));
    }

    /**
     * @return array<string, string>|null
     */
    private function studyReference(int $questionNumber): ?array
    {
        return self::STUDY_REFERENCES[$questionNumber] ?? null;
    }
}
